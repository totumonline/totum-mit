<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 18.08.17
 * Time: 17:08
 */

namespace totum\fieldTypes;

use totum\common\calculates\Calculate;
use totum\common\calculates\CalculateSelect;
use totum\common\calculates\CalculateSelectPreview;
use totum\common\calculates\CalculateSelectValue;
use totum\common\calculates\CalculateSelectViewValue;
use totum\common\criticalErrorException;
use totum\common\errorException;
use totum\common\Field;
use totum\common\Lang\RU;
use totum\tableTypes\aTable;

class Select extends Field
{
    const loadItemsCount = 50;
    protected $commonSelectList;
    protected $commonSelectViewList;
    protected $CalculateCodeViewSelect;
    protected $CalculateCodePreviews;

    protected function __construct($fieldData, aTable $table)
    {
        parent::__construct($fieldData, $table);

        if (!empty($this->data['codeSelect'])) {
            $this->CalculateCodeSelect = new CalculateSelect($this->data['codeSelect']);
        }
    }

    public function emptyCommonSelectViewList()
    {
        $this->commonSelectViewList = null;
    }

    public function cropSelectListForWeb($list, $checkedVals, $q = '', $parentId = null)
    {
        $previewdata = $list['previewdata'] ?? false;
        unset($list['previewdata']);

        $selectLength = $this->data['selectLength'] ?? static::loadItemsCount;

        $checkedNum = 0;

        $checkedVals = (array)($checkedVals ?? []);

        //Наверх выбранные;
        if (!empty($checkedVals) && count($list) > $selectLength) {
            if (empty($this->data['multiple'])) {
                $mm = $checkedVals[0];
                if (!is_array($mm) && array_key_exists($mm, $list) && $list[$mm][1] === 0) {
                    $v = $list[$mm];
                    unset($list[$mm]);
                    $list = [$mm => $v] + $list;
                    $checkedNum++;
                }
            } else {

                foreach ((array)$checkedVals as $mm) {
                    if (!is_array($mm) && array_key_exists($mm, $list) && $list[$mm][1] === 0) {
                        $v = $list[$mm];
                        unset($list[$mm]);
                        $list = [$mm => $v] + $list;
                        $checkedNum++;
                    }
                }
            }
        }

        $i = 0;
        $isSliced = false;
        $listMain = [];
        $objMain = [];
        $addInArrays = function ($k, $v) use (&$listMain, &$objMain, &$i) {
            $listMain[] = strval($k);
            $v[10] = $v[1];
            unset($v[1]);
            if (!empty($v[2]) && is_object($v[2])) {
                $v[2] = $v[2]();
            }
            $objMain[$k] = array_values($v);
            $i++;
        };

        foreach ($list as $k => $v) {
            if (($v[1] ?? 0) === 1 && !in_array($k, $checkedVals)) {
                unset($list[$k]);
            }
        }

        if (count($list) > ($selectLength + $checkedNum)) {
            if ($q) {
                $qfunc = $this->table->getTotum()->getLangObj()->getSearchFunction($q);
            }

            foreach ($list as $k => $v) {
                if ($i < $checkedNum) {
                    $addInArrays($k, $v);
                } else {
                    if ($q) {
                        if ($qfunc($v[0])) {
                            $addInArrays($k, $v);
                        }
                    } else {
                        $addInArrays($k, $v);
                    }
                }

                if ($i > $selectLength + $checkedNum) {
                    $isSliced = true;
                    break;
                }
            }
        } else {
            foreach ($list as $k => $v) {
                $addInArrays($k, $v);
            }
        }
        return ['list' => $listMain, 'indexed' => $objMain, 'sliced' => $isSliced, 'previewdata' => $previewdata];
    }

