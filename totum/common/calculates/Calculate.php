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
use totum\common\Formats;
use totum\common\Lang\LangInterface;
use totum\common\Lang\RU;
use totum\common\Model;
use totum\common\sql\SqlException;
use totum\common\Json\TotumJson;
use totum\fieldTypes\File;
use totum\models\TablesFields;
use totum\tableTypes\aTable;
use totum\tableTypes\calcsTable;
use totum\tableTypes\RealTables;

class Calculate
{
    use FuncDatesTrait;
    use FuncArraysTrait;
    use FuncStrTrait;
    use FuncNumbersTrait;
    use FuncNowTrait;

    protected static $codes;
    protected static $initCodes = [];

    protected $startSections;

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
    protected $vars;
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

    protected function translate(string $str, array|string $vars = []): string
    {
        return $this->Table->getTotum()->getLangObj()->translate($str, $vars);
    }

    protected function formStartSections()
    {
        if (key_exists('=', $this->code)) {
            $this->startSections = ['=' => $this->code['=']];
            unset($this->code['=']);
        }
    }

    public static function parseTotumCode($code, $table_name = null): array
    {
        $c = [];
        $fixes = [];
        $catches = [];
        $strings = [];
        $lineParams = [];

        $tableParams = [];


        $usedHashParams = function (string $clearedLine) use ($table_name, &$tableParams) {
            if ($table_name) {
                if (preg_match_all('/(.?)#(?:[a-z]{1,3}\.)?([a-z_0-9]+)/', $clearedLine, $matches)) {
                    foreach ($matches[2] as $i => $m) {
                        if ($matches[1][$i] !== '$') {
                            $tableParams[$table_name][$m] = 1;
                        }
                    }
                }
                if (preg_match_all('/@([a-z_0-9]{2,})\.([a-z_0-9]+)/', $clearedLine, $matches)) {
                    foreach ($matches[2] as $i => $m) {
                        $tableParams[$matches[1][$i]][$m] = 1;
                    }
                }
            }
        };
        $replace_line_params = function ($line) use ($usedHashParams, &$lineParams) {
            return preg_replace_callback(
                '/{([^}]+)}/',
                function ($matches) use ($usedHashParams, &$lineParams) {
                    if ($matches[1] === "") {
                        return '{}';
                    }
                    $Num = count($lineParams);
                    $lineParams[] = $matches[1];
                    $usedHashParams($matches[1]);
                    return '{' . $Num . '}';
                },
                $line
            );
        };
        $replace_strings = function ($line) use ($usedHashParams, &$strings, &$replace_line_params, &$replace_strings) {
            return preg_replace_callback(
                '/(?|(math|json|str|cond)`([^`]*)`|(")([^"]*)"|(\')([^\']*)\')/',
                function ($matches) use ($usedHashParams, &$strings, &$replace_line_params, &$replace_strings) {
                    if ($matches[1] === "") {
                        return '""';
                    }
                    switch ($matches[1]) {
                        case 'json':
                            if (!json_decode($matches[2]) && json_last_error()) {
                                $matches[2] = $replace_strings($matches[2]);
                                $usedHashParams($matches[2]);
                            }
                            break;
                        case 'math':
                        case 'str':
                        case 'cond':
                            $matches[2] = $replace_strings($matches[2]);
                            $matches[2] = $replace_line_params($matches[2]);
                            $usedHashParams($matches[2]);
                            break;
                    }
                    $stringNum = count($strings);
                    $strings[] = $matches[1] . $matches[2];
                    return '"' . $stringNum . '"';
                },
                $line
            );
        };

        foreach (preg_split('/[\r\n]+/', trim($code)) as $row) {
            $row = trim($row);
            /*Убрать комментарии*/
            if (str_starts_with($row, '//')) {
                continue;
            }
            /*Разбираем код построчно*/
            if (preg_match('/^([a-z0-9]*=\s*|~?[a-zA-Z0-9_]+)\s*(?<catch>[a-zA-Z0-9_]*)\s*:(.*)$/', $row, $matches)) {
                $lineName = trim($matches['1']);
                if (str_starts_with($lineName, '~')) {
                    $lineName = substr($lineName, 1);
                    $fixes[] = $lineName;
                }

                /*TryCatch*/
                if (substr($lineName, -1, 1) === '=' && $matches['catch']) {
                    $catch = $matches['catch'];
                    $catches [$lineName] = $catch;
                }

                $line = trim($matches[3]);
                /*Используемые параметры в функциях*/
                if ($table_name) {
                    if (preg_match_all('/\(.*?table:\s*\'([a-z0-9_]+)\'.*?\)/', $line, $matches)) {
                        foreach ($matches[1] as $i => $t_name) {
                            if (preg_match_all(
                                '/(field|where|order|sfield|bfield|tfield|preview|parent|section|table|filter):\s*\'([a-z0-9_]+)\'/',
                                $matches[0][$i],
                                $mches
                            )) {
                                foreach ($mches[2] as $field) {
                                    $tableParams[$t_name][$field] = 1;
                                }
                            }
                        }
                    }
                    if (preg_match_all('/\(.*?table:\s*\$#ntn.*?\)/', $line, $matches)) {
                        foreach ($matches[0] as $i => $t_name) {
                            if (preg_match_all('/(field|where|order):\s*\'([a-z0-9_]+)\'/', $matches[0][$i], $mches)) {
                                foreach ($mches[2] as $field) {
                                    $tableParams[$table_name][$field] = 1;
                                }
                            }
                        }
                    }
                }


                $line = $replace_strings($line);
                $line = str_replace(' ', '', $line);

                $line = $replace_line_params($line);
                $usedHashParams($line);
                $c[$lineName] = $line;
            }
        }


        if ($fixes) {
            $c['==fixes=='] = $fixes;
        }
        if ($strings) {
            $c['==strings=='] = $strings;
        }
        if ($lineParams) {
            $c['==lineParams=='] = $lineParams;
        }
        if ($tableParams) {
            $c['==usedFields=='] = $tableParams;
        }
        if ($catches) {
            $c['==catches=='] = $catches;
        }

        return $c;
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
                if (is_numeric($n)) {
                    return (float)$n;
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
                if (count($n2) === 0) {
                    if ($isTopLevel && (($n ?? "") === "")) {
                        $r = true;
                    } else {
                        $r = false;
                    }
                } else {
                    $r = false;
                    $n = static::__compare_normalize($n);

                    if (is_null($key)) {
                        foreach ($n2 as $nItem) {
                            if ($n === static::__compare_normalize($nItem)) {
                                $r = true;
                                break;
                            }
                        }
                    } else {
                        $key = strval($key);
                        foreach ($n2 as $nKey => $nItem) {
                            if (strval($nKey) === $key && $n === static::__compare_normalize($nItem)) {
                                $r = true;
                                break;
                            }
                        }
                    }
                }
                break;
            case '!=':
                if (count($n2) === 0) {
                    if ($isTopLevel && (($n ?? "") === "")) {
                        $r = true;
                    } else {
                        $r = false;
                    }
                } else {
                    $r = false;
                    $n = static::__compare_normalize($n);

                    if (is_null($key)) {
                        foreach ($n2 as $nItem) {
                            if ($n === static::__compare_normalize($nItem)) {
                                $r = true;
                                break;
                            }
                        }
                    } else {
                        $key = strval($key);
                        foreach ($n2 as $nKey => $nItem) {
                            if (strval($nKey) === $key && $n === static::__compare_normalize($nItem)) {
                                $r = true;
                                break;
                            }
                        }
                    }
                }
                $r = !$r;
                break;
            default:
                throw new errorException($Lang->translate('For lists comparisons, only available =, ==, !=.'));
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
            } else {
                $nIsRow = false;
            }
        }

