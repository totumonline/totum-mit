<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 05.04.17
 * Time: 18:49
 */

namespace totum\tableTypes;

use Closure;
use totum\common\calculates\Calculate;
use totum\common\calculates\CalculateAction;
use totum\common\errorException;
use totum\common\Field;
use totum\common\Lang\RU;
use totum\common\Model;
use totum\common\Totum;
use totum\fieldTypes\File;

abstract class JsonTables extends aTable
{
    protected $deletedIds = [];
    protected $modelConnects;
    protected $filteredIds;


    /**
     * Use in notify in case deep levels recalculates
     *
     * @var array
     */
    protected static $recalcs = [];

    /* Подумать: было бы логично убрать отсюда Cycle и оставить его только для calcs*/

    public function __construct(Totum $Totum, $tableRow, $Cycle = null, $light = false)
    {
        parent::__construct($Totum, $tableRow, null, $light);
    }


    public function getCycle()
    {
        return $this->Cycle;
    }

    /**
     * @param bool $isTableAdding
     */
    public function setIsTableAdding(bool $isTableAdding): void
    {
        $this->isTableAdding = $isTableAdding;
    }

    protected function reCalculate($inVars = [])
    {
        static::$recalcs[] = $this->tableRow['name'];

        //Для вставки в диапазон при активной Сортировке по полю порядок
        if (!empty($inVars['add']) && $this->tableRow['with_order_field'] && !empty($inVars['channel']) && $inVars['channel'] !== 'inner') {
            static::reCalculate(['channel' => $inVars['channel'], 'modify' => $inVars['modify'] ?? []]);
        }

        parent::reCalculate($inVars);
    }

    public function addField($field)
    {
    }

    public function deleteField()
    {
    }

    public function getChildrenIds($id, $parentField, $bfield)
    {
        if ($id) {
            $children = [];
            foreach ($this->tbl['rows'] as $row) {
                if ($bfield === 'id') {
                    $bval = (string)$row['id'];
                } else {
                    $bval = (string)$row[$bfield]['v'];
                }

                if (!array_key_exists($bval, $children)) {
                    $children[$bval] = [];
                }
                if ($parent = (string)$row[$parentField]['v']) {
                    if (!array_key_exists($parent, $children)) {
                        $children[$parent] = [];
                    }
                    $children[$parent][$bval] = &$children[$bval];
                }
            }

            if ($children[$id]) {
                $getChildren = function ($list) use (&$getChildren) {
                    if (!$list) {
                        return $list;
                    }
                    $l = [];
                    foreach ($list as $k => $v) {
                        $l[] = $k;
                        $l = array_merge($l, $getChildren($v));
                    }
                    return $l;
                };
                return array_values($getChildren($children[$id]));
            }
        }
        return [];
    }

    public function isTblUpdated($level = 0, $force = false)
    {
        if ($this->isTableDataChanged || $this->isTableAdding || !key_exists('params', $this->savedTbl)) {
            $this->updated = $this->getUpdatedJson();

            /*Возможно, здесь тоже стоит разнести сохранение и onSaveTable, но логика сложная и можно поломать пересчеты неочевидным образом*/
            if ($this->isOnSaving) {
                if ($this->Cycle) {
                    $this->Cycle->saveTables();
                    $this->updateReceiverTables($level);
                } else {
                    $this->saveTable();
                }
            } else {
                /*Это верхний уровень сохранения пересчетов для этой таблицы*/

                $this->isOnSaving = true;
                $oldTbl = $this->loadedTbl; //Exactly loaded! it's used for calculating changing in all process

                if ($this->Cycle) {
                    $this->Cycle->saveTables();
                    $this->updateReceiverTables($level);
                } else {
                    $this->saveTable();
                }

                foreach ($this->tbl['rows'] as $id => $row) {
                    $oldRow = ($oldTbl['rows'][$id] ?? []);
                    if ($oldRow && (!empty($row['is_del']) && empty($oldRow['is_del']))) {
                        $this->changeIds['deleted'][$id] = null;
                    } elseif (!empty($oldRow) && empty($row['is_del'])) {
                        //Здесь проставляется changed для web (только ли это в web нужно?) - можно облегчить!!!! - может, делать не здесь, а при изменении?
                        if (Calculate::compare('!==', $oldRow, $row, $this->getLangObj())) {
                            foreach ($row as $k => $v) {
                                /*key_exists for $oldRow[$k] не использовать!*/
                                if ($k !== 'n' && Calculate::compare('!==',
                                        ($oldRow[$k] ?? null),
                                        $v,
                                        $this->getLangObj())) {
                                    $this->changeIds['changed'][$id] = $this->changeIds['changed'][$id] ?? [];
                                    $this->changeIds['changed'][$id][$k] = null;
                                }
                            }
                        }
                    }
                }

                $this->loadedTbl['rows'] = $oldTbl['rows'] ?? [];
                $deleted = array_flip(array_keys(array_diff_key(
                    $oldTbl['rows'],
                    $this->tbl['rows']
                )));
                $added = array_flip(array_keys(array_diff_key(
                    $this->tbl['rows'],
                    $oldTbl['rows']
                )));
                $this->changeIds['deleted'] = $this->changeIds['deleted'] + $deleted;
                $this->changeIds['added'] = $this->changeIds['added'] + $added;

                $this->isOnSaving = false;
            }
            return true;
        } else {
            return false;
        }
    }

    public function setDuplicatedTbl($tbl, $updated = null)
    {
        $this->loadDataRow();

        $this->tbl = $tbl;
        $this->indexRows();


        $this->loadedTbl = $this->savedTbl = $this->tbl;
        $this->savedTbl[] = 'changed';

        $this->savedUpdated = $updated ?? $this->savedUpdated;
        $this->updated = $this->getUpdatedJson();
        $this->setIsTableDataChanged(true);
    }

    /**
     * @return bool
     */
    public function isTableAdding(): bool
    {
        return $this->isTableAdding;
    }

