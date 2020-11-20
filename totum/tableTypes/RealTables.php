<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 24.03.17
 * Time: 10:01
 */

namespace totum\tableTypes;

use PDO;
use totum\common\calculates\Calculate;
use totum\common\errorException;
use totum\common\Field;
use totum\common\Model;
use totum\common\sql\SqlException;
use totum\models\Table;

abstract class RealTables extends aTable
{
    protected $elseWhere = ['is_del' => false];

    protected $header = [];
    protected $cachedUpdate;
    protected $caches = [];
    protected $nTailLength;

    public function getChildrenIds($id, $parentField, $bfield)
    {
        if (!array_key_exists($parentField, $this->fields) || $this->fields[$parentField]['category'] !== 'column') {
            throw new errorException('Поле [' . $parentField . '] в строчной части таблицы [' . $this->tableRow['name'] . '] не найдено');
        }
        if ($bfield !== 'id' && !key_exists($bfield, $this->fields)) {
            throw new errorException('Поле [' . $bfield . '] в строчной части таблицы [' . $this->tableRow['name'] . '] не найдено');
        }

        return $this->model->childrenIdsRecursive($id, $parentField, $bfield);
    }

    public function createTable()
    {
        $fields = [];
        $fields[] = 'id SERIAL PRIMARY KEY NOT NULL';
        $fields[] = 'is_del BOOLEAN NOT NULL DEFAULT FALSE ';
        $this->model->createTable($fields);

        if ($this->getTableRow()['with_order_field']) {
            $this->addOrderField();
        }
    }

    public function addField($fieldId)
    {
        $field = static::getFullField($this->Totum->getModel('tables_fields__v', true)->getById($fieldId));
        if ($field['category'] === 'column') {
            $this->Totum->getModel($this->tableRow['name'])->addColumn($field['name']);
            if ($field['type'] === 'checkbox') {
                $this->Totum->getModel($this->tableRow['name'])->update(
                    [$field['name'] => json_encode(["v" => false])],
                    []
                );
            }
        }
    }

    public function createIndex($columnName)
    {
        if ($columnName !== 'id') {
            $this->Totum->getModel($this->tableRow['name'])->createIndexOnJsonbField($columnName);
        }
    }

    public function removeIndex($columnName)
    {
        if ($columnName !== 'id') {
            $this->Totum->getModel($this->tableRow['name'])->removeIndex($columnName);
        }
    }

    public function deleteField($fieldRow)
    {
        if ($fieldRow['category'] === 'column') {
            $this->Totum->getModel($this->tableRow['name'])->dropColumn($fieldRow['name']);
        } elseif ($fieldRow['category'] === 'filter') {
            $data = json_decode($fieldRow['data'], true);
            if (!empty($data['v']['column'])) {
                $countWithColumn = 0;
                $fields = $this->loadFields($this->getTableRow()['id']);
                foreach ($fields as $k => $v) {
                    if ($v['category'] === 'filter' && ($v['column'] ?? '') === $data['v']['column']) {
                        $countWithColumn++;
                    }
                }
                if ($countWithColumn < 2) {
                    $this->Totum->getTable(self::TABLES_IDS['tables'])->reCalculateFromOvers(
                        ['modify' => [$this->getTableRow()['id'] => ['indexes' => '-' . $data['v']['column']]]]
                    );
                }
            }
        }
    }

    public function isTblUpdated($level = 0, $force = false)
    {
        $tbl = $this->getTblForSave();

        $savedTbl = $this->savedTbl;
        if ($force || $this->isTableDataChanged || $tbl['__nTailLength'] !== $this->nTailLength) {
            $this->updated = $this->getUpdatedJson();
            $this->savedTbl['params'] = $tbl;

            /*Это чтобы лишний раз базу не дергать*/
            if ($this->isOnSaving) {
                $this->onSaveTable(['params' => $tbl], $savedTbl);
            } else {
                $this->isOnSaving = true;
                $this->onSaveTable(['params' => $tbl], $savedTbl);

                $this->saveTable();
                $this->isOnSaving = false;
            }
            $this->setIsTableDataChanged(false);

            return true;
        } else {
            return false;
        }
    }

    public function removeRows($remove, $channel)
    {
        $orderMinN = null;

        $this->setIsTableDataChanged(true);
        $isInnerChannel = $channel === 'inner';

        if ($codeActionsOnDeleteFields = $this->getFieldsForAction('Delete', 'column')) {
            $this->loadRowsByIds($remove);
            foreach ($remove as $id) {
                if (!empty($this->tbl['rows'][$id])) {
                    $this->rowChanged($this->tbl['rows'][$id], [], 'Delete');
                }
            }
        } else {
            foreach ($remove as $id) {
                $this->rowsOperations('Delete', ['id' => $id]);
            }
        }

        if (!empty($this->tableRow['with_order_field']) && !empty($this->tableRow['recalc_in_reorder'])) {
            $this->loadRowsByIds($remove);
            foreach ($remove as $id) {
                if ($row = ($this->tbl['rows'][$id] ?? null)) {
                    if (is_null($orderMinN) || $orderMinN > $row['n']) {
                        $orderMinN = $row['n'];
                    }
                }
            }
        }

        if ($isInnerChannel) {
            $this->model->delete(['id' => $remove]);
        } else {
            switch ($this->tableRow['deleting']) {
                case 'none':
                    throw new errorException('В таблице [[' . $this->tableRow['title'] . ']] запрещено удаление');
                case 'delete':
                    if ($this->tableRow['type'] === 'cycles') {
                        foreach ($remove as $id) {
                            $this->Totum->deleteCycle($id, $this->tableRow['id']);
                        }
                    }
                    $this->model->delete(['id' => $remove]);
                    break;
                case 'hide':
                    $this->model->update(['is_del' => true], ['id' => $remove]);
                    break;
            }

            /******aLog delete*****/
            if (in_array($channel, ['web', 'xml']) || $this->recalculateWithALog) {
                foreach ((array)$remove as $id) {
                    $this->Totum->totumActionsLogger()->delete($this->tableRow['id'], null, $id);
                }
            }
            /******aLog*****/
        }

        return $orderMinN;
    }

