<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 05.04.17
 * Time: 18:49
 */

namespace totum\tableTypes;

use totum\common\calculates\Calculate;
use totum\common\calculates\CalculateAction;
use totum\common\errorException;
use totum\common\Field;
use totum\common\Model;
use totum\common\Totum;
use totum\fieldTypes\File;

abstract class JsonTables extends aTable
{
    protected $deletedIds = [];
    protected $modelConnects;
    protected $reordered = false;
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

    public function refresh_rows()
    {
        throw new errorException('Для расчетных таблиц не предусмотрено построчное обновление. Они пересчитываются целиком');
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
            //DOTO упростить функцию - выкинуть лишний перебор
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
        if ($this->isTableDataChanged || $this->isTableAdding) {
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

                if ($this->Cycle) {
                    $this->Cycle->saveTables();
                    $this->updateReceiverTables($level);
                } else {
                    $this->saveTable();
                }


                foreach ($this->tbl['rows'] as $id => $row) {
                    $oldRow = ($this->loadedTbl['rows'][$id] ?? []);
                    if ($oldRow && (!empty($row['is_del']) && empty($oldRow['is_del']))) {
                        $this->changeIds['deleted'][$id] = null;
                    } elseif (!empty($oldRow) && empty($row['is_del'])) {
                        //Здесь проставляется changed для web (только ли это в web нужно?) - можно облегчить!!!! - может, делать не здесь, а при изменении?
                        if (Calculate::compare('!==', $oldRow, $row)) {
                            foreach ($row as $k => $v) {
                                /*key_exists for $oldRow[$k] не использовать!*/
                                if ($k !== 'n' && Calculate::compare('!==', ($oldRow[$k] ?? null), $v)) {
                                    $this->changeIds['changed'][$id] = $this->changeIds['changed'][$id] ?? [];
                                    $this->changeIds['changed'][$id][$k] = null;
                                }
                            }
                        }
                    }
                }
                $this->loadedTbl['rows'] = $this->loadedTbl['rows'] ?? [];
                $this->changeIds['deleted'] = $this->changeIds['deleted']
                    + array_flip(array_keys(array_diff_key(
                        $this->loadedTbl['rows'],
                        $this->tbl['rows']
                    )));
                $this->changeIds['added'] = array_flip(array_keys(array_diff_key(
                    $this->tbl['rows'],
                    $this->loadedTbl['rows']
                )));

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
     * @return \Closure
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
        $Log = $this->calcLog(["recalculate" => 'column']);

        /*****Берем старую таблицу*******/
        $SavedRows = $this->savedTbl['rows'] ?? [];

        /***reorder***/
        if ($reorder) {
            $old_order = array_intersect(array_keys($SavedRows), $reorder);

            if (!empty($this->tableRow['order_desc'])) {
                $reorder = array_reverse($reorder);
            }

            $reorders = array_combine($old_order, $reorder);
            $newRows = [];
            foreach ($SavedRows as $id => $row) {
                if (key_exists($id, $reorders)) {
                    $id = $reorders[$id];
                }
                $newRows[$id] = $SavedRows[$id];
            }
            $SavedRows = $newRows;
            unset($newRows);
            $this->setIsTableDataChanged(true);
        }


        /***insert field list***/
        $insertList = [];
        if ($insertField = $this->fields['insert'] ?? null) {
            if ($insertField['category'] === 'column' && !empty($insertField['code'])) {
                $insertCalcs = new Calculate($insertField['code']);
                $insertList = $insertCalcs->exec(
                    $insertField,
                    null,
                    [],
                    [],
                    $this->savedTbl,
                    $this->tbl,
                    $this
                );

                if ($insertCalcs->getError()) {
                    throw new errorException('Ошибка обработки поля insert [[' . $insertCalcs->getError() . ']]');
                }
                if (!is_array($insertList)) {
                    throw new errorException('Поле [[insert]] должно возвращать list  - Таблица [[' . $this->tableRow['id'] . ' - ' . $this->tableRow['title'] . ']]');
                }

                $insertList = array_filter(
                    $insertList,
                    function ($v) {
                        if (!is_null($v) && $v !== "") {
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
                    throw new errorException('Поле [[insert]] должно возвращать list с уникальными значениями - Таблица [[' . $this->tableRow['id'] . ' - ' . $this->tableRow['title'] . ']]');
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
                                $this->getCycle() ? $this->getCycle()->getId() : null,
                                $id,
                                $this->recalculateWithALog ?
                                    (is_bool($this->recalculateWithALog) ? 'скрипт' : $this->recalculateWithALog) : null
                            );
                        }
                    };
                    switch ($this->tableRow['deleting']) {
                        case 'none':
                            if ($channel !== 'inner') {
                                throw new errorException('В таблице запрещено удаление');
                            }
                        // no break
                        case 'delete':
                            $this->changeIds['changed'][$row['id']] = null;
                            $aLogDelete($row['id']);
                            continue 2;
                        case 'hide':
                            $newRow['is_del'] = true;
                            $aLogDelete($row['id']);
                            break;
                    }
                } elseif (in_array($row['id'], $restore)) {
                    $this->setIsTableDataChanged(true);
                    $this->changeIds['restored'][$row['id']] = null;
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
                    if (is_null($field['default'] ?? null) && empty($field['code']) && $field['type'] !== "comments") {
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
                $this->changeIds['duplicated'][$id] = $newRow['id'];
                $this->setIsTableDataChanged(true);
            } else {
                throw new errorException('id [[' . $id . ']] в таблице не найден');
            }
        }


        if (!empty($add)) {
            $getId = function ($addRow) use ($addWithId, $isCheck, $channel) {
                if ($addWithId && !empty($addRow['id']) && ($id = (int)$addRow['id']) > 0) {
                    if ($this->tbl['nextId'] <= $id) {
                        $this->tbl['nextId'] = $id;
                    } elseif (array_key_exists($id, $this->tbl['rows'])) {
                        throw new errorException('id ' . $id . ' в таблице уже существует. Нельзя добавить повторно');
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
                    throw new errorException('Строки с id ' . $after . ' не существует. Возможно, она была удалена');
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

            if (!key_exists(
                    $column['name'],
                    $modify[$thisRow['id']] ?? []
                ) && $this->insertRowSetData && key_exists(
                    $column['name'],
                    $this->insertRowSetData
                )) {
                $channel = 'inner';
                $newVal = $this->insertRowSetData[$column['name']];
                unset($this->insertRowSetData[$column['name']]);
            }
            $thisRow[$column['name']] = $Field->add(
                $channel,
                $newVal,
                $thisRow,
                $this->savedTbl,
                $this->tbl,
                $isCheck,
                ['duplicatedId' => $duplicatedIds[$thisRow['id']] ?? 0]
            );
            if (!$isCheck) {
                $this->addToALogAdd($Field, $channel, $this->tbl, $thisRow, $modify[$thisRow['id']] ?? []);
            }
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
                $Code = new CalculateAction($codeAction);
                $Code->execAction('DEFAULT ACTION', [], [], $loadedTbl, $tbl, $this, 'exec');
            }


            $checkAndChange = function ($field) use ($tbl, $loadedTbl) {
                if (key_exists($field['name'], $loadedTbl['params'] ?? []) && Calculate::compare(
                        '!==',
                        $loadedTbl['params'][$field['name']]['v'],
                        $tbl['params'][$field['name']]['v']
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
                        )) {
                        if (Calculate::compare(
                            '!==',
                            $loadedTbl['rows'][$row['id']][$field['name']]['v'],
                            $row[$field['name']]['v']
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
                    if ($field['type'] === 'file' && $this->tableRow['deleting'] !== 'hide') {
                        File::deleteFilesOnCommit($Oldrow[$field['name']]['v'], $this->getTotum()->getConfig());
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

        if (!empty($params['order'])) {
            $orders = [];
            foreach ($params['order'] as $of) {
                $field = $of['field'];
                $AscDesc = $of['ad'] === 'desc' ? -1 : 1;

                if (!array_key_exists($field, $this->sortedFields['column']) && !Model::isServiceField($field)) {
                    throw new errorException('Поля [[' . $field . ']] в строчной части таблицы [[' . $this->tableRow['name'] . ']] не существует');
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
                        $o = $ord['acsDesc'] * (Calculate::compare('>', $row1[$k], $row2[$k]) ? 1 : -1);
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
            ) && count($params['where']) === 1 && $params['where'][0]['field'] === 'id' && $params['where'][0]['operator'] === '=');

        if (isset($params['where'])) {
            foreach ($params['where'] as $wI) {
                $field = $wI['field'];
                $operator = $wI['operator'];
                $value = $wI['value'];


                if ($value === '*ALL*') {
                    continue;
                }
                if ($field === 'id') {
                    switch ($operator) {
                        case '=':
                            $value = (array)$value;
                            foreach ($value as &$val) {
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


                if (!array_key_exists($field, $this->sortedFields['column']) && !Model::isServiceField($field)
                ) {
                    throw new errorException('Поля [[' . $field . ']] в таблице [[' . $this->tableRow['name'] . ']] не существует');
                }
                $_array = true;
                if (in_array($field, Model::serviceFields)) {
                    $_array = false;
                }

                $where[] = ['field' => $field, 'isArray' => $_array, 'operator' => $operator, 'val' => $value];
            }
        }

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
                    $a = $row[$w['field']] ?? null;
                    if ($w['isArray']) {
                        $a = $a['v'] ?? null;
                    }
                    if (!Calculate::compare($w['operator'], $a, $w['val'])) {
                        $checkedTrue = false;
                        break;
                    }
                }

                if ($checkedTrue) {
                    if ($returnType === 'row') {
                        return $sectionReplaces($row);
                    } else {
                        return $sectionReplaces($row)[$params['field'][0]];
                    }
                }
            }
            return null;
        } else {
            $offset = ($params['offset']) ?? 0;
            if (!(ctype_digit(strval($offset)))) {
                throw new errorException('Параметр offset должен быть целым числом');
            }
            $offset = (int)$offset;

            $limit = ($params['limit']) ?? "";
            if ($limit !== "") {
                if (!(ctype_digit(strval($limit)))) {
                    throw new errorException('Параметр limit должен быть целым числом');
                }
                $limit = (int)$limit;
            }

            if ($limit !== "" || $offset !== 0) {
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
                    $a = $row[$w['field']] ?? null;
                    if ($w['isArray']) {
                        $a = $a['v'] ?? null;
                    }

                    if (!Calculate::compare($w['operator'], $a, $w['val'])) {
                        continue 2;
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
                        $val .= "";
                    }
                    $list[$row['id']] = array_merge($row, ['_VAL' => $val]);
                }

                if ($limit !== "" && count($list) === $limit) {
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
