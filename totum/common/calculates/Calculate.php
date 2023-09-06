<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 27.03.17
 * Time: 15:44
 */

namespace totum\common\calculates;

use totum\common\criticalErrorException;
use totum\common\errorException;
use totum\common\Field;
use totum\common\FieldModifyItem;
use totum\common\Lang\LangInterface;
use totum\common\Lang\RU;
use totum\common\Model;
use totum\common\sql\SqlException;
use totum\models\TmpTables;
use totum\tableTypes\aTable;

class Calculate
{
    use FuncDatesTrait;
    use FuncArraysTrait;
    use FuncStringsTrait;
    use FuncNumbersTrait;
    use FuncNowTrait;
    use FuncTablesTrait;
    use FuncOperationsTrait;
    use ParsesTrait;
    use FuncServicesTrait;

    protected static $codes;
    protected static $initCodes = [];

    protected array $startSections;

    protected $cachedCodes = [];
    protected $oldRow;
    protected $oldTbl;
    protected $newVal;
    protected $row;
    protected $tbl;
    protected $code;
    protected $varName;
    protected $log;
    protected $error;
    protected $varData;
    protected $newLog;
    protected $newLogParent;
    protected $whileIterators = [];
    /**
     * @var aTable
     */
    protected $Table;
    protected $vars = [];
    protected $fixedCodeNames = [];
    protected $fixedCodeVars = [];
    protected $CodeLineParams = [];
    protected $CodeStrings = [];
    /**
     * @var array
     */
    protected $CodeLineCatches;


    public function __construct($code)
    {
        if (!is_array($code)) {
            if (!array_key_exists($code, static::$initCodes)) {
                static::$initCodes[$code] = static::parseTotumCode($code);
            }
            $code = static::$initCodes[$code];
        }

        $this->fixedCodeNames = $code['==fixes=='] ?? [];
        $this->CodeStrings = $code['==strings=='] ?? [];
        $this->CodeLineParams = $code['==lineParams=='] ?? [];
        $this->CodeLineCatches = $code['==catches=='] ?? [];


        unset($code['==fixes==']);
        unset($code['==strings==']);
        unset($code['==lineParams==']);

        $this->code = $code;
        $this->formStartSections();
    }

    public function setStartSections($sections)
    {
        $this->startSections = [];
        foreach ($sections as $section) {
            $this->startSections[] = $this->code[$section];
        }
    }

    protected function clearNONEFields(bool|array $fields): bool|array
    {
        if ($fields) {
            foreach ($fields as $k => $v) {
                if ($v === '*NONE*') {
                    unset($fields[$k]);
                }
            }
        }
        return $fields;
    }

    protected function funcSelectRowListForSelect($params)
    {
        $params = $this->getParamsArray($params, ['where', 'order'], ['previewscode']);
        $this->__checkRequiredParams($params, ['field'], 'selectRowListForSelect');

        $params2 = $params;
        $baseField = $params['bfield'] ?? 'id';

        $params2['field'] = [$params['field'], $baseField, 'is_del'];
        $params2['sfield'] = [];
        if (!empty($params['section'])) {
            $params2['sfield'][] = $params2['section'];
            unset($params2['section']);
            $params2['with__sectionFunction'] = true;
        }

        $rows = $this->select($params2, 'rows');

        $rows = array_map(
            function ($row) use ($params, $baseField) {
                $r = ['value' => $row[$baseField]
                    , 'is_del' => $row['is_del']
                    , 'title' => $row[$params['field']]];

                if (!empty($params['section'])) {
                    $r['section'] = $row['__sectionFunction'] ?? $row[$params['section']];
                }
                return $r;
            },
            $rows
        );

        if (!empty($params['preview']) || !empty($params['previewscode'])) {
            $rows['previewdata'] = true;
        };
        return $rows;
    }

    protected function funcSelectRowListForTree($params)
    {
        $params = $this->getParamsArray($params, ['where', 'order']);

        $params2 = $params;

        $params['bfield'] = $params['bfield'] ?? 'id';

        $params2['field'] = [$params['field'], $params['bfield'], 'is_del'];
        $params2['sfield'] = [];

        $this->__checkNotEmptyParams($params, 'parent');

        $this->parentName = $params['parent'];
        $params2['field'][] = $params['parent'];

        if (key_exists('disabled', $params)) {
            $this->__checkListParam($params['disabled'], 'disabled');
            $disabled = array_flip(array_unique($params['disabled']));
        } else {
            $disabled = [];
        }

        $rows = $this->select($params2, 'rows');

        $thisField = $this->Table->getFields()[$this->varName];

        $ParentField = null;
        $treeListPrep = '';
        $treeRows = [];

        /* Дополненное дерево - ид папок из другой таблицы */
        if (empty($thisField['treeAutoTree'])) {
            $sourceTable = $this->getSourceTable($params);

            if (!$sourceTable) {
                return [];
            }
            $ParentField = Field::init($sourceTable->getFields()[$params['parent']], $sourceTable);
            if ($ParentField->getData('codeSelectIndividual')) {
                throw new errorException($this->translate('The [[%s]] parameter must [[not]] be [[%s]].',
                    [$params['parent'], 'codeSelectIndividual']));
            } else {
                $treeListPrep = 'PP/';

                $v = ['v' => $rows[0][$params['parent']] ?? null, '__isForChildTree' => true];
                $parentList = $ParentField->calculateSelectList($v, $rows[0], $sourceTable);
                unset($parentList['previewdata']);

                if ($ParentField->getData('type') === 'tree') {
                    foreach ($parentList as $val => $_r) {
                        $treeRows[] = ['value' => $treeListPrep . $val, 'title' => $_r[0], 'is_del' => $_r['1'], 'parent' => $_r[3] ? $treeListPrep . $_r[3] : null];
                    }
                } else {
                    foreach ($parentList as $val => $_r) {
                        $treeRows[] = ['value' => $treeListPrep . $val, 'title' => $_r[0], 'is_del' => $_r['1'], 'parent' => null];
                    }
                }
            }
        }
        foreach ($rows as $row) {
            $r = ['value' => $row[$params['bfield']]
                , 'is_del' => $row['is_del']
                , 'title' => $row[$params['field']]];

            if (is_array($row[$params['parent']])) {
                throw new errorException($this->translate('The %s field value should not be an array.',
                    $params['parent']));
            }

            $r['parent'] = ($row[$params['parent']] ?? null) ? $treeListPrep . $row[$params['parent']] : null;

            if (key_exists($row[$params['bfield']], $disabled)) {
                $r['disabled'] = true;
            }
            $treeRows[] = $r;
        }


        if (!empty($params['roots'])) {
            $TreeRowsChildrenIndexed = [];
            $TreeRowsIndexed = [];
            $newTreeRows = [];

            foreach ($treeRows as $row) {
                if ($parent = ($row['parent'] ?? null)) {
                    $TreeRowsChildrenIndexed[$row['parent']][] = $row;
                }
                $TreeRowsIndexed[$row['value']] = $row;
            }
            $getChildren = function ($parent) use (&$getChildren, &$newTreeRows, $TreeRowsIndexed, $TreeRowsChildrenIndexed) {
                if (key_exists($parent, $TreeRowsChildrenIndexed)) {
                    foreach ($TreeRowsChildrenIndexed[$parent] as $child) {
                        $newTreeRows[] = $child;
                        $getChildren($child['value']);
                    }
                }
            };
            foreach ((array)$params['roots'] as $root) {
                $root = $treeListPrep . $root;
                if (key_exists($root, $TreeRowsIndexed)) {
                    $TreeRowsIndexed[$root]['parent'] = null;
                    $newTreeRows[] = $TreeRowsIndexed[$root];
                    $getChildren($root);
                }
            }
            return $newTreeRows;
        }


        return $treeRows;
    }

