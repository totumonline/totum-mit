<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 18.07.17
 * Time: 11:21
 */

namespace totum\common\calculates;

use totum\common\calculates\Calculate;
use totum\common\errorException;
use totum\common\Field;
use totum\common\Lang\RU;
use totum\common\sql\SqlException;
use totum\models\Table;
use totum\tableTypes\aTable;

class CalculateSelect extends Calculate
{
    /**
     * @var mixed
     */
    protected $parentName;

    public function exec($fieldData, $newVal, $oldRow, $row, $oldTbl, $tbl, aTable $table, $vars = []): mixed
    {
        try {
            $r = parent::exec($fieldData, $newVal, $oldRow, $row, $oldTbl, $tbl, $table, $vars);
            if (!$this->error && !is_array($r)) {
                throw new errorException($this->translate('The code should return [[%s]].', 'rowList'));
            }
            $r = $this->getPreparedList($r);
        } catch (SqlException $e) {
            $this->newLog['text'] = ($this->newLog['text'] ?? '') . $this->translate('ERR!');
            $this->error = $this->translate('Database error while processing [[%s]] code.', $e->getMessage());
            $this->newLog['children'][] = ['type' => 'error', 'text' => $this->error];
            throw $e;
        } catch (errorException $e) {
            $this->newLog['text'] = ($this->newLog['text'] ?? '') . $this->translate('ERR!');
            $this->newLog['children'][] = ['type' => 'error', 'text' => $e->getMessage()];
            $this->error = $e->getMessage();
        }

        if ($this->error) {
            $this->error .= ' ('.$this->translate('field [[%s]] of [[%s]] table', [$this->varName, $this->Table->getTableRow()['name']]).')';
        }

        return $r ?? $this->error;
    }

    /**
     * @return mixed
     */
    public function getParentName()
    {
        return $this->parentName;
    }

    protected function funcSelectListAssoc($params)
    {
        $params = $this->getParamsArray($params, ['where', 'order']);
        $this->__checkRequiredParams($params, ['field'], 'selectListAssoc');

        $params2 = $params;

        $baseField = $params['bfield'] ?? 'id';

        $params2['field'] = [$params['field'], $baseField, 'is_del'];
        $params2['sfield'] = [];
        if (!empty($params['section'])) {
            // $params2['field'][] = $params['section'];
            $params2['sfield'][] = $params2['section'];
            unset($params2['section']);
            $params2['with__sectionFunction'] = true;
        }
        if (!empty($params['parent'])) {
            $params2['field'][] = $params['parent'];
        }

        $disabled = array_flip(array_unique($params['disabled'] ?? []));

        $rows = $this->select($params2, 'rows');

        $rows = array_map(
            function ($row) use ($params, $disabled, $baseField) {
                $r = ['value' => $row[$baseField]
                    , 'is_del' => $row['is_del']
                    , 'title' => $row[$params['field']]];
                if (array_key_exists('parent', $params)) {
                    $r['parent'] = $row[$params['parent']];
                }
                if (array_key_exists($row[$baseField], $disabled)) {
                    $r['disabled'] = true;
                }
                if (!empty($params['section'])) {
                    $r['section'] = $row['__sectionFunction'] ?? $row[$params['section']];
                }
                return $r;
            },
            $rows
        );

        if (!empty($params['preview'])) {
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
            $ParentField = Field::init($sourceTable->getFields()[$params['parent']], $sourceTable);
            if ($ParentField->getData('codeSelectIndividual')) {
                throw new errorException($this->translate('The [[%s]] parameter must [[not]] be [[%s]].', [$params['parent'], 'codeSelectIndividual']));
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

        if (!empty($params['preview'])) {
            $rows['previewdata'] = true;
        };
        return $rows;
    }

    protected function getPreparedList($rows)
    {
        $selectList = [];
        if ($this->varData['type'] === 'tree') {
            foreach ($rows as $row) {
                $r = [$row['title'] //0
                    , empty($row['is_del']) ? 0 : 1 //1
                    , null //2
                    , $row['parent'] ?? null //3
                    // disabled //4
                ];
                if ($row['disabled'] ?? false) {
                    $r[] = 1;
                }

                $selectList[$row['value']] = $r;
            }
        } else {
            $selectList['previewdata'] = $rows['previewdata'] ?? false;
            unset($rows['previewdata']);

            foreach ($rows as $row) {
                $selectList[$row['value']] = [$row['title']];                   //0
                $selectList[$row['value']][] = !empty($row['is_del']) ? 1 : 0;  //1
                $selectList[$row['value']][] = $row['section'] ?? null;         //2
                $selectList[$row['value']][] = $row['preview'] ?? null;         //3
            }
        }
        return $selectList;
    }
}