    protected function getByParamsFromRows($params, $returnType, $sectionReplaces)
    {
        $fields = $this->fields;
        $tableRow = $this->tableRow;

        $getNormalizeFunc = function ($field) {
            $normalizeFunc = null;

            if (Field::isFieldListValues($field['type'], $field['multiple'] ?? false)) {
                $normalizeFunc = function ($r) {
                    return json_decode($r, true);
                };
            } elseif ($field['type'] === 'checkbox') {
                $normalizeFunc = function ($r) {
                    return $r === 'false' ? false :
                        ($r === 'true' ? true
                            : null);
                };
            }
            return $normalizeFunc;
        };
        foreach ($fields as &$field) {
            $field['normalizeFunc'] = $getNormalizeFunc($field);
        }
        unset($field);

        $parentSectionReplaces = $sectionReplaces;
        $sectionReplaces = function ($row) use ($parentSectionReplaces, $params, $fields) {
            foreach ($row as $k => &$v) {
                if (!Model::isServiceField($k)) {
                    if ($fields[$k]['normalizeFunc']) {
                        $v = $fields[$k]['normalizeFunc']($v);
                    }
                    $v = ['v' => $v];
                }
            }
            unset($v);

            return $parentSectionReplaces($row);
        };

        list($whereStr, $paramsWhere) = $this->getWhereFromParams(
            $params['where'] ?? [],
            !in_array('is_del', ($params['field'] ?? []))
        );

        $order = null;

        if (isset($params['order'])) {
            $order = '';
            foreach ($params['order'] as $of) {
                if ($order) {
                    $order .= ',';
                }

                $field = $of['field'];
                $AscDesc = $of['ad'] === 'asc' ? 'asc NULLS FIRST' : 'desc NULLS LAST';

                if ((!array_key_exists($field, $fields) && !in_array(
                            $field,
                            Model::serviceFields
                        )) || (empty($this->tableRow['with_order_field']) && $field === 'n')) {
                    throw new errorException('Поля [[' . $field . ']] в таблице [[' . $tableRow['name'] . ']] не существует');
                }
                if (in_array($field, Model::serviceFields)) {
                    $order .= $field . ' ' . $AscDesc;
                } else {
                    $orderType = ($fields[$field]['type'] === 'number' ? 'NUMERIC' : 'TEXT');
                    $order .= "($field->>'v')::$orderType $AscDesc";
                }
            }
        }


        switch ($returnType) {
            case 'field':
                $limit = '0,1';
                break;
            case 'rows':
            case 'list':

                $offset = ($params['offset']) ?? "";
                if ($offset !== "" && !(ctype_digit(strval($offset)))) {
                    throw new errorException('Параметр offset должен быть целым числом');
                }
                $limit_ = ($params['limit']) ?? "";
                if ($limit_ !== "" && !(ctype_digit(strval($limit_)))) {
                    throw new errorException('Параметр limit должен быть целым числом');
                }
                $limit = $offset . ',' . $limit_;
                if ($limit === ',') {
                    $limit = null;
                }

                break;
            default:
                $limit = null;
        }


        $fieldsString = '';
        foreach (array_merge($params['field'], (array)($params['tfield'] ?? [])) as $f) {
            if ($fieldsString !== '') {
                $fieldsString .= ', ';
            }
            $fieldsString .= $f;
        }


        if ($returnType === 'rows' || $returnType === 'row') {

            //техническая выборка - не трогать
            if ($params['field'] === ['__all__']) {
                return $this->model->executePrepared(
                    true,
                    (object)['whereStr' => $whereStr, 'params' => $paramsWhere],
                    '*',
                    $order,
                    $limit
                );
            }


            if ($returnType === 'rows') {
                $rows = $this->model->executePrepared(
                    true,
                    (object)['whereStr' => $whereStr, 'params' => $paramsWhere],
                    $fieldsString,
                    $order,
                    $limit
                )->fetchAll();
                if (!empty($params['with__sectionFunction'])) {
                    foreach ($rows as &$row) {
                        $row['__sectionFunction'] = function () use ($sectionReplaces, $row, $params) {
                            return $sectionReplaces($row)[$params['sfield'][0]] ?? null;
                        };
                    }
                    unset($row);
                } else {
                    foreach ($rows as &$row) {
                        $row = $sectionReplaces($row);
                    }
                    unset($row);
                }
                return $rows;
            } elseif ($row = $this->model->executePrepared(
                true,
                (object)['whereStr' => $whereStr, 'params' => $paramsWhere],
                $fieldsString,
                $order
            )->fetch()) {
                return $sectionReplaces($row);
            } else {
                return [];
            }
        } else {
            $r = $this->model->executePrepared(
                true,
                (object)['whereStr' => $whereStr, 'params' => $paramsWhere],
                $fieldsString,
                $order,
                $limit
            )->fetchAll();

            if ($returnType === 'field') {
                if ($r) {
                    return $sectionReplaces($r[0])[$params['field'][0]];
                }
                return null;
            } else {
                foreach ($r as &$row) {
                    $row = $sectionReplaces($row)[$params['field'][0]];
                }
                unset($row);
            }
        }

        return $r;
    }

    public function checkInsertRow($tableData, $data)
    {
        if ($tableData) {
            $this->checkTableUpdated($tableData);
        }
        $this->reCalculate(['channel' => 'web', 'add' => [$data], 'isCheck' => true]);
        return $this->tbl['rowInserted'];
    }


    public function rowChanged($oldRow, $row, $action)
    {
        $this->cachedSelects = [];
        $this->setIsTableDataChanged(true);

        if ($actionFields = $this->getFieldsForAction($action, 'column')) {
            foreach ($oldRow as $k => &$v) {
                if (is_string($v)) {
                    $v = json_decode($v, true);
                }
            }
            unset($v);
            foreach ($row as $k => &$v) {
                if (is_string($v)) {
                    $v = json_decode($v, true);
                }
            }
            unset($v);


            foreach ($actionFields as $field) {
                $old = $oldRow[$field['name']]['v'] ?? null;
                $new = $row[$field['name']]['v'] ?? null;

                if ($action !== 'Change' || Calculate::compare('!==', $old, $new)) {
                    $this->changeIds['rowOperations'][] = function () use ($field, $oldRow, $row) {
                        Field::init($field, $this)->action(
                            $oldRow,
                            $row,
                            $this->savedTbl,
                            $this->tbl
                        );
                    };
                }
            }
        }


        switch ($action) {
            case 'Delete':
                $this->rowsOperations($action, $oldRow);
                break;
            case 'Add':
                $this->rowsOperations($action, $row);
                break;
            case 'Change':
                $changedKeys = [];
                foreach ($row as $k => $v) {
                    if (($oldRow[$k] ?? null) !== $v) {
                        $changedKeys[] = $k;
                    }
                }
                $this->rowsOperations($action, $row, $changedKeys);
                break;
        }
    }