    protected function getConditionsResult(array $params): bool
    {
        $conditionTest = true;
        if (!empty($params['condition'])) {
            foreach ($params['condition'] as $i => $c) {
                $condition = $this->execSubCode($c, 'condition' . (1 + $i));


                if (!is_bool($condition)) {
                    throw new errorException($this->translate('Parameter [[%s]] returned a non-true/false value.',
                        (1 + $i)));
                }
                if (!$condition) {
                    $conditionTest = false;
                    break;
                }
            }
        }
        return $conditionTest;
    }

    protected function translate(string $str, mixed $vars = []): string
    {
        return $this->Table?->getTotum()->getLangObj()->translate($str, $vars) ?? $str;
    }

    protected function formStartSections()
    {
        if (key_exists('=', $this->code)) {
            $this->startSections = ['=' => $this->code['=']];
            unset($this->code['=']);
        }
    }

    protected static function __compare_normalize($n)
    {

        switch (gettype($n)) {
            case 'NULL':
                return '';
            case 'boolean':
                return $n ? 'true' : 'false';
            case 'integer':
            case 'double':
            case 'string':
                if (is_numeric($n) && !is_infinite($n)) {
                    return bcadd(number_format((float)$n, 12, '.', ''), 0, 10);
                }
                return $n;
            case 'array':
                return static::__compare_array_normalize($n);
        }
        return $n;
    }

    protected static function __compare_array_normalize($n)
    {
        ksort($n);
        foreach ($n as &$nItem) {
            $nItem = static::__compare_normalize($nItem);
        }
        unset($nItem);
        return $n;
    }

    protected static function _compare_n_array($operator, $n, array $n2, LangInterface $Lang, $key = null, $isTopLevel = false)
    {
        switch ($operator) {
            case '!==':
                $r = true;
                break;
            case '==':
                $r = false;
                break;
            case '=':
            case '!=':
                if (count($n2) === 0) {
                    if ($isTopLevel && (($n ?? '') === '')) {
                        $r = true;
                    } else {
                        $r = false;
                    }
                } else {
                    $r = false;

                    if (is_null($key)) {
                        foreach ($n2 as $nItem) {
                            if (static::compare('==', $n, $nItem, $Lang)) {
                                $r = true;
                                break;
                            }
                        }
                    } else {
                        $key = strval($key);
                        foreach ($n2 as $nKey => $nItem) {
                            if (strval($nKey) === $key && static::compare('==', $n, $nItem, $Lang)) {
                                $r = true;
                                break;
                            }
                        }
                    }
                }

                if ($operator === '!=') {
                    $r = !$r;
                }
                break;
            default:
                throw new errorException($Lang->translate('For lists comparisons, only available =, ==, !=, !==.'));
        }
        return $r;
    }

    public static function compare(string $operator, $n, $n2, LangInterface $Lang)
    {
        $nIsRow = $n2IsRow = false;
        if ($nIsArray = is_array($n)) {
            if (count($n) > 0 && (array_keys($n) !== range(0, count($n) - 1))) {
                $nIsRow = true;
                $nIsArray = false;
            }
        }

        if ($n2IsArray = is_array($n2)) {
            if (count($n2) > 0 && (array_keys($n2) !== range(0, count($n2) - 1))) {
                $n2IsRow = true;
                $n2IsArray = false;
            }
        }


        if (($nIsArray && $n2IsArray) || ($n2IsRow && $nIsRow)) {
            switch ($operator) {
                case '!==':
                    $r = false;
                    if (count($n) === count($n2)) {
                        $r = static::__compare_array_normalize($n) === static::__compare_array_normalize($n2);
                    }
                    $r = !$r;
                    break;
                case '==':
                    $r = false;
                    if (count($n) === count($n2)) {
                        $r = static::__compare_array_normalize($n) === static::__compare_array_normalize($n2);
                    }
                    break;
                case '=':
                    if (count($n) === 0 && count($n2) === 0) {
                        $r = true;
                    } else {
                        $r = false;

                        foreach ($n as $key => $nItem) {
                            if (static::_compare_n_array('=', $nItem, $n2, $Lang, $nIsRow ? $key : null)) {
                                $r = true;
                                break 2;
                            }
                        }
                    }
                    break;
                case '!=':
                    if (count($n) === 0 && count($n2) === 0) {
                        $r = false;
                    } else {
                        $r = true;

                        foreach ($n as $key => $nItem) {
                            if (static::_compare_n_array('=', $nItem, $n2, $Lang, $nIsRow ? $key : null)) {
                                $r = false;
                                break 2;
                            }
                        }
                    }
                    break;
                default:
                    throw new errorException($Lang->translate('For lists comparisons, only available =, ==, !=, !==.'));
            }
        } elseif (is_numeric($n) && is_numeric($n2)) {
            $r = match ($n <=> $n2) {
                0 => in_array($operator, ['>=', '<=', '=', '==']),
                1 => in_array($operator, ['>=', '>', '!=', '!==']),
                default => in_array($operator, ['<=', '<', '!=', '!==']),
            };
        } elseif ($n2IsArray) {
            return static::_compare_n_array($operator, $n, $n2, $Lang, null, true);
        } elseif ($nIsArray) {
            return static::_compare_n_array($operator, $n2, $n, $Lang, null, true);
        } elseif ($n2IsRow || $nIsRow) {
            $r = match ($operator) {
                '!=', '!==' => true,
                '==', '=' => false,
                default => throw new errorException($Lang->translate('For lists comparisons, only available =, ==, !=, !==.')),
            };
        } else {
            $r = match (static::__compare_normalize($n) <=> static::__compare_normalize($n2)) {
                0 => in_array($operator, ['>=', '<=', '=', '==']),
                1 => in_array($operator, ['>=', '>', '!=', '!==']),
                default => in_array($operator, ['<=', '<', '!=', '!==']),
            };
        }

        return $r;
    }

    public static function getDateObject($dateFromParams, LangInterface $Lang): \DateTime|null
    {
        if (is_array($dateFromParams)) {
            throw new errorException($Lang->translate('There should be a date, not a list.'));
        }
        if (is_bool($dateFromParams)) {
            return null;
        }
        $dateFromParams = strval($dateFromParams);
        if ($dateFromParams !== '') {
            if (is_numeric($dateFromParams)) {
                $dt = new \DateTime();
                return $dt->setTimestamp((int)$dateFromParams);
            }
            foreach (['Y-m-d', 'd.m.y', 'd.m.Y', 'Y-m-d H:i', 'd.m.y H:i', 'd.m.Y H:i', 'Y-m-d H:i:s'] as $format) {
                if ($date = date_create_from_format($format, $dateFromParams)) {
                    if (!strpos($format, 'H')) {
                        $date->setTime(0, 0);
                    }
                    return $date;
                }
            }
        }
        return null;
    }

