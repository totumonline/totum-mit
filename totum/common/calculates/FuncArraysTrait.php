<?php
declare(strict_types=1);

namespace totum\common\calculates;

use Exception;
use totum\common\errorException;
use totum\common\Lang\RU;

trait FuncArraysTrait
{

    protected function funcListAdd(string $params): array
    {
        $params = $this->getParamsArray($params, ['list', 'item']);

        $MainList = [];
        $this->__checkListParam($params['list'], 'list');

        foreach ($params['list'] as $i => $list) {
            if ($list) {
                $this->__checkListParam($list, 'list' . (++$i));
                $MainList = array_merge($MainList, $list);
            }
        }
        foreach ($params['item'] ?? [] as $item) {
            if (is_null($MainList)) {
                $MainList = [$item];
            } else {
                $MainList[] = $item;
            }
        }
        return array_values($MainList);
    }

    protected function funcListCheck(string $params): bool
    {
        $params = $this->getParamsArray($params);
        $this->__checkRequiredParams($params, 'list');
        return is_array($params['list']);
    }

    protected function funcListCount(string $params): int
    {
        $params = $this->getParamsArray($params);

        $this->__checkListParam($params['list'], 'list');

        return count($params['list']);
    }

    protected function funcListCreate(string $params): array
    {
        $params = $this->getParamsArray($params, ['item']);
        return $params['item'] ?? [];
    }

    protected function funcListCross(string $params): array
    {
        $params = $this->getParamsArray($params, ['list']);

        $MainList = null;

        foreach ($params['list'] as $i => $list) {
            $this->__checkListParam($list, 'list' . (++$i));
            if (is_null($MainList)) {
                $MainList = $list;
            } else {
                $MainList = array_intersect($MainList, $list);
            }
        }

        return array_values($MainList);
    }

    protected function funcListCut(string $params): array
    {
        $params = $this->getParamsArray($params);

        $this->__checkListParam($params['list'], 'list');
        $list = $params['list'];
        $num = (int)($params['num'] ?? 1);

        if ($num !== 0) {
            if ($num > count($list)) {
                throw new errorException($this->translate('The [[%s]] parameter must be [[%s]].',
                    ['num', '<=' . count($params['list'])]));
            }
            switch ($params['cut'] ?? null) {
                case 'first':
                    array_splice($list, 0, $num);
                    break;
                case 'last':
                    array_splice($list, -$num, $num);
                    break;
                default:
                    throw new errorException($this->translate('The [[%s]] parameter is not correct.', 'cut'));
            }
        }
        return $list;
    }

    protected function funcListFilter(string $params): array
    {
        $params = $this->getParamsArray($params, []);
        $this->__checkListParam($params['list'], 'list');
        $this->__checkNotEmptyParams($params, ['key']);
        $isGerExp = $params['regexp'] ?? false;

        if ($isGerExp) {
            $regExpFlags = $params['regexp'] !== true && $params['regexp'] !== 'true' ? $params['regexp'] : 'u';

            if (!in_array($params['key']['operator'], ['=', '!=', '!==', '==='])) {
                throw new errorException($this->translate('The [[%s]] parameter must be [[%s]].',
                    ['key operator', '=, != (for regexp)']));
            } else {
                $operator = in_array($params['key']['operator'], ['=', '===']);
            }
            $pattern = '/' . str_replace('/', '\/', $params['key']['value']) . '/' . $regExpFlags;
            $matches = [];

            $getCompare = function ($v) use ($operator, $pattern, &$matches) {
                if (preg_match($pattern, $v, $_matches)) {
                    $matches[] = $_matches;
                    return $operator;
                }
                return !$operator;
            };
        } else {
            $operator = $params['key']['operator'];
            $value = $params['key']['value'];
            $getCompare = function ($v) use ($operator, $value) {
                return Calculate::compare($operator, $v, $value, $this->getLangObj());
            };
        }

        switch ($params['key']['field']) {
            case 'value':
                $filter = function ($k, $v) use ($getCompare) {
                    return $getCompare($v);
                };
                break;
            case 'key':
                $filter = function ($k, $v) use ($getCompare) {
                    return $getCompare($k);
                };
                break;
            default:

                if ($params['key']['field'] === 'item') {
                    $this->__checkRequiredParams($params, ['item']);
                } else {
                    $params['item'] = $params['key']['field'];
                }

                $skip = $params['skip'] ?? false;

                $filter = function ($k, $v) use ($params, $skip, $getCompare) {
                    if (!is_array($v)) {
                        if (!$skip) {
                            throw new errorException($this->translate('The array element does not fit the filtering conditions - the value is not a list.'));
                        }
                        return false;
                    } elseif (!array_key_exists(
                        $params['item'],
                        $v
                    )) {
                        if (!$skip) {
                            throw new errorException($this->translate('The array element does not fit the filtering conditions - [[item]] is not found.'));
                        }
                        return false;
                    }
                    return $getCompare($v[$params['item']]);
                };
                break;
        }

        $filtered = [];
        $nIsRow = false;
        if ((array_keys($params['list']) !== range(0, count($params['list']) - 1))) {
            $nIsRow = true;
        }
        foreach ($params['list'] as $k => $v) {
            if ($filter($k, $v)) {
                if ($nIsRow) {
                    $filtered[$k] = $v;
                } else {
                    $filtered[] = $v;
                }
            }
        }
        if ($isGerExp && ($params['matches'] ?? null)) {
            $this->vars[$params['matches']] = $matches;
        }

        return $filtered;
    }