    public function countByParams($params, $orders = null, $untilId = 0)
    {
        list($whereStr, $paramsWhere) = $this->getWhereFromParams($params);
        if ($untilId) {
            if (is_array($untilId)) {
                $isRefresh = -1;
            } else {
                $isRefresh = 0;
                $untilId = (array)$untilId;
            }
            array_push($paramsWhere, ... $untilId);
            return $this->model->executePreparedSimple(
                    true,
                    "select * from (select id, row_number()  over(order by $orders) as t from {$this->model->getTableName()} where $whereStr) z where id IN (" . implode(
                        ',',
                        array_fill(0, count($untilId), '?')
                    ) . ")",
                    $paramsWhere
                )->fetchColumn(1) + $isRefresh;
        }

        return $this->model->executePrepared(
            true,
            (object)['whereStr' => $whereStr, 'params' => $paramsWhere],
            'count(*) as count'
        )->fetchColumn(0);
    }

    protected function loadRowsByParams($params, $order = null, $offset = 0, $limit = null)
    {
        $paramsForFunc = ['where' => $params];
        $paramsForFunc['field'] = ['__all__'];

        if ($order) {
            $paramsForFunc['order'] = $order;
        }
        if ($offset) {
            $paramsForFunc['offset'] = $offset;
        }
        if ($limit) {
            $paramsForFunc['limit'] = $limit;
        }

        $rows = [];
        foreach ($this->getByParams($paramsForFunc, 'rows') as $k => $row) {
            $rows[$row['id']] = $row;
        }
        $this->rowsOperations('Load', null, $rows);
        return array_keys($rows);
    }

    public function saveTable()
    {
        $where = ['id' => $this->tableRow['id']];

        if ($this->getTableRow()['actual'] !== 'disable') {
            $where['updated'] = $this->savedUpdated;
        }

        $update = ['updated' => $this->updated];
        $update['header'] = json_encode($this->getTblForSave(), JSON_UNESCAPED_UNICODE);

        if (!$this->Totum->getNamedModel(Table::class)->update($update, $where)) {
            errorException::tableUpdatedException($this);
        }


        $this->markTableChanged();


        $this->setIsTableDataChanged(false);
        $this->savedUpdated = $this->updated;
        $this->cachedSelects = [];

        $this->Totum->tableChanged($this->tableRow['name']);
    }

    public function addOrderField()
    {
        $this->model->addOrderField();
    }

    public function removeOrderField()
    {
        $this->model->dropColumn('n');
    }

    /**
     * @return mixed
     */
    public function getNTailLength()
    {
        return $this->nTailLength;
    }

    protected function onSaveTable($tbl, $loadedTbl)
    {
        $fieldsWithActionOnChange = $this->getFieldsForAction('Change', 'param');
        if ($fieldsWithActionOnChange || !empty($this->changeIds['rowOperations'])) {
            $Log = $this->calcLog(['name' => 'ACTIONS', 'table' => $this]);
            if ($fieldsWithActionOnChange) {
                foreach ($fieldsWithActionOnChange as $field) {
                    if (Calculate::compare(
                        '!==',
                        $loadedTbl['params'][$field['name']]['v'],
                        $tbl['params'][$field['name']]['v']
                    )) {
                        Field::init($field, $this)->action(
                            $loadedTbl['params'],
                            $tbl['params'],
                            $loadedTbl,
                            $tbl
                        );
                    }
                }
            }
            while ($func = array_shift($this->changeIds['rowOperations'])) {
                $func();
            }

            $this->calcLog($Log, 'result', 'done');
        }
    }

    protected function loadRowsByIds(array $ids)
    {
        if ($notLoadedIds = array_diff_key(array_flip($ids), $this->tbl['rows'] ?? [])) {
            $this->loadRowsByParams([['field' => 'id', 'value' => array_keys($notLoadedIds), 'operator' => '=']]);
        }
    }

    protected function getTblForSave()
    {
        $tbl = $this->tbl;
        foreach ($this->sortedFields['filter'] ?? [] as $filterField) {
            unset($tbl['params'][$filterField['name']]);
        }
        $tbl['params']['__nTailLength'] = $this->nTailLength ?? 0;

        return $tbl['params'];
    }

    public function loadDataRow($fromConstructor = false, $force = false)
    {
        if (empty($this->loadedTbl)) {
            $this->loadedTbl = $this->savedTbl = $this->tbl = [
                'params' => json_decode($this->tableRow['header'], true)
            ];
            $this->nTailLength = $this->tbl['params']['__nTailLength'] ?? 0;
            $this->tbl['rows'] = [];
        }
    }

    public static function decodeRow($row)
    {
        foreach ($row as $k => &$v) {
            if (!in_array($k, Model::serviceFields)) {
                if (is_array($v)) {
                    debug_print_backtrace();
                }
                $v = json_decode($v, true);
            }
        }
        return $row;
    }

    protected function _copyTableData(&$table, $settings)
    {
        if ($settings['copy_params'] !== 'none' || $settings['copy_data'] !== 'none') {
            $table['tbl'] = $this->tbl;
            if ($settings['copy_params'] === 'none') {
                unset($table['tbl']['params']);
            }


            if ($settings['copy_data'] !== 'none') {
                $where = $this->elseWhere;
                if ($settings['copy_data'] === 'ids') {
                    $intervals = $this->_getIntervals($settings['intervals']);
                    $whereids = '';
                    foreach ($intervals as $i) {
                        if ($whereids !== '') {
                            $whereids .= ' OR ';
                        }
                        if ($i[0] === $i[1]) {
                            $whereids .= 'id=' . $i[0];
                        } else {
                            $whereids .= '(id>=' . $i[0] . ' AND id<=' . $i[1] . ')';
                        }
                    }
                    $where[] = '(' . $whereids . ')';
                }
                $table['tbl']['rows'] = $this->model->getAll($where);
            }
        }
    }