    public function getPreviewHtml(array $val, $row, $tbl, $withNames = false)
    {
        $Log = $this->table->calcLog(['itemId' => $row['id'] ?? null, 'cType' => 'previewHtml', 'field' => $this->data['name']]);

        if (!$this->CalculateCodePreviews) {
            $this->CalculateCodePreviews = new CalculateSelectPreview($this->data['codeSelect']);
        }
        try {
            $rowId = [];
            if ($this->data['category'] === 'column') {
                $rowId['id'] = $row['id'] ?? null;
            }
            $row = $this->CalculateCodePreviews->exec($this->data, $val, [], $row, $tbl, $tbl, $this->table);
            $htmls = [];

            if ($row['previewscode'] ?? null) {
                $CalcPreview = new Calculate($row['previewscode']);

                $data = $CalcPreview->exec(
                    ['name' => 'CALC PREVIEW ' . $this->data['name']],
                    [],
                    [],
                    $rowId,
                    [],
                    $this->table->getTbl(),
                    $this->table,
                    ['val' => $val['v']]
                );

                try {
                    if ($CalcPreview->getError()) {
                        throw new errorException($CalcPreview->getError());
                    }


                    if (!is_array($data)) {
                        throw new errorException("error");
                    }
                    foreach ($data as $_row) {
                        if (!is_array($_row) || !key_exists('title', $_row) || !key_exists('value', $_row)) {
                            throw new errorException("error");
                        }
                        $title = $_row['title'] ?? '';
                        $value = $_row['value'] ?? '';
                        if ($withNames) {
                            $htmls[$_row['name'] ?? ''] = [$title, $value, 'text', ''];
                        } else {
                            $htmls[] = [$title, $value, 'text', ''];
                        }
                    }
                } catch (errorException) {
                    $errorText = $this->translate('Value format error') . ': [{"title":"Title of preview","value":"Value of preview","name":"name of preview if it\'s needed"}]';
                    $exception = new errorException($errorText);
                    $exception->addPath($this->translate('field [[%s]] of [[%s]] table',
                        [$this->data['name'], $this->table->getTableRow()['name']]));
                    throw $exception;
                }
            }


            foreach ($row['__fields'] ?? [] as $name => $field) {
                $format = 'string';
                $elseData = [];
                $_val = $row[$name] ?? [];

                if ($name === 'id') {
                    $field = ['title' => 'id', 'type' => 'number'];
                }

                switch ($field['type']) {
                    case 'string':
                        if ($field['url'] ?? false) {
                            $format = 'url';
                        }
                        break;
                    case 'number':
                        foreach (['unitType', 'before', 'currency', 'prefix', 'postfix', 'thousandthSeparator', 'dectimalSeparator', 'dectimalPlaces'] as $key) {
                            if ($field[$key] ?? false) {
                                $elseData[$key] = $field[$key];
                            }
                        }
                        $format = 'number';
                        break;
                    case 'text':
                        $format = $field['textType'];
                        break;
                    case 'file':
                        $format = 'file';
                        break;


                }
                if ($withNames) {
                    if (!key_exists($name, $htmls)) {
                        $htmls[$name] = [$field['title'], $_val, $format, $elseData ?? []];
                    }
                } else {
                    $htmls[] = [$field['title'], $_val, $format, $elseData ?? []];
                }
                //string, url, currency, css, xml, text, html, json, totum, javascript
            }
        } catch (\Exception $exception) {
            $this->table->calcLog($Log, 'error', $exception->getMessage());
            throw $exception;
        }
        $this->table->calcLog($Log, 'result', $htmls);

        return $htmls;
    }

    protected function calculateSelectValueList(array $val, $row, $tbl = [], $vars = [])
    {
        if (!empty($this->data['values'])) {
            $list = [];
            foreach ($this->data['values'] ?? [] as $k => $v) {
                if (is_array($v)) {
                    if (!empty($v['disabled'])) {
                        $list[$k] = $v['title'];
                    }
                    $list[$k] = $v['title'];
                } else {
                    $list[$k] = $v;
                }
            }
        } elseif (array_key_exists('codeSelect', $this->data)) {
            if (is_null($this->CalculateCodeSelectValue)) {
                $this->CalculateCodeSelectValue = new CalculateSelectValue($this->data['codeSelect']);
            }
            $list = $this->CalculateCodeSelectValue->exec(
                $this->data,
                $val,
                [],
                $row,
                [],
                $tbl,
                $this->table,
                $vars
            );
        }

        return $this->commonSelectValueList = $list;
    }

