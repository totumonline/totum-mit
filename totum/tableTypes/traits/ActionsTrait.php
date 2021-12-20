<?php

namespace totum\config\totum\tableTypes\traits;

use totum\common\errorException;
use totum\common\Lang\RU;
use totum\tableTypes\aTable;

trait ActionsTrait
{
    public function actionClear($fields, $where, $limit = null)
    {
        $setValuesToDefaults = $this->getModifyForActionClear($fields, $where, $limit);
        if ($setValuesToDefaults) {
            $this->reCalculateFromOvers(
                [
                    'setValuesToDefaults' => $setValuesToDefaults
                ]
            );
        }
    }

    public function actionDelete($where, $limit = null)
    {
        $remove = $this->getRemoveForActionDeleteDuplicate($where, $limit);
        if ($remove) {
            $this->reCalculateFromOvers(
                [
                    'remove' => $remove
                ]
            );
        }
    }

    public function actionDuplicate($fields, $where, $limit = null, $after = null): array
    {
        $ids = $this->getRemoveForActionDeleteDuplicate($where, $limit);
        if ($ids) {
            $replaces = [];
            foreach ($ids as $id) {
                $replaces[$id] = $fields;
            }
            $duplicate = [
                'ids' => $ids,
                'replaces' => $replaces
            ];

            $added = $this->changeIds['added'];
            $this->reCalculateFromOvers(
                [
                    'duplicate' => $duplicate, 'addAfter' => $after
                ]
            );
            return array_keys(array_diff_key($this->changeIds['added'], $added));
        }
        return [];
    }

    /**
     * @param null|array $data
     * @param null|array $dataList
     * @param null|int $after
     * @return array
     * @throws errorException
     */
    public function actionInsert($data = null, $dataList = null, $after = null): array
    {
        $added = $this->changeIds['added'];
        if ($dataList) {
            $this->reCalculateFromOvers(['add' => $dataList, 'addAfter' => $after]);
        } elseif (!is_null($data) && is_array($data)) {
            $this->reCalculateFromOvers(['add' => [$data], 'addAfter' => $after]);
        }
        return array_keys(array_diff_key($this->changeIds['added'], $added));
    }

    public function actionPin($fields, $where, $limit = null)
    {
        $setFieldPinned = $this->getModifyForActionClear($fields, $where, $limit);

        if ($setFieldPinned) {
            $this->reCalculateFromOvers(
                [
                    'setValuesToPinned' => $setFieldPinned
                ]
            );
        }
    }

    public function actionReorder(array $ids, int $after = 0)
    {
        if (!$this->tableRow['with_order_field']) {
            throw new errorException($this->translate('The table [[%s]] has no n-sorting.', $this->tableRow['name']));
        }
        $this->reCalculateFromOvers(
            [
                'reorder' => $ids,
                'addAfter' => $after
            ]
        );
    }

    public function actionRestore($where, $limit = null)
    {
        $where[] = ['field' => 'is_del', 'operator' => '=', 'value' => true];
        $restore = $this->getRemoveForActionDeleteDuplicate($where, $limit);
        if ($restore) {
            $this->reCalculateFromOvers(
                [
                    'restore' => $restore
                ]
            );
        }
    }

    public function actionSet($params, $where, $limit = null)
    {
        $modify = $this->getModifyForActionSet($params, $where, $limit);
        if ($modify) {
            $this->reCalculateFromOvers(
                [
                    'modify' => $modify
                ]
            );
        }
    }

    protected function getModifyForActionClear($fields, $where, $limit)
    {
        return $this->prepareModify($fields, $where, $limit, true);
    }

    protected function getModifyForActionSet($params, $where, $limit)
    {
        return $this->prepareModify($params, $where, $limit);
    }

    protected function prepareModify($params, $where, $limit, $clear = false)
    {
        $rowParams = [];
        $pParams = [];
        if ($clear) {
            foreach ($params as $f) {
                if (key_exists($f, $this->fields)) {
                    if ($this->fields[$f]['category'] === 'column') {
                        $rowParams[$f] = null;
                    } else {
                        $pParams[$f] = null;
                    }
                }
            }
        } else {
            foreach ($params as $f => $value) {
                if (key_exists($f, $this->fields)) {
                    if ($this->fields[$f]['category'] === 'column') {
                        $rowParams[$f] = $clear ? null : $value;
                    } else {
                        $pParams[$f] = $clear ? null : $value;
                    }
                }
            }
        }

        $modify = [];

        if (!empty($rowParams)) {
            $getParams = ['where' => $where, 'field' => 'id'];
            if ((int)$limit === 1) {
                if ($id = $this->getByParams($getParams, 'field')) {
                    $return = [$id];
                } else {
                    return false;
                }
            } else {
                $return = $this->getByParams($getParams, 'list');
            }

            foreach ($return as $id) {
                $modify[$id] = $rowParams;
            }
        }
        if (!empty($pParams)) {
            $modify ['params'] = $pParams;
        }

        return $modify;
    }
}