    protected function reCalculateRows($calculate, $channel, $isCheck, $modifyCalculated, $isTableAdding, $remove, $add, $modify, $setValuesToDefaults, $setValuesToPinned, $duplicate, $reorder, $addAfter, $addWithId)
    {
        $orderMinN = null;

        if ($remove) {
            $orderMinN = $this->removeRows($remove, $channel);
        }

        /***reorder***/
        if ($reorder) {
            $startId = 0;
            foreach ($reorder as $id) {
                if (!is_int($id)) {
                    throw new errorException('Ошибка клиентской части. Получена строка вместо id');
                }
            }
            $old_order_arrays = $this->model->executePrepared(true, ['id' => $reorder], 'n, id', 'n')->fetchAll();
            if (!empty($this->tableRow['order_desc'])) {
                $reorder = array_reverse($reorder);
            }

            foreach ($old_order_arrays as $i => $orderRow) {
                if ($orderRow['id'] === $reorder[0]) {
                    array_splice($reorder, 0, 1);
                    unset($old_order_arrays[$i]);
                } else {
                    break;
                }
            }
            if ($reorder) {
                $old_order_arrays_rev = array_reverse($old_order_arrays);
                $reorder_rev = array_reverse($reorder);
                foreach ($old_order_arrays_rev as $i => $orderRow) {
                    if ($orderRow['id'] === $reorder_rev[0]) {
                        array_splice($reorder_rev, 0, 1);
                        unset($old_order_arrays_rev[$i]);
                    } else {
                        break;
                    }
                }

                $old_order_arrays = [];
                foreach (array_reverse($old_order_arrays_rev) as $oldOrdRow) {
                    $old_order_arrays[] = $oldOrdRow['n'];
                }

                $reorder = array_reverse($reorder_rev);
                $orderMinN = $old_order_arrays[0];
                $this->model->updatePrepared(true, ['n' => null], ['id' => $reorder]);

                foreach ($reorder as $i => $rId) {
                    $this->model->updatePrepared(true, ['n' => $old_order_arrays[$i]], ['id' => [$rId]]);
                }
                $this->tbl['rows'] = [];
                $this->setIsTableDataChanged(true);
            }
        }


        $modifiedIds = array_flip(array_merge(
            array_keys($modify),
            array_keys($setValuesToDefaults),
            array_keys($setValuesToPinned)
        ));
        unset($modifiedIds['params']);
        $modifiedIds = array_flip($modifiedIds);


        switch ($calculate) {
            case aTable::CALC_INTERVAL_TYPES['all_filtered']:
                $modifiedIds = $this->loadFilteredRows($channel, $modifiedIds);
                break;
            case aTable::CALC_INTERVAL_TYPES['all']:
                $this->loadFilteredRows($channel);
                break;
        }


        if ($duplicate) {
            if ($notLoadedDuplicates = array_diff($duplicate['ids'], array_keys($this->tbl['rows']))) {
                $notLoadedDuplicatesRows = $this->model->getAllIndexedById(['id' => $duplicate['ids']] + $this->elseWhere);
                $this->rowsOperations('Load', null, $notLoadedDuplicatesRows);
            }

            foreach ($duplicate['ids'] as $baseRowId) {
                $row = $this->duplicateRow(
                    $channel,
                    $this->tbl['rows'][$baseRowId],
                    ($duplicate['replaces'][$baseRowId] ?? []),
                    $addAfter
                );
                if (!is_a($this, cyclesTable::class)) {
                    //Для пересчета строки при дублировании, чтобы не сыпались ошибки обращения к #id;
                    $modifiedIds[] = $row['id'];
                }
                if ($this->tableRow['with_order_field'] && (is_null($orderMinN) || $orderMinN > $row['n'])) {
                    $orderMinN = $row['n'];
                }
                if ($addAfter) {
                    $addAfter = $row['id'];
                }
            }
        }

        if ($add) {
            if (!empty($this->tableRow['with_order_field'])) {
                $fIds = $channel !== 'inner' ? $this->loadFilteredRows(
                    $channel,
                    $this->webIdInterval
                ) : [];


                $afterN = null;
                if ("0" === (string)$addAfter) {
                    $afterN = 0;
                } elseif ($addAfter) {
                    $this->loadRowsByIds([$addAfter]);
                    if (!empty($this->tbl['rows'][$addAfter])) {
                        $afterN = $this->tbl['rows'][$addAfter]['n'];
                    } else {
                        throw new errorException('Строки с id ' . $addAfter . ' не существует. Возможно, она была удалена');
                    }
                }
            }

            foreach ($add as $rAdd) {
                if ($this->tableRow['with_order_field'] ?? false) {
                    if ((!is_null($afterN) || $this->issetActiveFilters($channel)) && $n = $this->getNextN(
                            $fIds,
                            $afterN
                        )) {
                        $afterN = $rAdd['n'] = $n;
                    }
                }

                $row = $this->addRow($channel, $rAdd, false, $addWithId, 0, $isCheck);

                if ($this->tableRow['with_order_field'] ?? false) {
                    if (is_null($orderMinN) || $orderMinN > $row['n']) {
                        $orderMinN = $row['n'];
                    }
                }
                if (!$isCheck && !is_a($this, cyclesTable::class)) {
                    $modifiedIds[] = $row['id'];
                } //Для пересчета строки при добавлении, чтобы не сыпались ошибки обращения к #id;
            }
        }
        if (!empty($this->tableRow['recalc_in_reorder'])) {
            if (!is_null($orderMinN)) {
                array_push(
                    $modifiedIds,
                    ...
                    $this->model->executePrepared(
                        true,
                        ['>=n' => $orderMinN, '!id' => $modifiedIds],
                        'id'
                    )->fetchAll(PDO::FETCH_COLUMN, 0)
                );
            }
        }

        $this->loadRowsByIds($modifiedIds);
        if (!empty($this->tableRow['with_order_field'])) {
            $ns = [];
            foreach ($modifiedIds as $mid) {
                $ns[] = $this->tbl['rows'][$mid]['n'];
            }
            array_multisort($ns, $modifiedIds);
        }

        if (count($modifiedIds) > 1) {
            if ($this->orderFieldName === 'id') {
                sort($modifiedIds);
            } else {
                $ordArray = [];
                foreach ($modifiedIds as $i => $id) {
                    if (!empty($this->tbl['rows'][$id])) {
                        $val = $this->tbl['rows'][$id][$this->orderFieldName];
                        if (!Model::isServiceField($this->orderFieldName)) {
                            $val = $val['v'];
                        }
                        $ordArray[] = $val;
                    } else {
                        unset($modifiedIds[$i]);
                    }
                }
                array_multisort($ordArray, $modifiedIds);
            }
        }


        foreach ($modifiedIds as $id) {
            if (!empty($this->tbl['rows'][$id])) {
                $this->tbl['rows'][$id] = $this->modifyRow(
                    $channel,
                    $modify[$id] ?? [],
                    $setValuesToDefaults[$id] ?? [],
                    $setValuesToPinned[$id] ?? [],
                    $this->tbl['rows'][$id],
                    $modifyCalculated,
                    !$isCheck
                );
            }
        }
    }


