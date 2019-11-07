<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 05.04.17
 * Time: 18:49
 */

namespace totum\tableTypes;


use totum\common\aLog;
use totum\common\Auth;
use totum\common\Calculate;
use totum\common\Controller;
use totum\common\errorException;
use totum\common\Field;
use totum\common\Log;
use totum\common\Mail;
use totum\common\Model;
use totum\common\Cycle;
use totum\common\Sql;
use totum\config\Conf;
use totum\fieldTypes\File;
use totum\models\Table;
use totum\models\TablesCalcsConnects;
use totum\models\TablesFields;

abstract class JsonTables extends aTable
{

    protected $deletedIds = [], $modelConnects;
    protected $reordered = false;
    protected $filteredIds;

    function __construct($tableRow, Cycle $Cycle, $light = false)
    {
        $this->Cycle = $Cycle;
        parent::__construct($tableRow, null, $light);

    }


    function getCycle()
    {
        return $this->Cycle;
    }

    function updateFromSource($level, $isTableAdding = false)
    {

        if ($level > 20) {

            throw new errorException('Больше 20 уровней вложенности изменения таблиц. Скорее всего зацикл пересчета: ' . implode(", ",
                    static::$recalcs) . '');
        }
        $debug = debug_backtrace(0, 1);

        Log::sql('updateFromSource level ' . $level . ' ' . $this->tableRow['name'] . ' from(' . preg_replace('/^.*?([^\/]+)$/',
                '$1',
                $debug[0]['file']) . ':' . $debug[0]['line'] . ')');


        $this->reCalculate(['isTableAdding' => $isTableAdding]);

        $this->isTblUpdated($level);


    }

    function refresh_rows()
    {
        throw new errorException('Для расчетных таблиц не предусмотрено построчное обновление. Они пересчитываются целиком');
    }

    function checkInsertRow($addData, $savedFieldName = null)
    {
        $filteredColumns = [];
        foreach ($this->sortedFields['filter'] as $k => $f) {
            $filteredColumns[$f['column']] = $k;
        }

        $afterSavedField = false;

        foreach ($this->sortedVisibleFields['column'] as $v) {

            $filtered = null;
            if (isset($filteredColumns[$v['name']])
                && $this->filtersFromUser[$filteredColumns[$v['name']]] != '*ALL*'
                && $this->filtersFromUser[$filteredColumns[$v['name']]] != ['*ALL*']
                && ($this->filtersFromUser[$filteredColumns[$v['name']]] ?? null) != '*NONE*'
                && ($this->filtersFromUser[$filteredColumns[$v['name']]] ?? null) != ['*NONE*']
            ) {
                $filtered = $this->filtersFromUser[$filteredColumns[$v['name']]] ?? null;
            }
            if (is_null($addData[$v['name']] ?? null) && !empty($filtered))
                $addData[$v['name']] = $filtered;

            if ($afterSavedField && !empty($v['code'])) {
                unset($addData[$v['name']]);
            }
            if ($savedFieldName == $v['name']) {
                $afterSavedField = true;
            }
        }

        $this->reCalculate(['channel' => 'web', 'add' => [$addData], 'modify' => ['params' => $this->filtersFromUser], 'isCheck' => true]);

        return $this->tbl['rows'][$this->tbl['insertedId']];
    }


    function addField($field)
    {

    }

    function checkEditRow($editData, $tableData = null)
    {
        $this->loadDataRow();
        if ($tableData) {
            $this->checkTableUpdated($tableData);
        }
        $table = [];

        $this->checkIsUserCanModifyIds([$editData['id'] => []], [], 'web');

        $this->editRow($editData);

        if (empty($this->tbl['rows'][$editData['id']])) {
            throw new errorException('Строка с id ' . $editData['id'] . ' не найдена');
        }

        $changedData = $this->tbl['rows'][$editData['id']];

        $data = ['rows' => [$changedData]];
        $this->tbl['rows'] = [];
        $data = $this->getValuesAndFormatsForClient($data, 'edit');
        $changedData = $data['rows'][0];

        $table['row'] = $changedData;
        $table['f'] = $this->getTableFormat();
        return $table;
    }

    function deleteField()
    {

    }