    /**
     * @param $ids
     * @return Closure
     * @throws errorException
     */
    protected function getIntervalsfunction($ids)
    {
        $intervals = $this->_getIntervals($ids);
        return function ($id) use ($intervals) {
            foreach ($intervals as $interval) {
                if ($id >= $interval[0] && $id <= $interval) {
                    return true;
                }
            }
            return false;
        };
    }

    protected function _copyTableData(&$table, $settings)
    {
        if ($settings['copy_params'] !== 'none' && $settings['copy_data'] !== 'none') {
            $table['tbl'] = $this->tbl;
            if ($settings['copy_params'] === 'none') {
                unset($table['tbl']['params']);
            }


            if ($settings['copy_data'] === 'none') {
                unset($table['tbl']['rows']);
            } else {
                foreach ($table['tbl']['rows'] as $k => $row) {
                    if (!empty($row['is_del'])) {
                        unset($table['tbl']['rows'][$k]);
                    }
                }
                if ($settings['copy_data'] === 'ids') {
                    $funcIsInInterval = $this->getIntervalsfunction($settings['intervals']);
                    foreach ($table['tbl']['rows'] as $k => $row) {
                        if (!$funcIsInInterval($row['id'])) {
                            unset($table['tbl']['rows'][$k]);
                        }
                    }
                }
            }
        }
    }