    /**
     *
     * Значение селекта (без секции)
     *
     * @param $val
     * @param $row
     * @param array $tbl
     * @return array|mixed|null|string
     */
    public function getSelectValue($val, $row, $tbl = [])
    {
        $list = $this->calculateSelectValueList(['v' => $val], $row, $tbl);

        if (!is_null($list)) {
            if (is_array($list)) {
                if (!empty($this->data['multiple'])) {
                    $return = '';
                    if ($val !== $this->data['errorText']) {
                        foreach ($val ?? [] as $v) {
                            if (!empty($return)) {
                                $return .= ', ';
                            }
                            if (empty($list[$v])) {
                                $return .= $v;
                            } else {
                                $return .= $list[$v];
                            }
                        }
                    } else {
                        $return = $this->data['errorText'];
                    }
                } else {
                    if ($v_ = $list[$val] ?? null) {
                        $return = $v_;
                    } elseif ($this->data['withEmptyVal'] ?? false) {
                        $return = $this->data['withEmptyVal'];
                    } else {
                        $return = $val;
                    }
                }
            } else {
                $return = $this->data['errorText'];
            }
        }

        return $return;
    }

    public function getLogValue($val, $row, $tbl = [])
    {
        return $this->getSelectValue($val, $row, $tbl);
    }

    public function calculateSelectList(array &$val, $row, $tbl = [], $vars = [])
    {
        if (empty($this->data['codeSelectIndividual'])) {
            if (!is_null($this->commonSelectList)) {
                return $this->commonSelectList;
            }
        }

        $Log = $this->table->calcLog(['itemId' => $row['id'] ?? null, 'cType' => 'selectList', 'field' => $this->data['name']]);

        $list = [];

        try {
            if (!empty($this->data['values'])) {
                foreach ($this->data['values'] ?? [] as $k => $v) {
                    if (is_array($v)) {
                        if (!empty($v['disabled'])) {
                            $list[$k] = [$v['title'], 2];
                        }
                        $list[$k] = [$v['title'], 0];
                    } else {
                        $list[$k] = [$v, 0];
                    }
                }
            } elseif (array_key_exists('codeSelect', $this->data)) {
                $list = $this->CalculateCodeSelect->exec(
                    $this->data,
                    $val,
                    [],
                    $row,
                    [],
                    $tbl,
                    $this->table,
                    $vars
                );
                if ($error = $this->CalculateCodeSelect->getError()) {
                    $val['e'] = (empty($val['e']) ? '' : $val['e'] . '; ') . $error;
                    $list = [];
                }
                $this->log = $this->CalculateCodeSelect->getLogVar();
            }

            if ($this->data['category'] === 'filter') {
                $add = [];
                if (!empty($this->data['selectFilterWithEmpty'])) {
                    $add[''] = [($this->data['selectFilterWithEmptyText'] ?? $this->translate('Empty')), 0];
                }
                if (!empty($this->data['selectFilterWithAll'])) {
                    $add['*ALL*'] = [($this->data['selectFilterWithAllText'] ?? $this->translate('All')), 0];
                }
                if (!empty($this->data['selectFilterWithNone'])) {
                    $add['*NONE*'] = [($this->data['selectFilterWithNoneText'] ?? $this->translate('Nothing')), 0];
                }
                $list = $add + $list;
            }

            $this->table->calcLog($Log, 'result', $list);
        } catch (\Exception $exception) {
            $this->table->calcLog($Log, 'error', $exception->getMessage());
            throw $exception;
        }


        return $this->commonSelectList = $list;
    }

