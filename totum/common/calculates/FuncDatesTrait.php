<?php
declare(strict_types=1);

namespace totum\common\calculates;

use DateTime;
use totum\common\errorException;

trait FuncDatesTrait
{
    protected function dateFormat(DateTime $date, $fStr, $lang = null): string
    {
        if (empty($lang)) {
            $lang = $this->getLangObj();
        } else {
            if (!class_exists('totum\\common\\Lang\\' . strtoupper($lang))) {
                throw new errorException($this->translate('Language %s not found.', strtolower($lang)));
            }
            $lang = new ('totum\\common\\Lang\\' . strtoupper($lang))();
        }

        return $lang->dateFormat($date, $fStr);
    }

    protected function funcDateAdd(string $params): null|array|string
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

    protected function funcDateFormat(string $params): array|string
    {
        $params = $this->getParamsArray($params, ['replace'], ['replace']);

        $this->__checkRequiredParams($params, ['date', 'format']);

        if (($params['date'] ?? null) === []) {
            return [];
        } elseif (empty($params['date'])) {
            return '';
        }
        $this->__checkNotArrayParams($params, ['format']);
        $format = strval($params['format']);

        if (is_array($params['date'])) {
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
                            if (!$replace($value) && !empty($value) && !is_numeric($value)) {
                                try {
                                    $date = $this->__checkGetDate($value, 'date', 'DateFormat');
                                    $value = $this->dateFormat($date, $format, $params['lang'] ?? null);
                                } catch (\Exception $e) {
                                }
                            }
                        } else {
                            try {
                                if (!is_numeric($value)) {
                                    $date = $this->__checkGetDate($value, 'date', 'DateFormat');
                                    $value = $this->dateFormat($date, $format, $params['lang'] ?? null);
                                }
                            } catch (\Exception $e) {
                            }
                        }

                    }
                }
                unset($value);
                return $array;
            };

            return $formatter($params['date'], $recursive);

        } else {
            $date = $this->__checkGetDate($params['date'], 'date', 'DateFormat');
        }
        return $this->dateFormat($date, $format, $params['lang'] ?? null);
    }

    protected function funcDateWeekDay(string $params): string
    {
        $params = $this->getParamsArray($params);
        $date = $this->__checkGetDate(($params['date'] ?? ''), 'date', 'DateFormat');

        return match ($params['format'] ?? null) {
            'number' => $date->format('N'),
            'short' => $this->getLangObj()->dateFormat($date, 'D'),
            'full' => $this->getLangObj()->dateFormat($date, 'l'),
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

    protected function funcDateIntervals($params)
    {
        $params = $this->getParamsArray($params);
        $this->__checkNotEmptyParams($params, ['date', 'type']);
        $this->__checkNumericParam($params['num'] ?? null, 'num');
        $this->__checkNotArrayParams($params, ['format', 'date']);
        $date = $this->__checkGetDate($params['date'], 'date', 'dateIntervals');
        $dateTime = match ($params['datetime'] ?? false) {
            true, 'true' => true,
            default => false
        };

        $result = [];

        switch ($params['type']) {
            case 'hour':
                $func = function ($start) use (&$result, $params) {
                    $row = [];
                    $row['start'] = clone $start;
                    $start->modify('+ 1 hour');
                    $row['end'] = (clone $start)->modify('-1 sec');
                    return (object)$row;
                };
                $start = $date->modify('-' . $date->format('i') . 'minutes - ' . $date->format('s') . ' sec');
                break;
            case 'day':
                $func = function ($start) use (&$result, $params) {
                    $row = [];
                    $row['start'] = clone $start;
                    $start->modify('+ 1 day');
                    $row['end'] = (clone $start)->modify('-1 sec');
                    return (object)$row;
                };
                $start = $date->modify('-' . $date->format('H') . 'hours -' . $date->format('i') . 'minutes - ' . $date->format('s') . ' sec');
                break;
            case 'week':
                $func = function ($start) use (&$result, $params) {
                    $row = [];
                    $row['start'] = clone $start;
                    $start->modify('+ 7 day');
                    $row['end'] = (clone $start)->modify('-1 sec');
                    return (object)$row;
                };

                $startDay = match ($params['weekdaystart'] ?? null) {
                    default => 1,
                    'tue' => 2,
                    'wed' => 3,
                    'thu' => 4,
                    'fri' => 5,
                    'sat' => 6,
                    'sun' => 7,
                };

                if ($date->format('N') >= $startDay) {
                    $startDays = $date->format('N') - $startDay;
                } else {
                    $startDays = $date->format('N') + (7 - $startDay);
                }


                $start = $date->modify('-' . $startDays . ' days -' . $date->format('H') . 'hours -' . $date->format('i') . 'minutes - ' . $date->format('s') . ' sec');
                break;
            case 'month':
                $func = function ($start) use (&$result, $params) {
                    $row = [];
                    $row['start'] = clone $start;
                    $start->modify('+ ' . $start->format('t') . ' day');
                    $row['end'] = (clone $start)->modify('-1 sec');
                    return (object)$row;
                };
                $start = date_create($date->format('Y') . '-' . $date->format('m') . '-01 00:00:00');
                break;
            case 'year':
                $func = function ($start) use (&$result, $params) {
                    $row = [];
                    $row['start'] = clone $start;
                    $start->modify('+ 1 year');
                    $row['end'] = (clone $start)->modify('-1 sec');
                    return (object)$row;
                };
                $start = date_create($date->format('Y') . '-01-01 00:00:00');
                break;
        };

        $defaultFormat = $dateTime ? 'Y-m-d H:i' : 'Y-m-d';
        for ($i = 0; $i < $params['num']; $i++) {
            $_ = $func($start);
            $row = ['start' => $_->start->format($defaultFormat), 'end' => $_->end->format($defaultFormat)];
            if (!empty($params['format'])) {
                $row['startf'] = $_->start->format($params['format']);
                $row['endf'] = $_->end->format($params['format']);
            }
            $result[] = $row;
        }

        return $result;

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