    protected function getCodes($stringIN, array $spesialSections = [])
    {
        $cacheString =& self::$codes;

        /*if (strpos($stringIN, '"') !== false || strpos($stringIN, '{') !== false) {
            $cacheString = &$this->cachedCodes;
        }*/

        if ($spesialSections) {
            $spesialSections = '|(?<as>\b' . implode('\b|\b', $spesialSections) . '\b)';
        } else {
            $spesialSections = '';
        }

        $cachKey = $stringIN . $spesialSections;


        if (empty($cacheString[$cachKey])) {
            $i = 0;
            $done = 1;
            $code = [];
            $string = $stringIN;

            while ($done && ($i < 100) && $string !== '') {
                $done = 0;
                $i++;
                $string = preg_replace_callback(
                    '`(?<func>(?<func_name>[a-zA-Z]{2,}\d*)*\((?<func_params>[^)]*)\))' . //func,func_name,func_params
                    '|(?<num>\-?[\d.,]+\%?)' .                      //num
                    '|(?<operator>\^|\+|\-|\*|/)' .       //operator
                    '|(?<string>"[^"]*")' .            //string
                    '|(?<comparison>!==|==|>=|<=|>|<|=|!=)' .       //comparison
                    '|(?<bool>false|true)' .   //10
                    '|(?<param>(?<param_name>(?:\$@|@\$|\$\$|\$\#?|\#(?i:(?:old|s|h|c|l|pnl)\.)?\$?)(?:[a-zA-Z0-9_]+(?:{[^}]*})?))(?<param_items>(?:\[\[?\$?\#?[a-zA-Z0-9_"]+\]?\])*))' . //param,param_name,param_items
                    '|(?<dog>@(?<dog_table>[a-zA-Z0-9_]{3,})\.(?<dog_field>[a-zA-Z0-9_]{2,})(?:\.(?<dog_field2>[a-zA-Z0-9_]{2,}))?(?<dog_items>(?:\[\[?\$?\#?[a-zA-Z0-9_"]+\]?\])*))' .
                    $spesialSections .      //as
                    '`',
                    //dog,dog_table, dog_field,dog_items

                    function ($matches) use ($string, &$done, &$code) {
                        if ($matches[0] !== '') {
                            if ($matches['func']) {
                                if (($funcName = $matches['func_name'])) {
                                    $code[] = [
                                        'type' => 'func',
                                        'func' => $funcName,
                                        'params' => $matches['func_params']
                                    ];
                                } else {
                                    throw new errorException($this->translate('TOTUM-code format error [[%s]].',
                                        $string));
                                }
                            } elseif ($matches['num'] !== '') {
                                $number = $matches['num'];
                                $cn = [
                                    'type' => 'string',
                                    'string' => $number
                                ];
                                if (substr($number, -1, 1) === '%') {
                                    $cn['percent'] = true;
                                    $cn['string'] = trim(substr($number, 0, -1));
                                }

                                //$code[] = $number;
                                $code[] = $cn;
                            } elseif ($operator = $matches['operator']) {
                                $code[] = [
                                    'type' => 'operator',
                                    'operator' => $operator
                                ];
                            } elseif ($param = $matches['string']) {
                                if (strlen(substr($param, 1, -1)) > 0) {
                                    $code[] = [
                                        'type' => 'stringParam',
                                        'string' => substr($param, 1, -1)
                                    ];
                                } else {
                                    $code[] = [
                                        'type' => 'string',
                                        'string' => ''
                                    ];
                                }
                            } elseif ($comparison = $matches['comparison']) {
                                if (array_key_exists('comparison', $code)) {
                                    throw new errorException($this->translate('There must be only one comparison operator in the string.'));
                                }

                                $code['comparison'] = $comparison;
                            } elseif ($param = $matches['bool']) {
                                $code[] = [
                                    'type' => 'boolean',
                                    'boolean' => $param
                                ];
                            } elseif ($param = $matches['param']) {
                                $code[] = [
                                    'type' => 'param',
                                    'param' => $matches['param_name'],
                                    'items' => $matches['param_items']
                                ];
                            } elseif ($param = $matches['dog']) {
                                $code[] = [
                                    'type' => 'param',
                                    'param' => $param,
                                    'table' => $matches['dog_table'],
                                    'field' => $matches['dog_field'],
                                    'field2' => $matches['dog_field2'],
                                    'items' => $matches['dog_items']
                                ];
                            } elseif ($param = $matches['as']) {
                                $code[] = [
                                    'type' => 'as',
                                    'string' => $param
                                ];
                            }

                            $done = 1;
                        }
                        return '';
                    },
                    $string,
                    1
                );

                $string = trim($string);
                if ($done === 0 && $string) {
                    throw new errorException($this->translate('TOTUM-code format error [[%s]].', $string));
                }
            }
            $cacheString[$cachKey] = $code;
        }

        return $cacheString[$cachKey];
    }

    public function getLogVar()
    {
        return $this->newLog;
    }

    public function getError()
    {
        return $this->error;
    }

    public function exec($fieldData, array $newVal, $oldRow, $row, $oldTbl, $tbl, aTable $table, $vars = []): mixed
    {
        $this->error = null;

        $this->vars = $vars;
        $this->fixedCodeVars = [];

        $this->whileIterators = [];
        $this->setEnvironmentVars($fieldData, $newVal, $oldRow, $row, $oldTbl, $tbl, $table);
        $this->varName = $fieldData['name'];

        $this->newLog = [];
        $this->newLogParent = &$this->newLog;


        $params = ['calc' => static::class, 'itemId' => $row['id'] ?? $oldRow['id'] ?? null];
        if ($this->varName[0] === 'C') {
            $params['name'] = $this->varName;
        } else {
            $params['field'] = $this->varName;
        }

        if (!key_exists('cType', $table->getCalculateLog()->getParams())) {
            $Log = $table->calcLog($params);
        }

        try {
            if (empty($this->startSections)) {
                throw new errorException($this->translate('Code format error - no start section.'));
            }

            foreach ($this->startSections as $sectionName => $section) {
                try {
                    $r = $this->execSubCode($section, $sectionName);
                } catch (\Exception $exception) {
                    if (key_exists($sectionName, $this->CodeLineCatches)) {
                        if (key_exists($this->CodeLineCatches[$sectionName], $this->code)) {
                            $this->vars['exception'] = $exception->getMessage();
                            $r = $this->execSubCode(
                                $this->code[$this->CodeLineCatches[$sectionName]],
                                $this->CodeLineCatches[$sectionName]
                            );
                        } else {
                            throw new errorException($this->translate('The [[catch]] code of line [[%s]] was not found.',
                                $this->code[$this->CodeLineCatches[$sectionName]]));
                        }
                    } else {
                        throw $exception;
                    }
                }
            }
            if (!empty($Log)) {
                $table->calcLog($Log, 'result', $r);
            }
        } catch (errorException $e) {
            $this->newLog['text'] = ($this->newLog['text'] ?? '') . $this->translate('ERR!');
            $this->newLog['children'][] = ['type' => 'error', 'text' => $e->getMessage()];
            $this->error = $e->getMessage();

            if (!empty($Log)) {
                $table->calcLog($Log, 'error', $this->error);
            }
            if (get_called_class() !== Calculate::class) {
                throw $e;
            }
        } catch (\Exception $e) {
            $this->newLog['text'] = ($this->newLog['text'] ?? '') . $this->translate('ERR!');
            if (is_a($e, SqlException::class)) {
                $this->newLog['children'][] =
                    ['type' => 'error', 'text' => $this->translate('Database error while processing [[%s]] code.',
                        $e->getMessage())];
                $this->error = $this->translate('Database error while processing [[%s]] code.', $e->getMessage());
            } else {
                $this->newLog['children'][] =
                    ['type' => 'error', 'text' => $this->translate('Critical error while processing [[%s]] code.',
                        $e->getMessage())];
                $this->error = $this->translate('Critical error while processing [[%s]] code.', $e->getMessage());
            }
            if (!empty($Log)) {
                $table->calcLog($Log, 'error', $this->error);
            }

            throw $e;
        }
        if ($this->error) {
            $this->error .= ' (' . $this->translate('field [[%s]] of [[%s]] table',
                    [$this->varName, $this->Table->getTableRow()['name']]) . ')';
        }

        return $r ?? $this->error;
    }