    public function calculateSelectListWithPreviews(array &$val, $row, $tbl = [])
    {
        $Log = $this->table->calcLog(['itemId' => $row['id'] ?? null, 'cType' => 'viewWithPreviews', 'field' => $this->data['name']]);

        try {
            $list = $this->calculateSelectList($val, $row, $tbl = []);

            if ($list['previewdata'] ?? false) {
                unset($list['previewdata']);
                foreach ($list as $_v => &$l) {
                    $l[] = $this->getPreviewHtml(['v' => $_v], $row, $tbl, true);
                }
            } else {
                unset($list['previewdata']);
            }
            unset($l);

            $this->table->calcLog($Log, 'result', $list);
        } catch (\Exception $e) {
            $this->table->calcLog($Log, 'error', $e->getMessage());
            throw $e;
        }

        return $list;
    }

    protected function calculateSelectViewList(array &$val, $row, $tbl = [])
    {
        if (empty($this->data['codeSelectIndividual'])) {
            if (!is_null($this->commonSelectViewList)) {
                return $this->commonSelectViewList;
            }
        }
        $list = [];

        if (!empty($this->data['values'])) {
            foreach ($this->data['values'] ?? [] as $k => $v) {
                if (is_array($v)) {
                    if (!empty($v['disabled'])) {
                        $list[$k] = [$v['title'], 2];
                    }
                    $list[$k] = [$v['title'], 0];
                } else {
                    $list[$k] = [$v, 0];
                }
            }
        } elseif (array_key_exists('codeSelect', $this->data)) {
            if (is_null($this->CalculateCodeViewSelect)) {
                $this->CalculateCodeViewSelect = new CalculateSelectViewValue($this->data['codeSelect']);
            }

            $Log = $this->table->calcLog(['itemId' => $row['id'] ?? null, 'cType' => 'selectViewList', 'field' => $this->data['name']]);

            try {
                $list = $this->CalculateCodeViewSelect->exec(
                    $this->data,
                    $val,
                    [],
                    $row,
                    [],
                    $tbl,
                    $this->table
                );
                if ($error = $this->CalculateCodeViewSelect->getError()) {
                    $val['e'] = (empty($val['e']) ? '' : $val['e'] . '; ') . $error;
                    $list = [];
                }

                $this->table->calcLog($Log, 'result', $list);
            } catch (\Exception $e) {
                $this->table->calcLog($Log, 'error', $e->getMessage());
                throw $e;
            }
        }


        return $this->commonSelectViewList = $list;
    }

    public function getValueFromCsv($val)
    {
        if (!empty($this->data['multiple'])) {
            $vals = preg_split('/\]\s*\[/', $val);
            foreach ($vals as &$v) {
                $v = preg_replace('/^\s*\[?([^:]*\s*).*$/', '$1', $v);
                if ($v === '') {
                    $v = null;
                }
            }
            $val = $vals;
        } else {
            $val = preg_replace('/^\s*\[?([^:]*\s*).*$/', '$1', $val);
            if ($val === '') {
                $val = null;
            }
        }
        return $val;
    }

    public function addXmlExport(\SimpleXMLElement $simpleXMLElement, $fVar)
    {
        if (!empty($this->data['multiple'])) {
            $paramInXml = $simpleXMLElement->addChild($this->data['name']);
            $v_ = [];
            foreach ($fVar['v_'] as $i_v_) {
                $v_[$i_v_[2]] = $i_v_;
            }
            foreach ($fVar['v'] as $v) {
                $value = $paramInXml->addChild('value', $v);
                $value->addAttribute('title', $v_[$v][0]);
                $value->addAttribute('correct', $v_[$v][0] === 1 ? '0' : '1');
            }
        } else {
            if (is_array($fVar['v'])) {
                $paramInXml = $simpleXMLElement->addChild($this->data['name'], json_encode($fVar['v']));
                $fVar['e'] = 'list в немульти поле';
            } elseif (!is_null($fVar['v']) && isset($fVar['v_'])) {
                $paramInXml = $simpleXMLElement->addChild($this->data['name'], $fVar['v']);

                $paramInXml->addAttribute('title', $fVar['v_'][0]);
                if ($fVar['v_'][1]) {
                    $paramInXml->addAttribute('correct', '0');
                } else {
                    $paramInXml->addAttribute('correct', '1');
                }
            }
        }

        if (empty($paramInXml)) {
            $paramInXml = $simpleXMLElement->addChild($this->data['name']);
        }

        if (isset($fVar['e'])) {
            $paramInXml->addAttribute('error', $fVar['e']);
        }
        if (isset($fVar['c'])) {
            $paramInXml->addAttribute('c', $fVar['c']);
            $paramInXml->addAttribute('h', isset($fVar['h']) ? '1' : '0');
        }
    }

