<?php

namespace totum\common\calculates;

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

    public static function bcRoundNumber($val, $step, $dectimal, $type): string
    {
        $VAL = $val;
        $func = function ($val, $dectimal) use ($type) {
            $mod = bcmod($val, 1, 10);

            if (strlen($mod) > $dectimal + 2) {
                if ($val > 0) {
                    if ($type === 'up' || ($type != 'down' && $mod[$dectimal + 2] >= 5)) {
                        $val = bcadd($val, 1 / (10 ** $dectimal), $dectimal);
                    } else {
                        $val = bcadd($val, 0, $dectimal);
                    }
                } else {
                    if ($type === 'down' || ($type != 'up' && $mod[$dectimal + 2] >= 5)) {
                        $val = bcsub($val, bcdiv(1, bcpow(10, $dectimal, 15), 15), $dectimal);
                    } else {
                        $val = bcadd($val, 0, $dectimal);
                    }
                }
            }
            return $val;
        };

        if (!empty($step)) {
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
        return Calculate::rtrimZeros(bccomp($params['num'], 0, 10) === 1 ? $params['num'] : bcmul($params['num'], -1, 10));
    }

    protected function funcNumFormat(string $params): string
    {
        $params = $this->getParamsArray($params);
        $this->__checkRequiredParams($params, ['num']);
        $this->__checkNotArrayParams($params, ['num', 'dectimals', 'decsep', 'thousandssep', 'unittype']);
        $this->__checkNumericParam($params['num'], 'num');

        return number_format(
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
            true);
    }
}