    protected function funcListItem(string $params)
    {
        $params = $this->getParamsArray($params);

        $this->__checkListParam($params['list'], 'list');

        $this->__checkRequiredParams($params, ['item']);
        $this->__checkNotArrayParams($params, ['item']);


        return $params['list'][$params['item']] ?? null;
    }

    protected function funcListJoin(string $params): string
    {
        $params = $this->getParamsArray($params);
        $this->__checkListParam($params['list'], 'list');
        $this->__checkNotArrayParams($params, ['str']);

        return implode(($params['str'] ?? ''), $params['list']);
    }

    protected function funcListMath(string $params): array
    {
        $params = $this->getParamsArray($params, ['list']);

        $list = $params['list'][0] ?? false;
        $this->__checkListParam($list, 'list');

        $func = match ($params['operator'] ?? '') {
            '+' => function ($l, $num) {
                return round($l + $num, 10);
            },
            '-' => function ($l, $num) {
                return round($l - $num, 10);
            },
            '*' => function ($l, $num) {
                return round($l * $num, 10);
            },
            '^' => function ($l, $num) {
                return pow($l, $num);
            },
            '/' => function ($l, $num) {
                if ((float)$num === 0.0) {
                    throw new errorException($this->translate('Division by zero.'));
                }
                return round($l / $num, 10);
            },
            default => throw new errorException($this->translate('The [[%s]] parameter must be set to one of the following values: %s',
                ['operator', '+,-,/,*'])),
        };

        for ($i = 1; $i < count($params['list']); $i++) {
            $list2 = $params['list'][$i] ?? false;
            $this->__checkListParam($list2, 'list2');
            foreach ($list as $k => &$l) {
                if (empty($l)) {
                    $l = 0;
                }

                if (!is_numeric((string)$l)) {
                    throw new errorException($this->translate('Non-numeric parameter in the list %s', ''));
                }
                if (!key_exists($k, $list2)) {
                    throw new errorException($this->translate('There is no [[%s]] key in the [[%s]] list.',
                        [$k, ($i + 1)]));
                }
                if (empty($list2[$k])) {
                    $list2[$k] = 0;
                }
                if (!is_numeric((string)$list2[$k])) {
                    throw new errorException($this->translate('Non-numeric parameter in the list %s',
                        (string)($i + 1)));
                }

                $l = $func($l, $list2[$k]);
            }
            unset($l);
        }


        if (key_exists('num', $params)) {
            $num = $params['num'];
            $this->__checkNumericParam($num, 'num');
            foreach ($list as &$l) {
                if (empty($l)) {
                    $l = 0;
                }
                if (!is_numeric((string)$l)) {
                    throw new errorException($this->translate('Non-numeric parameter in the list %s', ''));
                }
                $l = $func($l, $num);
            }
            unset($l);
        }
        return $list;
    }