    public function normalizeN()
    {
        $this->model->removeIndex('n');

        $this->model->exec('update ' . $this->tableRow['name'] . ' l set n=n.nn FROM (SELECT id, n, row_number() OVER (ORDER BY n)  AS nn ' .
            'FROM ' . $this->tableRow['name'] . ') n WHERE l.id=n.id');

        $this->model->createIndex('n', true);
        $this->nTailLength = 0;

        $this->saveTable();
    }

    protected function getNewTblForRecalc()
    {
        return [
            'rows' => $this->tbl['rows'],
            'params' => []
        ];
    }

    protected function rowsOperations($operation, $row = null, $rowsIndexedByIdOrChanges = [])
    {
        switch ($operation) {
            case 'Delete':

                $this->changeIds['deleted'][$row['id']] = null;
                foreach ($this->sortedFields['column'] as $field) {
                    if ($field['type'] === 'file') {
                        $this->loadRowsByIds([$row['id']]);
                        $this->deleteFilesOnCommit($this->tbl['rows'][$row['id']][$field['name']]['v']);
                    }
                }
                unset($this->tbl['rows'][$row['id']]);

                break;
            case 'Load':
                foreach ($rowsIndexedByIdOrChanges as $id => &$row) {
                    $row = $this->decodeRow($row);
                }
                unset($row);
                $this->tbl['rows'] = $rowsIndexedByIdOrChanges + $this->tbl['rows'];
                break;
            case 'Add':
                $this->changeIds['added'][$row['id']] = null;
                $this->tbl['rows'][$row['id']] = $row;
                break;
            case 'Change':

                if (empty($this->changeIds['changed'][$row['id']])) {
                    $this->changeIds['changed'][$row['id']] = [];
                }

                $this->changeIds['changed'][$row['id']] += array_flip($rowsIndexedByIdOrChanges);
                $this->tbl['rows'][$row['id']] = $row;
                break;
        }
    }

    protected function loadModel()
    {
        $this->model = $this->Totum->getModel($this->tableRow['name']);
    }

    protected function modifyRow($channel, $modify = [], $setValuesToDefaults = [], $setValuesToPinned = [], $oldRow, $modifyCalculated = true, $saveIt = true)
    {
        $changedData = ['id' => $oldRow['id']];

        if (!empty($this->tableRow['with_order_field'])) {
            $changedData['n'] = $oldRow['n'];
        }
        foreach ($this->sortedFields['column'] as $k => $v) {
            $newVal = $modify[$k] ?? null;

            $oldRow[$k] = ($oldVal = $oldRow[$k] ?? null);

            $field = Field::init($v, $this);
            $changedFlag = $field->getModifyFlag(
                array_key_exists($k, $modify),
                $newVal,
                $oldVal,
                array_key_exists($k, $setValuesToDefaults),
                array_key_exists($k, $setValuesToPinned),
                $modifyCalculated
            );

            $changedData[$v['name']] = $field->modify(
                $channel,
                $changedFlag,
                $newVal,
                $oldRow,
                $changedData,
                $this->savedTbl,
                $this->tbl,
                !$saveIt
            );
        }

        unset($changedData['id']);
        unset($changedData['n']);

        if ($saveIt === false) {
            return array_merge($oldRow, $changedData);
        }

        foreach ($changedData as $k => $v) {
            $identical = true;
            $keys = array_keys($changedData[$k] + (array)$oldRow[$k]);
            foreach ($keys as $key) {
                if (!array_key_exists($key, $changedData[$k])
                    || !array_key_exists($key, $oldRow[$k])
                    || (is_array($changedData[$k][$key]) && is_array($oldRow[$k][$key]) ? $changedData[$k][$key] != $oldRow[$k][$key] : $changedData[$k][$key] !== $oldRow[$k][$key])
                ) {
                    $identical = false;
                    break;
                }
            }
            if ($identical) {
                unset($changedData[$k]);
            }
        }


        if ($changedData
            /* ВНИМАНИЕ - ДЛЯ СТАРЫХ ПРОЕКТОВ */
            || ($this->tableRow['name'] === 'tree' && empty($oldRow['top']['v']))
        ) {
            $changedSaveData = $changedData;
            foreach ($changedSaveData as &$fData) {
                $fData = json_encode($fData, JSON_UNESCAPED_UNICODE);
            }

            if ($result = $this->model->update(
                $changedSaveData,
                ['id' => $oldRow['id']] + $this->elseWhere,
                $oldRow
            )
            ) {
                $row = $this->model->executePrepared(
                    true,
                    ['id' => $oldRow['id']] + $this->elseWhere,
                    '*',
                    null,
                    '0,1'
                )->fetch();
                $row = static::decodeRow($row);

                if ($row !== $oldRow) {
                    $this->setIsTableDataChanged(true);
                    $this->rowChanged($oldRow, $row, 'Change');

                    /******aLog  modify clear *****/
                    foreach ($row as $k => $v) {
                        if (!key_exists($k, $this->fields)) {
                            continue;
                        }

                        $Field = Field::init($this->fields[$k], $this);
                        $this->addToALogModify(
                            $Field,
                            $channel,
                            $this->tbl,
                            $row,
                            $row['id'],
                            $modify,
                            $setValuesToDefaults,
                            $setValuesToPinned,
                            $oldRow[$Field->getName()] ?? []
                        );
                    }
                }
                /******aLog*****/

                return $row;
            }
        }
        return $oldRow;
    }