    public function getChildrenIds($id, $parentField)
    {

        if ($id) {
            $children = [];
//DOTO упростить функцию - выкинуть лишний перебор
            foreach ($this->tbl['rows'] as $row) {
                if (!array_key_exists($row['id'], $children)) {
                    $children[$row['id']] = [];
                }
                if ($parent = (int)$row[$parentField]['v']) {
                    if (!array_key_exists($parent, $children)) $children[$parent] = [];
                    $children[$parent][$row['id']] = &$children[$row['id']];
                }
            }

            if ($children[$id]) {
                $getChildren = function ($list) use (&$getChildren) {
                    if (!$list) return $list;
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

    function isTblUpdated($level = 0, $force = false)
    {
        /*$tbl = $this->getTblForSave();
        $savedTbl = $this->savedTbl;*/

        if ($this->isTableDataChanged) {

            $this->updated = static::getUpdatedJson();

            self::recalcLog($this->getTableRow()['name'] . ($this->getTableRow()['type'] == 'calcs' ? '/' . $this->getCycle()->getId() : ''),
                'Изменено');


            /*Возможно, здесь тоже стоит разнести сохранение и onSaveTable, но логика сложная и можно поломать пересчеты неочевидным образом*/
            if ($this->isOnSaving) {

                $this->Cycle->saveTables();
                $this->updateReceiverTables($level);
                self::recalcLog('..');

            } else {
                /*Это верхний уровень сохранения пересчетов для этой таблицы*/

                $this->isOnSaving = true;

                $this->Cycle->saveTables();
                $this->updateReceiverTables($level);
                self::recalcLog('..');


                foreach ($this->tbl['rows'] as $id => $row) {
                    $oldRow = ($this->loadedTbl['rows'][$id] ?? []);
                    if ($oldRow && (!empty($row['is_del']) && empty($oldRow['is_del']))) $this->changeIds['deleted'][$id] = null;
                    elseif (!empty($oldRow) && empty($row['is_del'])) {
                        if ($oldRow != $row) {
                            foreach ($row as $k => $v) {
                                if ($k != 'n' && ($oldRow[$k] ?? null) != $v) {//Здесь проставляется changed для web (только ли это в web нужно?)
                                    $this->changeIds['changed'][$id] = $this->changeIds['changed'][$id] ?? [];
                                    $this->changeIds['changed'][$id][$k] = null;
                                }
                            }

                        }
                    }
                }
                $this->loadedTbl['rows'] = $this->loadedTbl['rows'] ?? [];
                $this->changeIds['deleted'] = $this->changeIds['deleted']
                    + array_flip(array_keys(array_diff_key($this->loadedTbl['rows'],
                        $this->tbl['rows'])));
                $this->changeIds['added'] = array_flip(array_keys(array_diff_key($this->tbl['rows'],
                    $this->loadedTbl['rows'])));

                $this->isOnSaving = false;
            }
            return true;
        } else
            return false;
    }

    function setDuplicatedTbl($tbl, $updated = null)
    {

        $this->loadDataRow();

        $this->tbl = $tbl;
        $this->indexRows();


        $this->loadedTbl = $this->savedTbl = $this->tbl;
        $this->savedTbl[] = 'changed';

        $this->savedUpdated = $updated ?? $this->savedUpdated;
        $this->updated = static::getUpdatedJson();
        $this->isTableDataChanged = true;

        /** @var TablesCalcsConnects $CalcsConnects */
        $CalcsConnects = TablesCalcsConnects::init();

        /*  $CalcsConnects->createNewConnect(
              $this->tableRow['id'],
              $this->Cycle->getId(),
              $this->Cycle->getCyclesTableId(),
              TablesFields::TableId);*/
    }

    /**
     * @param $ids
     * @return \Closure
     * @throws errorException
     */
    protected
    function getIntervalsfunction($ids)
    {
        $intervals = $this->_getIntervals($ids);
        return function ($id) use ($intervals) {
            foreach ($intervals as $interval) {
                if ($id >= $interval[0] && $id <= $interval) return true;
            }
            return false;
        };
    }

    protected
    function _copyTableData(&$table, $settings)
    {

        if ($settings['copy_params'] != 'none' && $settings['copy_data'] != 'none') {
            $table['tbl'] = $this->tbl;
            if ($settings['copy_params'] == 'none') {
                unset($table['tbl']['params']);
            }


            if ($settings['copy_data'] == 'none') {
                unset($table['tbl']['rows']);
            } else {
                foreach ($table['tbl']['rows'] as $k => $row) {
                    if (!empty($row['is_del'])) unset($table['tbl']['rows'][$k]);
                }
                if ($settings['copy_data'] == 'ids') {
                    $funcIsInInterval = $this->getIntervalsfunction($settings['intervals']);
                    foreach ($table['tbl']['rows'] as $k => $row) {
                        if (!$funcIsInInterval($row['id'])) unset($table['tbl']['rows'][$k]);
                    }
                }
            }
        }
    }

    protected
    function saveSourceTables()
    {

        /** @var TablesCalcsConnects $model */
        $model = TablesCalcsConnects::init();
        $model->addConnects($this->tableRow['id'],
            $this->Cycle->getId(),
            $this->Cycle->getCyclesTableId(),
            $this->source_tables);
    }

    protected
    function reCalculateRows($calculate, $channel, $isCheck, $modifyCalculated, $isTableAdding, $remove, $add, $modify, $setValuesToDefaults, $setValuesToPinned, $duplicate, $reorder, $addAfter, $addWithId)
    {
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
            $this->isTableDataChanged = true;
        }


        /***insert field list***/
        $insertList = [];
        if ($insertField = $this->fields['insert'] ?? null) {
            if ($insertField['category'] == 'column' && !empty($insertField['code'])) {
                $insertCalcs = new Calculate($insertField['code']);
                $insertList = $insertCalcs->exec($insertField,
                    null,
                    [],
                    [],
                    $this->savedTbl,
                    $this->tbl,
                    $this);

                Controller::addLogVar($this, ['insert'], 'c', $insertCalcs->getLogVar());
                if ($insertCalcs->getError()) {
                    throw new errorException('Ошибка обработки поля insert [[' . $insertCalcs->getError() . ']]');
                }
                if (!is_array($insertList)) throw new errorException('Поле [[insert]] должно возвращать list  - Таблица [[' . $this->tableRow['id'] . ' - ' . $this->tableRow['title'] . ']]');

                $insertList = array_filter($insertList,
                    function ($v) {
                        if (!is_null($v) && $v !== "") return true;
                    });
                $type = SORT_STRING;
                if (in_array($this->fields['insert']['type'], ['select', 'tree', 'listRow'])) {
                    $type = SORT_REGULAR;
                }
                if (count(array_unique($insertList,
                        $type)) != count($insertList)) throw new errorException('Поле [[insert]] должно возвращать list с уникальными значениями - Таблица [[' . $this->tableRow['id'] . ' - ' . $this->tableRow['title'] . ']]');

            } else {
                unset($insertField);
            }
        }
        /**** delete ****/
        foreach ($SavedRows as $row) {
            $newRow = ['id' => $row['id']];

            if (!empty($row['_E'])) $newRow['_E'] = true;
            if (!empty($insertField)) {

                if (key_exists('c', $row['insert'])) {
                    $c = $row['insert']['c'];
                } else
                    $c = $row['insert']['v'];

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
                                $this->isTableDataChanged = true;
                                $this->changeIds['changed'][$row['id']] = null;
                            }
                            $newRow['InsDel'] = true;
                        }

                    }
                    $newRow['insert']['c'] = $c;
                }
            }

            if (!empty($row['is_del']) || $isDeletedRow = ($remove && in_array($row['id'], $remove))) {

                if ($isDeletedRow ?? null)
                    $this->isTableDataChanged = true;

                $aLogDelete = function ($id) use ($channel) {
                    if ($this->tableRow['type'] != 'tmp'
                        && (in_array($channel, ['web', 'xml']) || $this->recalculateWithALog)
                    ) {
                        aLog::delete($this->tableRow['id'],
                            $this->getCycle() ? $this->getCycle()->getId() : null,
                            $id);
                    }
                };


                switch ($this->tableRow['deleting']) {
                    case 'none':
                        if ($channel != 'inner') throw new errorException('В таблице запрещено удаление');
                    case 'delete':
                        $this->changeIds['changed'][$row['id']] = null;

                        foreach ($this->sortedFields['column'] as $field) {
                            if ($field['type'] === 'file') {
                                if ($deleteFiles = $this->tbl['rows'][$row['id']][$field['name']]['v']) {
                                    Sql::addOnCommit(function () use ($deleteFiles) {
                                        foreach ($deleteFiles as $file) {
                                            if ($file = ($file['file'] ?? null)) {
                                                unlink(File::getDir() . $file);
                                                if (is_file($preview = File::getDir() . $file . '_thumb.jpg')) {
                                                    unlink($preview);
                                                }
                                            }
                                        }
                                    });
                                }
                            }
                        }

                        $aLogDelete($row['id']);
                        continue 2;
                    case 'hide':
                        $newRow['is_del'] = true;
                        $aLogDelete($row['id']);
                        break;
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
                $this->isTableDataChanged = true;
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
                    if (is_null($field['default']) && empty($field['code']) && $field['type'] != "comments") {
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
                $this->isTableDataChanged = true;
            } else throw new errorException('id [[' . $id . ']] в таблице не найден');
        }


        if (!empty($add)) {

            $getId = function ($addRow) use ($addWithId) {

                if ($addWithId && !empty($addRow['id']) && ($id = (int)$addRow['id']) > 0) {
                    if ($this->tbl['nextId'] <= $id) {
                        $this->tbl['nextId'] = $id;
                    } else {
                        if (array_key_exists($id, $this->tbl['rows'])) {
                            throw new errorException('id ' . $id . ' в таблице уже существует. Нельзя добавить повторно');
                        }
                    }
                } else {
                    $id = ++$this->tbl['nextId'];
                }
                return $id;
            };

            if ($this->tableRow['with_order_field']
                &&
                (!is_null($addAfter) ||
                    ($channel != 'inner' && $this->issetActiveFilters($channel) &&
                        ($filteredIds = $this->getFilteredIds($channel))))) {

                if (!is_null($addAfter)) {
                    $after = $addAfter;
                } else {
                    $after = $filteredIds[count($filteredIds) - 1];
                }

                if ($after && !key_exists($after,
                        $SavedRows)) throw new errorException('Строки с id ' . $after . ' не существует. Возможно, она была удалена');

                foreach (($add ?? []) as $addRow) {
                    $newRow = ['id' => $getId($addRow), '_E' => true];

                    $orderDuplicatesAfter[$after][$newRow['id']] = $newRow;
                    $modify[$newRow['id']] = $addRow;
                    $this->isTableDataChanged = true;
                }
            } else {
                foreach (($add ?? []) as $addRow) {
                    $newRow = ['id' => $getId($addRow), '_E' => true];
                    $this->tbl['rows'][$newRow['id']] = $newRow;
                    $modify[$newRow['id']] = $addRow;
                    $this->isTableDataChanged = true;
                }
            }
            if ($channel == 'web' && $isCheck == true) {
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
            $this->isTableDataChanged = true;
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
            if ($changedFlag = $Field->getModifyFlag(array_key_exists($column['name'], $modifyRow),
                $newVal,
                $oldVal,
                array_key_exists($column['name'], $setValuesToDefaultsRow),
                array_key_exists($column['name'], $setValuesToPinnedRow),
                $modifyCalculated)
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
                $isCheck);

            $this->checkIsModified($oldVal, $thisRow[$column['name']]);

            $this->addToALogModify($Field,
                $channel,
                $this->tbl,
                $thisRow,
                $thisRow['id'],
                $modifyRow,
                $setValuesToDefaultsRow,
                $setValuesToPinnedRow,
                $oldVal);
        };
        $addRowField = function ($column, &$thisRow) use ($modify, $modifyCalculated, $channel, $isCheck, $duplicatedIds) {
            $Field = Field::init($column, $this);

            $thisRow[$column['name']] = $Field->add(
                $channel,
                $modify[$thisRow['id']][$column['name']] ?? null,
                $thisRow,
                $this->savedTbl,
                $this->tbl,
                $isCheck,
                ['duplicatedId' => $duplicatedIds[$thisRow['id']] ?? 0]);
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

                $changedFlag = $Field->getModifyFlag(array_key_exists($footerField['name'],
                    $modify['params'] ?? []),
                    $newVal,
                    $oldVal,
                    array_key_exists($footerField['name'], $setValuesToDefaults['params'] ?? []),
                    array_key_exists($footerField['name'], $setValuesToPinned['params'] ?? []),
                    $modifyCalculated);


                $this->tbl['params'][$footerField['name']] = $Field->modify(
                    $channel,
                    $changedFlag,
                    $newVal,
                    $this->savedTbl['params'],
                    $this->tbl['params'],
                    $this->savedTbl,
                    $this->tbl,
                    $isCheck);

                $this->checkIsModified($oldVal, $this->tbl['params'][$footerField['name']]);

                $this->addToALogModify($Field,
                    $channel,
                    $this->tbl,
                    $this->tbl['params'],
                    null,
                    $modify['params'] ?? [],
                    $setValuesToDefaults['params'] ?? [],
                    $setValuesToPinned['params'] ?? [],
                    $oldVal);
            }
        };

        Log::__print('test', '--calc--' . $this->getTableRow()['name'] . '---start-');
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
                    Log::__print('test', $footerField['name']);
                    $calculateRowFooterField($footerField);
                }
            }
            foreach (($footerColumns[''] ?? []) as $footerField) {
                $calculateRowFooterField($footerField);
            }
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
            foreach ($footerRows as $footerField) {
                $calculateRowFooterField($footerField);
            }
        }

    }

    protected
    function loadRowsByIds(array $ids)
    {
        return array_intersect_key($this->tbl['rows'], $ids);
    }

    protected
    function getPreparedTbl()
    {
        $tbl = $this->getTblForSave();
        if (!empty($this->tableRow['deleting']) && $this->tableRow['deleting'] === 'delete') {
            foreach ($tbl as $id => $row) {
                if (!empty($row['is_del']))
                    unset($tbl[$id]);
            }
        }
        $tbl['rows'] = array_values($tbl['rows']);
        return json_encode($tbl, JSON_UNESCAPED_UNICODE);
    }

    protected
    function indexRows()
    {
        $rows = [];
        foreach (($this->tbl['rows'] ?? []) as $row) {
            $rows[$row['id']] = $row;
        }
        $this->tbl['rows'] = $rows;
    }

    protected function checkRightFillOrder($id_first, $id_last, $count)
    {
        $ids = array_keys($this->tbl['rows']);
        return $ids[array_search($id_first, $ids) + $count - 1] === $id_last;

    }

    protected function getFilteredIds($channel, $idsFilter = [])
    {
        $issetBlockedFilters = false;
        $params = [];


        if (!empty($idsFilter)) {
            $params[] = ['field' => 'id', 'operator' => '=', 'value' => $idsFilter];
        } elseif ($channel == 'xml' && !empty($this->filtersFromUser['id'])) {
            $params[] = ['field' => 'id', 'operator' => '=', 'value' => array_map(function ($v) {
                return (int)$v;
            },
                (array)$this->filtersFromUser['id'])];
        }


        foreach ($this->sortedFields['filter'] ?? [] as $fName => $field) {
            switch ($channel) {
                case 'xml':
                    if (!array_key_exists($fName, $this->sortedXmlFields['filter'])) continue 2;
                    break;
                case 'web':
                    if (empty($this->fields[$fName]['showInWeb'])) continue 2;
                    break;
                default:
                    throw new errorException('Применение фильтров в канале ' . $channel . ' не описано');
            }


            if (!empty($field['column']) //определена колонка
                && (isset($this->sortedFields['column'][$this->fields[$fName]['column']]) || $field['column'] === 'id') //определена колонка и она существует в таблице
                && !is_null($fVal_V = $this->tbl['params'][$fName]['v']) //не "Все"
                && !(is_array($fVal_V) && count($fVal_V) == 0) //Не ничего не выбрано - не Все в мульти
                && !(!empty($idsFilter) && (
                    (Field::init($field,
                        $this)->isChannelChangeable('modify',
                        $channel)))) // если это запрос на подтверждение прав доступа и фильтр доступен ему на редактирование
            ) {

                if ($fVal_V === '*NONE*' || (is_array($fVal_V) && in_array('*NONE*', $fVal_V))) {
                    $issetBlockedFilters = true;
                    break;

                } elseif ($fVal_V === '*ALL*' || (is_array($fVal_V) && in_array('*ALL*',
                            $fVal_V))
                    || (!in_array($this->fields[$fName]['type'], ['select', 'tree']) && $fVal_V === '')) {
                    continue;
                } else {

                    $param = [];
                    $param['field'] = $field['column'];
                    $param['value'] = $fVal_V;
                    $param['operator'] = '=';

                    if
                    (!empty($this->fields[$fName]['intervalFilter'])) {

                        switch ($this->fields[$fName]['intervalFilter']) {
                            case  'start':
                                $param['operator'] = '>=';
                                break;
                            case  'end':
                                $param['operator'] = '<=';
                                break;
                        }

                    } else {
                        //Для вебного Выбрать Пустое в мультиселекте
                        if (($fVal_V === [""] || $fVal_V === "")
                            && $channel === 'web'
                            && in_array($field['type'], ['select', 'tree'])
                            && in_array($this->fields[$field['column']]['type'], ['select', 'tree'])
                            && (!empty($this->fields[$field['column']]['multiple']) || !empty($field['selectFilterWithEmpty']))
                        ) {
                            $param['value'] = "";
                        }
                    }
                    $params[] = $param;
                }
            }
        }
        $filteredIds = [];
        if (!$issetBlockedFilters) {
            $sortFieldName = 'id';
            if ($this->tableRow['order_field'] === 'n') {
                $sortFieldName = 'n';
            } else if ($this->tableRow['order_field'] && $this->tableRow['order_field'] != 'id') {
                if (!in_array($this->fields[$this->orderFieldName]['type'], ['select', 'tree'])) {
                    $sortFieldName = $this->orderFieldName;
                }
            }
            $order = [['field' => $sortFieldName, 'ad' => 'asc']];
            $params = ['where' => $params, 'order' => $order, 'field' => ['id']];

            $filteredIds = $this->getByParams($params, 'list');
        }

        if (empty($idsFilter)) {
            $this->changeIds['filteredIds'] = $filteredIds;
        }

        return $filteredIds;
    }

    protected
    function onSaveTable($tbl, $loadedTbl)
    {
        self::recalcLog($this->getTableRow()['name'] . ($this->getTableRow()['type'] == 'calcs' ? '/' . $this->getCycle()->getId() : ''),
            'Экшены');

        //При добавлении таблицы
        if ($loadedTbl == ['rows' => []]) {
            if ($fieldsWithActionOnAdd = $this->getFieldsForAction('Add', 'param')) {
                foreach ($fieldsWithActionOnAdd as $field) {
                    Field::init($field, $this)->action(
                        null,
                        $tbl['params'],
                        null,
                        $tbl
                    );
                }
            }

            $ColumnFootersOnChange = [];
            $CommonFootersOnChange = [];
            if ($fieldsWithActionOnAdd = $this->getFieldsForAction('Add', 'footer')) {
                foreach ($fieldsWithActionOnAdd as $field) {
                    if (!empty($field['column'])) $ColumnFootersOnChange[$field['column']][] = $field;
                    else $CommonFootersOnChange[] = $field;
                }
            }

            if ($tbl['rows']) {
                foreach ($this->sortedFields['column'] as $field) {
                    if (!empty($field['CodeActionOnAdd'])) {
                        foreach ($tbl['rows'] as $row) {
                            Field::init($field, $this)->action(
                                null,
                                $row,
                                null,
                                $tbl
                            );
                        }
                    }
                    foreach ($ColumnFootersOnChange[$field['name']] ?? [] as $field) {
                        Field::init($field, $this)->action(
                            null,
                            $tbl['params'],
                            null,
                            $tbl
                        );
                    }
                }
            }

            foreach ($CommonFootersOnChange as $field) {
                Field::init($field, $this)->action(
                    null,
                    $tbl['params'],
                    null,
                    $tbl
                );
            }

        } else {
            //При изменении таблицы

            if ($fieldsWithActionOnChange = $this->getFieldsForAction('Change', 'param')) {
                foreach ($fieldsWithActionOnChange as $field) {


                    if (empty($loadedTbl['params'][$field['name']]) || Calculate::compare('!==',
                            $loadedTbl['params'][$field['name']]['v'],
                            $tbl['params'][$field['name']]['v'])) {

                        Field::init($field, $this)->action(
                            $loadedTbl['params'] ?? [],
                            $tbl['params'],
                            $loadedTbl,
                            $tbl
                        );
                    }
                }
            }


            $ColumnFootersOnChange = [];
            $CommonFootersOnChange = [];
            foreach ($this->getFieldsForAction('Change', 'footer') as $field) {
                if (!empty($field['column'])) $ColumnFootersOnChange[$field['column']][] = $field;
                else $CommonFootersOnChange[] = $field;
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
                            $actionIt = true;
                        }
                    } elseif (!empty($field['CodeActionOnChange'])) {

                        if (Calculate::compare('!==',
                            $loadedTbl['rows'][$row['id']][$field['name']]['v'],
                            $row[$field['name']]['v'])) {
                            $actionIt = true;
                        }

                    }

                    if ($actionIt) {

                        Field::init($field, $this)->action(
                            $loadedTbl['rows'][$row['id']] ?? null,
                            $row,
                            $loadedTbl,
                            $tbl
                        );
                    }
                }
                foreach ($deletedRows as $Oldrow) {
                    if (!empty($field['CodeActionOnDelete'])) {
                        Field::init($field, $this)->action(
                            $Oldrow,
                            [],
                            $loadedTbl,
                            $tbl
                        );

                    }
                    if ($field['type'] === 'file') {
                        if ($deleteFiles = $Oldrow[$field['name']]['v']) {
                            foreach ($deleteFiles as $file) {
                                if ($file = ($file['file'] ?? null)) {
                                    unlink(File::getDir() . $file);
                                    if (is_file($preview = File::getDir() . $file . '_thumb.jpg')) {
                                        unlink($preview);
                                    }
                                }
                            }
                        }
                    }
                }

                foreach ($ColumnFootersOnChange[$field['name']] ?? [] as $field) {
                    if (Calculate::compare('!==',
                        $loadedTbl['params'][$field['name']]['v'],
                        $tbl['params'][$field['name']]['v'])) {
                        Field::init($field, $this)->action(
                            $loadedTbl['params'],
                            $tbl['params'],
                            $loadedTbl,
                            $tbl
                        );
                    }
                }
            }

            foreach ($CommonFootersOnChange as $field) {
                if (Calculate::compare('!==',
                    $loadedTbl['params'][$field['name']]['v'],
                    $tbl['params'][$field['name']]['v'])) {
                    Field::init($field, $this)->action(
                        $loadedTbl['params'],
                        $tbl['params'],
                        $loadedTbl,
                        $tbl
                    );
                }
            }
        }
        self::recalcLog('..');
    }

    protected
    function getNewTblForRecalculate()
    {
        return [
            'nextId' => $this->tbl['nextId'] ?? 0,
            'rows' => [],
            'params' => []
        ];
    }


    protected function getUpdated()
    {
        return $this->dataRow['updated'] ?? '';
    }

    protected
    function onCreateTable()
    {
        $this->source_tables[TablesFields::TableId] = true;
    }

    protected
    function onDeleteTable()
    {
        //TODO сделать обработку codeActionOnDelete при удалении таблиц
    }

    protected
    function editRow($editData)
    {
        $id = $editData['id'];
        $data = [];
        $dataSetToDefault = [];

        foreach ($editData as $k => $v) {
            if (is_array($v) && array_key_exists('v', $v)) {
                if (array_key_exists('h', $v)) {
                    if ($v['h'] == false) {
                        $dataSetToDefault[$k] = true;
                        continue;
                    }
                }
                $data[$k] = $v['v'];
            }
        }
        $this->reCalculate(['channel' => 'web', 'modify' => [$id => $data], 'setValuesToDefaults' => [$id => $dataSetToDefault], 'isCheck' => true]);

    }


    protected
    function updateReceiverTables($level = 0)
    {
        ++$level;
        Log::sql('updateReceiverTables ' . $this->getTableRow()['name'] . ' ' . $level);
        $receiverTables = [];
        $updateds = [];
        foreach ($this->modelConnects->getReceiverTables($this->tableRow['id'],
            $this->Cycle->getId(),
            $this->Cycle->getCyclesTableId()
        ) as $receiverTableId) {
            /** @var JsonTables $aTable */
            $aTable = tableTypes::getTable(Table::getTableRowById($receiverTableId), $this->Cycle->getId());
            if ('true' === $aTable->getTableRow()['__auto_recalc'] ?? 'true') {
                $receiverTables[$receiverTableId] = $aTable;
                $updateds[$receiverTableId] = $aTable->getSavedUpdated();
            }
        }

        foreach ($receiverTables as $receiverTableId => $aTable) {
            if ($updateds[$receiverTableId] == $aTable->getSavedUpdated()) {
                $aTable->updateFromSource($level);
            }
        }

    }

    protected
    function getByParamsFromRows($params, $returnType, $sectionReplaces)
    {
        $array = $this->tbl['rows'] ?? [];

        $isNumericField = function ($field) {
            return (in_array($field,
                Model::serviceFields) || $this->fields[$field]['type'] == 'numeric' ? 'numeric' : 'text');
        };

        if (array_key_exists('order', $params)) {
            $orders = [];
            foreach ($params['order'] as $of) {

                $field = $of['field'];
                $AscDesc = $of['ad'] == 'desc' ? -1 : 1;

                if (!array_key_exists($field, $this->sortedFields['column']) && !Model::isServiceField($field)) {
                    throw new errorException('Поля [[' . $field . ']] в строчной части таблицы [[' . $this->tableRow['name'] . ']] не существует');
                }
                $orders[$field] = ['orderNumeric' => $isNumericField($field), 'acsDesc' => $AscDesc];

            }

            $fOrdering = function ($row1, $row2) use ($orders) {
                $o = 0;
                foreach ($orders as $k => $ord) {
                    if (!Model::isServiceField($k)) {
                        $row1[$k] = $row1[$k]['v'];
                        $row2[$k] = $row2[$k]['v'];
                    }
                    if ($row1[$k] != $row2[$k]) {
                        $o = $ord['acsDesc'] * (Calculate::compare('>', $row1[$k], $row2[$k]) ? 1 : -1);
                    }
                    if ($o != 0) return $o;
                }
            };

        }
        $where = [];
        if (isset($params['where'])) {
            foreach ($params['where'] as $wI) {

                $field = $wI['field'];
                $operator = $wI['operator'];
                $value = $wI['value'];

                if ($field === 'id') {
                    switch ($operator) {
                        case '=':
                            $value = (array)$value;
                            foreach ($value as &$val) $val = strval($val);
                            unset($val);

                            $array = array_intersect_key($array, array_flip(array_unique($value)));
                            continue 2;
                        case '!=':
                            $value = (array)$value;
                            foreach ($value as &$val) $val = strval($val);
                            unset($val);

                            $array = array_diff_key($array, array_flip(array_unique($value)));
                            continue 2;
                    }
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
        if ($returnType === 'where') return $where;


        $isDelInFields = in_array('is_del',
                $params['field']) || (count($where) == 1 && $where[0]['field'] == 'id' && $where[0]['operator'] == '=');

        if ($returnType == 'field' || $returnType == 'row') {
            if (isset($fOrdering)) {
                usort($array, $fOrdering);
            }

            $keyFields = array_flip($params['field']);


            foreach ($array as $row) {
                if (!empty($row['is_del'])) {
                    if (!$isDelInFields) continue;
                } else {
                    if ($isDelInFields) $row['is_del'] = $row['is_del'] ?? false;
                }
                if (!array_intersect_key($keyFields, $row)) continue;

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
            $list = [];
            foreach ($array as $row) {
                if ($returnType != 'rows' && !key_exists($params['field'][0], $row)) continue;
                if (!empty($row['is_del'])) {
                    if (!$isDelInFields) continue;
                } else {
                    if ($isDelInFields) $row['is_del'] = $row['is_del'] ?? false;
                }
                foreach ($where as $w) {
                    $a = $row[$w['field']] ?? null;
                    if ($w['isArray']) {
                        $a = $a['v'] ?? null;
                    }

                    if (!Calculate::compare($w['operator'], $a, $w['val'])) continue 2;
                }


                if ($returnType == 'rows') {
                    if ($params['field'] == ['__all__'])
                        $list[] = $row;
                    else {

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
            }

            if (isset($fOrdering)) {
                uasort($list, $fOrdering);
            }
            $lst = [];


            switch ($returnType) {
                case 'list':
                case 'rows':
                    if ($params['field'] == ['__all__']) {
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