    protected function funcListMax(string $params)
    {
        $params = $this->getParamsArray($params);

        $this->__checkListParam($params['list'], 'list');

        $max = null;
        foreach ($params['list'] as $l) {
            $l = strval($l);
            if (is_null($max)) {
                $max = $l;
                continue;
            }
            if (is_numeric($l) && is_numeric($max)) {
                if (floatval($max) < floatval($l)) {
                    $max = $l;
                }
            } elseif ($l > $max) {
                $max = $l;
            }
        }
        if (is_null($max)) {
            if (array_key_exists('default', $params)) {
                return $params['default'];
            }
            throw new errorException($this->translate('Fill in the parameter [[%s]].', 'default'));
        }

        return $max;
    }

    protected function funcListMin(string $params)
    {
        $params = $this->getParamsArray($params);
        $this->__checkListParam($params['list'], 'list');

        $min = null;
        foreach ($params['list'] as $l) {
            $l = strval($l);
            if (is_null($min)) {
                $min = $l;
                continue;
            }
            if (is_numeric($l) && is_numeric($min)) {
                if (floatval($min) > floatval($l)) {
                    $min = $l;
                }
            } elseif ($l < $min) {
                $min = $l;
            }
        }
        if (is_null($min)) {
            if (array_key_exists('default', $params)) {
                return $params['default'];
            }

            throw new errorException($this->translate('Fill in the parameter [[%s]].', 'default'));
        }

        return $min;
    }

    protected function funcListMinus(string $params): array
    {
        $params = $this->getParamsArray($params, ['list', 'item']);

        $MainList = null;

        if (!$params['list'][0]) {
            return [];
        }

        foreach ($params['list'] as $i => $list) {
            if ($list) {
                $this->__checkListParam($list, 'list' . (++$i));
                if (is_null($MainList)) {
                    $MainList = $list;
                } else {
                    $MainList = array_diff($MainList, $list);
                }
            }
        }
        foreach ($params['item'] ?? [] as $item) {
            $MainList = array_diff($MainList, [$item]);
        }

        return array_values($MainList ?? []);
    }

    protected function funcListNumberRange(string $params): array
    {
        $params = $this->getParamsArray($params);

        $this->__checkRequiredParams($params, ['min', 'max', 'step']);

        $this->__checkNumericParam($params['min'], 'min');
        $this->__checkNumericParam($params['max'], 'max');
        $this->__checkNumericParam($params['step'], 'step');

        if ($params['step'] == 0) {
            throw new errorException($this->translate('The [[%s]] parameter must be [[%s]].', ['step', '!=0']));
        } elseif ($params['step'] > 0) {
            $list = [$next = $params['min']];
            while (($next += $params['step']) < $params['max']) {
                $list[] = $next;
            }
        } else {
            $list = [$next = $params['max']];
            while (($next += $params['step']) > $params['min']) {
                $list[] = $next;
            }
        }
        return $list;
    }

    protected function funcListRepeat(string $params): array
    {
        $params = $this->getParamsArray($params);
        $this->__checkRequiredParams($params, ['item', 'num'], 'ListRepeat');

        return array_fill(0, (int)$params['num'], $params['item']);
    }

    protected function funcListSearch(string $params): array
    {
        $params = $this->getParamsArray($params, []);
        $this->__checkListParam($params['list'], 'list');

        $this->__checkNotEmptyParams($params, 'key');


        switch ($params['key']['field']) {
            case 'value':
                $filter = function ($k, $v) use ($params) {
                    return Calculate::compare($params['key']['operator'],
                        $v,
                        $params['key']['value'],
                        $this->getLangObj());
                };
                break;
            default:
                if ($params['key']['field'] === 'item') {
                    $this->__checkRequiredParams($params, 'item');
                } else {
                    $params['item'] = $params['key']['field'];
                }

                $filter = function ($k, $v) use ($params) {
                    if (!is_array($v)) {
                        throw new errorException($this->translate('The array element does not fit the filtering conditions - the value is not a list.'));
                    } elseif (!array_key_exists(
                        $params['item'],
                        $v
                    )) {
                        throw new errorException($this->translate('The array element does not fit the filtering conditions - [[item]] is not found.'));
                    }
                    return Calculate::compare($params['key']['operator'],
                        $v[$params['item']],
                        $params['key']['value'],
                        $this->getLangObj());
                };
                break;

        }

        $filtered = [];
        foreach ($params['list'] as $k => $v) {
            if ($filter($k, $v)) {
                $filtered[] = $k;
            }
        }

        return $filtered;
    }