    protected function setEnvironmentVars($varData, $newVal, $oldRow, $row, $oldTbl, $tbl, $table)
    {
        // Log::calcs($newVal, $row, $tbl, $dectimalPlaces);

        $this->varName = $varData['name'];
        $this->varData = $varData;
        $this->newVal = $newVal;
        $this->oldRow = $oldRow;
        $this->row = $row;
        $this->oldTbl = $oldTbl;
        $this->tbl = $tbl;
        $this->Table = $table;
    }

    protected function operatorExec($operator, $left, $right)
    {
        if ($left != 0) {
            $this->__checkNumericParam($left, $this->translate('left element'));
        }
        if ($right != 0) {
            $this->__checkNumericParam($right, $this->translate('right element'));
        }
        $func = match ($operator) {
            '+' => 'bcadd',
            '-' => 'bcsub',
            '*' => 'bcmul',
            '^' => 'bcpow',
            '/' => bccomp($right,
                0,
                10) === 0 ? throw new errorException($this->translate('Division by zero.')) : 'bcdiv',
            default => throw new errorException($this->translate('Unknown operator [[%s]].')),
        };

        $res = $func($left, $right, 10);
        return Calculate::rtrimZeros($res);
    }

    public function getReadCodeForLog($code)
    {
        $code = preg_replace_callback(
            '/{(.*?)}/',
            function ($m) {
                if ($m[1] === '') {
                    return '{}';
                }
                return '{' . $this->CodeLineParams[$m[1]] . '}';
            },
            $code
        );
        $code = preg_replace_callback(
            '/"(.*?)"/',
            function ($m) {
                if ($m[1] === '') {
                    return '""';
                }
                $qoute = $this->CodeStrings[$m[1]][0];

                switch ($qoute) {
                    case '"':
                    case "'":
                        return $qoute . substr($this->CodeStrings[$m[1]], 1) . $qoute;
                }

                if (str_starts_with($this->CodeStrings[$m[1]], 'json')) {
                    return $this->CodeStrings[$m[1]];
                }

                if (str_starts_with($this->CodeStrings[$m[1]], 'str')) {
                    $typeLength = 3;
                } else {
                    $typeLength = 4;
                }
                $type = substr($this->CodeStrings[$m[1]], 0, $typeLength);

                $back_replace_strings = function ($str) {
                    return preg_replace_callback(
                        '/"(\d+)"/',
                        function ($matches) {
                            return '"' . substr($this->CodeStrings[$matches[1]], 1) . '"';
                        },
                        $str
                    );
                };

                $replaced = $back_replace_strings($this->CodeStrings[$m[1]]);
                return $type . '`' . substr($replaced, $typeLength) . '`';


            },
            $code
        );
        return $code;
    }

    protected function inVarsApply($inVars)
    {
        $pastVals = [];
        foreach ($inVars as $k => $v) {
            if (array_key_exists($k, $this->vars)) {
                $pastVals[$k] = [true, $this->vars[$k]];
            } else {
                $pastVals[$k] = [false];
            }
            $this->vars[$k] = $v;
        }
        return $pastVals;
    }

    protected function inVarsRevert($pastVals)
    {
        foreach ($pastVals as $k => $v) {
            if (!$v[0]) {
                unset($this->vars[$k]);
            } else {
                $this->vars[$k] = $v[1];
            }
        }
    }

    protected function execSubCode($code, $codeName, $notLoging = false, $inVars = []): mixed
    {
        $Log = $this->Table->calcLog(['name' => $codeName, 'code' => function () use ($code) {
            return $this->getReadCodeForLog($code);
        }]);
        try {
            $pastVals = $this->inVarsApply($inVars);

            $codes = $this->getCodes($code);

            $result = null;
            $result2 = null;

            $res =& $result;
            $operator = null;
            $comparison = null;

            foreach ($codes as $k => $r) {
                $rTmp = null;
                if ($k === 'comparison') {
                    $comparison = $r;
                    $res =& $result2;
                    continue;
                } elseif (is_string($r)) {
                    $rTmp = $r;
                } else {
                    switch ($r['type']) {
                        case 'spec_math':
                            $rTmp = $this->parseTotumMath($r['string']);
                            break;
                        case 'operator':
                            $operator = $r['operator'];
                            continue 2;
                        case 'func':
                            $func = $r['func'];

                            if (str_starts_with($func, 'ext')) {
                                $func = $this->Table->getTotum()->getConfig()->getCalculateExtensionFunction($func);
                                try {
                                    $rTmp = $func->call($this, $r['params'], $rTmp);
                                } catch (errorException $e) {
                                    $e->addPath($this->translate('Function [[%s]]', $r['func']));
                                    throw $e;
                                }
                            } else {
                                $funcName = 'func' . $func;
                                if (!is_callable([$this, $funcName])) {
                                    throw new errorException($this->translate('Function [[%s]] is not found.', $func));
                                }

                                try {
                                    $rTmp = $this->$funcName($r['params'], $rTmp);
                                } catch (errorException $e) {
                                    $e->addPath($this->translate('Function [[%s]]', $r['func']));
                                    throw $e;
                                }
                            }


                            break;
                        case 'param':
                            $rTmp = $this->getParam($r['param'], $r);
                            break;
                        case 'stringParam':
                            $spec = substr($this->CodeStrings[$r['string']], 0, 4);

                            $rTmp = match ($spec) {
                                'math' => $this->parseTotumMath(substr($this->CodeStrings[$r['string']], 4)),
                                'json' => $this->parseTotumJson(substr($this->CodeStrings[$r['string']], 4)),
                                'jsot' => $this->parseTotumJson(substr($this->CodeStrings[$r['string']], 4), true),
                                'cond' => $this->parseTotumCond(substr($this->CodeStrings[$r['string']], 4)),
                                'qrow' => $this->parseTotumQrow(substr($this->CodeStrings[$r['string']], 4)),
                                default => match (substr($this->CodeStrings[$r['string']], 0, 3)) {
                                    'str' => $this->parseTotumStr(substr($this->CodeStrings[$r['string']], 3)),
                                    default => substr($this->CodeStrings[$r['string']], 1),
                                },
                            };

                            break;
                        case 'string':
                            $rTmp = $r['string'];
                            break;
                        case 'boolean':
                            $rTmp = $r['boolean'] === 'true';
                            break;
                        default:
                            throw  new  errorException($this->translate('TOTUM-code format error [[%s]].',
                                print_r($r, 1)));
                    }
                }

                if ($operator
                    ||
                    /*Фикс парсинга вычитания*/
                    (!is_null($res) && is_numeric($rTmp) && $rTmp < 0 && ($operator = '+'))) {
                    $res = $this->operatorExec($operator, $res, $rTmp);
                    $operator = null;
                } else {
                    if (!is_null($res)) {
                        throw new errorException($this->translate('TOTUM-code format error: missing operator in expression [[%s]].',
                            $code));
                    }

                    $res = $rTmp;
                }
            }

            if ($comparison) {
                $r = static::compare($comparison, $result, $result2, $this->getLangObj());
                $result = $r;
            }

            $this->inVarsRevert($pastVals);

            $this->Table->calcLog($Log, 'result', $result);
        } catch (\Exception $e) {
            $this->Table->calcLog($Log, 'error', $e->getMessage());
            throw $e;
        }

        return $result;
    }