    protected function reCalculateRows($calculate, $channel, $isCheck, $modifyCalculated, $isTableAdding, $remove, $restore, $add, $modify, $setValuesToDefaults, $setValuesToPinned, $duplicate, $reorder, $addAfter, $addWithId)
    {
        $Log = $this->calcLog(['recalculate' => 'column']);

        /*****Берем старую таблицу*******/
        $SavedRows = $this->savedTbl['rows'] ?? [];

        /***reorder***/
        if ($reorder) {

            if ($addAfter) {
                $addAfter = (int)$addAfter;
                $newRows = [];
                $reorderIds = array_flip($reorder);

                foreach ($SavedRows as $id => $row) {
                    if ($addAfter === $id) {
                        if (!key_exists($id, $reorderIds)) {
                            $newRows[$id] = $row;
                        }
                        foreach ($reorderIds as $_id => $_) {
                            if (!key_exists($_id, $SavedRows)) continue;
                            $newRows[$_id] = $SavedRows[$_id];
                            $this->changeIds['reorderedIds'][$_id] = 1;
                            $this->changeInOneRecalcIds['reorderedIds'][$_id] = 1;
                        }
                        $addAfter = null;
                    } else {
                        if (key_exists($id, $reorderIds)) continue;
                        $newRows[$id] = $row;
                    }
                }

                if ($addAfter) {
                    throw new errorException($this->translate('Row %s not found', $addAfter));
                }

            } elseif (count($SavedRows) === count($reorder)) {
                $old_order = array_intersect(array_keys($SavedRows), $reorder);
                $reorders = array_combine($old_order, $reorder);
                $newRows = [];
                /*Удаляем несортируемые с начала и с конца*/
                while (array_key_first($reorders) === $reorders[array_key_first($reorders)]) {
                    unset($reorders[array_key_first($reorders)]);
                }
                while (array_key_last($reorders) === $reorders[array_key_last($reorders)]) {
                    unset($reorders[array_key_last($reorders)]);
                }


                foreach ($SavedRows as $id => $row) {
                    if (key_exists($id, $reorders)) {
                        $id = $reorders[$id];
                        $this->changeIds['reorderedIds'][$id] = 1;
                        $this->changeInOneRecalcIds['reorderedIds'][$id] = 1;
                    }
                    $newRows[$id] = $SavedRows[$id];
                }
            } else {
                $newRows = [];
                $reorderIds = array_flip($reorder);
                foreach ($SavedRows as $id => $row) {
                    if (key_exists($id, $reorderIds)) {
                        $id = array_shift($reorder);
                        $this->changeIds['reorderedIds'][$id] = 1;
                        $this->changeInOneRecalcIds['reorderedIds'][$id] = 1;
                    }
                    $newRows[$id] = $SavedRows[$id];
                }
            }

            $SavedRows = $newRows;
            unset($newRows);
            $this->setIsTableDataChanged(true);
            $this->changeIds['reordered'] = true;
        }


        /***insert field list***/
        $insertList = [];
        if ($insertField = $this->fields['insert'] ?? null) {
            if ($insertField['category'] === 'column' && !empty($insertField['code'])) {
                $insertCalcs = new Calculate($insertField['code']);
                $insertList = $insertCalcs->exec(
                    $insertField,
                    ['v' => null],
                    [],
                    [],
                    $this->savedTbl,
                    $this->tbl,
                    $this
                );

                if ($insertCalcs->getError()) {
                    throw new errorException($this->translate('Error processing field insert: [[%s]]',
                        $insertCalcs->getError()));
                }
                if (!is_array($insertList)) {
                    throw new errorException($this->translate('The [[insert]] field should return list - Table [[%s]]',
                        $this->tableRow['title']));
                }

                $insertList = array_filter(
                    $insertList,
                    function ($v) {
                        if (!is_null($v) && $v !== '') {
                            return true;
                        }
                    }
                );
                $type = SORT_STRING;
                if (in_array($this->fields['insert']['type'], ['select', 'tree', 'listRow'])) {
                    $type = SORT_REGULAR;
                }
                if (count(array_unique(
                        $insertList,
                        $type
                    )) !== count($insertList)) {
                    throw new errorException($this->translate('The [[insert]] field should return a list with unique values - Table [[%s]]',
                        $this->tableRow['title']));
                }
            } else {
                unset($insertField);
            }
        }
        /**** delete ****/
        foreach ($SavedRows as $row) {
            $newRow = ['id' => $row['id']];

            if (!empty($row['_E'])) {
                $newRow['_E'] = true;
            }
            if (!empty($insertField)) {
                if (key_exists('c', $row['insert'])) {
                    $c = $row['insert']['c'];
                } else {
                    $c = $row['insert']['v'];
                }

                if (!is_null($c)) {
                    $isInInsert = false;
                    foreach ($insertList as $k => $inc) {
                        if (!static::isDifferentFieldData($inc, $c)) {
                            unset($insertList[$k]);
                            $newRow['insert']['c'] = $c;
                            $isInInsert = true;
                            break;
                        }
                    }

                    /*Если строка в  insert не существует*/
                    if (!$isInInsert) {
                        if (empty($newRow['_E'])) {
                            $this->changeIds['deleted'][$row['id']] = null;
                            $this->changeInOneRecalcIds['deleted'][$row['id']] = null;
                            continue;
                        } else {
                            if (empty($row['InsDel'])) {
                                $this->setIsTableDataChanged(true);
                                $this->changeIds['changed'][$row['id']] = null;
                            }
                            $newRow['InsDel'] = true;
                        }
                    }
                    $newRow['insert']['c'] = $c;
                }
            }

            if (!empty($row['is_del']) || $isDeletedRow = ($remove && in_array($row['id'], $remove))) {
                if ($isDeletedRow ?? null) {
                    $this->setIsTableDataChanged(true);
                    $aLogDelete = function ($id) use ($channel) {
                        if ($this->tableRow['type'] !== 'tmp'
                            && (in_array($channel, ['web', 'xml']) || $this->recalculateWithALog)
                        ) {
                            $this->Totum->totumActionsLogger()->delete(
                                $this->tableRow['id'],
                                $this->getCycle()?->getId(),
                                $id,
                                $this->recalculateWithALog ?
                                    (is_bool($this->recalculateWithALog) ? $this->translate('script') : $this->recalculateWithALog) : null
                            );
                        }
                    };
                    switch ($this->getDeleteMode()) {
                        case 'none':
                            if ($channel !== 'inner') {
                                throw new errorException($this->translate('You are not allowed to delete from this table'));
                            }
                        // no break
                        case 'delete':
                            $aLogDelete($row['id']);
                            $this->changeIds['deleted'][$row['id']] = null;
                            $this->changeInOneRecalcIds['deleted'][$row['id']] = null;
                            continue 2;
                        case 'hide':
                            $newRow['is_del'] = true;
                            $this->changeIds['deleted'][$row['id']] = null;
                            $this->changeInOneRecalcIds['deleted'][$row['id']] = null;
                            $aLogDelete($row['id']);
                            break;
                    }
                } elseif (in_array($row['id'], $restore)) {
                    $this->setIsTableDataChanged(true);
                    $this->changeIds['restored'][$row['id']] = null;
                    $this->changeInOneRecalcIds['restored'][$row['id']] = null;
                    if ($this->tableRow['type'] !== 'tmp'
                        && (in_array($channel, ['web', 'xml']) || $this->recalculateWithALog)
                    ) {
                        $this->Totum->totumActionsLogger()->restore(
                            $this->tableRow['id'],
                            $this->getCycle() ? $this->getCycle()->getId() : null,
                            $row['id']
                        );
                    }
                } else {
                    $newRow['is_del'] = true;
                }
            }
            $this->tbl['rows'][$row['id']] = $newRow;
        }

        if (!empty($insertList)) {
            foreach ($insertList as $insVal) {
                $newRow = ['id' => ++$this->tbl['nextId']];
                $newRow['insert']['c'] = $insVal;
                $this->tbl['rows'][$newRow['id']] = $newRow;
                $this->changeIds['added'][$newRow['id']] = null;
                $this->changeInOneRecalcIds['added'][$newRow['id']] = null;
                $this->setIsTableDataChanged(true);
            }
        }
        /***insert***/


        $orderDuplicatesAfter = [];
        $duplicatedIds = [];
        foreach (($duplicate['ids'] ?? []) as $id) {
            if ($row = ($this->savedTbl['rows'][$id])) {
                $newRow = [];
                $newRow['id'] = ++$this->tbl['nextId'];
                $newRow['_E'] = true;

                /******Расчет дублированной строки для  JSON-таблиц********/
                foreach ($this->sortedFields['column'] as $field) {
                    if (array_key_exists($field['name'], ($duplicate['replaces'][$row['id']] ?? []))) {
                        $modify[$newRow['id']][$field['name']] = $duplicate['replaces'][$row['id']][$field['name']];
                        continue;
                    }
                    if (!empty($field['copyOnDuplicate'])) {
                        if (!empty($field['code']) && empty($field['codeOnlyInAdd']) && empty($row[$field['name']]['h'])) {
                            continue;
                        }
                        $modify[$newRow['id']][$field['name']] = $row[$field['name']]['v'];
                        continue;
                    }
                    if (is_null($field['default'] ?? null) && empty($field['code']) && $field['type'] !== 'comments') {
                        $modify[$newRow['id']][$field['name']] = $row[$field['name']]['v'];
                    }
                }
                /****** / Расчет дублированной строки для  JSON-таблиц********/
                if (!empty($this->tableRow['with_order_field'])) {
                    $orderDuplicatesAfter[$addAfter ?? $duplicate['ids'][0]][$newRow['id']] = $newRow;
                } else {
                    $this->tbl['rows'][$newRow['id']] = $newRow;
                }
                $duplicatedIds[$newRow['id']] = $row['id'];
                $this->changeIds['added'][$newRow['id']] = null;
                $this->changeInOneRecalcIds['added'][$newRow['id']] = null;
                $this->changeIds['duplicated'][$id] = $newRow['id'];
                $this->changeInOneRecalcIds['duplicated'][$id] = $newRow['id'];
                $this->setIsTableDataChanged(true);
            } else {
                throw new errorException($this->translate('Row %s not found', $id));
            }
        }


        if (!empty($add)) {
            $getId = function ($addRow) use ($addWithId, $isCheck, $channel) {
                if ($addWithId && !empty($addRow['id']) && ($id = (int)$addRow['id']) > 0) {
                    if ($this->tbl['nextId'] <= $id) {
                        $this->tbl['nextId'] = $id;
                    } elseif (array_key_exists($id, $this->tbl['rows'])) {
                        throw new errorException($this->translate('The row with id %s in the table already exists. Cannot be added again',
                            $id));
                    }
                } else {
                    if ($isCheck && $channel === 'web') {
                        return '';
                    }
                    $id = ++$this->tbl['nextId'];
                }
                return $id;
            };

            if ($this->tableRow['with_order_field']
                &&
                (!is_null($addAfter) ||
                    ($channel !== 'inner' && ($this->issetActiveFilters($channel) || $this->webIdInterval) &&
                        ($filteredIds = $this->loadFilteredRows($channel, $this->webIdInterval))))) {
                if (!is_null($addAfter)) {
                    $after = $addAfter;
                } else {
                    $after = $filteredIds[count($filteredIds) - 1];
                }

                if ($after && !key_exists(
                        $after,
                        $SavedRows
                    )) {
                    throw new errorException($this->translate('Row %s not found', $after));
                }

                foreach (($add ?? []) as $addRow) {
                    $newRow = ['id' => $getId($addRow), '_E' => true];

                    $orderDuplicatesAfter[$after][$newRow['id']] = $newRow;
                    $modify[$newRow['id']] = $addRow;
                    $this->setIsTableDataChanged(true);
                }
            } else {
                foreach (($add ?? []) as $addRow) {
                    $newRow = ['id' => $getId($addRow), '_E' => true];
                    $this->tbl['rows'][$newRow['id']] = $newRow;
                    $modify[$newRow['id']] = $addRow;
                    $this->setIsTableDataChanged(true);
                }
            }
            if ($channel === 'web' && $isCheck === true) {
                $this->tbl['insertedId'] = $newRow['id'];
            }
            $this->changeIds['added'][$newRow['id']] = null;
            $this->changeInOneRecalcIds['added'][$newRow['id']] = null;
        }


        if (!empty($orderDuplicatesAfter)) {
            $newRows = [];

            /*Вставка в начало $addAfter=0*/
            if (!empty($orderDuplicatesAfter[0])) {
                foreach ($orderDuplicatesAfter[0] as $_id => $_row) {
                    $newRows[$_id] = $_row;
                }
            }

            foreach ($this->tbl['rows'] as $id => $row) {
                $newRows[$id] = $row;
                if (!empty($orderDuplicatesAfter[$id])) {
                    foreach ($orderDuplicatesAfter[$id] as $_id => $_row) {
                        $newRows[$_id] = $_row;
                    }
                }
            }
            $this->tbl['rows'] = $newRows;
            $this->setIsTableDataChanged(true);
        }

        if (!empty($this->tableRow['with_order_field'])) {
            $i = 0;
            foreach ($this->tbl['rows'] as &$thisRow) {
                $thisRow['n'] = ++$i;
            }
            unset($thisRow, $i);
        }


        /********Пересчет строчной части*************/

        $modifyRowField = function ($column, &$thisRow, $oldRow) use ($modify, $modifyCalculated, $channel, $isCheck, $setValuesToDefaults, $setValuesToPinned) {
            if (!empty($thisRow['is_del'])) {
                $thisRow[$column['name']] = $oldRow[$column['name']] ?? null;
                return;
            }

            $modifyRow = $modify[$thisRow['id']] ?? [];
            $setValuesToDefaultsRow = $setValuesToDefaults[$thisRow['id']] ?? [];
            $setValuesToPinnedRow = $setValuesToPinned[$thisRow['id']] ?? [];


            $newVal = $modifyRow[$column['name']] ?? null;
            $oldVal = $oldRow[$column['name']] ?? null;

            $Field = Field::init($column, $this);
            if ($changedFlag = $Field->getModifyFlag(
                array_key_exists($column['name'], $modifyRow),
                $newVal,
                $oldVal,
                array_key_exists($column['name'], $setValuesToDefaultsRow),
                array_key_exists($column['name'], $setValuesToPinnedRow),
                $modifyCalculated
            )
            ) {
                if ($changedFlag !== false) {
                    $thisRow['_E'] = true;
                }
            }

            $thisRow[$column['name']] = $Field->modify(
                $channel,
                $changedFlag,
                $newVal,
                $oldRow,
                $thisRow,
                $this->savedTbl,
                $this->tbl,
                $isCheck
            );

            $this->checkIsModified($oldVal, $thisRow[$column['name']]);

            $this->addToALogModify(
                $Field,
                $channel,
                $this->tbl,
                $thisRow,
                $thisRow['id'],
                $modifyRow,
                $setValuesToDefaultsRow,
                $setValuesToPinnedRow,
                $oldVal
            );
        };
        $addRowField = function ($column, &$thisRow) use ($modify, $modifyCalculated, $channel, $isCheck, $duplicatedIds) {
            $Field = Field::init($column, $this);

            $newVal = $modify[$thisRow['id']][$column['name']] ?? null;
            $_channel = $channel;

            if (!key_exists(
                    $column['name'],
                    $modify[$thisRow['id']] ?? []
                ) && $this->insertRowSetData && key_exists(
                    $column['name'],
                    $this->insertRowSetData
                )) {
                $_channel = 'webInsertRow';
                $newVal = $this->insertRowSetData[$column['name']];
            }

            $thisRow[$column['name']] = $Field->add(
                $_channel,
                $newVal,
                $thisRow,
                $this->savedTbl,
                $this->tbl,
                $isCheck,
                ['duplicatedId' => $duplicatedIds[$thisRow['id']] ?? 0]
            );
            if (!$isCheck) {
                $this->addToALogAdd($Field,
                    $channel,
                    $this->tbl,
                    $thisRow,
                    $this->insertRowSetData ?? $modify[$thisRow['id']] ?? []);
            }
            unset($this->insertRowSetData[$column['name']]);
        };

        $calculateRowFooterField = function ($footerField) use ($modify, $isTableAdding, $channel, $modifyCalculated, $isCheck, $setValuesToDefaults, $setValuesToPinned) {
            if ($isTableAdding) {
                $this->tbl['params'][$footerField['name']] = Field::init($footerField, $this)->add(
                    $channel,
                    $modify['params'][$footerField['name']] ?? null,
                    $this->tbl['params'],
                    $this->savedTbl,
                    $this->tbl
                );
            } else {
                $newVal = $modify['params'][$footerField['name']] ?? null;
                $oldVal = $this->savedTbl['params'][$footerField['name']] ?? null;

                $Field = Field::init($footerField, $this);

                $changedFlag = $Field->getModifyFlag(
                    array_key_exists(
                        $footerField['name'],
                        $modify['params'] ?? []
                    ),
                    $newVal,
                    $oldVal,
                    array_key_exists($footerField['name'], $setValuesToDefaults['params'] ?? []),
                    array_key_exists($footerField['name'], $setValuesToPinned['params'] ?? []),
                    $modifyCalculated
                );


                $this->tbl['params'][$footerField['name']] = $Field->modify(
                    $channel,
                    $changedFlag,
                    $newVal,
                    $this->savedTbl['params'] ?? [],
                    $this->tbl['params'],
                    $this->savedTbl,
                    $this->tbl,
                    $isCheck
                );

                $this->checkIsModified($oldVal, $this->tbl['params'][$footerField['name']]);

                $this->addToALogModify(
                    $Field,
                    $channel,
                    $this->tbl,
                    $this->tbl['params'],
                    null,
                    $modify['params'] ?? [],
                    $setValuesToDefaults['params'] ?? [],
                    $setValuesToPinned['params'] ?? [],
                    $oldVal
                );
            }
        };


        if (key_exists('tree', $this->fields) && !empty($this->fields['tree']['treeViewCalc'])) {
            $Field = Field::init($this->fields['tree'], $this);

            foreach ($this->tbl['rows'] as $row) {
                $savedRow = $this->savedTbl['rows'][$row['id']] ?? [];
                if (($row['tree']['v'] ?? $savedRow['tree']['v'] ?? null) === null) {
                    $level = 0;
                } else {
                    $level = $Field->getLevelValue(
                        $savedRow['tree']['v'] ?? null,
                        $savedRow,
                        $this->tbl
                    );
                }
                $sortData[$level][] = $row;
            }
            if ($this->fields['tree']['treeViewCalc'] === 'endtoroot') {
                krsort($sortData);
            } else {
                ksort($sortData);
            }
            $newModifyedRows = [];
            foreach ($sortData as $rows) {
                foreach ($rows as $row) {
                    $newModifyedRows[$row['id']] = $row;
                }
            }
            $this->tbl['rows'] = $newModifyedRows;
            unset($newModifyedRows);
        }


        if (!empty($this->tableRow['calculate_by_columns'])) {
            $footerColumns = $this->getFooterColumns($this->sortedFields['footer'] ?? []);

            foreach (($this->sortedFields['column'] ?? []) as $column) {
                $PrevRow = [];

                foreach ($this->tbl['rows'] as &$thisRow) {
                    $thisRow['PrevRow'] = $PrevRow;
                    if ($oldRow = ($this->savedTbl['rows'][$thisRow['id']] ?? null)) {
                        $modifyRowField($column, $thisRow, $oldRow);
                    } else {
                        $addRowField($column, $thisRow);
                    }
                    unset($thisRow['PrevRow']);
                    $PrevRow = $thisRow;
                }

                unset($thisRow, $PrevRow);


                foreach (($footerColumns[$column['name']] ?? []) as $footerField) {
                    $calculateRowFooterField($footerField);
                }
            }
            $this->calcLog($Log, 'result', 'done');

            $Log = $this->calcLog(['recalculate' => 'footer']);
            foreach (($footerColumns[''] ?? []) as $footerField) {
                $calculateRowFooterField($footerField);
            }

            $this->calcLog($Log, 'result', 'done');
        } else {
            $footerColumnRows = [];
            $footerRows = [];
            foreach ($this->sortedFields['footer'] as $k => $f) {
                if (!empty($f['column'])) {
                    $footerColumnRows[$k] = $f;
                } else {
                    $footerRows[$k] = $f;
                }
            }

            $PrevRow = [];
            foreach ($this->tbl['rows'] as &$thisRow) {
                $thisRow['PrevRow'] = $PrevRow;
                foreach (($this->sortedFields['column'] ?? []) as $column) {
                    if ($oldRow = ($this->savedTbl['rows'][$thisRow['id']] ?? null)) {
                        $modifyRowField($column, $thisRow, $oldRow);
                    } else {
                        $addRowField($column, $thisRow);
                    }
                }
                unset($thisRow['PrevRow']);
                $PrevRow = $thisRow;
            }
            unset($thisRow);

            foreach ($footerColumnRows as $footerField) {
                $calculateRowFooterField($footerField);
            }
            $this->calcLog($Log, 'result', 'done');
            $Log = $this->calcLog(['recalculate' => 'footer']);
            foreach ($footerRows as $footerField) {
                $calculateRowFooterField($footerField);
            }
            $this->calcLog($Log, 'result', 'done');
        }
        if (key_exists('insertedId', $this->tbl)) {
            $this->tbl['rowInserted'] = $this->tbl['rows'][$this->tbl['insertedId']];
        }

        if (key_exists('tree', $this->fields) && !empty($this->fields['tree']['treeViewCalc'])) {
            if ($this->tableRow['order_field'] === 'n') {
                $ns = array_column($this->tbl['rows'], 'n');
                array_multisort($ns, $this->tbl['rows']);
                $this->tbl['rows'] = array_combine(array_column($this->tbl['rows'], 'id'), $this->tbl['rows']);
            } else {
                ksort($this->tbl['rows']);
            }
        }
    }

