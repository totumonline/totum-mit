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
use totum\common\Controller;
use totum\common\errorException;
use totum\common\Field;
use totum\common\Formats;
use totum\tableTypes\aTable;

class Select extends Field
{
    const loadItemsCount = 50;
    protected $commonSelectList;
    protected $commonSelectValueList;
    protected $commonSelectViewList;
    protected $commonSelectListWithPreviews;
    protected $CalculateCodeViewSelect;
    protected $CalculateCodePreviews;

    protected function __construct($fieldData, aTable $table)
    {
        parent::__construct($fieldData, $table);

        if (!empty($this->data['codeSelect'])) {
            $this->CalculateCodeSelect = new CalculateSelect($this->data['codeSelect']);
        }
    }

    public function cropSelectListForWeb($list, $checkedVals, $q = '', $parentId = null)
    {
        $previewdata = $list['previewdata'] ?? false;
        unset($list['previewdata']);

        $selectLength = $this->data['selectLength'] ?? static::loadItemsCount;

        $checkedNum = 0;

        //Наверх выбранные;
        if (!empty($checkedVals) && count($list) > $selectLength) {
            if (empty($this->data['multiple'])) {
                $mm = $checkedVals;
                if (array_key_exists($mm, $list) && $list[$mm][1] === 0) {
                    $v = $list[$mm];
                    unset($list[$mm]);
                    $list = [$mm => $v] + $list;
                    $checkedNum++;
                }
            } else {
                foreach ((array)$checkedVals as $mm) {
                    if (array_key_exists($mm, $list) && $list[$mm][1] === 0) {
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
            unset($v[1]);
            if (!empty($v[2]) && is_object($v[2])) {
                $v[2] = $v[2]();
            }
            $objMain[$k] = array_values($v);
            $i++;
        };

        foreach ($list as $k => $v) {
            if (($v[1] ?? 0) === 1) {
                unset($list[$k]);
            }
        }

        if (count($list) > ($selectLength + $checkedNum)) {
            if ($q) {
                $qs = explode(' ', str_ireplace('ё', 'е', $q));
                $qfunc = function ($v) use ($qs) {
                    $v = str_ireplace('ё', 'е', $v);
                    foreach ($qs as $q) {
                        if ($q !== "" && mb_stripos($v, $q) === false) {
                            return false;
                        }
                    }
                    return true;
                };
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

    public function getPreviewHtml($val, $row, $tbl, $withNames = false)
    {
        $Log = $this->table->calcLog(['itemId' => $row['id'] ?? null, 'cType' => "previewHtml", 'field' => $this->data['name']]);

        if (!$this->CalculateCodePreviews) {
            $this->CalculateCodePreviews = new CalculateSelectPreview($this->data['codeSelect']);
        }
        try {
            $row = $this->CalculateCodePreviews->exec($this->data, $val, [], $row, $tbl, $tbl, $this->table);
            $htmls = [];

            if ($row['previewscode'] ?? null) {
                $CalcPreview = new Calculate($row['previewscode']);
                $data = $CalcPreview->exec(
                    [],
                    [],
                    [],
                    $this->table->getTbl()['params'],
                    [],
                    $this->table->getTbl(),
                    $this->table,
                    ['val' => $val]
                );
                foreach ($data as $_row) {
                    $title = $_row['title'] ?? '';
                    $value = $_row['value'] ?? '';
                    if ($withNames) {
                        $htmls[$_row['name'] ?? ''] = [$title, $value, 'text', ''];
                    } else {
                        $htmls[] = [$title, $value, 'text', ''];
                    }
                }
            }


            foreach ($row['__fields'] ?? [] as $name => $field) {
                $format = 'string';
                $elseData = [];
                $val = $row[$name];

                if ($name === 'id') {
                    $field = ['title' => 'id'];
                }

                switch ($field['type']) {
                    case 'string':
                        if ($field['url'] ?? false) {
                            $format = 'url';
                        }
                        break;
                    case 'number':
                        if ($field['unitType'] ?? false) {
                            $elseData['unitType'] = $field['unitType'];
                        }
                        if ($field['currency'] ?? false) {
                            $format = 'currency';
                        }
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
                        $htmls[$name] = [$field['title'], $val, $format, $elseData ?? []];
                    }
                } else {
                    $htmls[] = [$field['title'], $val, $format, $elseData ?? []];
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

    protected function calculateSelectValueList($val, $row, $tbl = [])
    {
        if (empty($this->data['codeSelectIndividual'])) {
            if (!is_null($this->commonSelectValueList)) {
                return $this->commonSelectValueList;
            }
        }

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
                $this->table
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
        $list = $this->calculateSelectValueList($val, $row, $tbl);

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

    public function calculateSelectList(&$val, $row, $tbl = [])
    {
        if (empty($this->data['codeSelectIndividual'])) {
            if (!is_null($this->commonSelectList)) {
                return $this->commonSelectList;
            }
        }

        $Log = $this->table->calcLog(['itemId' => $row['id'] ?? null, 'cType' => "selectList", 'field' => $this->data['name']]);

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
                    $this->table
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
                    $add[''] = [($this->data['selectFilterWithEmptyText'] ?? 'Пустое'), 0];
                }
                if (!empty($this->data['selectFilterWithAll'])) {
                    $add['*ALL*'] = [($this->data['selectFilterWithAllText'] ?? 'Все'), 0];
                }
                if (!empty($this->data['selectFilterWithNone'])) {
                    $add['*NONE*'] = [($this->data['selectFilterWithNoneText'] ?? 'Ничего'), 0];
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

    public function calculateSelectListWithPreviews(&$val, $row, $tbl = [])
    {
        $Log = $this->table->calcLog(['itemId' => $row['id'] ?? null, 'cType' => "viewWithPreviews", 'field' => $this->data['name']]);

        try {
            $list = $this->calculateSelectList($val, $row, $tbl = []);

            if ($list['previewdata']) {
                unset($list['previewdata']);
                foreach ($list as $val => &$l) {
                    $l[] = $this->getPreviewHtml($val, $row, $tbl, true);
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

    protected function calculateSelectViewList(&$val, $row, $tbl = [])
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

            $Log = $this->table->calcLog(['itemId' => $row['id'] ?? null, 'cType' => "selectViewList", 'field' => $this->data['name']]);

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

        if ($this->data['category'] === 'filter') {
            $add = [];
            if (!empty($this->data['selectFilterWithEmpty'])) {
                $add[''] = [($this->data['selectFilterWithEmptyText'] ?? 'Пустое'), 0];
            }
            if (!empty($this->data['selectFilterWithAll'])) {
                $add['*ALL*'] = [($this->data['selectFilterWithAllText'] ?? 'Все'), 0];
            }
            if (!empty($this->data['selectFilterWithNone'])) {
                $add['*NONE*'] = [($this->data['selectFilterWithNoneText'] ?? 'Ничего'), 0];
            }
            $list = $add + $list;
        }
        return $this->commonSelectViewList = $list;
    }

    public function getValueFromCsv($val)
    {
        if (!empty($this->data['multiple'])) {
            $vals = preg_split('/\]\s*\[/', $val);
            foreach ($vals as &$v) {
                $v = preg_replace('/^\s*\[?([a-z_\d]*).*$/', '$1', $v);
                if ($v === '') {
                    $v = null;
                }
            }
            $val = $vals;
        } else {
            $val = preg_replace('/^\s*\[?([a-z_\d]*).*$/', '$1', $val);
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
        parent::addViewValues($viewType, $valArray, $row, $tbl);

        $list = $this->calculateSelectViewList($valArray, $row, $tbl);

        $getSelectData = function ($v, $list) {
            if (!is_null($list)) {
                if (is_array($list)) {
                    if (!empty($this->data['multiple'])) {
                        $v_ = [];
                        if ($v !== $this->data['errorText'] && (is_null($v) || is_array($v))) {
                            foreach (($v ?? []) as $_v) {
                                if (empty($list[$_v])) {
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
                            } elseif (is_null($v) && ($this->data['withEmptyVal'] ?? false)) {
                                $v_ = [$this->data['withEmptyVal'], 0];
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

        if (!is_null($list)) {
            if (is_array($list)) {
                $valArray['v_'] = $getSelectData($valArray['v'], $list);
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
                                $valArray['v'] = $func('', [count($valArray['v']) . ' элем.', 0]);
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
                if (array_key_exists('c', $valArray)) {
                    if ($valArray['c'] !== $valArray['v']) {
                        $valArrayTmp = $valArray;
                        $valArrayTmp['v'] = $valArrayTmp['c'];

                        $list = $this->calculateSelectViewList($valArrayTmp, $row, $tbl);
                        if (is_array($list)) {
                            $valArray['c_'] = $getSelectData($valArray['c'], $list);
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
        if (($this->data['multiple'] ?? false) === true && !is_array($val)) {
            if (is_numeric($val)) {
                $val = [strval($val)];
            } elseif (is_null($val)) {
                $val = [];
            } else {
                if ($v = json_decode($val, true)) {
                    $val = strval($v);
                } else {
                    $val = [strval($val)];
                }
            }
        }
        if ($this->data['multiple']) {
            foreach ($val as &$v) {
                if (is_int($v)) {
                    $v = strval($v);
                }
            }
        } else {
            if (is_array($val)) {
                if (count($val) === 0) {
                    $val = null;
                } else {
                    $val = strval($val[0]);
                }
            } else {
                $val = strval($val);
            }
        }

        if ($val === "" && !($this->data['category'] === 'filter' && $this->data['selectFilterWithEmpty'] === true)) {
            $val = null;
        }
    }

    protected function getDefaultValue()
    {
        if (!empty($this->data['multiple'])) {
            if ($default = json_decode($this->data['default'] ?? "", true)) {
                if (!is_array($default)) {
                    $default = [$default];
                }
            } else {
                $default = [$this->data['default'] ?? ""];
            }
        } else {
            $default = $this->data['default'] ?? "";
        }
        return $default;
    }

    protected function modifyValue($modifyVal, $oldVal, $isCheck, $row)
    {
        if (empty($modifyVal)) {
            return $modifyVal;
        }

        if (!empty($this->data['multiple']) && !is_array($modifyVal)) {
            if (is_object($modifyVal)) {
                if (empty($oldVal)) {
                    $oldVal = array();
                }
                switch ($modifyVal->sign) {
                    case '-':
                        $modifyVal = array_diff($oldVal, (array)$modifyVal->val);
                        break;
                    case '+':
                        $modifyVal = array_merge($oldVal, (array)$modifyVal->val);
                        break;
                    default:
                        throw new errorException('Операция [[' . $modifyVal->sign . ']] над листами непредусмотрена');
                }
            } else {
                $tmpVal = substr($modifyVal, 1);
                if (empty($oldVal)) {
                    $oldVal = array();
                }
                switch ($modifyVal{0}) {
                    case '-':
                        $modifyVal = [];
                        foreach ($oldVal as $v) {
                            if ($v == $tmpVal) {
                                continue;
                            }
                            $modifyVal[] = $v;
                        }
                        break;
                    case '+':
                        $modifyVal = $oldVal;
                        $modifyVal[] = $tmpVal;
                        break;
                }
            }
            $modifyVal = array_values($modifyVal);
        }
        return $modifyVal;
    }
}