    protected function getExecParamVal($paramVal, string $paramsName, $isTreePartValue = false)
    {
        try {
            $codes = $this->getCodes($paramVal);
        } catch (errorException $e) {
            throw new errorException($this->translate('TOTUM-code format error [[%s]].', $paramVal));
        }
        if (count($codes) < 2 || !key_exists(1, $codes)) {
            throw new errorException($this->translate('The [[%s]] parameter must contain 2 elements.', $paramsName));
        }

        if ($isTreePartValue && !key_exists('comparison', $codes)) {
            throw new errorException($this->translate('The %s parameter must contain a comparison element.',
                $paramsName));
        }

        if (is_array($codes[0])) {
            $varName = $this->__getValue($codes[0]);
        } else {
            $varName = $codes[0];
        }

        if (is_array($codes[1])) {
            $value = $this->__getValue($codes[1]);
        } else {
            $value = $codes[1];
        }
        if ($isTreePartValue) {
            return [
                'field' => $varName,
                'operator' => $codes['comparison'],
                'value' => $value
            ];
        }
        return [$varName => $value];
    }

    protected function getExecVariableVal($paramVal)
    {
        try {
            $codes = $this->getCodes($paramVal);
        } catch (errorException $e) {
            throw new errorException($this->translate('TOTUM-code format error [[%s]].', $paramVal));
        }

        foreach ($codes as &$v) {
            if (is_array($v)) {
                $v = $this->__getValue($v);
            }
        }
        unset($v);

        return $codes;
    }

