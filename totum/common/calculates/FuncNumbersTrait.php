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
                $val = number_format((float)$val, 12, '.', '');
            } else {
                throw new errorException('Infinite value in round operation');
            }
        } else {
            throw new errorException('Not number value in round operation');
        }

        if (bccomp($val, 0, 10) === 0) {
        } elseif (!empty($step)) {

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

        $func = function ($data) use (&$func) {
            if (is_array($data)) {
                foreach ($data as $k => &$v) {
                    $v = $func($v);
                }
                unset($v);
            } else {
                if (!is_numeric($data)) {
                    throw new errorException($this->translate('Data parameter  / data values must be numeric.'));
                }
                $data += 0;
            }
            return $data;
        };


        return $func($params['data']);
    }

    protected function funcNumFormat(string $params): string
    {
        $params = $this->getParamsArray($params);

        if (is_null($params['num']) || $params['num'] === '') {
            return '';
        }

        $this->__checkRequiredParams($params, ['num']);
        $this->__checkNotArrayParams($params, ['num', 'dectimals', 'decsep', 'thousandssep', 'unittype', 'prefix']);
        $this->__checkNumericParam($params['num'], 'num');

        return ((string)($params['prefix'] ?? '')) . number_format(
                (float)$params['num'],
                (int)($params['dectimals'] ?? 0),
                (string)($params['decsep'] ?? ','),
                (string)($params['thousandssep'] ?? '')
            )
            . ((string)($params['unittype'] ?? ''));
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
        $this->__checkNotArrayParams($params, ['num', 'dectimals', 'decsep', 'thousandssep', 'unittype']);
        $this->__checkNumericParam($params['num'], 'num');

        return Calculate::bcRoundNumber($params['num'],
            $params['step'] ?? 0,
            $params['dectimal'] ?? 0,
            $params['type'] ?? null,
            $this->varData);
    }
}