    protected function funcListSection(string $params): array
    {
        $params = $this->getParamsArray($params, []);
        $this->__checkListParam($params['list'], 'list');
        $this->__checkRequiredParams($params, ['item']);

        $filter = function ($v) use ($params) {
            if (!is_array($v)) {
                throw new errorException($this->translate('The array element does not fit the filtering conditions - the value is not a list.'));
            } elseif (!array_key_exists(
                $params['item'],
                $v
            )) {
                throw new errorException($this->translate('The array element does not fit the filtering conditions - [[item]] is not found.'));
            }
            return $v[$params['item']];
        };


        $filtered = [];
        foreach ($params['list'] as $k => $v) {
            $filtered[$k] = $filter($v);
        }

        return $filtered;
    }

    protected function funcListSort(string $params): array
    {
        $params = $this->getParamsArray($params, [], [], []);
        $this->__checkListParam($params['list'], 'list');

        $flags = 0;
        $params['type'] = $params['type'] ?? 'regular';
        $this->__checkNotArrayParams($params, ['type', 'key', 'item', 'direction']);

        $flags = match ($params['type']) {
            'number' => $flags | SORT_NUMERIC,
            'string' => $flags | SORT_STRING,
            default => $flags | SORT_REGULAR,
        };

        switch ($params['key'] = $params['key'] ?? 'value') {
            case 'key':
                if (!empty($params['direction']) && $params['direction'] === 'desc') {
                    $isAssoc = (array_keys($params['list']) !== range(
                                0,
                                count($params['list']) - 1
                            )) && count($params['list']) > 0;

                    if ($isAssoc) {
                        krsort($params['list'], $flags);
                    } else {
                        $params['list'] = array_reverse($params['list'], false);
                    }
                } else {
                    ksort($params['list'], $flags);
                }

                break;

            case 'value':
                $isAssoc = (array_keys($params['list']) !== range(
                            0,
                            count($params['list']) - 1
                        )) && count($params['list']) > 0;
                if (!empty($params['direction']) && $params['direction'] === 'desc') {
                    if ($isAssoc) {
                        arsort($params['list'], $flags);
                    } else {
                        rsort($params['list'], $flags);
                    }
                } elseif ($isAssoc) {
                    asort($params['list'], $flags);
                } else {
                    sort($params['list'], $flags);
                }

                break;
            default:
                if ($params['key'] === 'item') {
                    $this->__checkRequiredParams($params, ['item']);
                } else {
                    $params['item'] = $params['key'];
                }

                if (!empty($params['direction']) && $params['direction'] === 'desc') {
                    $sort = SORT_DESC;
                } else {
                    $sort = SORT_ASC;
                }
                $column = array_column($params['list'], $params['item']);
                array_multisort($column, $flags, $sort, $params['list']);
        }

        return $params['list'];
    }

    protected function funcListSum(string $params): float
    {
        $params = $this->getParamsArray($params);
        $this->__checkListParam($params['list'], 'list');

        $sum = 0;
        foreach ($params['list'] as $l) {
            $sum += floatval($l);
        }

        return round($sum, 10);
    }

    protected function funcListTrain(string $params): array
    {
        $params = $this->getParamsArray($params);
        $this->__checkListParam($params['list'], 'list');

        $mainlist = [];
        foreach ($params['list'] as $list) {
            if (!is_array($list)) {
                throw new errorException($this->translate('All list elements must be lists.'));
            }
            $mainlist = array_merge($mainlist, $list);
        }

        return $mainlist;
    }

    protected function funcListUniq(string $params): array
    {
        $params = $this->getParamsArray($params);
        if (!empty($params['list'])) {
            $this->__checkListParam($params['list'], 'list');
            return array_values(
                array_unique(
                    $params['list'],
                    is_array($params['list'][0] ?? null) ? SORT_REGULAR : SORT_STRING
                )
            );
        } else {
            return [];
        }
    }

    protected function funcRowAdd(string $params): array
    {
        $params = $this->getParamsArray($params, ['row', 'field'], ['field']);

        $MainList = [];
        foreach ($params['row'] ?? [] as $i => $row) {
            if ($row) {
                $this->__checkListParam($row, 'row' . (++$i));
                $MainList = array_replace($MainList, $row);
            }
        }
        foreach ($params['field'] ?? [] as $field) {
            $field = $this->getExecParamVal($field, 'field');
            $k = array_keys($field)[0];
            $v = array_values($field)[0];
            if (is_null($MainList)) {
                $MainList = [$k => $v];
            } else {
                $MainList[$k] = $v;
            }
        }
        return $MainList;
    }