    public function addViewValues($viewType, array &$valArray, $row, $tbl = [])
    {
        if ($valArray['v'] === '') {
            $valArray['v'] = null;
        }

        parent::addViewValues($viewType, $valArray, $row, $tbl);
        $getSelectData = function ($v, $list, &$valArray) {
            if (!is_null($list)) {
                if (is_array($list)) {
                    if (!empty($this->data['multiple'])) {
                        $v_ = [];
                        if ($v !== $this->data['errorText'] && (is_null($v) || is_array($v))) {
                            foreach (($v ?? []) as $_v) {
                                if (is_array($_v)) {
                                    $v_[] = [json_encode($_v, JSON_UNESCAPED_UNICODE), 1, json_encode($_v,
                                        JSON_UNESCAPED_UNICODE)];
                                    $valArray['e'] = $this->translate('Field data type error');
                                } elseif (empty($list[$_v])) {
                                    $v_[] = [$_v, 1, $_v];
                                } else {
                                    $v_[] = array_merge($list[$_v], [$_v]);
                                }
                            }
                        } else {
                            $v_ = [[$this->data['errorText'], 0]];
                        }
                    } else {
                        if (!is_array($v)) {
                            if ($v_ = $list[$v] ?? null) {
                                $v_ = $v_;
                            } elseif (is_null($v)) {
                                $v_ = [$this->data['withEmptyVal'] ?? '', 0];
                            } else {
                                $v_ = [$v, 1];
                            }
                        } else {
                            $v_ = [$this->data['errorText'], 1];
                        }
                    }
                    return $v_;
                }
            }
        };

        $add = [];
        $_valArray = $valArray;
        $list = [];
        if ($this->data['category'] === 'filter') {

            if (!empty($this->data['selectFilterWithAll'])) {
                $add['*ALL*'] = [($this->data['selectFilterWithAllText'] ?? $this->translate('All')), 0];
            }
            if (!empty($this->data['selectFilterWithNone'])) {
                $add['*NONE*'] = [($this->data['selectFilterWithNoneText'] ?? $this->translate('Nothing')), 0];
            }
            if (!empty($this->data['selectFilterWithEmpty'])) {
                $add[''] = [($this->data['selectFilterWithEmptyText'] ?? $this->translate('Empty')), 0];
            }

        }

        if (empty($list)) {
            $list = $this->calculateSelectViewList($_valArray, $row, $tbl);
        }
        $list = $add + $list;

        if (!empty($_valArray['e'])) {
            $valArray['e'] = $_valArray['e'];
        }


        if (!is_null($list)) {
            if (is_array($list)) {
                $valArray['v_'] = $getSelectData($valArray['v'], $list, $valArray);
            } else {
                $valArray['v_'] = [$valArray['v'], 1];

                if (!array_key_exists('e', $valArray)) {
                    $valArray['e'] = $list;
                } else {
                    $valArray['e'] .= "\n" . $list;
                }
            }
        }

        switch ($viewType) {
            case 'print':
                $func = function ($arrayVals, $arrayTitles) use (&$func) {
                    if (!$arrayTitles) {
                        return '';
                    }
                    if (is_array($arrayVals)) {
                        $v = [$arrayVals[0], $arrayTitles[0]];
                    } else {
                        $v = [$arrayVals, $arrayTitles];
                        $arrayVals = [$arrayVals];
                        $arrayTitles = [$arrayVals];
                    }
                    if ($v[1][0] !== '' && !is_null($v[1][0]) && !empty($this->data['unitType'])) {
                        $v[1][0] .= ' ' . $this->data['unitType'];
                    }
                    return '<div><span' . ($v[1][1] ? ' class="deleted"' : '') . '>' . htmlspecialchars($v[1][0]) . '</span></div>' . $func(
                            array_slice(
                                $arrayVals,
                                1
                            ),
                            array_slice($arrayTitles, 1)
                        );
                };

                if ($this->data['multiple'] && ($this->data['printTextfull'] ?? false)) {
                    $valArray['v'] = $func($valArray['v'], $valArray['v_']);
                } else {
                    if ($this->data['multiple']) {
                        if ($valArray['v']) {
                            if (count($valArray['v']) === 1) {
                                $valArray['v'] = $func(
                                    $valArray['v'][count($valArray['v']) - 1],
                                    $valArray['v_'][count($valArray['v_']) - 1]
                                );
                            } else {
                                $valArray['v'] = $func('', [count($valArray['v']) . $this->translate('  elem.'), 0]);
                            }
                        } else {
                            $valArray['v'] = '';
                        }
                    } else {
                        $valArray['v'] = $func([$valArray['v']], [$valArray['v_']]);
                    }
                }
                unset($valArray['v_']);
                break;
            case 'csv':

                $val = '';
                if (!empty($this->data['multiple'])) {
                    foreach ($valArray['v'] as $k => $v) {
                        if ($val) {
                            $val .= ' ';
                        }
                        $val .= '[' . $v . ':' . $valArray['v_'][$k][0] . ']';
                    }
                } else {
                    $val = '[' . $valArray['v'] . ':' . $valArray['v_'][0] . ']';
                }
                $valArray['v'] = $val;

                break;

            case 'web':
                if (empty($valArray['e'])) {
                    if ($this->data['multiple'] ?? false) {
                        if ($valArray['v'] && (!is_array($valArray['v']) || !key_exists(0, $valArray['v']))) {
                            $valArray['e'] = $this->translate('Field data format error');
                        }
                    } else {
                        if (!is_null($valArray['v']) && !is_string($valArray['v'])) {
                            $valArray['e'] = $this->translate('Field data format error');
                        }
                    }
                }

                if (array_key_exists('c', $valArray)) {
                    if ($valArray['c'] !== $valArray['v']) {
                        $valArrayTmp = $valArray;
                        $valArrayTmp['v'] = $valArrayTmp['c'];

                        $list = $this->calculateSelectViewList($valArrayTmp, $row, $tbl);
                        if (is_array($list)) {
                            $valArray['c_'] = $getSelectData($valArray['c'], $list, $valArray);
                        } else {
                            $valArray['c_'] = [$this->data['errorText'], 1];
                            if (!array_key_exists('e', $valArray)) {
                                $valArray['e'] = $list;
                            }
                        }
                    }
                }

                break;
        }
    }