    protected function getParam($param, $paramArray)
    {
        $r = null;
        $isHashtag = false;
        if (is_array($param) || strlen($param) === 0) {
            throw new errorException($this->translate('TOTUM-code format error [[%s]].', $this->varName));
        }

        switch ($param[0]) {
            case '@':
                if ($param[1] === '$') {
                    $paramName = substr($param, 2);
                    $r = $this->Table->getTotum()->getConfig()->globVar($paramName);
                } else {
                    /*Ищем по другому полю*/

                    $processHardSelect = function ($WhereFieldName) use (&$paramArray) {
                        if (empty($paramArray['items'])) {
                            $r = $this->Table->getSelectByParams(
                                ['table' => $paramArray['table'],
                                    'field' => $paramArray['field'],
                                    'order' => [['field' => 'id', 'ad' => 'asc']],
                                ],
                                'list',
                                $this->row['id'] ?? null,
                                get_class($this) === Calculate::class
                            );
                        } else {
                            $this->__processParamItems($paramArray['items'],
                                function ($item, $isSection, $_itemFull) use ($WhereFieldName, &$paramArray, &$r) {
                                    if ($isSection) {
                                        $r = $this->Table->getSelectByParams(
                                            ['table' => $paramArray['table'],
                                                'field' => $paramArray['field'],
                                                'order' => [['field' => 'id', 'ad' => 'asc']],
                                                'where' => [
                                                    ['field' => $WhereFieldName, 'operator' => '=', 'value' => $item]
                                                ]],
                                            'list',
                                            $this->row['id'] ?? null,
                                            get_class($this) === Calculate::class
                                        );
                                    } else {
                                        $r = $this->Table->getSelectByParams(
                                            ['table' => $paramArray['table'],
                                                'field' => $paramArray['field'],
                                                'order' => [['field' => 'id', 'ad' => 'asc']],
                                                'where' => [
                                                    ['field' => $WhereFieldName, 'operator' => '=', 'value' => $item]
                                                ]],
                                            'field',
                                            $this->row['id'] ?? null,
                                            get_class($this) === Calculate::class
                                        );
                                    }
                                    $paramArray['items'] = substr($paramArray['items'], mb_strlen($_itemFull));
                                    return true;
                                });
                        }
                        return $r;
                    };

                    if (!empty($paramArray['field2'])) {
                        $r = $processHardSelect($paramArray['field2']);
                    } elseif ($paramArray['field'] === 'id' || $paramArray['field'] === 'n' || ($this->Table->getTotum()->getTable($paramArray['table'],
                            $this->Table->getCycle()?->getId()
                            ?? (($this->row['id'] ?? null) && $this->Table->isCalcsTableFromThisCyclesTable($paramArray['table']) ? $this->row['id'] : null)
                        )->getFields()[$paramArray['field']]['category'] ?? null) === 'column') {
                        $r = $processHardSelect('id');
                    } else {
                        $r = $this->Table->getSelectByParams(
                            ['table' => $paramArray['table'],
                                'field' => $paramArray['field']],
                            'field',
                            $this->row['id'] ?? null,
                            get_class($this) === Calculate::class
                        );
                    }
                }
                $isHashtag = true;
                break;
            case '$':
                if ($param[1] === '@') {
                    $paramName = substr($param, 2);
                    $r = $this->Table->getTotum()->getConfig()->procVar($paramName);
                } elseif ($param[1] === '#') {
                    $nameVar = substr($param, 2);
                    switch ($nameVar) {
                        case 'nh':
                            $r = $this->Table->getTotum()->getConfig()->getFullHostName();
                            break;
                        case 'nti':
                            $r = $this->funcNowTableId();
                            break;
                        case 'ntn':
                            $r = $this->funcNowTableName();
                            break;
                        case 'nth':
                            $r = $this->funcNowTableHash();
                            break;
                        case 'nf':
                            $r = $this->funcNowField();
                            break;
                        case 'nfv':
                            $r = $this->funcNowFieldValue();
                            break;
                        case 'onfv':
                            $r = $this->getParam('#old.' . $this->varName, ['param' => '#old.' . $this->varName]);
                            break;
                        case 'nci':
                            $r = $this->funcNowCycleId();
                            break;
                        case 'nd':
                            $r = date('Y-m-d');
                            break;
                        case 'ndt':
                            $r = date('Y-m-d H:i');
                            break;
                        case 'ndts':
                            $r = date('Y-m-d H:i:s');
                            break;
                        case 'lc':
                            $r = [];
                            break;
                        case 'nr':
                            $r = $this->funcNowRoles();
                            break;
                        case 'nu':
                            $r = $this->funcNowUser();
                            break;
                        case 'nl':
                            $r = "\n";
                            break;
                        case 'tb':
                            $r = "\t";
                            break;
                        case 'duplicatedId':
                            $r = $this->vars[$nameVar] ?? 0;
                            break;
                        case 'ih':
                            $r = $this->Table->getInsertRowHash();
                            break;
                        default:
                            if (array_key_exists($nameVar, $this->whileIterators)) {
                                $r = $this->whileIterators[$nameVar];
                            } else {
                                if (!array_key_exists($nameVar, $this->vars)) {
                                    throw new errorException($this->translate('Variable [[%s]] is not defined.',
                                        $nameVar));
                                }
                                if (gettype($this->vars[$nameVar]) === 'object') {
                                    $this->vars[$nameVar] = $this->vars[$nameVar]();
                                }
                                $r = $this->vars[$nameVar];
                            }
                    }

                    $isHashtag = true;
                } else {
                    if ($param[1] === '$') {
                        $codeName = $this->getParam(
                            $param = substr($param, 1),
                            ['type' => 'param', 'param' => $param]
                        );
                    } else {
                        $codeName = substr($param, 1);
                    }

                    if (is_array($codeName) || is_bool($codeName)) {
                        throw new errorException($this->translate('[[%s]] should be of type string.', 'Code line'));
                    }
                    $inVars = [];
                    if ($varsStart = strpos($codeName, '{')) {
                        $codeNum = substr($codeName, $varsStart + 1, -1);
                        if ($codeNum !== '') {
                            $vars = $this->CodeLineParams[$codeNum];
                            $vars = $this->getParamsArray($vars, ['var'], ['var']);
                            foreach ($vars['var'] ?? [] as $var) {
                                $inVars = array_merge($inVars, $this->getExecParamVal($var, 'var'));
                            }
                        }
                        $codeName = substr($codeName, 0, $varsStart);
                    }

                    if (!array_key_exists($codeName, $this->code)) {
                        throw new errorException($this->translate('Code [[%s]] was not found.', $codeName));
                    }

                    /** ~codeName **/
                    if (in_array($codeName, $this->fixedCodeNames)) {
                        $cacheCodeName = $codeName . json_encode($inVars, JSON_UNESCAPED_UNICODE);
                        if (!array_key_exists($cacheCodeName, $this->fixedCodeVars)) {
                            $this->fixedCodeVars[$cacheCodeName] = $this->execSubCode(
                                $this->code[$codeName],
                                $param,
                                false,
                                $inVars
                            );
                        } else {
                            $Log = $this->Table->calcLog(['name' => $codeName, 'type' => 'fixed']);
                            $this->Table->calcLog($Log, 'result', $this->fixedCodeVars[$cacheCodeName]);
                        }
                        $r = $this->fixedCodeVars[$cacheCodeName];
                    } else {
                        try {
                            $r = $this->execSubCode($this->code[$codeName], $param, false, $inVars);
                        } catch (errorException $e) {
                            $e->addPath($this->translate('Code line [[%s]].', $codeName));
                            throw $e;
                        }
                    }
                }
                break;
            case '#':
                $nameVar = substr($param, 1);

                if ($nameVar[0] === '$') {
                    $nameVar = $this->getParam($nameVar, ['type' => 'param', 'param' => $nameVar]);
                }

                if (is_array($nameVar) || is_bool($nameVar)) {
                    throw new errorException($this->translate('Invalid parameter name'));
                }
                $nameVar = (string)$nameVar;

                if (array_key_exists($nameVar, $this->whileIterators)) {
                    $r = $this->whileIterators[$nameVar];
                } else {
                    if (preg_match('/^old\./i', $nameVar)) {
                        $nameVar = substr($nameVar, 4);

                        if (array_key_exists($nameVar, $this->oldRow ?? [])) {
                            $rowVar = $this->oldRow[$nameVar];
                        } elseif (array_key_exists($nameVar, $this->oldTbl['params'] ?? [])) {
                            $rowVar = $this->oldTbl['params'][$nameVar];
                        } else {
                            $rowVar = '';
                        }
                    } elseif (str_starts_with($nameVar, 's.') || str_starts_with($nameVar, 'l.')) {
                        $paramArray['param'] = substr($nameVar, 2);

                        if ($fName = $this->getParam($paramArray['param'], $paramArray)) {
                            if ($selectField = ($this->Table->getFields()[$fName] ?? null)) {
                                $paramArray['param'] = '#' . $fName;

                                $Field = Field::init($selectField, $this->Table);
                                $r = match (substr($nameVar, 0, 2)) {
                                    's.' => $Field->getSelectValue(
                                        $this->getParam($paramArray['param'], $paramArray),
                                        $this->row,
                                        $this->tbl
                                    ),
                                    'l.' => $Field->getLevelValue(
                                        $this->getParam($paramArray['param'], $paramArray),
                                        $this->row,
                                        $this->tbl
                                    ),
                                };

                            }
                        }
                    } elseif (preg_match('/^prv\./i', $nameVar)) {
                        $nameVar = substr($nameVar, 4);

                        if (!key_exists('PrevRow', $this->row)) {
                            throw new errorException($this->translate('Previous row not found. Works only for calculation tables.'));
                        } else {
                            $rowVar = $this->row['PrevRow'][$nameVar] ?? '';
                        }
                    } elseif (preg_match('/^pnl\./i', $nameVar)) {
                        $nameVar = substr($nameVar, 4);

                        if (!key_exists('__edit_hash', $this->vars)) {
                            return $this->getParam('#' . $nameVar, $paramArray);
                        } else {
                            $hashData = TmpTables::init($this->Table->getTotum()->getConfig())->getByHash(
                                TmpTables::SERVICE_TABLES['edit_row'],
                                $this->Table->getUser(),
                                $this->vars['__edit_hash']
                            );
                            $rowData = $this->Table->checkEditRow($hashData);
                            if ($rowData['id'] === $this->row['id']) {
                                $rowVar = $rowData[$nameVar] ?? [];
                            } else {
                                return $this->getParam('#' . $nameVar, $paramArray);
                            }
                        }
                    } else {
                        switch (substr($nameVar, 0, 2)) {
                            case 'h.':
                                $nameVar = substr($nameVar, 2);
                                $typeVal = 'h';
                                break;
                            case 'c.':
                                $nameVar = substr($nameVar, 2);
                                $typeVal = 'c';
                                break;
                        }

                        if ($nameVar === $this->varName && $this::class === Calculate::class) {
                            throw new errorException($this->translate('Cannot access the current value of the field from the Code.'));
                        } elseif (key_exists($nameVar, $this->row)) {
                            $rowVar = $this->row[$nameVar];
                        } elseif (key_exists($nameVar, $this->tbl['params'] ?? [])) {
                            $rowVar = $this->tbl['params'][$nameVar];
                        } elseif (key_exists(
                                $nameVar,
                                $this->oldRow ?? []
                            ) && !key_exists(
                                $nameVar,
                                $this->row ?? []
                            )) {
                            if (in_array($nameVar, Model::serviceFields)) {
                                $rowVar = null;
                            } else {
                                $this->Table->getTotum()->addOrderFieldCodeError($this->Table, $this->varName);
                                $rowVar = ['v' => null];
                            }
                        } elseif (key_exists($nameVar, $this->Table->getSortedFields()['filter'])) {
                            $rowVar = ['v' => null];
                        } elseif ($nameVar === 'id' && key_exists(
                                $this->varName,
                                $this->Table->getFields()
                            ) && $this->Table->getFields()[$this->varName]['category'] === 'column') {
                            $rowVar = null;
                        } else {
                            throw new errorException($this->translate('Field [[%s]] is not found.', $nameVar));
                        }
                    }

                    if (isset($rowVar)) {
                        if (in_array($nameVar, Model::serviceFields)) {
                            $r = $rowVar;
                        } else {
                            if (is_string($rowVar)) {
                                $rowVar = json_decode($rowVar, true);
                            }

                            $r = match ($typeVal ?? null) {
                                'h' => $rowVar['h'] ?? false,
                                'c' => key_exists('c', $rowVar) ? $rowVar['c'] : ($rowVar['v'] ?? null),
                                default => $rowVar['v'] ?? null,
                            };
                        }
                    }
                }
                $isHashtag = true;
                break;
            case '\'':
                $r = substr($param, 1, -1);
                break;
            case '"':
                $r = substr($this->CodeStrings[substr($param, 1, -1)], 1);
                break;
            default:
                if (in_array($param, ['true', 'false'])) {
                    $r = $param === 'true';
                } else {
                    $r = $param;
                }
        }


        $paramName = $param;
        if (!empty($paramArray['items'])) {


            $itemsNames = '';

            $this->__processParamItems($paramArray['items'],
                function ($item, $isSection) use (&$r, &$itemsNames) {
                    if (is_array($item)) {
                        throw new errorException($this->translate('The key must be an one value',
                            $item));
                    }

                    $itemsNames .= "[$item]";

                    if ($isSection) {
                        if (is_array($r)) {
                            $r = array_map(
                                function ($_ri) use ($item) {
                                    if (!is_array($_ri) || !key_exists(
                                            $item,
                                            $_ri
                                        )) {
                                        throw new errorException($this->translate('The key [[%s]] is not found in one of the array elements.',
                                            $item));
                                    }
                                    return $_ri[$item];
                                },
                                $r
                            );
                        } else {
                            $itemsNames .= '...';
                            $r = null;
                            return true;
                        }
                    } elseif (is_array($r) && array_key_exists($item, $r)) {
                        $r = $r[$item];
                    } else {
                        $itemsNames .= '...';
                        $r = null;
                        return true;
                    }
                });
            $paramName = $param . $itemsNames;
            $isHashtag = true;
        }

        if ($isHashtag) {
            $this->Table->getCalculateLog()->addParam($paramName, $r);
        }
        return $r;
    }