    protected function loadRowsByIds(array $ids)
    {
        return array_intersect_key($this->tbl['rows'], $ids);
    }

    protected function getPreparedTbl()
    {
        $tbl = $this->getTblForSave();
        if (!empty($this->tableRow['deleting']) && $this->tableRow['deleting'] === 'delete') {
            foreach ($tbl as $id => $row) {
                if (!empty($row['is_del'])) {
                    unset($tbl[$id]);
                }
            }
        }
        $tbl['rows'] = array_values($tbl['rows']);
        return json_encode($tbl, JSON_UNESCAPED_UNICODE);
    }

    protected function indexRows()
    {
        $rows = [];
        foreach (($this->tbl['rows'] ?? []) as $row) {
            $rows[$row['id']] = $row;
        }
        $this->tbl['rows'] = $rows;
    }

    protected function loadRowsByParams($params, $order = null, $offset = 0, $limit = null)
    {
        return $this->getByParams(
            ['where' => $params, 'field' => 'id', 'order' => $order, 'offset' => $offset, 'limit' => $limit],
            'list'
        );
    }

    protected function onSaveTable($tbl, $loadedTbl)
    {
        /*Actions*/

        $Log = $this->calcLog(['name' => 'ACTIONS', 'table' => $this]);

        //При добавлении таблицы
        if ($this->isTableAdding) {
            $this->isTableAdding = false;
            if ($fieldsWithActionOnAdd = $this->getFieldsForAction('Add', 'param')) {
                foreach ($fieldsWithActionOnAdd as $field) {
                    Field::init($field, $this)->action(
                        null,
                        $tbl['params'],
                        null,
                        $tbl,
                        'add'
                    );
                }
            }

            $ColumnFootersOnAdd = [];
            $CommonFootersOnAdd = [];
            if ($fieldsWithActionOnAdd = $this->getFieldsForAction('Add', 'footer')) {
                foreach ($fieldsWithActionOnAdd as $field) {
                    if (!empty($field['column'])) {
                        $ColumnFootersOnAdd[$field['column']][] = $field;
                    } else {
                        $CommonFootersOnAdd[] = $field;
                    }
                }
            }
            if ($this->getTableRow()['calculate_by_columns']) {
                foreach ($this->sortedFields['column'] as $field) {
                    if (!empty($field['CodeActionOnAdd'])) {
                        foreach ($tbl['rows'] ?? [] as $row) {
                            Field::init($field, $this)->action(
                                null,
                                $row,
                                null,
                                $tbl,
                                'add'
                            );
                        }
                    }
                    foreach ($ColumnFootersOnAdd[$field['name']] ?? [] as $_field) {
                        Field::init($_field, $this)->action(
                            null,
                            $tbl['params'],
                            null,
                            $tbl,
                            'add'
                        );
                    }
                }
            } else {
                foreach ($this->sortedFields['column'] as $field) {
                    if (!empty($field['CodeActionOnAdd'])) {
                        foreach ($tbl['rows'] ?? [] as $row) {
                            Field::init($field, $this)->action(
                                null,
                                $row,
                                null,
                                $tbl,
                                'add'
                            );
                        }
                    }
                }
                foreach ($this->sortedFields['column'] as $field) {
                    foreach ($ColumnFootersOnAdd[$field['name']] ?? [] as $_field) {
                        Field::init($_field, $this)->action(
                            null,
                            $tbl['params'],
                            null,
                            $tbl,
                            'add'
                        );
                    }
                }
            }

            foreach ($CommonFootersOnAdd as $field) {
                Field::init($field, $this)->action(
                    null,
                    $tbl['params'],
                    null,
                    $tbl,
                    'add'
                );
            }
        } else {
            //При изменении таблицы


            $codeAction = $this->tableRow['default_action'] ?? null;
            if ($codeAction && !preg_match('/^\s*=\s*:\s*$/', $codeAction)) {
                $this->execDefaultTableAction($codeAction, $loadedTbl, $tbl);
            }


            $checkAndChange = function ($field) use ($tbl, $loadedTbl) {
                if (key_exists($field['name'], $loadedTbl['params'] ?? []) && Calculate::compare(
                        '!==',
                        $loadedTbl['params'][$field['name']]['v'],
                        $tbl['params'][$field['name']]['v'],
                        $this->getLangObj()
                    )) {
                    Field::init($field, $this)->action(
                        $loadedTbl['params'],
                        $tbl['params'],
                        $loadedTbl,
                        $tbl,
                        'change'
                    );
                }
            };


            if ($fieldsWithActionOnChange = $this->getFieldsForAction('Change', 'param')) {
                foreach ($fieldsWithActionOnChange as $field) {
                    $checkAndChange($field);
                }
            }


            $ColumnFootersOnChange = [];
            $CommonFootersOnChange = [];
            foreach ($this->getFieldsForAction('Change', 'footer') as $field) {
                if (!empty($field['column'])) {
                    $ColumnFootersOnChange[$field['column']][] = $field;
                } else {
                    $CommonFootersOnChange[] = $field;
                }
            }
            $deletedRows = [];
            foreach ($tbl['rows'] as $row) {
                if (!empty($row['is_del'])
                    && (
                        !array_key_exists($row['id'], $loadedTbl['rows'])
                        || empty($loadedTbl['rows'][$row['id']]['is_del'])
                    )
                ) {
                    $deletedRows[$row['id']] = $row;
                }
            }
            foreach ($loadedTbl['rows'] ?? [] as $row) {
                if (empty($row['is_del'])
                    && (
                        !array_key_exists($row['id'], $tbl['rows'])
                        || !empty($tbl['rows'][$row['id']]['is_del'])
                    )
                ) {
                    $deletedRows[$row['id']] = $row;
                }
            }


            foreach ($this->sortedFields['column'] as $field) {
                foreach ($tbl['rows'] as $row) {
                    $actionIt = false;
                    if (empty($loadedTbl['rows'][$row['id']])) {
                        if (!empty($field['CodeActionOnAdd'])) {
                            $actionIt = 'add';
                        }
                    } elseif (!empty($field['CodeActionOnChange']) && key_exists(
                            $field['name'],
                            $loadedTbl['rows'][$row['id']]
                        ) &&
                        is_array($loadedTbl['rows'][$row['id']][$field['name']]) && key_exists('v',
                            $loadedTbl['rows'][$row['id']][$field['name']])) {
                        if (Calculate::compare(
                            '!==',
                            $loadedTbl['rows'][$row['id']][$field['name']]['v'],
                            $row[$field['name']]['v'],
                            $this->getLangObj()
                        )) {
                            $actionIt = 'change';
                        }
                    }

                    if ($actionIt) {
                        Field::init($field, $this)->action(
                            $loadedTbl['rows'][$row['id']] ?? null,
                            $row,
                            $loadedTbl,
                            $tbl,
                            $actionIt
                        );
                    }
                }
                foreach ($deletedRows as $Oldrow) {
                    if (!empty($field['CodeActionOnDelete'])) {
                        Field::init($field, $this)->action(
                            $Oldrow,
                            [],
                            $loadedTbl,
                            $tbl,
                            'delete'
                        );
                    }
                    if ($field['type'] === 'file' && $this->getDeleteMode() !== 'hide') {
                        File::deleteFilesOnCommit(
                            Field::init($field,
                                $this)->filterDuplicatedFiled(
                                $Oldrow[$field['name']]['v'] ?? [],
                                $Oldrow['id']
                            )
                            ,
                            $this->getTotum()->getConfig());
                    }
                }

                foreach ($ColumnFootersOnChange[$field['name']] ?? [] as $_field) {
                    $checkAndChange($_field);
                }
            }

            foreach ($CommonFootersOnChange as $field) {
                $checkAndChange($field);
            }
        }
        $this->calcLog($Log, 'result', 'done');
    }