    protected function checkValByType(&$val, $row, $isCheck = false)
    {

        if (($this->data['multiple'] ?? false) === true) {
            if (!is_array($val)) {
                if (is_numeric($val)) {
                    $val = [strval($val)];
                } elseif (is_null($val)) {
                    $val = [];
                } else {
                    if ($v = json_decode($val, true)) {
                        $val = (array)$v;
                    } else {
                        $val = (array)$val;
                    }
                }
            }
            foreach ($val as &$v) {
                $v = is_array($v) ? json_encode($v) : strval($v);
            }
            unset($v);
            $val = array_values($val);
        } else {
            if (is_array($val)) {
                if (count($val) === 0) {
                    $val = null;
                } else {
                    $firstValue = $val[array_key_first($val)];
                    if (is_array($firstValue)) {
                        $val = json_encode($firstValue, JSON_UNESCAPED_UNICODE);
                    } else {
                        $val = strval($firstValue);
                    }
                }
            } else {
                $val = match ($val) {
                    true => 'true',
                    false => 'false',
                    default => strval($val)
                };
            }
        }

        if ($val === '' && !($this->data['category'] === 'filter' && ($this->data['selectFilterWithEmpty'] ?? false) === true)) {
            $val = null;
        }
    }

    public function add($channel, $inNewVal, $row = [], $oldTbl = [], $tbl = [], $isCheck = false, $vars = [])
    {
        if (!$isCheck) {
            $this->checkSelectVal($channel, $inNewVal, $row, $tbl);
        }

        return parent::add($channel,
            $inNewVal,
            $row,
            $oldTbl,
            $tbl,
            $isCheck,
            $vars);
    }