        if ($n2IsArray = is_array($n2)) {
            if (count($n2) > 0 && (array_keys($n2) !== range(0, count($n2) - 1))) {
                $n2IsRow = true;
                $n2IsArray = false;
            } else {
                $n2IsRow = false;
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
                    throw new errorException($Lang->translate('For lists comparisons, only available =, ==, !=.'));
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
        $dateFromParams = strval($dateFromParams);
        if ($dateFromParams !== '') {
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

    protected function getCodes($stringIN)
    {
        $cacheString =& self::$codes;

        /*if (strpos($stringIN, '"') !== false || strpos($stringIN, '{') !== false) {
            $cacheString = &$this->cachedCodes;
        }*/

        if (empty($cacheString[$stringIN])) {
            $i = 0;
            $done = 1;
            $code = [];
            $string = $stringIN;

            while ($done && ($i < 100) && $string !== "") {
                $done = 0;
                $i++;
                $string = preg_replace_callback(
                    '`(?<func>(?<func_name>[a-zA-Z]{2,}\d*)*\((?<func_params>[^)]*)\))' . //func,func_name,func_params
                    '|(?<num>\-?[\d.,]+\%?)' .                      //num
                    '|(?<operator>\^|\+|\-|\*|/)' .       //operator
                    '|(?<string>"[^"]*")' .            //string
                    '|(?<comparison>!==|==|>=|<=|>|<|=|!=)' .       //comparison
                    '|(?<bool>false|true)' .   //10
                    '|(?<param>(?<param_name>(?:\$@|@\$|\$\$|\$\#?|\#(?i:(?:old|s|h|c|l)\.)?\$?)(?:[a-zA-Z0-9_]+(?:{[^}]*})?))(?<param_items>(?:\[\[?\$?\#?[a-zA-Z0-9_"]+\]?\])*))' . //param,param_name,param_items
                    '|(?<dog>@(?<dog_table>[a-zA-Z0-9_]{3,})\.(?<dog_field>[a-zA-Z0-9_]{2,})(?<dog_items>(?:\[\[?\$?\#?[a-zA-Z0-9_"]+\]?\])*))`',
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
                                } elseif (is_numeric($cn['string'])) {
                                    $cn['string'] = ctype_digit($cn['string']) ? (int)$cn['string'] : (float)$cn['string'];
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
                                        'string' => ""
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
                                    'items' => $matches['dog_items']
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
            $cacheString[$stringIN] = $code;
        }

        return $cacheString[$stringIN];
    }

    public function getLogVar()
    {
        return $this->newLog;
    }

    public function getError()
    {
        return $this->error;
    }

    protected function funcXmlExtract($params)
    {
        $params = $this->getParamsArray($params);
        $this->__checkRequiredParams($params, ['xml'], 'xmlExtract');

        $params['attrpref'] = $params['attrpref'] ?? '';
        $params['textname'] = $params['textname'] ?? 'TEXT';

        if ($xml = @simplexml_load_string($params['xml'])) {
            $getData = function (\SimpleXMLElement $xml) use (&$getData, $params) {
                $children = [];
                foreach ($xml->attributes() as $k => $attr) {
                    $children[$params['attrpref'] . $k] = (string)$attr;
                }
                foreach ($xml->getNamespaces() as $pref => $namespace) {
                    foreach ($xml->children($namespace) as $k => $child) {
                        $children[$pref . ':' . $k][] = $getData($child);
                    }
                }
                foreach ($xml->children() as $k => $child) {
                    $children[$k][] = $getData($child);
                }
                if ((string)$xml) {
                    $children[$params['textname']] = trim((string)$xml);
                }
                return $children;
            };

            return [$xml->getName() => $getData($xml)];
        } else {
            throw new errorException($this->translate('XML Format Error.'));
        }

    }

    public function exec($fieldData, $newVal, $oldRow, $row, $oldTbl, $tbl, aTable $table, $vars = [])
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

    protected function funcLinkToDataTable($params)
    {
        $params = $this->getParamsArray($params);

        if (!is_a($this, CalculateAction::class) && empty($params['hide'])) {
            errorException::criticalException($this->translate('You cannot use linktoDataTable outside of actionCode without hide:true.'));
        }

        $tableRow = $this->__checkTableIdOrName($params['table'], 'table');

        $tmp = $this->Table->getTotum()->getTable($tableRow);
        $tmp->addData(['tbl' => $params['data'] ?? [], 'params' => ($params['params'] ?? [])]);
        if (empty($params['hide'])) {
            if (empty($params['width'])) {
                $width = 130;
                foreach ($tmp->getVisibleFields('web', true)['column'] as $field) {
                    $width += $field['width'];
                }
                if ($width > 1200) {
                    $width = 1200;
                }
            } else {
                $width = $params['width'];
            }
            $table = [
                'title' => $params['title'] ?? $tableRow['title'],
                'table_id' => $tableRow['id'],
                'sess_hash' => $tmp->getTableRow()['sess_hash'],
                'width' => $width,
                'height' => $params['height'] ?? '80vh'
            ];
            $this->Table->getTotum()->addToInterfaceDatas(
                'table',
                $table,
                $params['refresh'] ?? false,
                ['header' => $params['header'] ?? true,
                    'footer' => $params['footer'] ?? true]
            );
        }
        return $tmp->getTableRow()['sess_hash'];
    }

    protected function funcExec($params)
    {
        $params = $this->getParamsArray($params, ['var'], ['var']);

        $code = $params['code'] ?? $params['kod'] ?? '';
        if (!empty($code)) {
            if (preg_match('/^[a-z_0-9]{3,}$/', $code) && key_exists($code, $this->Table->getFields())) {
                $code = $this->Table->getFields()[$code]['code'] ?? '';
            }

            $CA = new Calculate($code);
            try {
                $Vars = [];
                foreach ($params['var'] ?? [] as $v) {
                    $Vars = array_merge($Vars, $this->getExecParamVal($v));
                }
                $r = $CA->exec(
                    $this->varData,
                    $this->newVal,
                    $this->oldRow,
                    $this->row,
                    $this->oldTbl,
                    $this->tbl,
                    $this->Table,
                    $Vars
                );

                $this->newLogParent['children'][] = $CA->getLogVar();
                return $r;
            } catch (errorException $e) {
                $this->newLogParent['children'][] = $CA->getLogVar();
                throw $e;
            }
        }
        return null;
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
        $left = floatval($left);
        $right = floatval($right);

        switch ($operator) {
            case '+':
                $result = $left + $right;
                break;
            case '-':
                $result = $left - $right;
                break;
            case '*':
                $result = $left * $right;
                break;
            case '^':
                $result = pow($left, $right);
                break;
            case '/':
                if ((float)$right === 0.0) {
                    throw new errorException($this->translate('Division by zero.'));
                }
                $result = $left / $right;
                break;
            default:
                throw new errorException($this->translate('Unknown operator [[%s]].'));
        }

        $result = (float)(string)round($result, 10);
        /* $this->addInLogVar('Вычисление сравнения',
             ['left' => $left, 'operator' => $operator, 'right' => $right, 'result' => $result]);*/

        return $result;
    }

    public function getReadCodeForLog($code)
    {
        $code = preg_replace_callback(
            '/{(.*?)}/',
            function ($m) {
                if ($m[1] === "") {
                    return '{}';
                }
                return '{' . $this->CodeLineParams[$m[1]] . '}';
            },
            $code
        );
        $code = preg_replace_callback(
            '/"(.*?)"/',
            function ($m) {
                if ($m[1] === "") {
                    return '""';
                }
                $qoute = $this->CodeStrings[$m[1]][0];
                switch ($qoute) {
                    case '"':
                    case "'":
                        return $qoute . substr($this->CodeStrings[$m[1]], 1) . $qoute;
                        break;
                    default:
                        $back_replace_strings = function ($str) {
                            return preg_replace_callback(
                                '/"(\d+)"/',
                                function ($matches) {
                                    if ($matches[1] === "") {
                                        return '""';
                                    }
                                    return '"' . $this->CodeStrings[$matches[1]] . '"';
                                },
                                $str
                            );
                        };

                        $replaced = $back_replace_strings($this->CodeStrings[$m[1]]);
                        return substr($this->CodeStrings[$m[1]], 0, 4) . '`' . substr(
                                $replaced,
                                4
                            ) . '`';
                }
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

    protected function execSubCode($code, $codeName, $notLoging = false, $inVars = [])
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
                            $rTmp = $this->getMathFromString($r['string']);
                            break;
                        case 'operator':
                            $operator = $r['operator'];
                            continue 2;
                            break;
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
                                'math' => $this->getMathFromString(substr($this->CodeStrings[$r['string']], 4)),
                                'json' => $this->parseTotumJson(substr($this->CodeStrings[$r['string']], 4)),
                                'cond' => $this->parseTotumCond(substr($this->CodeStrings[$r['string']], 4)),
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

    protected function funcGetUsingFields($params)
    {
        $params = $this->getParamsArray($params);
        $tableRow = $this->__checkTableIdOrName($params['table'], 'table');
        if (empty($params['field']) || !is_string($params['field'])) {
            throw new errorException($this->translate('Parametr [[%s]] is required and should be a string.', 'field'));
        }

        $query = <<<SQL
select table_name->>'v' as table_name, name->>'v' as name, version->>'v' as version from tables_fields where data->'v'->'code'->'==usedFields=='-> :table ->> :field = '1'
           OR data->'v'->'codeSelect'->'==usedFields=='-> :table ->> :field = '1'
            OR data->'v'->'codeAction'->'==usedFields=='-> :table ->> :field = '1';
SQL;


        return TablesFields::init($this->Table->getTotum()->getConfig(), true)->executePreparedSimple(
            false,
            $query,
            ['table' => $tableRow['name'], 'field' => $params['field']]
        )->fetchAll();
    }

    protected function funcLogRowList($params)
    {
        $params = $this->getParamsArray($params);
        $where = [];

        if (!ctype_digit((string)$params['table'])) {
            $where['tableid'] = $this->__checkTableIdOrName($params['table'] ?? null, 'table')['id'];
        } else {
            $where['tableid'] = $params['table'];
        }
        if (!empty($params['cycle'])) {
            $where['cycleid'] = (int)$params['cycle'];
        }
        if (!empty($params['id'])) {
            $where['rowid'] = (int)$params['id'];
        }
        $where['field'] = (string)($params['field'] ?? '');

        $fields = ['comment' => 'modify_text', 'dt' => 'dt', 'user' => 'userid', 'action' => 'action', 'value' => 'v'];
        if (empty($params['params']) || !is_array($params['params'])) {
            $params['params'] = array_keys($fields);
        }

        $fieldsStr = '';
        foreach ($params['params'] as $param) {
            if (key_exists($param, $fields)) {
                if ($fieldsStr) {
                    $fieldsStr .= ',';
                }
                $fieldsStr .= $fields[$param] . ' as ' . $param;
            }
        }
        if ($fieldsStr) {
            $data = $this->Table->getTotum()->getModel('_log', true)->executePrepared(
                true,
                $where,
                $fieldsStr,
                'dt desc',
                key_exists('limit', $params) ? '0,' . ((int)$params['limit']) : null
            )->fetchAll(\PDO::FETCH_ASSOC);

            if (in_array('dt', $params['params'])) {
                foreach ($data as &$_row) {
                    $_row['dt'] = substr($_row['dt'], 0, 19);
                }
                unset($_row);
            }
            return $data;
        } else {
            throw new errorException($this->translate('The [[%s]] parameter is not correct.', 'params'));
        }
    }

    protected function funcTableLogSelect($params)
    {
        $params = $this->getParamsArray($params);
        $this->__checkListParam($params['users'], 'users');
        $date_from = $this->__checkGetDate($params['from'], 'from', 'TableLogSelect');

        $date_to = $this->__checkGetDate($params['to'], 'to', 'TableLogSelect');
        $date_to->modify('+1 day');

        $date_to = $date_to->format('Y-m-d');
        $date_from = $date_from->format('Y-m-d');
        $data = [];
        if ($params['users']) {
            $slqData = $this->Table->getTotum()->getModel('_log', true)->executePrepared(
                true,
                ['userid' => $params['users'], '>=dt' => $date_from, '<dt' => $date_to],
                'tableid, cycleid,rowid,field,modify_text,v,action,userid,dt',
                'dt'
            );

            $action = [];
            $tmp_data = [];
            foreach ($slqData as $row) {
                $row['dt'] = substr($row['dt'], 0, 19);

                $tmp_action = [$row['userid'], $row['tableid'], $row['cycleid'], $row['rowid'], $row['action'], $row['dt']];
                if (array_slice($action, 0, 5) == array_slice($tmp_action, 0, 5)) {
                    $Date = date_create($action[5]);
                    $Date->modify('+1 second');
                    if ($Date->format('Y-m-d H:i:s') >= $row['dt']) {
                        $tmp_data[] = $row;
                        $action = $tmp_action;
                        continue;
                    }
                }

                if (!empty($tmp_data)) {
                    $fields = [];
                    foreach ($tmp_data as $t) {
                        if ($t['field']) {
                            $fields[$t['field']] = [$t['v'], $t['modify_text']];
                        } elseif ($t['modify_text'] && (string)$row['action'] === "4") {
                            $fields[$this->translate('Deleting')] = $t['modify_text'];
                        }
                    }

                    $data[] = ['userid' => $row['userid'], 'tableid' => $tmp_data[0]['tableid'], 'cycleid' => $tmp_data[0]['cycleid'], 'rowid' => $tmp_data[0]['rowid'], 'action' => $tmp_data[0]['action'], 'dt' => $tmp_data[0]['dt'], 'fields' => $fields];
                }
                $tmp_data = [$row];


                $action = $tmp_action;
            }

            if (!empty($tmp_data)) {
                $fields = [];
                foreach ($tmp_data as $t) {
                    if ($t['field']) {
                        $fields[$t['field']] = [$t['v'], $t['modify_text']];
                    }
                }
                $data[] = ['userid' => $row['userid'], 'tableid' => $tmp_data[0]['tableid'], 'cycleid' => $tmp_data[0]['cycleid'], 'rowid' => $tmp_data[0]['rowid'], 'action' => $tmp_data[0]['action'], 'dt' => $tmp_data[0]['dt'], 'fields' => $fields];
            }

            if (!empty($params['order'])) {
                usort(
                    $data,
                    function ($a, $b) use ($params) {
                        foreach ($params['order'] as $o) {
                            if (!key_exists(
                                $o['field'],
                                $a
                            )) {
                                throw new errorException($this->translate('No key %s was found in the data row.',
                                    $o['field']));
                            }
                            if ($a[$o['field']] != $b[$o['field']]) {
                                $r = $a[$o['field']] < $b[$o['field']] ? -1 : 1;
                                if ($o['ad'] === 'desc') {
                                    return -$r;
                                }
                                return $r;
                            }
                        }
                        return 0;
                    }
                );
            }
        }
        return $data;
    }

    protected function funcFileGetContent($params)
    {
        $params = $this->getParamsArray($params);
        if (empty($params['file'])) {
            throw new errorException($this->translate('Fill in the parameter [[%s]].', 'file'));
        }

        return File::getContent($params['file'], $this->Table->getTotum()->getConfig());
    }

    protected function funcWhile($params)
    {
        $vars = $this->getParamsArray(
            $params,
            ['action', 'preaction', 'postaction'],
            ['action', 'preaction', 'postaction', 'limit']
        );

        $iteratorName = $vars['iterator'] ?? '';

        $return = null;

        if (!empty($vars['preaction'])) {
            foreach ($vars['preaction'] as $i => $action) {
                $return = $this->execSubCode($action, 'preaction' . (++$i));
            }
        }

        if (!empty($vars['action'])) {
            $limit = (int)array_key_exists('limit', $vars) ? $this->execSubCode($vars['limit'], 'limit') : 1;
            $whileIterator = 0;
            $isPostaction = false;

            while ($limit-- > 0) {
                if ($iteratorName) {
                    $this->whileIterators[$iteratorName] = $whileIterator;
                }

                if (!isset($vars['condition'])) {
                    $conditionTest = true;
                } else {
                    $conditionTest = true;
                    foreach ($vars['condition'] as $i => $c) {
                        $condition = $this->execSubCode($c, 'condition' . (1 + $i));
                        if (!is_bool($condition)) {
                            throw new errorException($this->translate('Parameter [[%s]] returned a non-true/false value.',
                                'condition' . (1 + $i)));
                        }
                        if (!$condition) {
                            $conditionTest = false;
                            break;
                        }
                    }
                }

                if ($conditionTest) {
                    foreach ($vars['action'] as $i => $action) {
                        $return = $this->execSubCode($action, 'action' . (++$i));
                    }
                    $isPostaction = true;
                } else {
                    break;
                }


                $whileIterator++;
            }

            if ($isPostaction && !empty($vars['postaction'])) {
                foreach ($vars['postaction'] as $i => $action) {
                    $return = $this->execSubCode($action, 'postaction' . (++$i));
                }
            }
        }

        return $return;
    }

    protected function getExecParamVal($paramVal)
    {
        try {
            $codes = $this->getCodes($paramVal);
        } catch (errorException $e) {
            throw new errorException($this->translate('TOTUM-code format error [[%s]].', $paramVal));
        }

        if (count($codes) < 2) {
            throw new errorException($this->translate('The parameter must contain 2 elements.'));
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
        return [$varName => $value];
    }

    protected function getParam($param, $paramArray)
    {
        $r = null;
        $isHashtag = false;
        if (strlen($param) === 0) {
            throw new errorException($this->translate('TOTUM-code format error [[%s]].', $this->varName));
        }


        switch ($param[0]) {
            case '@':
                if ($param[1] === '$') {
                    $paramName = substr($param, 2);
                    $r = $this->Table->getTotum()->getConfig()->globVar($paramName);
                } else {
                    $r = $this->Table->getSelectByParams(
                        ['table' => $paramArray['table'], 'field' => $paramArray['field']],
                        'field',
                        $this->row['id'] ?? null,
                        get_class($this) === Calculate::class
                    );
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
                                if (gettype($this->vars[$nameVar]) === 'function') {
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

                    $inVars = [];
                    if ($varsStart = strpos($codeName, '{')) {
                        $codeNum = substr($codeName, $varsStart + 1, -1);
                        if ($codeNum !== "") {
                            $vars = $this->CodeLineParams[$codeNum];
                            $vars = $this->getParamsArray($vars, ['var'], ['var']);
                            foreach ($vars['var'] ?? [] as $var) {
                                $inVars = array_merge($inVars, $this->getExecParamVal($var));
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
                            $Log = $this->Table->calcLog(['name' => $codeName, 'type' => "fixed"]);
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

                            switch ($typeVal ?? null) {
                                case 'h':
                                    $r = $rowVar['h'] ?? false;
                                    break;
                                case 'c':
                                    $r = key_exists('c', $rowVar) ? $rowVar['c'] : ($rowVar['v'] ?? null);
                                    break;
                                default:
                                    $r = $rowVar['v'] ?? null;
                            }
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
                } elseif (is_numeric($param)) {
                    $r = ctype_digit($param) ? (int)$param : (float)$param;
                } else {
                    $r = $param;
                }
        }


        $paramName = $param;
        if (!empty($paramArray['items'])) {
            $itemsNames = '';

            if (preg_match_all('/\[(.*?)(?:\]\]|\])/', $paramArray['items'], $items)) {
                foreach ($items[0] as $_item) {
                    $_item = substr($_item, 1, -1);
                    $isSection = $_item[0] === '[' && substr($_item, -1, 1) === ']';
                    if ($isSection) {
                        $_item = substr($_item, 1, -1);
                    }
                    $item = $this->getParam($_item, ['type' => 'param', 'param' => $_item]);
                    $itemsNames .= "[$item]";

                    if (is_numeric($item)) {
                        $item = (string)$item;
                    }

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
                            break;
                        }
                    } elseif (is_array($r) && array_key_exists($item, $r)) {
                        $r = $r[$item];
                    } else {
                        $itemsNames .= '...';
                        $r = null;
                        break;
                    }
                }
            }
            $paramName = $param . $itemsNames;
            $isHashtag = true;
        }

        if ($isHashtag) {
            $this->Table->getCalculateLog()->addParam($paramName, $r);
        }
        return $r;
    }


    protected function funcIf($params)
    {
        $vars = $this->getParamsArray($params);

        if (empty($vars['condition'])) {
            throw new errorException($this->translate('Fill in the parameter [[%s]].', 'condition'));
        }
        $conditionTest = true;
        foreach ($vars['condition'] as $i => $c) {
            $condition = $this->execSubCode($c, 'condition' . (1 + $i));
            if (!is_bool($condition)) {
                throw new errorException($this->translate('Parameter [[%s]] returned a non-true/false value.',
                    'condition' . (1 + $i)));
            }
            if (!$condition) {
                $conditionTest = false;
                break;
            }
        }

        if ($conditionTest) {
            if (array_key_exists('then', $vars)) {
                return $this->execSubCode($vars['then'], 'then');
            } else {
                return null;
            }
        } elseif (array_key_exists('else', $vars)) {
            return $this->execSubCode($vars['else'], 'else');
        } else {
            return null;
        }

    }


    protected function funcErrorExeption($params)
    {
        if ($params = $this->getParamsArray($params)) {
            if (!empty($params['text'])) {
                throw new errorException((string)$params['text']);
            }
        }
    }

    protected function funcExecSSH($params)
    {
        if (!$this->Table->getTotum()->getConfig()->isExecSSHOn()) {
            throw new criticalErrorException($this->translate('The ExecSSH function is disabled. Enable it in Conf.php.'));
        }
        $params = $this->getParamsArray($params);
        if (empty($params['ssh'])) {
            throw new errorException($this->translate('Fill in the parameter [[%s]].', 'ssh'));
        }
        $string = $params['ssh'];
        if ($params['vars'] ?? null) {
            $localeOld = setlocale(LC_CTYPE, 0);
            setlocale(LC_CTYPE, "en_US.UTF-8");

            if (!is_array($params['vars'])) {
                throw new errorException($this->translate('The parameter [[%s]] should be of type row/list.', 'vars'));
            }
            if (key_exists('0', $params['vars'])) {
                foreach ($params['vars'] as $v) {
                    $string .= ' ' . escapeshellarg($v) . '';
                }
            } else {
                foreach ($params['vars'] as $k => $v) {
                    $string .= ' ' . escapeshellcmd($k) . '=' . escapeshellarg($v) . '';
                }
            }
            setlocale(LC_CTYPE, $localeOld);
        }
        return shell_exec($string);
    }

    protected function funcJsonExtract($params)
    {
        if ($params = $this->getParamsArray($params)) {
            return json_decode($params['text'] ?? null, true);
        }
    }

    protected function funcJsonCreate($params)
    {
        if ($params = $this->getParamsArray($params, ['field', 'flag'], ['field'])) {
            $data = $params['data'] ?? [];
            foreach ($params['field'] ?? [] as $f) {
                $f = $this->getExecParamVal($f);
                if (ctype_digit(strval(array_keys($f)[0]))) {
                    $data = $f + $data;
                } else {
                    $data = array_merge($data, $f);
                }
            }
            $flags = 0;
            if (key_exists('flag', $params)) {
                $escaped = false;
                foreach ($params['flag'] as $flag) {
                    switch ($flag) {
                        case 'ESCAPED_UNICODE':
                            $escaped = true;
                            break;
                        case 'PRETTY':
                            $flags = $flags | JSON_PRETTY_PRINT;
                            break;
                    }
                }
                if (!$escaped) {
                    $flags = $flags | JSON_UNESCAPED_UNICODE;
                }
            } else {
                $flags = JSON_UNESCAPED_UNICODE;
            }

            return json_encode($data, $flags);
        }
    }

    protected function __checkGetDate($dateFromParams, $paramName, $funcName)
    {
        if (empty($dateFromParams) || !($date = static::getDateObject($dateFromParams, $this->getLangObj()))) {
            throw new errorException($this->translate('The [[%s]] parameter is not correct.', $paramName));
        }
        return $date;
    }

    protected function funcUserInRoles($params)
    {
        if ($params = $this->getParamsArray($params, ['role'])) {
            $roles = $this->Table->getTotum()->getUser()->getRoles();
            foreach ($params['role'] ?? [] as $role) {
                if (in_array($role, $roles)) {
                    return true;
                }
            }
        }
        return false;
    }

    protected function funcGetVar($params)
    {
        $params = $this->getParamsArray($params, [], ['default']);
        $this->__checkNotEmptyParams($params, ['name']);
        $this->__checkNotArrayParams($params, ['name']);

        if (!array_key_exists(
            $params['name'],
            $this->vars
        )) {
            if (array_key_exists('default', $params)) {
                $this->vars[$params['name']] = $this->execSubCode($params['default'], 'default');
            } else {
                throw new errorException($this->translate('The [[%s]] parameter has not been set in this code.',
                    $params['name']));
            }
        }
        return $this->vars[$params['name']];
    }

    /**
     * @deprecated
     */
    protected function funcSetVar($params)
    {
        $params = $this->getParamsArray($params);

        $this->__checkNotEmptyParams($params, ['name']);
        $this->__checkNotArrayParams($params, ['name']);

        $this->__checkRequiredParams($params, ['value']);

        return $this->vars[$params['name']] = $params['value'];
    }

    protected function funcVar(string $params)
    {
        $params = $this->getParamsArray($params, [], ['default']);
        $this->__checkNotEmptyParams($params, ['name']);
        $this->__checkNotArrayParams($params, ['name']);

        if (array_key_exists('value', $params)) {
            $this->vars[$params['name']] = $params['value'];
        } elseif (!array_key_exists($params['name'], $this->vars)) {
            if (array_key_exists('default', $params)) {
                $this->vars[$params['name']] = $this->execSubCode($params['default'], 'default');
            } else {
                throw new errorException($this->translate('The [[%s]] parameter has not been set in this code.',
                    $params['name']));
            }
        }
        return $this->vars[$params['name']];
    }


    protected function funcGetTableSource(string $params)
    {
        $params = $this->getParamsArray($params);
        return $this->Table->getSelectByParams(
            $params,
            'table',
            $this->row['id'] ?? null,
            $this::class === Calculate::class
        );
    }

    protected function funcGetTableUpdated(string $params): array
    {
        $params = $this->getParamsArray($params);
        $this->__checkNotEmptyParams($params, ['table']);
        $this->__checkNotArrayParams($params, ['table']);

        $sourceTableRow = $this->Table->getTotum()->getTableRow($params['table']);

        if (!$sourceTableRow) {
            throw new errorException($this->translate('Table [[%s]] is not found.', $params['table']));
        }

        if ($sourceTableRow['type'] === 'calcs') {
            $this->__checkNotEmptyParams($params, ['cycle']);
            $this->__checkNotArrayParams($params, ['cycle']);

            $SourceCycle = $this->Table->getTotum()->getCycle($params['cycle'], $sourceTableRow['tree_node_id']);
            $SourceTable = $SourceCycle->getTable($sourceTableRow);
        } else {
            $SourceTable = $this->Table->getTotum()->getTable($sourceTableRow);
        }
        return json_decode($SourceTable->getSavedUpdated(), true);
    }


    protected function select($params, $mode, $withOutSection = false, $codeNameForLog = '')
    {
        $params = $this->getParamsArray($params, ['where', 'order', 'sfield']);

        if ($withOutSection) {
            unset($params['section']);
        }
        return $this->Table->getSelectByParams(
            $params,
            $mode,
            $this->row['id'] ?? null,
            $this::class === Calculate::class
        );
    }

    protected function funcSelect($params, $codeNameForLog)
    {
        return $this->select($params, 'field', false, $codeNameForLog);
    }

    protected function funcSelectRow($params, $codeNameForLog)
    {
        $params = $this->getParamsArray($params, ['where', 'order', 'field', 'sfield', 'tfield']);
        if (!empty($params['fields'])) {
            $params['field'] = array_merge($params['field'] ?? [], $params['fields']);
        }
        if (!empty($params['sfields'])) {
            $params['sfield'] = array_merge($params['sfield'] ?? [], $params['sfields']);
        }
        unset($params['section']);

        $row = $this->Table->getSelectByParams(
            $params,
            'row',
            $this->row['id'] ?? null,
            get_class($this) === Calculate::class
        );
        if (!empty($row['__sectionFunction'])) {
            $row = $row['__sectionFunction']();
        }
        return $row;
    }

    protected function funcSelectRowList($params, $codeNameForLog)
    {
        $params = $this->getParamsArray($params, ['where', 'order', 'field', 'sfield', 'tfield']);
        if (!empty($params['fields'])) {
            $params['field'] = array_merge($params['field'] ?? [], $params['fields']);
        }
        if (!empty($params['sfields'])) {
            $params['sfield'] = array_merge($params['sfield'] ?? [], $params['sfields']);
        }
        unset($params['section']);

        return $this->Table->getSelectByParams(
            $params,
            'rows',
            $this->row['id'] ?? null,
            get_class($this) === Calculate::class
        );
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

    protected function __checkNotArrayParams(array $params, array $cheks)
    {
        foreach ($cheks as $param) {
            if (key_exists($param, $params) && is_array($params[$param])) {
                throw new errorException($this->translate('The parameter [[%s]] should [[not]] be of type row/list.',
                    $param));
            }
        }
    }

    protected function __checkNumericParam($isDigit, $paramName)
    {
        if (is_array($isDigit) || !is_numeric($isDigit)) {
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

    protected function funcReCalculate(string $params)
    {
        $params = $this->getParamsArray($params, ['field']);
        $tableRow = $this->__checkTableIdOrName($params['table'], 'table');

        $inVars = [];
        if (key_exists('field', $params)) {
            $inVars['inAddRecalc'] = $params['field'];
        }
        if ($tableRow['type'] === 'calcs') {
            if (empty($params['cycle']) && $this->Table->getTableRow()['type'] === 'calcs' && $this->Table->getTableRow()['tree_node_id'] === $tableRow['tree_node_id']) {
                $params['cycle'] = [$this->Table->getCycle()->getId()];
            }


            if (!is_array($params['cycle'])) {
                $this->__checkNotEmptyParams($params, ['cycle']);
            }

            $Cycles = (array)$params['cycle'];
            foreach ($Cycles as $cycleId) {
                $params['cycle'] = $cycleId;
                $Cycle = $this->Table->getTotum()->getCycle($params['cycle'], $tableRow['tree_node_id']);
                /** @var calcsTable $table */
                $table = $Cycle->getTable($tableRow);
                $table->reCalculateFromOvers($inVars, $this->Table->getCalculateLog(), 0);
            }
        } elseif ($tableRow['type'] === 'tmp') {
            if ($this->Table->getTableRow()['type'] === 'tmp' && $this->Table->getTableRow()['name'] === $tableRow['name']) {
                if (empty($params['hash'])) {
                    $table = $this->Table;
                }
            }
            if (empty($table)) {
                $table = $this->Table->getTotum()->getTable($tableRow, $params['hash']);
            }
            $table->reCalculateFromOvers($inVars, $this->Table->getCalculateLog());
        } else {
            $table = $this->Table->getTotum()->getTable($tableRow);

            if (is_subclass_of($table, RealTables::class) && !empty($params['where'])) {
                $ids = $table->getByParams(['field' => 'id', 'where' => $params['where']], 'list');
                $inVars['modify'] = array_fill_keys($ids, []);
                $table->reCalculateFromOvers($inVars, $this->Table->getCalculateLog());
            } else {
                $table->reCalculateFromOvers($inVars, $this->Table->getCalculateLog());
            }
        }
    }

    protected function funcSelectTreeChildren($params)
    {
        $params = $this->getParamsArray($params);
        return $this->select($params, 'treeChildren');
    }

    protected function funcSelectList($params)
    {
        return $this->select($params, 'list');
    }

    protected function __getValue(array $paramArray)
    {
        switch ($paramArray['type']) {
            case 'param':
                return $this->getParam($paramArray['param'], $paramArray);
            case 'string':
                return $paramArray['string'];
            case 'stringParam':
                $spec = substr($this->CodeStrings[$paramArray['string']], 0, 4);

                switch ($spec) {
                    case 'math':
                        return $this->getMathFromString(substr($this->CodeStrings[$paramArray['string']], 4));
                    case 'json':
                        return $this->parseTotumJson($str = substr($this->CodeStrings[$paramArray['string']], 4));
                    case 'cond':
                        return $this->parseTotumCond($str = substr($this->CodeStrings[$paramArray['string']], 4));
                    default:
                        switch (substr($spec, 0, 3)) {
                            case 'str':
                                return $this->parseTotumStr(substr($this->CodeStrings[$paramArray['string']], 3));
                        }
                        return substr($this->CodeStrings[$paramArray['string']], 1);
                }
            // no break
            case 'boolean':
                return !($paramArray['boolean'] === 'false');
            default:
                throw new errorException($this->translate('TOTUM-code format error [[%s]].', $paramArray['string']));
        }
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

                                if (count($whereCodes) != 3) {
                                    throw new errorException($this->translate('The parameter must contain 3 elements.'));
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
                                    ), 'operator' => $whereCodes['comparison'], 'value' => $value
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

    protected function parseTotumCond($string)
    {
        $string = preg_replace('/\s+/', '', $string);

        $actions = preg_split(
            '`(
                        \(|\)|
                        [&]{2}|
                        [|]{2}|
                        ==|
                        !=|
                        >=|
                        <=|
                        [><=]
                        )`x',
            $string,
            null,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
        );

        $pack_Sc = function ($actions) use (&$pack_Sc, &$calcIt) {
            $sc = 0;
            $interval_start = null;
            $i = 0;
            while ($i < count($actions)) {
                if ($actions[$i] === "") {
                    array_splice($actions, $i, 1);
                    continue;
                }
                switch ((string)$actions[$i]) {
                    case '(':
                        if ($sc++ === 0) {
                            $interval_start = $i;
                        }
                        break;
                    case ')':
                        if ($sc < 1) {
                            throw new errorException($this->translate('The unpaired closing parenthesis.'));
                        }
                        if (--$sc === 0) {
                            array_splice(
                                $actions,
                                $interval_start,
                                $i + 1 - $interval_start,
                                $calcIt($pack_Sc(array_slice(
                                    $actions,
                                    $interval_start + 1,
                                    $i - $interval_start - 1
                                )))
                            );
                            $i = $interval_start - 1;
                        }
                        break;
                }
                $i++;
            }
            return $actions;
        };

        $checkValue = function ($varIn, $onlyBool = true) {
            if ($varIn === 'false' || $varIn === false) {
                return false;
            }
            if ($varIn === 'true' || $varIn === true) {
                return true;
            }

            $var = $this->execSubCode($varIn, 'CondCode ' . $varIn);

            if ($onlyBool) {
                if ($var === 'false' || $var === false) {
                    return false;
                }
                if ($var === 'true' || $var === true) {
                    return true;
                }
                throw new errorException($this->translate('The parameter [[%s]] should be of type true/false.',
                    'cond:' . $varIn));
            }
            return $var;
        };

        $getValue = function ($var) use ($checkValue) {
            if ($var && !is_numeric($var)) {
                $var = $checkValue($var, false);
            }
            return $var;
        };

        $calcIt = function ($action) use ($checkValue, $getValue, $string) {
            $i = 0;
            while ($i < count($action)) {
                switch ((string)$action[$i]) {
                    case '<':
                    case '>':
                    case '=':
                    case '==':
                    case '!=':
                    case '<=':
                    case '>=':

                        $left = $getValue($action[$i - 1]);
                        $right = $getValue($action[$i + 1]);

                        $val = static::compare($action[$i], $left, $right, $this->getLangObj());
                        array_splice($action, $i - 1, 3, $val);
                        $i--;
                }
                $i++;
            }


            $i = 0;

            while ($i < count($action)) {
                switch ((string)$action[$i]) {
                    case '&&':
                        $left = $i === 0 ? true : $checkValue($action[$i - 1]);

                        if (!$left) {
                            $val = false;
                        } elseif (key_exists($i + 1, $action)) {
                            $val = $checkValue($action[$i + 1]);
                        } else {
                            break 2;
                        }

                        if ($i === 0) {
                            array_splice($action, 0, 2, $val);
                        } else {
                            array_splice($action, $i - 1, 3, $val);
                        }

                        $i--;
                        break;
                    case '||':
                        $left = $i === 0 ? false : $checkValue($action[$i - 1]);

                        if ($left) {
                            $val = true;
                        } elseif (key_exists($i + 1, $action)) {
                            $val = $checkValue($action[$i + 1]);
                        } else {
                            break 2;
                        }

                        if ($i === 0) {
                            array_splice($action, 0, 2, $val);
                        } else {
                            array_splice($action, $i - 1, 3, $val);
                        }

                        $i--;
                }
                $i++;
            }
            if (count($action) !== 1) {
                throw new errorException($this->translate('TOTUM-code format error [[%s]].', 'cond:' . $string));
            }
            return $checkValue($action[0]);
        };
        return $calcIt($pack_Sc($actions));
    }

    protected function getMathFromString($string)
    {
        $string = preg_replace('/\s+/', '', $string);

        $actions = preg_split(
            '`((?<=[^(+\-^*/])[()+\-^*/]|[(])`',
            $string,
            null,
            PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY
        );

        $pack_Sc = function ($actions) use (&$pack_Sc, &$calcIt) {
            $sc = 0;
            $interval_start = null;
            $i = 0;
            while ($i < count($actions)) {
                if ($actions[$i] === "") {
                    array_splice($actions, $i, 1);
                    continue;
                }
                switch ((string)$actions[$i]) {
                    case '(':
                        if ($sc++ === 0) {
                            $interval_start = $i;
                        }
                        break;
                    case ')':
                        if ($sc < 1) {
                            throw new errorException($this->translate('The unpaired closing parenthesis.'));
                        }
                        if (--$sc === 0) {
                            array_splice(
                                $actions,
                                $interval_start,
                                $i + 1 - $interval_start,
                                $calcIt($pack_Sc(array_slice(
                                    $actions,
                                    $interval_start + 1,
                                    $i - $interval_start - 1
                                )))
                            );
                            $i = $interval_start - 1;
                        }
                        break;
                }
                $i++;
            }
            return $actions;
        };

        $checkValue = function ($var) {
            if ($var && !is_numeric($var)) {
                $var = $this->execSubCode($var, 'MathCode');
            }
            return $var;
        };

        $calcIt = function ($action) use ($checkValue, $string) {
            $i = 0;
            while ($i < count($action)) {
                switch ((string)$action[$i]) {
                    case '^':
                        $left = $checkValue($action[$i - 1]);
                        $right = $checkValue($action[$i + 1]);
                        $val = $this->operatorExec($action[$i], $left, $right);
                        array_splice($action, $i - 1, 3, $val);
                        $i--;
                }
                $i++;
            }

            $i = 0;
            while ($i < count($action)) {
                switch ((string)$action[$i]) {
                    case '/':
                    case '*':
                        $left = $checkValue($action[$i - 1]);
                        $right = $checkValue($action[$i + 1]);
                        $val = $this->operatorExec($action[$i], $left, $right);
                        array_splice($action, $i - 1, 3, $val);
                        $i--;
                }
                $i++;
            }

            $i = 0;

            while ($i < count($action)) {
                switch ((string)$action[$i]) {
                    case '+':
                    case '-':
                        $left = $i === 0 ? 0 : $checkValue($action[$i - 1]);
                        $right = $checkValue($action[$i + 1]);

                        $val = $this->operatorExec($action[$i], $left, $right);

                        if ($i === 0) {
                            array_splice($action, 0, 2, $val);
                        } else {
                            array_splice($action, $i - 1, 3, $val);
                        }
                        $i--;
                }
                $i++;
            }
            if (count($action) !== 1 || !is_numeric((string)$action[0])) {
                throw new errorException($this->translate('TOTUM-code format error [[%s]].', 'math:' . $string));
            }
            return $action[0];
        };
        return $calcIt($pack_Sc($actions));
    }

    protected function getSourceTable($params)
    {
        $tableRow = $this->__checkTableIdOrName($params['table'], 'table');

        switch ($tableRow['type']) {
            case 'calcs':
                $this->__checkNotEmptyParams($params, 'cycle');

                $Cycle = $this->Table->getTotum()->getCycle($params['cycle'], $tableRow['tree_node_id']);
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

    protected function parseTotumJson(string $str)
    {
        $r = json_decode($str, true);
        if (json_last_error() && ($error = json_last_error_msg())) {
            try {
                $TJ = new TotumJson($str);
                $TJ->setTotumCalculate(function ($param) {
                    return $this->execSubCode($param, 'paramFromJson');
                });
                $TJ->setStringCalculate(function ($str) {
                    if (key_exists($str, $this->CodeStrings)) {
                        return substr($this->CodeStrings[$str], 1);
                    } else {
                        return $str;
                    }
                });
                $TJ->parse();
            } catch (\Exception $e) {
                throw new errorException($e->getMessage());
            }

            return $TJ->getJson();
        } else {
            return $r;
        }
    }

    protected function getEnvironment(): array
    {
        $env = [
            'table' => $this->Table->getTableRow()['name']
        ];
        switch ($this->Table->getTableRow()['type']) {
            case 'calcs':
                $env['cycle_id'] = $this->Table->getCycle()->getId();
                break;
            case 'tmp':
                $env['cycle_id'] = $this->Table->getTableRow()['sess_hash'];
                break;
        }

        if (!empty($this->row['id'])) {
            $env['id'] = $this->row['id'];
        }

        return $env;
    }

    protected function parseTotumStr($string): string
    {
        $string = preg_replace('/\s+/', '', $string);
        $result = "";

        foreach (explode('+', $string) as $i => $part) {
            if ($part === '') {
                $result .= " ";
            } else {
                $res = $this->execSubCode($part, 'part' . $i);
                if (is_array($res)) {
                    $res = json_encode($res, JSON_UNESCAPED_UNICODE);
                }
                $result .= $res;
            }
        }
        return $result;
    }


    protected function funcGetFromScript($params)
    {
        $params = $this->getParamsArray($params, ['post'], ['post']);

        if (empty($params['uri']) || !preg_match(
                '`https?://`',
                $params['uri']
            )) {
            throw new errorException($this->translate('The %s parameter is required and must start with %s.',
                ['uri', 'http/https']));
        }

        $link = $params['uri'];
        if (!empty($params['post'])) {
            $post = $this->__getActionFields($params['post'], 'GetFromScript');
        } elseif (!empty($params['posts'])) {
            $post = $params['posts'];
        } else {
            $post = null;
        }


        if (!empty($params['gets'])) {
            $link .= !str_contains($link, '?') ? '?' : '&';
            $link .= http_build_query($params['gets']);
        }

        $toBfl = $params['bfl'] ?? in_array(
                'script',
                $this->Table->getTotum()->getConfig()->getSettings('bfl') ?? []
            );

        try {
            $r = $this->cURL(
                $link,
                'http://' . $this->Table->getTotum()->getConfig()->getFullHostName(),
                $params['header'] ?? 0,
                $params['cookie'] ?? '',
                $post,
                (($params['ssh'] ?? false) ? 'parallel' : $params['timeout'] ?? null),
                ($params['headers'] ?? ""),
                ($params['method'] ?? ""),
            );
            if ($toBfl) {
                $this->Table->getTotum()->getOutersLogger()->error(
                    "getFromScript",
                    [
                        'link' => $link,
                        'ref' => 'http://' . $this->Table->getTotum()->getConfig()->getFullHostName(),
                        'header' => $params['header'] ?? 0,
                        'headers' => $params['headers'] ?? 0,
                        'cookie' => $params['cookie'] ?? '',
                        'post' => $post,
                        'timeout' => ($params['timeout'] ?? null),
                        'result' => mb_check_encoding($r, 'utf-8') ? $r : base64_encode($r)
                    ]
                );
            }
            return $r;
        } catch (\Exception $e) {
            if ($toBfl) {
                $r = $r ?? '';
                $this->Table->getTotum()->getOutersLogger()->error(
                    'getFromScript:',
                    ['error' => $e->getMessage()] + [
                        'link' => $link,
                        'ref' => 'http://' . $this->Table->getTotum()->getConfig()->getFullHostName(),
                        'header' => $params['header'] ?? 0,
                        'headers' => $params['headers'] ?? 0,
                        'cookie' => $params['cookie'] ?? '',
                        'post' => $post,
                        'timeout' => ($params['timeout'] ?? null),
                        'result' => mb_check_encoding($r, 'utf-8') ? $r : base64_encode($r)
                    ]
                );
            }
            throw new errorException($e->getMessage());
        }
    }

    protected function funcGlobVar($params)
    {
        $params = $this->getParamsArray($params, [], []);

        $this->__checkNotEmptyParams($params, 'name');

        $_params = [];
        if (key_exists('value', $params)) {
            $_params['value'] = $params['value'];
        } elseif (key_exists('default', $params)) {
            $_params['default'] = $params['default'];
        } elseif (key_exists('block', $params)) {
            $_params['block'] = $params['block'];
        }
        if ($params['date'] ?? false) {
            $_params['date'] = true;
        }

        return $this->Table->getTotum()->getConfig()->globVar($params['name'], $_params);
    }

    protected function funcProcVar($params)
    {
        $params = $this->getParamsArray($params, [], []);
        $this->__checkNotEmptyParams($params, 'name');

        $_params = [];
        if (key_exists('value', $params)) {
            $_params['value'] = $params['value'];
        } elseif (key_exists('default', $params)) {
            $_params['default'] = $params['default'];
        }

        return $this->Table->getTotum()->getConfig()->procVar($params['name'], $_params);
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


                $fieldValue = $this->__getValue($fc[2] ?? $fc[1]);

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
                throw $e;

                throw new errorException($e->getMessage() . ' [[' . $funcName . ']] field [[' . $this->getReadCodeForLog($f) . ']]');
            } catch (\Exception $e) {
                throw new errorException($this->translate('TOTUM-code format error [[%s]].', $f));
            }
            $fields[$fieldName] = $fieldValue;
        }
        return $fields;
    }

    /**
     * @param $url
     * @param string $ref
     * @param int $header
     * @param string $cookie
     * @param null $post
     * @param int|"parallel"|null $timeout
     * @param null $headers
     * @param null $method
     * @return bool|string
     * @throws errorException
     */
    public static function cURL($url, $ref = '', $header = 0, $cookie = '', $post = null, $timeout = null, $headers = null, $method = null)
    {
        if ($headers) {
            $headers = (array)$headers;
        } else {
            $headers = [];
        }
        if ($cookie) {
            $headers[] = "Cookie: " . $cookie;
        }

        if ($timeout === "parallel") {
            $data = "";
            if (empty($method)) {
                $method = null;
            }
            $localeOld = setlocale(LC_CTYPE, 0);
            setlocale(LC_CTYPE, "en_US.UTF-8");
            if (!is_null($post)) {
                $method = $method ?? "POST";
                if (!empty($post)) {
                    $post = is_array($post) ? http_build_query($post) : $post;
                    $data = '--data ' . escapeshellarg($post);
                }
            } else {
                $method = $method ?? "GET";
            }

            if ($ref) {
                $ref = '--referer ' . escapeshellarg($ref);
            }

            $hhs = [];
            foreach ($headers ?? [] as $h) {
                $hhs[] = '-H ' . escapeshellarg($h);
            }

            setlocale(LC_CTYPE, $localeOld);

            $hhs = implode(' ', $hhs);
            `curl --insecure --request $method $ref $hhs $url $data  > /dev/null 2>&1 &`;

            return null;
        }


        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
        curl_setopt($ch, CURLOPT_REFERER, $ref);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, $header);

        if ($timeout) {
            curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        }

        if (!empty($method)) {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        }

        if (!is_null($post)) {
            if (empty($method)) {
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
                curl_setopt($ch, CURLOPT_POST, 1);
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($post) ? http_build_query($post) : $post);
        }


        if ($headers) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, (array)$headers);
        }

        $result = curl_exec($ch);
        if ($error = curl_error($ch)) {
            curl_close($ch);
            throw new errorException($error);
        }
        curl_close($ch);
        return $result;
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