    protected function funcRowCreate(string $params): array
    {
        $params = $this->getParamsArray($params, ['field'], ['field']);
        $row = [];
        foreach ($params['field'] ?? [] as $f) {
            $f = $this->getExecParamVal($f, 'field');
            if (ctype_digit(strval(array_keys($f)[0]))) {
                $row = $f + $row;
            } else {
                $row = array_merge($row, $f);
            }
        }

        return $row;
    }

    protected function funcRowCreateByLists(string $params): array
    {
        $params = $this->getParamsArray($params, [], []);
        $this->__checkListParam($params['keys'], 'keys');
        $this->__checkListParam($params['values'], 'values');

        if (count($params['keys']) !== count($params['values'])) {
            throw new errorException($this->translate('The number of the [[%s]] must be equal to the number of [[%s]].',
                ['keys', 'values']));
        }

        return array_combine($params['keys'], $params['values']);
    }

    protected function funcRowKeys(string $params): array
    {
        $params = $this->getParamsArray($params, []);
        $this->__checkListParam($params['row'], 'row');
        return array_keys($params['row']);
    }

    protected function funcRowKeysRemove(string $params): array
    {
        $params = $this->getParamsArray($params, ['key'], [], []);

        $this->__checkListParam($params['row'], 'row');

        if (array_key_exists('keys', $params)) {
            $this->__checkListParam($params['keys'], 'keys');
        }
        $keys = array_unique(array_merge(($params['key'] ?? []), ($params['keys'] ?? [])));

        if (!empty($keys) && !empty($params['row'])) {
            if ($params['recursive'] ?? false) {
                $remover = function ($row) use (&$remover, $keys) {
                    foreach ($keys as $key) {
                        unset($row[$key]);
                    }
                    foreach ($row as $k => $item) {
                        if (is_array($item)) {
                            $row[$k] = $remover($item);
                        }
                    }
                    return $row;
                };
            } else {
                $remover = function ($row) use (&$remover, $keys) {
                    foreach ($keys as $key) {
                        unset($row[$key]);
                    }
                    return $row;
                };
            }
            $row = $remover($params['row']);
        } else {
            $row = $params['row'];
        }

        return $row;
    }

    protected function funcRowKeysReplace(string $params): array
    {
        $params = $this->getParamsArray($params, []);
        $this->__checkListParam($params['row'], 'row');

        $this->__checkRequiredParams($params, ['from', 'to']);

        if (is_array($params['from']) && is_array($params['to'])) {
            if (count($params['from']) != count($params['to'])) {

                throw new errorException($this->translate('The number of the [[%s]] must be equal to the number of [[%s]].',
                    ['from', 'to']));
            }
        }

        if (is_array($params['to']) != is_array($params['from'])) {
            throw new errorException($this->translate('The [[%s]] parameter must be one type with [[%s]] parameter.',
                ['to', 'from']));
        }

        $recursive = $params['recursive'] ?? false;


        if (is_array($params['from']) && is_array($params['to'])) {
            $funcKeyReplace = function ($k) use ($params) {
                $_seach = array_search(strval($k), $params['from']);
                if ($_seach !== false) {
                    return $params['to'][$_seach];
                }
                return $k;
            };
        } elseif (is_array($params['from'])) {
            $funcKeyReplace = function ($k) use ($params) {
                $_seach = array_search(strval($k), $params['from']);
                if ($_seach !== false) {
                    return $params['to'];
                }
                return $k;
            };
        } else {
            $funcKeyReplace = function ($k) use ($params) {
                if (strval($k) == $params['from']) {
                    return $params['to'];
                }
                return $k;
            };
        }


        $funcReplace = function ($row) use ($recursive, &$funcReplace, &$funcKeyReplace) {
            $rowOut = [];
            foreach ($row as $k => $v) {
                if ($recursive && is_array($v)) {
                    $vOut = $funcReplace($v);
                } else {
                    $vOut = $v;
                }
                $rowOut[$funcKeyReplace($k)] = $vOut;
            }
            return $rowOut;
        };

        return $funcReplace($params['row']);
    }