    protected function __processParamItems($itemsString, $callback)
    {
        if (preg_match_all('/\[(.*?)(?:\]\]|\])/', $itemsString, $items)) {
            foreach ($items[0] as $_itemFull) {
                $_item = substr($_itemFull, 1, -1);
                $isSection = $_item[0] === '[' && substr($_item, -1, 1) === ']';
                if ($isSection) {
                    $_item = substr($_item, 1, -1);
                }
                $item = $this->getParam($_item, ['type' => 'param', 'param' => $_item]);
                if (is_numeric($item)) {
                    $item = (string)$item;
                }
                if ($callback($item, $isSection, $_itemFull)) {
                    break;
                }

            }
        }
    }

    protected function __checkGetDate($dateFromParams, $paramName, $funcName)
    {
        if (empty($dateFromParams) || !($date = static::getDateObject($dateFromParams, $this->getLangObj()))) {
            throw new errorException($this->translate('The [[%s]] parameter is not correct.', $paramName));
        }
        return $date;
    }

    protected function __checkBoolOrNull(mixed $fieldvalue)
    {
        return match ($fieldvalue) {
            'true', true => true,
            'false', false => false,
            default => null
        };
    }


    protected function __checkTableIdOrName($tableId, string $paramName): array
    {
        if (empty($tableId)) {
            throw new errorException($this->translate('Fill in the parameter [[%s]].', $paramName));
        }

        $table = $this->Table->getTotum()->getTableRow($tableId);
        if (!$table) {
            throw new errorException($this->translate('Table [[%s]] is not found.', $tableId));
        }
        return $table;
    }

    protected function __checkRequiredParams(array $params, array|string $requireds, string $funcName = null)
    {
        foreach ((array)$requireds as $param) {
            if (!key_exists($param, $params)) {
                if ($funcName) {
                    throw new errorException($this->translate('Parametr [[%s]] is required in [[%s]] function.',
                        [$param, $funcName]));
                }
                throw new errorException($this->translate('Fill in the parameter [[%s]].',
                    [$param]));
            }
        }
    }

    protected function __checkNotArrayParams(array $params, array|string $cheks)
    {
        foreach ((array)$cheks as $param) {
            if (key_exists($param, $params) && is_array($params[$param])) {
                throw new errorException($this->translate('The parameter [[%s]] should [[not]] be of type row/list.',
                    $param));
            }
        }
    }

    protected function __checkNumericParam($isDigit, $paramName, $withEfloats = false)
    {
        if (is_array($isDigit) || !is_numeric($isDigit) || (!$withEfloats && !preg_match('/^[-+]?[0-9.]+$/',
                    $isDigit))) {
            throw new errorException($this->translate('The %s parameter must be a number.', $paramName));
        }
    }

    protected function __checkListParam(&$List, $paramName)
    {
        if (is_null($List) || $List === '') {
            $List = [];
        }
        if (!is_array($List)) {
            throw new errorException($this->translate('The parameter [[%s]] should be of type row/list.', $paramName));
        }
    }

    protected function __getValue(array $paramArray)
    {
        return match ($paramArray['type']) {
            'param' => $this->getParam($paramArray['param'], $paramArray),
            'as', 'string' => $paramArray['string'],
            'stringParam' => match ($spec = substr($this->CodeStrings[$paramArray['string']], 0, 4)) {
                'math' => $this->parseTotumMath(substr($this->CodeStrings[$paramArray['string']], 4)),
                'json' => $this->parseTotumJson($str = substr($this->CodeStrings[$paramArray['string']], 4)),
                'jsot' => $this->parseTotumJson($str = substr($this->CodeStrings[$paramArray['string']], 4), true),
                'cond' => $this->parseTotumCond($str = substr($this->CodeStrings[$paramArray['string']], 4)),
                'qrow' => $this->parseTotumQrow(substr($this->CodeStrings[$paramArray['string']], 4)),
                default => match (substr($spec, 0, 3)) {
                    'str' => $this->parseTotumStr(substr($this->CodeStrings[$paramArray['string']], 3)),
                    default => substr($this->CodeStrings[$paramArray['string']], 1),
                },
            },
            'boolean' => !($paramArray['boolean'] === 'false'),
            default => throw new errorException($this->translate('TOTUM-code format error [[%s]].',
                $paramArray['string'])),
        };
    }

