<?php

namespace totum\common\calculates;

use totum\common\errorException;

trait FuncNumbersTrait
{

    public static function rtrimZeros($num)
    {
        if (str_contains($num, '.') && str_ends_with($num, '0')) {
            $num = rtrim($num, '0');
            $num = rtrim($num, '.');
        }
        return $num;
    }

    public static function bcRoundNumber($val, $step, $dectimal, $type, $logData = []): string
    {
        $func = function ($val, $dectimal) use ($logData, $type) {
            $mod = bcmod($val, 1, 10);
            if ($val > 0) {
                if (($type === 'up' && (int)(substr($mod,
                            $dectimal + 2) ?? 0)) ||
                    ($type != 'down' && ($mod[$dectimal + 2] ?? 0) >= 5)) {
                    $val = bcadd($val, number_format(1 / (10 ** $dectimal), $dectimal, '.', ''), $dectimal);
                }
            } elseif ($val < 0) {
                if (($type === 'down' && (int)(substr($mod,
                            $dectimal + 3) ?? 0)) || ($type !== 'up' && ($mod[$dectimal + 3] ?? 0) >= 5)) {
                    $val = bcsub($val, number_format(1 / (10 ** $dectimal), $dectimal, '.', ''), $dectimal);
                }
            }

            return bcadd($val, 0, $dectimal);
        };


        if (is_numeric($val)) {
            if (!is_infinite($val)) {
                if (!preg_match('/^[0-9.]+$/', $val)) {
                    $val = number_format((float)$val, 12, '.', '');
                }
            } else {
                throw new errorException('Infinite value in round operation');
            }
        } else {
            throw new errorException('Not number value in round operation');
        }

        if (bccomp($val, 0, 10) === 0) {
        } elseif (!empty((float)$step)) {

            $fig = 10 ** $dectimal;
            $stepMul = bcmul($step, $fig, 10);

            $val = bcmul($val, $fig, 10);
            $val = bcdiv($val, $stepMul, 10);


            $val = $func($val, 0);

            $val = bcmul($val, $stepMul, 10);
            $val = bcdiv($val, $fig, 10);

            $val = bcadd($val, 0, $dectimal);
        } else {
            $val = $func($val, $dectimal);
        }

        return Calculate::rtrimZeros($val);
    }

    protected function funcModul(string $params)
    {
        $params = $this->getParamsArray($params);
        $this->__checkRequiredParams($params, ['num']);
        $this->__checkNotArrayParams($params, ['num']);
        return Calculate::rtrimZeros(bccomp($params['num'], 0, 10) === 1 ? $params['num'] : bcmul($params['num'],
            -1,
            10));
    }

    protected function funcNumTransform(string $params)
    {
        $params = $this->getParamsArray($params);
        $this->__checkRequiredParams($params, ['data']);

        if (is_array($params['data'])) {
            $recursive = match ($params['recursive'] ?? true) {
                'false', false => false,
                'true', true => true,
                default => $params['recursive']
            };
            if ($recursive === false) {
                $recursive = [0];
            }

            $keys = $params['keys'] ?? null;
            if ($keys) {
                $keys = (array)$keys;
            }

            $formatter = function ($array, $recursive, $level = 0) use (&$formatter, $keys) {
                $isLevel = $recursive === true || in_array($level, $recursive);
                if ($isLevel && is_array($recursive)) {
                    array_splice($recursive, array_search($level, $recursive), 1);
                }
                $goFuther = !!$recursive;

                foreach ($array as $key => &$value) {
                    if (is_array($value)) {
                        if ($goFuther) {
                            $value = $formatter($value, $recursive, $level + 1);
                        }
                    } elseif ($isLevel && ($keys === null || in_array($key, $keys))) {
                        try {
                            $this->__checkNumericParam($value, 'num', true);
                            $value += 0;
                        } catch (\Exception $e) {
                        }
                    }
                }
                unset($value);
                return $array;
            };

            return $formatter($params['data'], $recursive);
        }
        $this->__checkNumericParam($params['data'], 'num', true);
        return $params['data'] += 0;
    }

