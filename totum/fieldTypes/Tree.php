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
use totum\common\calculates\CalculateSelectValue;
use totum\common\errorException;
use totum\common\Field;
use totum\common\Lang\RU;
use totum\tableTypes\aTable;

class Tree extends Field
{
    protected $commonSelectList;
    protected $commonSelectValueList;
    const loadItemsCount = 50;
    /**
     * @var mixed
     */
    protected $parentName;

    protected function __construct($fieldData, aTable $table)
    {
        parent::__construct($fieldData, $table);

        if (!empty($this->data['codeSelect'])) {
            $this->CalculateCodeSelect = new CalculateSelect($this->data['codeSelect']);
        }
    }

    public function clearCachedLists()
    {
        $this->commonSelectList = null;
        $this->commonSelectValueList = null;
    }

    public function calculateSelectValueList($val, $row, $tbl = [])
    {
        if (empty($this->data['codeSelectIndividual'])) {
            if (!is_null($this->commonSelectValueList)) {
                return $this->commonSelectValueList;
            }
        }
        $list = [];

        if (array_key_exists('codeSelect', $this->data)) {
            if (is_null($this->CalculateCodeSelectValue)) {
                $this->CalculateCodeSelectValue = new CalculateSelectValue($this->data['codeSelect']);
            }

            $Log = $this->table->calcLog(['itemId' => $row['id'] ?? null, 'cType' => 'treeList', 'field' => $this->data['name']]);

            try {
                $list = $this->CalculateCodeSelectValue->exec(
                    $this->data,
                    ['v' => $val],
                    [],
                    $row,
                    [],
                    $tbl,
                    $this->table
                );
                $this->table->calcLog($Log, 'result', $list);
            } catch (\Exception $e) {
                $this->table->calcLog($Log, 'error', $e->getMessage());
                throw $e;
            }
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
        $return = '';
        if (!is_null($list)) {
            if (is_array($list)) {
                if (!empty($this->data['multiple'])) {
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

    public function getLevelValue($val, $row, $tbl = [])
    {
        if (empty($val)) {
            if (empty($this->data['multiple'])) {
                return null;
            } else {
                return [];
            }
        }

        $arrayVal = ['v' => $val];
        $list = $this->calculateSelectList($arrayVal, $row, $tbl);

        $calcLevel = function ($v, $level = 0) use (&$calcLevel) {
            return key_exists('path', $v) ? $calcLevel($v['path'], $level + 1) : $level;
        };

        if (!is_null($list)) {
            if (is_array($list)) {
                if (!empty($this->data['multiple'])) {
                    $return = [];
                    if ($val !== $this->data['errorText']) {
                        foreach ($val ?? [] as $v) {
                            if (empty($list[$v])) {
                                $return[] = 0;
                            } else {
                                $return[] = $calcLevel($list[$v]);
                            }
                        }
                    } else {
                        $return = [];
                    }
                } else {
                    if (empty($list[$val])) {
                        $return = 0;
                    } else {
                        $return = $calcLevel($list[$val]);
                    }
                }
            } else {
                $return = null;
            }
        }

        return $return ?? null;
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
        $list = [];

        if (array_key_exists('codeSelect', $this->data)) {
            $Log = $this->table->calcLog(['itemId' => $row['id'] ?? null, 'cType' => "treeList", 'field' => $this->data['name']]);

            try {
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
                $this->table->calcLog($Log, 'result', $list);
            } catch (\Exception $e) {
                $this->table->calcLog($Log, 'error', $e->getMessage());
                throw $e;
            }
            $this->log = $this->CalculateCodeSelect->getLogVar();
            $this->parentName = $this->CalculateCodeSelect->getParentName();
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

        if (empty($val['__isForChildTree'])) {
            $remove_children = function ($id) use (&$list, &$remove_children) {
                foreach ($list as $k => $v) {
                    if (($v[3] ?? null) == $id) {
                        $remove_children($k);
                        unset($list[$k]);
                    }
                }
            };
            foreach ($list as $k => &$l) {
                if ($l[3] ?? null) {
                    if (key_exists($l[3], $list)) {
                        $l['path'] =& $list[$l[3]];
                    } else {
                        $remove_children($k);
                        unset($list[$k]);
                    }
                }
            }
            unset($l);
        }
        return $this->commonSelectList = $list;
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
                $paramInXml = $simpleXMLElement->addChild($this->data['name'], json_encode($fVar['v'], JSON_UNESCAPED_UNICODE));
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

    public function cropSelectListForWeb($list, $checkedVals, $q = '', $parentIds = null)
    {
        $selectLength = $this->data['selectLength'] ?? static::loadItemsCount;
        $notAllTree = count($list) > $selectLength;


        $listMain = [];
        $objMain = [];
        $deepLevels = [];

        $checkedVals = (array)$checkedVals;
        $addInArrays = function ($k, $noCheckParent = false, $formDeepLevel = false) use (&$listMain, $checkedVals, &$deepLevels, &$objMain, &$list, &$addInArrays) {
            if ($k !== "" && !key_exists($k, $objMain) && ($v = $list[$k] ?? null)) {
                $listMain[] = $k;
                $parent = $v[3] ?? null;

                if (in_array($k, $checkedVals)) {
                    $objMain[$k] = ["id" => $k, "parent" => $parent ?? '#', "text" => $v[0]];

                    $objMain[$k]["state"]["selected"] = true;
                    if (!empty($v[1])) {
                        $objMain[$k]["state"]["deleted"] = true;
                    }
                } elseif (!empty($v[1])) {
                    return;
                } else {
                    $objMain[$k] = ["id" => $k, "parent" => $parent ?? '#', "text" => $v[0]];
                }
                if (!empty($v[4])) {
                    $objMain[$k]["state"]["disabled"] = true;
                }

                if ($formDeepLevel) {
                    $deepLevel = 0;
                    $deepParent = $list[$k]['path'] ?? null;
                    while ($deepParent) {
                        $deepLevel++;
                        $deepParent = $deepParent['path'] ?? null;
                    }

                    $deepLevels[] = $deepLevel;
                }

                if ($parent && key_exists($parent, $list)) {
                    foreach ($list[$parent]['children'] ?? [] as $id) {
                        $addInArrays($id, true, $formDeepLevel);
                    }
                }
                if (empty($v["children"])) {
                    $objMain[$k]["children"] = false;
                } elseif (empty($this->data['treeSelectFolders'])) {
                    $objMain[$k]["state"]["disabled"] = true;
                }


                if (!$noCheckParent && $parent && key_exists($parent, $list)) {
                    $addInArrays($parent, false, $formDeepLevel);
                }
            }
        };
        $top = [];

        foreach ($list as $k => &$l) {
            if ($l[3] ?? null) {
                if (!$l[1]) {
                    $l['path']["children"][] = $k;
                }
            } else {
                $top[] = $k;
            }
        }
        unset($l);


        if (!empty($q)) {
            $ids = [];
            $qFunc = $this->table->getTotum()->getLangObj()->getSearchFunction($q);
            foreach ($list as $id => $v) {
                if (!$qFunc($v[0])) {
                    continue;
                }
                while ($id = $list[$id][3] ?? null) {
                    $ids[$id] = 1;
                }
            }

            return ['list' => array_keys($ids)];
        } elseif (!empty($parentIds)) {
            $massload = is_array($parentIds);
            $parentIds = (array)$parentIds;
            $parents = array_intersect_key($list, array_flip($parentIds));

            foreach ($parents as $parent) {
                foreach ($parent["children"] ?? [] as $id) {
                    $addInArrays($id, true, true);
                }
            }
            if ($massload) {
                $okeys = array_keys($objMain);
                array_multisort($okeys, $deepLevels);
                $parents = [];
                foreach ($okeys as $id) {
                    $item = $objMain[$id];
                    $parents[$item['parent']][] = $item;
                }


                return ['list' => $parents];
            }
        } else {
            if (!$notAllTree) {
                foreach ($list as $k => $v) {
                    $addInArrays($k);
                }
            } else {
                foreach (array_merge($top, $checkedVals) as $k) {
                    $addInArrays($k);
                }
            }
        }

        foreach ($objMain as $k => &$v) {
            if ($list[$k]["children"] ?? null) {
                if (!array_intersect_key(
                    $objMain,
                    array_flip($list[$k]["children"])
                )) {
                    $v["children"] = true;
                }
            }
        }
        unset($v);

        $r = ['list' => array_values($objMain)];
        if (key_exists('selectTable', $this->data) &&
            $this->table->getTotum()->getUser()->isTableInAccess($this->table->getTotum()->getTableRow($this->data['selectTable'])['id'])
        ) {
            $r['parent'] = $this->parentName;
        }
        return $r;
    }

    public function addViewValues($viewType, array &$valArray, $row, $tbl = [])
    {
        parent::addViewValues($viewType, $valArray, $row, $tbl);

        $list = $this->calculateSelectList($valArray, $row, $tbl);

        $getParentsTitle = function ($parents, $level = 0) use (&$getParentsTitle) {
            if ($level > 100) {
                return $this->translate('The looped tree');
            }
            return (!empty($parents['path']) ? $getParentsTitle(
                        $parents['path'],
                        $level + 1
                    ) . ' > ' : '') . $parents[0];
        };

        $getTreeItem = function ($v) use ($list, $getParentsTitle, &$valArray) {
            if ($itemList = $list[$v] ?? false) {
                if (!empty($this->data['treeViewTypeFull'])) {
                    $v_ = [$getParentsTitle($itemList), $itemList[1]];
                } else {
                    $v_ = [$itemList[0], ($itemList[1] || ($itemList[4] ?? false)) ? 1 : 0, $getParentsTitle($itemList)];
                }
                unset($itemList);
            } elseif (is_null($v) && ($this->data['withEmptyVal'] ?? false)) {
                $v_ = [$this->data['withEmptyVal'], 0];
            } else {
                $v_ = [$v, 1];
                if (!is_null($v)) {
                    $valArray['e'] = $this->translate('Value not found');
                }
            }
            return $v_;
        };


        $getSelectData = function ($v, $list) use ($getParentsTitle, $getTreeItem, &$valArray) {
            if (!is_null($list)) {
                if (is_array($list)) {
                    if (!empty($this->data['multiple'])) {
                        $v_ = [];
                        if ($v !== $this->data['errorText'] && (is_null($v) || is_array($v))) {
                            foreach (($v ?? []) as $_v) {
                                $v_[] = $getTreeItem($_v);
                            }
                        } else {
                            $v_ = [$v, 0];
                            $valArray['e'] = $this->translate('Value format error');
                        }
                    } else {
                        if (!is_array($v)) {
                            $v_ = $getTreeItem($v);
                        } else {
                            $v_ = [$this->data['errorText'], 1];
                            $valArray['e'] = $this->translate('Multiselect instead of select');
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

                        $list = $this->calculateSelectList($valArrayTmp, $row, $tbl);
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
        if ($this->data['multiple'] ?? false) {
            foreach ($val as &$v) {
                if (is_array($v)) {
                    $v = json_encode($v, JSON_UNESCAPED_UNICODE);
                } elseif (!is_null($v)) {
                    $v = strval($v);
                }
            }
        } else {
            if (is_array($val)) {
                if (count($val) === 0 || is_null($val[0] ?? null)) {
                    $val = null;
                } else {
                    $val = is_array($val[0]) ? json_encode($val[0], JSON_UNESCAPED_UNICODE) : strval($val[0]);
                }
            } else {
                $val = strval($val);
            }
        }
        if ($val === '') {
            $val = null;
        }
    }

    protected function getDefaultValue()
    {
        if (!empty($this->data['multiple'])) {
            if ($default = json_decode(($this->data['default'] ?? '[]'), true)) {
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
}