    public function modify($channel, $changeFlag, $newVal, $oldRow, $row = [], $oldTbl = [], $tbl = [], $isCheck = false)
    {
        $r = parent::modify($channel,
            $changeFlag,
            $newVal,
            $oldRow,
            $row,
            $oldTbl,
            $tbl,
            $isCheck);

        if ($changeFlag === static::CHANGED_FLAGS['changed'] && $newVal !== ($oldRow[$this->data['name']]['v'] ?? null)) {
            $this->checkSelectVal($channel, $newVal, $row, $tbl, $oldRow);
        }

        return $r;
    }

    protected function getDefaultValue()
    {
        if (!empty($this->data['multiple'])) {
            if ($default = json_decode($this->data['default'] ?? '[]', true)) {
                if (!is_array($default)) {
                    $default = [$default];
                }
            } else {
                $default = [];
                if (key_exists('default', $this->data)) {
                    $default = [$this->data['default']];
                }
            }
        } else {
            $default = $this->data['default'] ?? '';
        }
        return $default;
    }

    protected function modifyValue($modifyVal, $oldVal, $isCheck, $row)
    {
        if (empty($modifyVal)) {
            return $modifyVal;
        }

        if (is_object($modifyVal) && empty($this->data['multiple'])) {
            throw new errorException($this->translate('Operation [[%s]] over not mupliple select is not supported.',
                $modifyVal->sign));
        }

        if (!empty($this->data['multiple']) && !is_array($modifyVal)) {
            if (is_object($modifyVal)) {
                if (empty($oldVal)) {
                    $oldVal = array();
                }
                $modifyVal = match ($modifyVal->sign) {
                    '-' => array_diff($oldVal, (array)$modifyVal->val),
                    '+' => array_merge($oldVal, (array)$modifyVal->val),
                    default => throw new errorException($this->translate('Operation [[%s]] over lists is not supported.',
                        $modifyVal->sign)),
                };
            } else {

                $modifyVal = match ($modifyVal) {
                    false => 'false',
                    true => 'true',
                    default => (string)$modifyVal
                };
            }
            if ($modifyVal === '' || $modifyVal === null) {
                $modifyVal = [];
            } elseif (!is_array($modifyVal)) {
                $modifyVal = [$modifyVal];
            }
            $modifyVal = array_values($modifyVal);
        }
        return $modifyVal;
    }

    public function checkSelectVal($channel, $newVal, array $row, array $tbl, array $oldRow = [], $vars = [])
    {
        if (!empty($this->data['checkSelectValues']) && $channel !== 'inner') {
            if (($newVal === [] || ($newVal ?? '') === '')) {
                return;
            }
            if (is_null($this->CalculateCodeSelectValue)) {
                $this->CalculateCodeSelectValue = new CalculateSelectValue($this->data['codeSelect']);
            }
            $this->CalculateCodeSelectValue->hiddenInPreparedList(true);
            $list = $this->calculateSelectValueList(['v' => $newVal], $row, $tbl, $vars);
            if (is_string($list)) {
                throw new errorException($list);
            }
            $this->CalculateCodeSelectValue->hiddenInPreparedList(false);

            $check = function ($v) use ($oldRow, $list) {
                if (!key_exists($v, $list) || ($list[$v] && !in_array($v,
                            (array)($oldRow[$this->data['name']]['v'] ?? [])))) {
                    throw new criticalErrorException($this->translate('This value is not available for entry in field %s.',
                        $this->data['title']));
                }
            };

            if (!empty($this->data['multiple'])) {
                foreach ($newVal as $v) {
                    $check($v);
                }
            } else {
                $check($newVal);
            }
        }
    }
}