    protected function funcNumFormat(string $params): string|array
    {
        $params = $this->getParamsArray($params, ['replace'], ['replace']);

        if (is_null($params['num']) || $params['num'] === '') {
            return '';
        }
        if ($params['num'] === []) {
            return [];
        }

        $this->__checkRequiredParams($params, ['num']);
        $this->__checkNotArrayParams($params, ['dectimals',  'decimals', 'decsep', 'thousandssep', 'unittype', 'prefix']);

        $format = function ($num) use ($params) {
            return ((string)($params['prefix'] ?? '')) . number_format(
                    (float)$num,
                    (int)($params['dectimals'] ?? $params['decimals'] ?? 0),
                    (string)($params['decsep'] ?? ','),
                    (string)($params['thousandssep'] ?? '')
                )
                . ((string)($params['unittype'] ?? ''));
        };


        if (is_array($params['num'])) {
            $recursive = match ($params['recursive'] ?? true) {
                'false', false => false,
                'true', true => true,
                default => $params['recursive']
            };
            if ($recursive === false) {
                $recursive = [0];
            }

            $keys = $params['keys'] ?? null;
            if ($keys) {
                $keys = (array)$keys;
            }

            $replace = function (&$v) {
                return false;
            };
            if ($params['replace'] ?? []) {
                $replaces = [];
                foreach ($params['replace'] as $r) {
                    $r = $this->getExecVariableVal($r);
                    if (count($r) === 1) {
                        $from = [null, ''];
                        $to = $r[0];
                    } else {
                        $from = $r[0];
                        $to = $r[1];
                    }
                    if (is_array($to)) {
                        throw new errorException($this->translate('The parameter [[%s]] should [[not]] be of type row/list.',
                            'replace->to'));
                    }
                    $replaces[] = ['from' => $from, 'to' => $to];
                }
                $replace = function (&$val) use ($replaces) {
                    foreach ($replaces as $replace) {
                        $replace['from'] = (array)$replace['from'];
                        foreach ($replace['from'] as $from) {
                            if ($from === $val) {
                                $val = $replace['to'];
                                return true;
                            }
                        }
                    }
                    return false;
                };
            }

            $formatter = function ($array, $recursive, $level = 0) use ($format, &$formatter, $keys, $replace) {
                $isLevel = $recursive === true || in_array($level, $recursive);
                if ($isLevel && is_array($recursive)) {
                    array_splice($recursive, array_search($level, $recursive), 1);
                }
                $goFuther = !!$recursive;

                foreach ($array as $key => &$value) {
                    if (is_array($value)) {
                        if ($goFuther) {
                            $value = $formatter($value, $recursive, $level + 1);
                        }
                    } elseif ($isLevel && ($keys === null || in_array($key, $keys))) {
                        if ((($keys !== null && in_array($key,
                                    $keys)) || is_numeric($key))) {
                            if (!$replace($value) && !empty($value)) {
                                try {
                                    $this->__checkNumericParam($value, 'num', true);
                                    $value = $format($value);
                                } catch (\Exception $e) {
                                }
                            }
                        } else {
                            try {
                                $this->__checkNumericParam($value, 'num', true);
                                $value = $format($value);
                            } catch (\Exception $e) {
                            }
                        }

                    }
                }
                unset($value);
                return $array;
            };

            return $formatter($params['num'], $recursive);
        }

        $this->__checkNumericParam($params['num'], 'num', true);
        return $format($params['num']);
    }

    protected function funcNumRand(string $params): int
    {
        $params = $this->getParamsArray($params);
        if (key_exists('min', $params)) {
            if (key_exists('max', $params)) {
                return rand($params['min'] ?? 0, $params['max'] ?? 0);
            }
            return rand($params['min'] ?? 0);
        }
        return rand();
    }


    protected function funcRound(string $params)
    {
        $params = $this->getParamsArray($params);
        $this->__checkRequiredParams($params, ['num']);
        $this->__checkNotArrayParams($params, ['num', 'dectimals', 'dectimal', 'decimals', 'decsep', 'thousandssep', 'unittype']);
        $this->__checkNumericParam($params['num'], 'num');

        return Calculate::bcRoundNumber($params['num'],
            $params['step'] ?? 0,
            $params['dectimal'] ?? $params['dectimals'] ?? $params['decimals']  ?? 0,
            $params['type'] ?? null,
            $this->varData);
    }
}