    protected function getNewTblForRecalc()
    {
        return [
            'nextId' => $this->tbl['nextId'] ?? 0,
            'rows' => [],
            'params' => []
        ];
    }


    public function getUpdated()
    {
        return $this->dataRow['updated'] ?? '';
    }

    protected function onDeleteTable()
    {
        //TODO сделать обработку codeActionOnDelete при удалении таблиц
    }

    protected function updateReceiverTables($level = 0)
    {
    }

    protected function getByParamsFromRows($params, $returnType, $sectionReplaces)
    {
        $array = $this->tbl['rows'] ?? [];


        $isNumericField = function ($field) {
            return (in_array(
                $field,
                Model::serviceFields
            ) || $this->fields[$field]['type'] === 'numeric' ? 'numeric' : 'text');
        };

        /*order*/
        if (!empty($params['order'])) {
            $orders = [];
            foreach ($params['order'] as $of) {
                $field = $of['field'];
                $AscDesc = $of['ad'] === 'desc' ? -1 : 1;

                if (!array_key_exists($field, $this->sortedFields['column']) && !Model::isServiceField($field)) {
                    throw new errorException($this->translate('The [[%s]] field in the rows part of table [[%s]] does not exist',
                        [$field, $this->tableRow['name']]));
                }
                $orders[$field] = ['orderNumeric' => $isNumericField($field), 'acsDesc' => $AscDesc];
            }

            $fOrdering = function ($row1, $row2) use ($orders) {
                $o = 0;
                foreach ($orders as $k => $ord) {
                    if (!Model::isServiceField($k)) {
                        $row1[$k] = $row1[$k]['v'] ?? null;
                        $row2[$k] = $row2[$k]['v'] ?? null;
                    } else {
                        $row1[$k] = $row1[$k] ?? null;
                        $row2[$k] = $row2[$k] ?? null;
                    }
                    if ($row1[$k] !== $row2[$k]) {
                        $o = $ord['acsDesc'] * (Calculate::compare('>',
                                $row1[$k],
                                $row2[$k],
                                $this->getLangObj()) ? 1 : -1);
                    }
                    if ($o !== 0) {
                        return $o;
                    }
                }
            };
        }


        $where = [];
        $isDelInFields = (key_exists(
                'where',
                $params
            ) && count($params['where']) === 1 && key_exists('field',
                $params['where'][0]) && $params['where'][0]['field'] === 'id' && $params['where'][0]['operator'] === '=');

        if (isset($params['where'])) {

            $checkFieldAndValue = function (string $field, $value) {
                if (!array_key_exists($field, $this->sortedFields['column']) && !Model::isServiceField($field)
                ) {
                    throw new errorException($this->translate('The [[%s]] field is not found in the [[%s]] table.',
                        [$field, $this->tableRow['title']]));
                }
            };

            /**
             * @param $_level
             * @return array[ string where, array $params ]
             * @throws errorException
             */
            $getWhereForlevel = function ($_level) use ($checkFieldAndValue, &$getWhereForlevel): array {
                $type = $_level['type'];
                $params = [];
                unset($_level['type']);
                $whereConds = [];
                foreach ($_level as $cond) {
                    if (key_exists('operator', $cond)) {
                        if (($cond['right']['type'] ?? '') !== 'fieldName') {
                            list($_cond, $_params) = $this->processFieldWhere(
                                $cond['left']['value'],
                                $cond['operator'],
                                $cond['right']['value']
                            );
                            if ($_cond) {
                                $_cond = '(' . implode(' AND ', $_cond) . ')';
                                array_push($params, ...$_params);
                                $whereConds[] = $_cond;
                            }
                        } else {
                            if ($cond['operator'] === '=') {
                                throw new errorException('If you matching fieldName by fieldName use operator ==. Operator = with includes is not available');
                            }
                            throw new errorException('select by matching DB fields is not done yet');
                        }
                    } else {
                        list($_cond, $_params) = $getWhereForlevel($cond);
                        if ($_cond) {
                            $whereConds[] = $_cond;
                            array_push($params, ...$_params);
                        }
                    }

                }
                $type = match ($type) {
                    '||' => ' OR ',
                    '&&' => ' AND '
                };
                return ['(' . implode($type, $whereConds) . ')', $params];
            };

            $getCheckNeededRow = function ($qrow): callable {
                $checkQrowLevel = function ($row, $_level) use (&$checkQrowLevel): bool {
                    $type = $_level['type'];
                    unset($_level['type']);
                    foreach ($_level as $cond) {
                        if (key_exists('operator', $cond)) {
                            if (($cond['right']['type'] ?? '') !== 'fieldName') {
                                $isTrue = Calculate::compare($cond['operator'],
                                    Model::isServiceField($cond['left']['value']) ? $row[$cond['left']['value']] : $row[$cond['left']['value']]['v'],
                                    $cond['right']['value'],
                                    $this->getLangObj());
                            } else {
                                throw new errorException('select by matching DB fields is not done yet');
                            }
                        } else {
                            $isTrue = $checkQrowLevel($row, $cond);
                        }
                        if ($type === '&&') {
                            if (!$isTrue) {
                                return false;
                            }
                        } else {
                            if ($isTrue) {
                                return true;
                            }
                        }
                    }
                    if ($type === '&&') {
                        return true;
                    } else {
                        return false;
                    }
                };
                return function ($row) use ($qrow, $checkQrowLevel) {
                    return $checkQrowLevel($row, $qrow);
                };
            };

            foreach ($params['where'] as $wI) {


                if (key_exists('qrow', $wI)) {
                    $where[] = $getCheckNeededRow($wI['qrow']);
                } else {
                    $field = $wI['field'];
                    $operator = $wI['operator'];
                    $value = $wI['value'];


                    if ((array)$value === ['*ALL*']) {
                        continue;
                    }

                    $checkFieldAndValue($field, $value);

                    if ($field === 'id') {
                        switch ($operator) {
                            case '=':
                                $value = (array)$value;
                                foreach ($value as &$val) {
                                    if(is_array($val)){
                                        throw new errorException($this->translate('An invalid value for id filtering was passed to the select function.'));
                                    }
                                    $val = strval($val);
                                }
                                unset($val);
                                $array = array_intersect_key($array, array_flip(array_unique($value)));

                                continue 2;
                            case '!=':
                                $value = (array)$value;
                                foreach ($value as &$val) {
                                    $val = strval($val);
                                }
                                unset($val);

                                $array = array_diff_key($array, array_flip(array_unique($value)));
                                continue 2;
                        }

                    } elseif ($field === 'is_del') {
                        $isDelInFields = true;
                    }
                    $where[] = [
                        'field' => $field,
                        'isArray' => !in_array($field, Model::serviceFields),
                        'operator' => $operator,
                        'val' => $value];
                }
            }
        }

        $offset = ($params['offset']) ?? 0;
        if (!(ctype_digit(strval($offset)))) {
            throw new errorException($this->translate('The %s parameter must be a number.', 'offset'));
        }
        $offset = (int)$offset;

        if ($returnType === 'field' || $returnType === 'row') {
            if (isset($fOrdering)) {
                usort($array, $fOrdering);
            }

            $keyFields = array_flip($params['field']);

            foreach ($array as $row) {
                if (!empty($row['is_del'])) {
                    if (!$isDelInFields) {
                        continue;
                    }
                } else {
                    $row['is_del'] = $row['is_del'] ?? false;
                }
                if (!array_intersect_key($keyFields, $row)) {
                    continue;
                }

                $checkedTrue = true;
                foreach ($where as $w) {
                    if (is_callable($w)) {
                        if (!$w($row)) {
                            $checkedTrue = false;
                            break;
                        }
                    } else {
                        $a = $row[$w['field']] ?? null;
                        if ($w['isArray']) {
                            $a = $a['v'] ?? null;
                        }
                        if (!Calculate::compare($w['operator'], $a, $w['val'], $this->getLangObj())) {
                            $checkedTrue = false;
                            break;
                        }
                    }
                }

                if ($checkedTrue) {
                    if ($offset) {
                        $offset--;
                        continue;
                    }
                    if ($returnType === 'row') {
                        return $sectionReplaces($row);
                    } else {
                        return $sectionReplaces($row)[$params['field'][0]];
                    }
                }
            }
            return null;
        } else {


            $limit = ($params['limit']) ?? '';
            if ($limit !== '') {
                if (!(ctype_digit(strval($limit)))) {
                    throw new errorException($this->translate('The %s parameter must be a number.', 'limit'));
                }
                $limit = (int)$limit;
            }

            if ($limit !== '' || $offset !== 0) {
                if (isset($fOrdering)) {
                    uasort($array, $fOrdering);
                    $fOrdering = null;
                }
            }


            $list = [];
            foreach ($array as $row) {
                if ($returnType !== 'rows' && !key_exists($params['field'][0], $row)) {
                    continue;
                }
                if (!empty($row['is_del'])) {
                    if (!$isDelInFields) {
                        continue;
                    }
                } else {
                    $row['is_del'] = $row['is_del'] ?? false;
                }

                foreach ($where as $w) {
                    if (is_callable($w)) {
                        if (!$w($row)) {
                            continue 2;
                        }
                    } else {
                        $a = $row[$w['field']] ?? null;
                        if ($w['isArray']) {
                            $a = $a['v'] ?? null;
                        }

                        if (!Calculate::compare($w['operator'], $a, $w['val'], $this->getLangObj())) {
                            continue 2;
                        }
                    }
                }

                if ($offset > 0) {
                    $offset--;
                    continue;
                }

                if ($returnType === 'rows') {
                    if ($params['field'] === ['__all__']) {
                        $list[] = $row;
                    } else {
                        $return = [];
                        if (!empty($params['with__sectionFunction'])) {
                            foreach ($params['field'] as $f) {
                                if (array_key_exists($f, $row)) {
                                    $return[$f] = Model::isServiceField($f) ? $row[$f] : $row[$f]['v'];
                                }
                            }
                            $return['__sectionFunction'] = function () use ($sectionReplaces, $row, $params) {
                                return $sectionReplaces($row)[$params['sfield'][0]] ?? null;
                            };
                        } else {
                            $return = $sectionReplaces($row);
                        }

                        $list[] = array_merge($row, ['_VAL' => $return]);
                    }
                } else {
                    $val = $sectionReplaces($row)[$params['field'][0]];

                    /***Фиг его значет зачем это - либо для чисел, либо для null. И почему только для selectList тоже не ясно**/
                    if (!is_bool($val) && !is_array($val)) {
                        $val .= '';
                    }
                    $list[$row['id']] = array_merge($row, ['_VAL' => $val]);
                }

                if ($limit !== '' && count($list) === $limit) {
                    break;
                }
            }


            if (isset($fOrdering)) {
                uasort($list, $fOrdering);
            }

            $lst = [];


            switch ($returnType) {
                case 'list':
                case 'rows':
                    if ($params['field'] === ['__all__']) {
                        return $list;
                    }

                    foreach ($list as $v) {
                        $lst[] = $v['_VAL'];
                    }
                    break;
            }
            return $lst;
        }
    }
}