    protected function funcRowListAdd(string $params): array
    {
        $params = $this->getParamsArray($params, ['rowlist', 'field'], ['field']);

        $MainList = [];

        foreach ($params['rowlist'] ?? [] as $i => $rowList) {
            if ($rowList) {
                $this->__checkListParam($rowList, 'rowlist' . (++$i));
                $max = count($MainList) > count($rowList) ? count($MainList) : count($rowList);
                for ($i = 0; $i < $max; $i++) {
                    $MainList[$i] = array_replace($MainList[$i] ?? [], $rowList[$i] ?? []);
                }
            }
        }
        foreach ($params['field'] ?? [] as $field) {
            $field = $this->getExecParamVal($field, 'field');
            $k = array_keys($field)[0];
            $v = array_values($field)[0];
            if (is_array($v) && array_key_exists(0, $v)) {
                $max = count($MainList) > count($v) ? count($MainList) : count($v);
                for ($i = 0; $i < $max; $i++) {
                    $MainList[$i] = array_replace($MainList[$i] ?? [], [$k => $v[$i] ?? null]);
                }
            } else {
                $max = count($MainList);
                for ($i = 0; $i < $max; $i++) {
                    $MainList[$i] = array_replace($MainList[$i] ?? [], [$k => $v]);
                }
            }
        }
        return $MainList;
    }

    protected function funcRowListCreate(string $params): array
    {
        $params = $this->getParamsArray($params, ['field'], ['field']);

        $rows = [];
        $listCount = 0;
        foreach ($params['field'] ?? [] as $f) {
            $f = $this->getExecParamVal($f, 'field');
            $rows = array_replace($rows, $f);
        }
        $rowList = [];
        foreach ($rows as $list) {
            if (is_array($list) && key_exists(0, $list)) {
                if (count($list) > $listCount) {
                    $listCount = count($list);
                }
            }
        }
        foreach ($rows as $f => &$list) {
            if (!is_array($rows[$f]) || !key_exists(0, $rows[$f])) {
                $list = array_fill(0, $listCount, $list);
            } elseif (count($list) < $listCount) {
                $diff = $listCount - count($list);
                for ($i = 0; $i < $diff; $i++) {
                    $list[] = null;
                }
            }
            $list = array_values($list);
        }
        unset($list);

        for ($i = 0; $i < $listCount; $i++) {
            $rowList[$i] = [];
            foreach ($rows as $f => $list) {
                $rowList[$i][$f] = $list[$i];
            }
        }
        return $rowList;
    }

    protected function funcRowValues(string $params): array
    {
        $params = $this->getParamsArray($params, []);
        $this->__checkListParam($params['row'], 'row');
        return array_values($params['row']);
    }

    protected function funclistReplace(string $params): array
    {
        $params = $this->getParamsArray($params, ['action'], ['action'], []);
        $this->__checkListParam($params['list'], 'list');
        $key = $params['key'] ?? null;
        $value = $params['value'] ?? null;

        $this->__checkRequiredParams($params, ['action']);
        $this->__checkNotArrayParams($params, ['key', 'value']);

        $actions = [];
        foreach ($params['action'] as $_a) {
            $actions[] = $this->getCodes($_a);
        }

        $list = $params['list'];
        foreach ($list as $k => $v) {
            $inVars = [];
            $pastVals = [];
            if ($key) {
                $inVars[$key] = $k;
            }
            if ($value) {
                $inVars[$value] = $v;
            }
            if ($inVars) {
                $pastVals = $this->inVarsApply($inVars);
            }

            foreach ($actions as $a => $action) {
                $Log = $this->Table->calcLog(['name' => 'iteration ' . $k . ' / action' . ($a + 1)]);

                try {
                    if (count($action) > 1) {
                        $_k = $this->__getValue($action[0]);
                        $_v = $this->__getValue($action[1]);
                        $list[$k][$_k] = $_v;
                        $this->Table->calcLog($Log, 'result', [$_k => $_v]);
                    } else {
                        $list[$k] = $this->__getValue($action[0]);
                        $this->Table->calcLog($Log, 'result', $list[$k]);
                    }
                } catch (Exception $e) {
                    $this->Table->calcLog($Log, 'error', $e->getMessage());
                    throw $e;
                }
            }

            if ($pastVals) {
                $this->inVarsRevert($pastVals);
            }
        }

        return $list;
    }
}