    protected function duplicateRow($channel, $baseRow, $replaces, $addAfter)
    {


        /******Расчет дублированной строки для  REAL-таблиц********/

        $baseRow = $this->modifyRow($channel, [], [], [], $baseRow);
        $newRowData = [];
        foreach ($this->sortedFields['column'] as $field) {
            if (array_key_exists($field['name'], ($replaces))) {
                $newRowData[$field['name']] = $replaces[$field['name']];
                continue;
            }
            if (!empty($field['copyOnDuplicate'])) {
                if (!empty($field['code']) && empty($field['codeOnlyInAdd']) && empty($baseRow[$field['name']]['h'])) {
                    continue;
                }
                $newRowData[$field['name']] = $baseRow[$field['name']]['v'];
                continue;
            }
            if (!key_exists('default', $field) && empty($field['code']) && $field['type'] !== "comments") {
                $newRowData[$field['name']] = $baseRow[$field['name']]['v'];
            }
        }
        if (!empty($this->tableRow['with_order_field'])) {
            if ($addAfter) {
                $this->loadRowsByIds([$addAfter]);
                if ($n = $this->getNextN(null, $this->tbl['rows'][$addAfter]['n'])) {
                    $newRowData['n'] = $n;
                }
            } elseif ($n = $this->getNextN(null, $baseRow['n'])) {
                $newRowData['n'] = $n;
            }
        }

        /******Расчет дублированной строки для  REAL-таблиц********/

        $Log = $this->calcLog(['name' => 'DUPLICATE ROW']);
        $this->CalculateLog->addParam('duplicated_id', $baseRow['id']);

        $row = $this->addRow('inner', $newRowData, true, false, $baseRow['id']);
        $this->calcLog($Log, 'result', 'done');

        if ($row && $this->tableRow['name'] === 'tables') {
            $this->changeIds['rowOperations'][] = function () use ($baseRow, $row) {
                $this->Totum->getNamedModel(Table::class)->dulpicateTableFiedls($row, $baseRow);
            };
            $this->tbl['rows'] = [];
        }


        $this->changeIds['duplicated'][$baseRow['id']] = $row['id'];
        return $row;
    }

    public static function getNSize($n)
    {
        return strlen($n) - strpos($n, '.') - 1;
    }

    protected function getNextN($idRows = null, $prevN = null)
    {
        if (!empty($idRows) && is_null($prevN)) {

            if (empty($this->tableRow['order_desc'])) {
                $prevN = $this->model->executePrepared(true, ['id' => $idRows], 'MAX(n) as n')->fetchColumn(0);
            } else {
                $prevN = $this->model->executePrepared(true, ['id' => $idRows], 'MIN(n) as n')->fetchColumn(0);
            }
        }
        if (!is_null($prevN)) {
            if (!empty($this->tableRow['order_desc'])) {
                $nextN = $this->model->executePrepared(true, ['<n' => $prevN], 'MAX(n) as n')->fetchColumn(0);
                [$prevN, $nextN] = [$nextN, $prevN];
            } else {
                $nextN = $this->model->executePrepared(true, ['>n' => $prevN], 'MIN(n) as n')->fetchColumn(0);
            }
            if ($nextN) {
                $scalePrev = static::getNSize($prevN);
                $scaleNext = static::getNSize($nextN);
                $scale = $scaleNext < $scalePrev ? $scalePrev : $scaleNext;

                $diff = bcsub($nextN, $prevN, $scale);
                $scaleDiff = static::getNSize($diff);
                $len = 4;

                while (bccomp(
                        $diff,
                        ($nPlus = '0.' . (str_repeat('0', $len - 1)) . '1'),
                        $scaleDiff > $len ? $scaleDiff : $len
                    ) !== 1) {
                    $len += 4;
                }


                $n = bcadd($prevN, $nPlus, $len < $scalePrev ? $scalePrev : $len);
                $scaleN = static::getNSize($n);
                $scaleComp = $scaleN > $scaleNext ? $scaleN : $scaleNext;
                if (bccomp($n, $nextN, $scaleComp) !== -1) {
                    throw new SqlException("Ошибка логики n: $n>=$nextN");
                }
                if ($this->nTailLength < $scaleComp) {
                    $this->nTailLength = $scaleComp;
                }
            } else {
                $n = bcadd($prevN, 1, 0);
            }
        }
        if (!empty($n)) {
            return $n;
        }
    }

    /**
     * @param $channel
     * @param $addData
     * @param false $fromDuplicate
     * @param false $addWithId
     * @param int $duplicatedId
     * @param false $isCheck
     * @return array|null
     * @throws errorException
     */
    protected function addRow($channel, $addData, $fromDuplicate = false, $addWithId = false, $duplicatedId = 0, $isCheck = false)
    {
        $changedData = ['id' => ''];

        if ($addWithId && ($id = (int)($addData['id'] ?? 0)) > 0) {
            if ($this->model->getPrepared(['id' => $id], 'id')) {
                throw new errorException('id ' . $id . ' в таблице уже существует. Нельзя добавить повторно');
            }
            $changedData['id'] = $id;
        }

        if (!empty($this->tableRow['with_order_field']) && !$isCheck) {
            if (!empty($addData['n'])) {
                $changedData['n'] = $addData['n'];
            } else {
                if (empty($id)) {
                    $id = $this->model->executePreparedSimple(
                        true,
                        'SELECT nextval(\'' . $this->tableRow['name'] . '_id_seq\')',
                        []
                    )->fetchColumn(0);
                    $n = $id;
                } else {
                    $n = $this->model->getField('max(n)+1 as n', []);
                }
                $changedData['id'] = $id;
                $changedData['n'] = $n;
            }
        }

        foreach ($this->sortedFields['column'] as $v) {
            $field = Field::init($v, $this);
            $changedData[$v['name']] = $field->add(
                $channel,
                $addData[$v['name']] ?? null,
                $changedData,
                $this->savedTbl,
                $this->tbl,
                $isCheck,
                ['duplicatedId' => $duplicatedId]
            );
        }

        //*****
        if (empty($changedData['id'])) {
            unset($changedData['id']);
        }

        if ($changedData) {
            if ($isCheck) {
                $this->tbl['rowInserted'] = $changedData;
                return null;
            }

            $changedSaveData = $changedData;

            foreach ($changedSaveData as $k => &$fData) {
                if ($k !== 'n') {
                    $fData = json_encode($fData, JSON_UNESCAPED_UNICODE);
                }
            }
            unset($fData);

            $this->setIsTableDataChanged(true);

            if ($resultId = $this->model->insertPrepared($changedSaveData)) {
                $row = static::decodeRow($this->model->getById($resultId));
                $this->rowChanged([], $row, 'Add');


                /******aLog add *****/
                foreach ($changedData as $k => $v) {
                    if (key_exists($k, $this->fields)) {
                        $Field = Field::init($this->fields[$k], $this);
                        $this->addToALogAdd($Field, $channel, $this->tbl, $row, $addData);
                    }
                }
                /******aLog*****/

                return $row;
            }
        } else {
            debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            throw new errorException('Ошибка добавления строки');
        }
    }

