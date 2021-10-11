<?php

namespace totum\common\calculates;

trait FuncNumbersTrait
{

    protected function funcModul(string $params): float|int
    {
        $params = $this->getParamsArray($params);
        $this->__checkRequiredParams($params, ['num']);
        $this->__checkNotArrayParams($params, ['num']);
        return abs((float)$params['num']);
    }

    protected function funcNumFormat(string $params): string
    {
        $params = $this->getParamsArray($params);
        $this->__checkRequiredParams($params, ['num']);
        $this->__checkNotArrayParams($params, ['num', 'dectimals', 'decsep', 'thousandssep', 'unittype']);
        $this->__checkNumericParam($params['num'], 'num');

        return number_format(
                (float)$params['num'],
                (int)$params['dectimals'] ?? 0,
                (string)$params['decsep'] ?? ',',
                (string)$params['thousandssep'] ?? ''
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

    protected function funcRound(string $params): float|int
    {
        $params = $this->getParamsArray($params);
        $this->__checkRequiredParams($params, ['num']);
        $this->__checkNotArrayParams($params, ['num', 'dectimals', 'decsep', 'thousandssep', 'unittype']);
        $this->__checkNumericParam($params['num'], 'num');

        $val = (float)$params['num'];

        $func = 'round';
        if (!empty($params['type'])) {
            $func = match ($params['type']) {
                'up' => 'ceil',
                'down' => 'floor',
                default => 'round'
            };
        }

        if (!empty($params['step'])) {
            $fig = (int)str_pad('1', $params['dectimal'] ?? 0 + 1, '0');
            $step = $params['step'] * $fig;

            $val = $func($val * $fig / $step) * $step / $fig;
            $val = round($val, 10);
            $val = bcadd($val, 0, $params['dectimal'] ?? 0);
        } else {
            $val = $func($val);
        }
        return $val;
    }
}