    protected function getParamsArray(string|array $paramsString, array $arrayParams = [], array $notExecParams = [], array $threePartParams = ['where', 'filter', 'key']): array
    {
        if (is_array($paramsString)) {
            return $paramsString;
        }

        $notExecParams = array_merge($notExecParams, ['condition', 'then', 'else']);
        $arrayParams = array_merge($arrayParams, ['where', 'order', 'filter', 'condition', 'preview']);

        $params = [];
        /*Кеш матчей не ускоряет*/
        if (preg_match_all('/([a-z0-9_]{2,}):([^;]+)(;|$)/', $paramsString, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $param = $match[1];
                $paramVal = null;
                if (in_array($param, $arrayParams)) {
                    if (!isset($params[$param])) {
                        $params[$param] = [];
                    }
                    $paramVal = &$params[$param][];
                } else {
                    $paramVal = &$params[$param];
                }

                if (isset($params[$param]) && !is_array($params[$param])) {
                    throw new errorException($this->translate('[[%s]] is not a multiple parameter.', $param));
                }
                $paramVal = trim($match[2]);

                switch ($param) {
                    case 'order':
                        if (preg_match(
                            '/^(.*?)(?i:(asc|desc))?$/',
                            $paramVal,
                            $matches
                        )) {
                            $field = $this->execSubCode($matches[1], 'orderField');
                            $AscDesc = !empty($matches[2]) && strtolower($matches[2]) === 'desc' ? 'desc' : 'asc';

                            $paramVal = [
                                'field' => $field, //$this->getParam($field, []),
                                'ad' => $AscDesc
                            ];
                        } else {
                            throw new errorException($this->translate('TOTUM-code format error [[%s]].',
                                'order: ' . $paramVal));
                        }
                        break;
                    default:
                        if (!in_array($param, $notExecParams)) {
                            if (in_array($param, $threePartParams)) {
                                try {
                                    $whereCodes = $this->getCodes($paramVal);
                                } catch (errorException $e) {
                                    throw new errorException($this->translate('TOTUM-code format error [[%s]].',
                                        $param . ': ' . $paramVal . ' ' . $e->getMessage()));
                                }


                                if ($param === 'where' && count($whereCodes) === 1) {
                                    $value = $this->__getValue($whereCodes[0]);
                                    if (is_array($value) && count($value) === 1 && key_exists('qrow', $value)) {
                                        $paramVal = $value;
                                    }
                                    break;
                                }

                                if (count($whereCodes) != 3) {
                                    throw new errorException($this->translate('The [[%s]] parameter must contain 3 elements.',
                                        $param));
                                }
                                if (empty($whereCodes['comparison'])) {
                                    throw new errorException($this->translate('The %s parameter must contain a comparison element.',
                                        $param));
                                }
                                if ($param === 'filter' && $whereCodes['comparison'] !== '=') {
                                    throw new errorException($this->translate('The [[%s]] parameter must be [[%s]].',
                                        [$param, '=']));
                                }

                                if (is_array($whereCodes[0])) {
                                    $field = $this->__getValue($whereCodes[0]);
                                } else {
                                    $field = $whereCodes[0];
                                }
                                if (is_array($whereCodes[1])) {
                                    $value = $this->__getValue($whereCodes[1]);
                                } else {
                                    $value = $whereCodes[1];
                                }

                                $paramVal = [
                                    'field' => $this->getParam(
                                        $field,
                                        []
                                    ),
                                    'operator' => $whereCodes['comparison'],
                                    'value' => $value
                                ];
                            } else {
                                $paramVal = $this->execSubCode($paramVal, $param, true);
                            }
                        }
                        break;
                }

                unset($paramVal);
            }
        }

        return $params;
    }

    protected function getSourceTable($params): ?aTable
    {
        $tableRow = $this->__checkTableIdOrName($params['table'], 'table');

        switch ($tableRow['type']) {
            case 'calcs':
                if (empty($params['cycle'])) {
                    if ($tableRow['id'] === $this->Table->getTableRow()['id'] ||
                        ($this->Table->getTableRow()['type'] === 'calcs' &&
                            $tableRow['tree_node_id'] === $this->Table->getTableRow()['tree_node_id'])) {
                        $params['cycle'] = $this->Table->getCycle()?->getId();
                    } elseif ((int)$tableRow['tree_node_id'] === $this->Table->getTableRow()['id']) {
                        $params['cycle'] = $this->row['id'] ?? null;
                    }
                }

                try {
                    $this->__checkNotEmptyParams($params, 'cycle');
                    $Cycle = $this->Table->getTotum()->getCycle($params['cycle'], $tableRow['tree_node_id']);
                } catch (errorException) {
                    return null;
                }

                $table = $Cycle->getTable($tableRow);
                unset($params['cycle']);

                break;
            case 'tmp':
                if (empty($params['hash']) && $this->Table->getTableRow()['name'] != $tableRow['name']) {
                    throw new errorException($this->translate('Fill in the parameter [[%s]].', 'hash'));
                }
                if (!empty($params['hash'])) {
                    $table = $this->Table->getTotum()->getTable($tableRow, $params['hash'] ?? null);
                } else {
                    $table = $this->Table;
                }
                break;
            default:
                $table = $this->Table->getTotum()->getTable($tableRow);

                break;
        }

        $table->addCalculateLogInstance($this->Table->getCalculateLog());

        return $table;
    }

    protected function getEnvironment(): array
    {
        $env = [
            'table' => $this->Table->getTableRow()['name']
        ];
        $env['extra'] = match ($this->Table->getTableRow()['type']) {
            'calcs' => $this->Table->getCycle()->getId(),
            'tmp' => $this->Table->getTableRow()['sess_hash'],
            default => null
        };

        if (!empty($this->row['id'])) {
            $env['id'] = $this->row['id'];
        }

        return $env;
    }


    protected function __getActionFields($fieldParams, $funcName)
    {
        $fields = [];

        if (empty($fieldParams)) {
            return false;
        }
        foreach ($fieldParams as $f) {
            $fc = $this->getCodes($f);
            try {
                if (count($fc) < 2) {
                    throw new \Exception();
                }


                $fieldName = $this->__getValue($fc[0]);
                if (empty($fieldName)) {
                    throw new \Exception();
                }


                $fieldValue = $this->__getValue($fc[2] ?? $fc[1] ?? throw new errorException($this->translate('TOTUM-code format error: missing part of parameter.')));

                if (in_array(strtolower($funcName), ['set', 'setlist', 'setlistextended'])) {
                    if ($fc[1]['type'] === 'operator') {
                        $percent = $fc[2]['percent'] ?? false;
                        $fieldValue = new FieldModifyItem($fc[1]['operator'], $fieldValue, $percent);
                    } elseif (empty($fc['comparison'])) {
                        $fieldValue = new FieldModifyItem('+', $fieldValue, $fc[1]['percent'] ?? false);
                    }
                }

                //if (is_null($fieldValue)) throw new Exception();
            } catch (errorException $e) {
                $e->addPath('[[' . $funcName . ']] field [[' . $this->getReadCodeForLog($f) . ']]');
                throw $e;
            } catch (SqlException $e) {
                //throw $e;
                throw new errorException($e->getMessage() . ' [[' . $funcName . ']] field [[' . $this->getReadCodeForLog($f) . ']]');
            } catch (criticalErrorException $e) {
                $e->addPath('[[' . $funcName . ']] field [[' . $this->getReadCodeForLog($f) . ']]');
                throw $e;
            } catch (\Exception $e) {
                throw new errorException($this->translate('TOTUM-code format error [[%s]].',
                    $this->getReadCodeForLog($f)));
            }

            $fields[$fieldName] = $fieldValue;
        }
        return $fields;
    }

    protected function getLangObj(): LangInterface
    {
        return $this->Table->getLangObj();
    }

    protected function __checkNotEmptyParams(array $params, array|string $requireds)
    {
        foreach ((array)$requireds as $param) {
            if (empty($params[$param])) {
                throw new errorException($this->translate('Fill in the parameter [[%s]].',
                    [$param]));
            }
        }
    }
}
