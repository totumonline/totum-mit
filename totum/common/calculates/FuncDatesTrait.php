<?php
declare(strict_types=1);

namespace totum\common\calculates;

use DateTime;
use totum\common\errorException;
use totum\common\Formats;

trait FuncDatesTrait
{
    protected function dateFormat(DateTime $date, $fStr, $lang = null): string
    {
        switch ($lang) {
            case 'ru':
                $result = '';
                $format = new Formats();
                foreach (preg_split(
                             '/([DlMF])/',
                             $fStr,
                             -1,
                             PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE
                         ) as $split) {
                    $var = null;
                    switch ($split) {
                        case 'D':
                            $var = 'weekDaysShort';
                        // no break
                        case 'l':
                            $var = $var ?? 'weekDays';
                            $result .= $format->getConstant($var)[$date->format('N')];
                            break;
                        case 'F':
                            $var = 'months';
                        // no break
                        case 'M':
                            $var = $var ?? 'monthsShort';
                            $result .= $format->getConstant($var)[$date->format('n')];
                            break;
                        default:
                            $result .= $date->format($split);
                    }
                }
                return $result;
            default:
                return $date->format($fStr);
        }
    }

    protected function funcDateAdd(string $params): ?string
    {
        $params = $this->getParamsArray($params);
        $this->__checkRequiredParams($params, ['date']);

        if (empty($params['date'])) {
            return null;
        }
        $date = $this->__checkGetDate($params['date'], 'date', 'dateAdd');

        foreach (['days' => 'day', 'hours' => 'hour', 'minutes' => 'minute', 'years' => 'year', 'months' => 'month'] as $period => $datePeriodStr) {
            if (!empty($params[$period])) {
                $this->__checkNumericParam($params[$period], $period);

                $periodVal = intval($params[$period]);
                if ($periodVal > 0) {
                    $periodVal = '+' . $periodVal;
                }

                $date->modify($periodVal . ' ' . $datePeriodStr);
            }
        }
        return $this->dateFormat($date, ($params['format'] ?? 'Y-m-d H:i'), $params['lang'] ?? null);
    }

    protected function funcDateDiff($params)
    {
        return $this->funcDiffDates($params);
    }

    /** @noinspection PhpMissingBreakStatementInspection */

    protected function funcDateFormat(string $params): string
    {
        $params = $this->getParamsArray($params);

        $this->__checkRequiredParams($params, ['date', 'format']);
        if (empty($params['date'])) {
            return '';
        }

        $this->__checkNotArrayParams($params, ['date', 'format']);

        $date = $this->__checkGetDate(($params['date'] ?? ''), 'date', 'DateFormat');
        return $this->dateFormat($date, strval($params['format']), $params['lang'] ?? null);
    }

    protected function funcDateWeekDay(string $params): string
    {
        $params = $this->getParamsArray($params);
        $date = $this->__checkGetDate(($params['date'] ?? ''), 'date', 'DateFormat');

        return match ($params['format'] ?? null) {
            'number' => $date->format('N'),
            'short' => Formats::weekDaysShort[$date->format('N')],
            'full' => Formats::weekDays[$date->format('N')],
            default => throw new errorException($this->translate('The [[%s]] parameter is not correct.', 'format')),
        };

    }

    /*
     * @deprecated
     * */

    protected function funcDiffDates(string $params): float|int
    {
        $vars = $this->getParamsArray($params, ['date']);
        if (empty($vars['date']) || count($vars['date']) != 2) {
            throw new errorException($this->translate('There must be two [[%s]] parameters in the [[%s]] function.',
                ['date', 'diffDates']));
        }

        $date1 = $this->__checkGetDate($vars['date'][0], 'date - 1', 'diffDates');
        $date2 = $this->__checkGetDate($vars['date'][1], 'date - 2', 'diffDates');

        return $this->diffDates($date1, $date2, $vars['unit'] ?? 'day');
    }

    protected function funcNowDate(string $params): string
    {
        $params = $this->getParamsArray($params);
        return $this->dateFormat(date_create(), ($params['format'] ?? 'Y-m-d H:i'), $params['lang'] ?? null);
    }

    private function diffDates(DateTime $date1, DateTime $date2, $unit): float|int
    {
        switch ($unit) {
            case 'year':
                $diff = $date1->diff($date2);
                return $diff->y + $diff->m / 12 + $diff->d / 365;
            case 'month':
                $diff = $date1->diff($date2);
                return $diff->m + $diff->y * 12 + $diff->d / 30;
            case 'minute':
                return ($date2->getTimestamp() - $date1->getTimestamp()) / (60);
            case 'hour':
                return ($date2->getTimestamp() - $date1->getTimestamp()) / (60 * 60);
            default:
                return ($date2->getTimestamp() - $date1->getTimestamp()) / (24 * 60 * 60);
        }
    }
}