    protected function getWhereFromParams($paramsWhere, $withoutDeleted = true)
    {
        $where = [];
        $params = [];

        $fields = $this->fields;

        if ($withoutDeleted && count($paramsWhere) === 1 && $paramsWhere[0]["field"] === 'id' && $paramsWhere[0]["operator"] === '=') {
            $withoutDeleted = false;
        }

        foreach ($paramsWhere as $wI) {
            if (!is_array($wI)) {
                $where[] = $wI;
                continue;
            }

            $fieldName = $wI['field'];
            $operator = $wI['operator'];
            $value = $wI['value'];

            if ($value === '*ALL*') {
                continue;
            }

            if (!array_key_exists($fieldName, $fields) && !Model::isServiceField($fieldName)) {
                throw new errorException('Поля [[' . $fieldName . ']] в таблице [[' . $this->tableRow['name'] . ']] не существует');
            }


            if (Model::isServiceField($fieldName)) {
                $fieldQuoted = '"' . $fieldName . '"';
            } else {
                $fieldQuoted = "($fieldName->>'v')";
                $fieldQuotedJsonb = "($fieldName->'v')";
            }

            /*Проверка на число - чтобы ошибок в базе не случалось*/
            if ($fieldName === 'id' || $fieldName === 'n' || $fields[$fieldName]['type'] === 'number') {
                foreach ((array)$value as $v) {
                    if ($v !== "" && !is_null($v) && !is_numeric((string)$v)) {
                        throw new errorException('Для выборки по числовому полю [[' . $fieldName . ']] должно быть передано число');
                    }
                }

                if ($fieldName === 'id') {
                    $fieldQuoted = "(id)::NUMERIC";
                } elseif ($fieldName !== 'n') {
                    $fieldQuoted = "$fieldQuoted::NUMERIC";
                }
            }


            /*Поиск в полях-листах*/
            if (Field::isFieldListValues(
                $fields[$wI['field']]['type'] ?? null,
                $fields[$wI['field']]['multiple'] ?? false
            )) {
                $trueFalse = "TRUE";
                $sqlOperator = '=';

                switch ($operator) {
                    case '!==':
                        /*Сравнение с пустой строкой*/
                        if ($value === '' || is_null($value)) {
                            $where[] = "($fieldQuoted is NULL OR $fieldQuoted = '') = FALSE";
                        } /*Сравнение с листом*/
                        elseif (is_array($value)) {
                            $where[] = "($fieldQuotedJsonb != ?::JSONB OR $fieldQuoted is NULL)";
                            $params[] = json_encode(
                                $value,
                                JSON_UNESCAPED_UNICODE
                            );
                        } /*Сравнение с числом или строкой*/
                        else {
                            if (is_bool($value)) {
                                $value = $value ? 'true' : 'false';
                            }
                            $where[] = "($fieldQuoted != ? OR $fieldQuoted is NULL)";
                            $params[] = (string)$value;
                        }

                        break;
                    case '==':
                        /*Сравнение с пустой строкой*/
                        if (is_null($value) || $value === '') {
                            $where[] = "($fieldQuoted is NULL OR $fieldQuoted = '') = TRUE";
                        } /*Сравнение с листом*/
                        elseif (is_array($value)) {
                            $where[] = "($fieldQuotedJsonb = ?::JSONB )";
                            $params[] = json_encode(
                                $value,
                                JSON_UNESCAPED_UNICODE
                            );
                        } /*Сравнение с числом или строкой*/
                        else {
                            if (is_bool($value)) {
                                $value = $value ? 'true' : 'false';
                            }
                            $where[] = "($fieldQuoted = ?)";
                            $params[] = (string)$value;
                        }

                        break;
                    case '!=':
                        $trueFalse = 'FALSE';
                    // no break
                    case '=':

                        /*Сравнение с пустой строкой*/
                        if (empty($value) && ($value === '' || is_null($value))) {
                            $where[] = "($fieldQuoted is NULL 
                            OR $fieldQuoted='' 
                            OR $fieldQuoted='[]' 
                            OR $fieldQuotedJsonb @> '[\"\"]'::jsonb 
                            OR $fieldQuotedJsonb @> '[null]'::jsonb ) = $trueFalse";
                        } /*Сравнение с пустым листом*/
                        elseif (empty($value) && $value === []) {
                            $where[] = "($fieldQuoted is NULL OR $fieldQuoted='' OR $fieldQuoted='[]') = $trueFalse";
                        } /*Сравнение с листом*/
                        elseif (is_array($value)) {
                            if ($fields[$wI['field']]['type'] === 'listRow') {
                                $isAssoc = (array_keys($value) !== range(0, count($value) - 1));

                                $where_tmp = '';
                                foreach ($value as $k => $v) {
                                    if ($where_tmp !== '') {
                                        $where_tmp .= ' OR ';
                                    }
                                    if ($isAssoc) {
                                        if (is_numeric((string)$v)) {
                                            $where_tmp .= "$fieldQuotedJsonb @> ?::jsonb OR ";
                                            $params[] = json_encode(
                                                [$k => is_string($v) ? (float)$v : (string)$v],
                                                JSON_UNESCAPED_UNICODE
                                            );
                                        }
                                        $where_tmp .= "$fieldQuotedJsonb @> ?::jsonb ";
                                        $params[] = json_encode([$k => $v], JSON_UNESCAPED_UNICODE);
                                    } else {
                                        if (is_numeric((string)$v)) {
                                            $where_tmp .= "$fieldQuotedJsonb @> ?::jsonb OR ";
                                            $params[] = json_encode(
                                                [is_string($v) ? (float)$v : (string)$v]

                                            );
                                        }
                                        $where_tmp .= "$fieldQuotedJsonb @> ?::jsonb ";
                                        $params[] = json_encode([$v], JSON_UNESCAPED_UNICODE);
                                    }
                                }
                            } else {
                                $where_tmp = '';
                                foreach ($value as $v) {
                                    if ($where_tmp !== '') {
                                        $where_tmp .= ' OR ';
                                    }
                                    $where_tmp .= "$fieldQuotedJsonb @> ?::jsonb";
                                    $params[] = json_encode(
                                        [(string)$v],
                                        JSON_UNESCAPED_UNICODE
                                    );
                                }
                            }
                            $where[] = "($where_tmp) = $trueFalse";
                        } /*С булевым*/
                        elseif (is_bool($value) || in_array((string)$value, ["true", "false"])) {
                            if (is_bool($value)) {
                                $value = $value ? "true" : "false";
                            }
                            $null = "";
                            if ($operator == '!=') {
                                $null = " OR $fieldQuoted is NULL ";
                            }

                            $where[] = "(($fieldQuoted = ? or $fieldQuotedJsonb @> ?::jsonb or $fieldQuotedJsonb @> ?::jsonb) = $trueFalse $null)";

                            $params[] = $value;
                            $params[] = "[\"" . $value . "\"]";
                            $params[] = "[" . $value . "]";
                        } /*Сравнение с числом или строкой*/
                        else {

                            /*равно или содержит*/
                            $q = "$fieldQuoted = ?  OR $fieldQuotedJsonb @>  ?::jsonb ";
                            $params[] = (string)$value;
                            $params[] = json_encode([(string)$value], JSON_UNESCAPED_UNICODE);
                            if ($fields[$wI['field']]['type'] === 'listRow' && is_numeric((string)$value)) {
                                /*равно или содержит числовой вариант*/
                                $q .= "OR $fieldQuotedJsonb @> ?::jsonb";
                                $params[] = "[$value]";
                            }
                            $null = "";
                            if ($operator == '!=') {
                                $null = " OR $fieldQuoted is NULL ";
                            }
                            $where[] = "(($q) = $trueFalse $null)";
                        }
                        break;
                    default:
                        throw new errorException('Операторы для работы с листами только [[=]]/[[==]]/[[!=]]/[[!==]]');
                }
            } /* Поиск не в полях-листах по массиву */
            elseif (is_array($value)) {
                switch ($operator) {
                    case '==':
                        $where[] = 'FALSE';
                        break;
                    case '!==':
                        $where[] = 'TRUE';
                        break;
                    case '<':
                    case '<=':
                    case '>=':
                    case '>':
                        throw new errorException('При сравнении с листом операторы  <=> не допустимы');
                        break;
                    case '=':
                    case '!=':
                        /*Если на вход пришел пустой массив*/
                        if (empty($value)) {
                            if ($operator === '=') {
                                $where[] = 'FALSE';
                            } else {
                                $where[] = 'TRUE';
                            }
                        } else {
                            foreach ($value as $v) {
                                if (is_array($v)) {
                                    throw new errorException("В параметре where [[$fieldName]] получен лист, в качестве элемента которого содержится лист");
                                }
                            }
                            /*Если в массиве содержится пустое значение*/
                            $q = "";
                            if (in_array('', $value, true) || in_array(null, $value, true)) {
                                $value = array_filter(
                                    $value,
                                    function ($v) {
                                        return !(is_null($v) || $v === '');
                                    }
                                );
                                $q .= "$fieldQuoted  IS " . ($operator === '=' ? '' : 'NOT') . " NULL ";
                            }
                            /*если есть непустые значения*/
                            if (!empty($value)) {
                                if ($q) {
                                    $q .= " OR ";
                                }
                                $q .= "$fieldQuoted " . ($operator === '=' ? 'IN' : 'NOT IN') . ' (?' . str_repeat(
                                        ',?',
                                        count($value) - 1
                                    ) . ')';
                                array_push($params, ...$value);
                            }
                            $where[] = "($q)";
                        }
                        break;
                    default:
                        throw new errorException('Операторы для работы с листами только [[=]] и [[!=]]');

                }
            } /* Поиск не в полях-листах не по массиву*/
            else {
                switch ($operator) {
                    case '==':
                        $operator = '=';
                        break;
                    case '!==':
                        $operator = '!=';
                        break;
                }

                if ($value === '' || is_null($value)) {
                    switch ($operator) {
                        case '=':
                        case '<=':
                            $where[] = '(' . $fieldQuoted . '::text = \'\' OR ' . $fieldQuoted . ' is NULL)';
                            break;
                        case '!=':
                        case '>':
                            $where[] = '(' . $fieldQuoted . '::text != \'\' AND ' . $fieldQuoted . ' is NOT NULL)';
                            break;
                        case '>=':
                            $where[] = 'true';
                            break;
                        case '<':
                            $where[] = 'false';
                            break;
                    }
                } else {
                    if ($operator === '!=') {
                        $operator = 'IS DISTINCT FROM';
                    }

                    $where[] = '' . $fieldQuoted . ' ' . $operator . ' ? '
                        . (in_array(
                            $operator,
                            ['<', '<=']
                        ) ? ' OR (' . $fieldQuoted . '::text = \'\' OR ' . $fieldQuoted . ' is NULL)' : '');

                    $params[] = $value;
                }
            }
        }

        if ($withoutDeleted) {
            $where[] = 'is_del = false';
        }

        if (empty($where)) {
            $whereStr = "TRUE";
        } else {
            $whereStr = '(' . implode(') AND (', $where) . ')';
        }

        return [$whereStr, $params];
    }
}
