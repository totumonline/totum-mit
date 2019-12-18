<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 24.03.17
 * Time: 10:01
 */

namespace totum\tableTypes;


use totum\common\aLog;
use totum\common\Calculate;
use totum\common\Controller;
use totum\common\Cycle;
use totum\common\errorException;
use totum\common\Field;
use totum\common\Model;
use totum\common\Sql;
use totum\common\SqlExeption;
use totum\fieldTypes\File;
use totum\models\Table;
use totum\models\TablesFields;

abstract class RealTables extends aTable
{

    protected $header = [];
    protected $cachedUpdate, $caches = [], $nTailLength;

    public function getChildrenIds($id, $parentField)
    {
        if (!array_key_exists($parentField, $this->fields) || $this->fields[$parentField]['category'] != 'column') {
            throw new errorException('Поле [' . $parentField . '] в строчной части таблицы [' . $this->tableRow['name'] . '] не найдено');
        }

        return Sql::getFieldArray('WITH RECURSIVE cte_name (id) AS ( select
                                                    id
                                                  from ' . $this->tableRow['name'] . '
                                                  where is_del = false AND (' . $parentField . ' ->> \'v\') :: int=' . (int)$id . '
                                                  UNION select
                                                          tp.id
                                                        from ' . $this->tableRow['name'] . ' tp
                                                          JOIN cte_name c ON (tp.' . $parentField . ' ->> \'v\') :: int = c.id AND
                                                                             tp.is_del = false ) SELECT id
                                                                                                FROM cte_name');

    }

    function checkEditRow($editData, $tableData = null)
    {
        if ($tableData) {
            $this->checkTableUpdated($tableData);
        }
        $table = [];
        $id = $editData['id'];
        $this->checkIsUserCanModifyIds([$id => []], [], 'web');

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

        $this->loadRowsByIds([$id]);


        if (empty($this->tbl['rows'][$id])) {
            throw new errorException('Строка с id ' . $id . ' не найдена');
        }

        $changedData = $this->modifyRow('web',
            $data ?? [],
            $dataSetToDefault ?? [],
            [],
            $this->tbl['rows'][$id],
            true,
            false);

        $data = ['rows' => [$changedData]];

        $this->tbl['rows'] = [];

        $data = $this->getValuesAndFormatsForClient($data, 'edit');

        $changedData = $data['rows'][0];


        $table['row'] = $changedData;
        $table['f'] = $this->getTableFormat();
        return $table;
    }


    function addField($fieldId)
    {
        $field = TablesFields::getFullField(Model::initService('tables_fields__v')->getById($fieldId));
        if ($field['category'] == 'column') {
            Sql::exec('ALTER TABLE ' . $this->tableRow['name'] . ' ADD COLUMN "' . $field['name'] . '" JSONB NOT NULL DEFAULT \'{"v":null}\' ');
        }
    }

    function createIndex($columnName)
    {
        if ($columnName != 'id') {
            Sql::exec('CREATE INDEX IF NOT EXISTS ' . $this->tableRow['name'] . '___ind___' . $columnName . ' ON ' . $this->tableRow['name'] . ' ((' . $columnName . ' ->> \'v\'))');
            Sql::exec('ANALYZE ' . $this->tableRow['name']);
        }
    }

    function removeIndex($columnName)
    {
        if ($columnName != 'id') {
            Sql::exec('DROP INDEX IF EXISTS ' . $this->tableRow['name'] . '___ind___' . $columnName);
            Sql::exec('ANALYZE ' . $this->tableRow['name']);
        }
    }

    function deleteField($fieldRow)
    {

        if ($fieldRow['category'] == 'column') {
            Sql::exec('ALTER TABLE ' . $this->tableRow['name'] . ' DROP COLUMN "' . $fieldRow['name'] . '" ');
        } elseif ($fieldRow['category'] === 'filter') {
            $data = json_decode($fieldRow['data'], true);
            if (!empty($data['v']['column'])) {
                $countWithColumn = 0;
                $fields = TablesFields::getFields($this->getTableRow()['id']);
                foreach ($fields as $k => $v) {
                    if ($v['category'] === 'filter' && ($v['column'] ?? '') === $data['v']['column']) $countWithColumn++;
                }
                if ($countWithColumn < 2) {
                    tableTypes::getTable(Table::getTableRowById(Table::$TableId))->reCalculateFromOvers(
                        ['modify' => [$this->getTableRow()['id'] => ['indexes' => '-' . $data['v']['column']]]]
                    );
                }
            }
        }
    }

    function isTblUpdated($level = 0, $force = false)
    {


        $tbl = $this->getTblForSave();

        $savedTbl = $this->savedTbl;
        if ($force || $this->isTableDataChanged || $tbl['__nTailLength'] != $this->nTailLength) {
            $this->updated = static::getUpdatedJson();
            $this->savedTbl['params'] = $tbl;

            sql::transactionStart();

            /*Это чтобы лишний раз базу не дергать*/
            if ($this->isOnSaving) {
                $this->onSaveTable(['params' => $tbl], $savedTbl);
            } else {
                $this->isOnSaving = true;
                $this->onSaveTable(['params' => $tbl], $savedTbl);

                $this->saveTable();
                $this->isOnSaving = false;
            }

            $this->isTableDataChanged = false;

            sql::transactionCommit();
            return true;
        } else
            return false;
    }

    function removeRows($remove, $channel)
    {
        $orderMinN = null;

        $this->isTableDataChanged = true;
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
                    if (is_null($orderMinN) || $orderMinN > $row['n']) $orderMinN = $row['n'];
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
                            $cycle = Cycle::init($id, $this->tableRow['id']);
                            $cycle->delete(true);
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
                    aLog::delete($this->tableRow['id'], null, $id);
                }
            }
            /******aLog*****/
        }

        return $orderMinN;
    }

    function getByParamsFromRows($params, $returnType, $sectionReplaces)
    {

        $fields = $this->fields;
        $tableRow = $this->tableRow;

        $getNormalizeFunc = function ($field) {
            $normalizeFunc = null;

            if (Field::isFieldListValues($field['type'], $field['multiple'] ?? false)) {
                $normalizeFunc = function ($r) {
                    return json_decode($r, true);
                };
            } elseif ($field['type'] == 'checkbox') {
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

        $where = $this->getWhereFromParams($params['where'] ?? [], !in_array('is_del', ($params['field'] ?? [])));

        if ($returnType == 'where') {
            return $where;
        }

        $order = null;

        if (isset($params['order'])) {
            $order = '';
            foreach ($params['order'] as $of) {

                if ($order) $order .= ',';

                $field = $of['field'];
                $AscDesc = $of['ad'] == 'asc' ? 'asc NULLS FIRST' : 'desc NULLS LAST';

                if ((!array_key_exists($field, $fields) && !in_array($field,
                            Model::serviceFields)) || (empty($this->tableRow['with_order_field']) && $field == 'n')) {
                    throw new errorException('Поля [[' . $field . ']] в таблице [[' . $tableRow['name'] . ']] не существует');
                }
                if (in_array($field, Model::serviceFields)) {
                    $order .= $field . ' ' . $AscDesc;
                } else {
                    $order .= '(' . $field . '->>\'v\')::' . ($fields[$field]['type'] == 'number' ? 'numeric' : 'text') . ' ' . $AscDesc;
                }

            }
        }


        if ($returnType == 'field')
            $limit = '0,1';
        else $limit = null;


        $fieldsString = '';
        foreach (array_merge($params['field'], (array)($params['tfield'] ?? [])) as $f) {
            if ($fieldsString !== '') $fieldsString .= ', ';
            $fieldsString .= $f;
        }


        if ($returnType == 'rows' || $returnType == 'row') {

            //техническая выборка - не трогать
            if ($params['field'] == ['__all__']) {
                return $this->model->getAll($where, '*', $order, $limit);
            }


            if ($returnType == 'rows') {
                $rows = $this->model->getAll($where, $fieldsString, $order, $limit);
                if (!empty($params['with__sectionFunction'])) {
                    foreach ($rows as &$row) {
                        $row['__sectionFunction'] = function () use ($sectionReplaces, $row, $params) {
                            return $sectionReplaces($row)[$params['sfield'][0]] ?? null;
                        };
                    }
                    unset($row);
                } else {
                    foreach ($rows as &$row) $row = $sectionReplaces($row);
                    unset($row);
                }
                return $rows;
            } else {
                if ($row = $this->model->get($where, $fieldsString, $order)) {
                    return $sectionReplaces($row);
                } else return [];
            }

        } else {
            if (!empty($_GET['test'])) {

                $r = $this->model->getAll($where, $fieldsString, $order, $limit);

            }
            $r = $this->model->getAll($where, $fieldsString, $order, $limit);


            if ($returnType == 'field') {
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

    function checkInsertRow($addData, $savedFieldName = null)
    {
        $filteredColumns = [];
        foreach ($this->sortedFields['filter'] as $k => $f) {
            if (!empty($f['showInWeb'])) {
                $filteredColumns[$f['column']] = $k;
            }
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
        return $this->tbl['rowInsered'];
    }


    function rowChanged($oldRow, $row, $action)
    {

        $this->cachedSelects = [];
        $this->isTableDataChanged = true;

        if ($actionFields = $this->getFieldsForAction($action, 'column')) {
            foreach ($oldRow as $k => &$v) if (is_string($v)) $v = json_decode($v, true);
            unset($v);
            foreach ($row as $k => &$v) if (is_string($v)) $v = json_decode($v, true);
            unset($v);


            foreach ($actionFields as $field) {
                $old = $oldRow[$field['name']]['v'] ?? null;
                $new = $row[$field['name']]['v'] ?? null;

                if ($action != 'Change' || Calculate::compare('!==', $old, $new)) {
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
                    if (($oldRow[$k] ?? null) != $v) {
                        $changedKeys[] = $k;
                    }
                }
                $this->rowsOperations($action, $row, $changedKeys);
                break;
        }


    }

    function loadRowsByParams($params, $order = null)
    {


        $paramsForFunc = ['where' => $params];

        $paramsForFunc['field'] = ['__all__'];

        if ($order) $paramsForFunc['order'] = $order;

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

        if ($this->getTableRow()['actual'] != 'disable') {
            $where['updated'] = $this->savedUpdated;
        }

        $update = ['updated' => $this->updated];
        $update['header'] = json_encode($this->getTblForSave(), JSON_UNESCAPED_UNICODE);

        if (!Table::init()->update($update, $where)) {
            errorException::tableUpdatedException($this);
        }
        $this->markTableChanged();
        $this->isTableDataChanged = false;
        $this->savedUpdated = $this->updated;
        $this->cachedSelects = [];
        Controller::setSomeTableChanged();

    }

    public function addOrderField()
    {
        Sql::exec('ALTER TABLE ' . $this->tableRow['name'] . ' ADD COLUMN "n" numeric ');
        Sql::exec('Update ' . $this->tableRow['name'] . ' set "n"=id ');
        Sql::exec('CREATE UNIQUE INDEX IF NOT EXISTS ' . $this->tableRow['name'] . '___ind___n ON ' . $this->tableRow['name'] . ' ("n")');
        Sql::exec('ANALYZE ' . $this->tableRow['name']);
    }

    public function removeOrderField()
    {
        Sql::exec('ALTER TABLE ' . $this->tableRow['name'] . ' drop COLUMN IF EXISTS "n" ');
        Sql::exec('ANALYZE ' . $this->tableRow['name']);
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

        self::recalcLog($this->getTableRow()['name'], 'Экшены');

        if ($fieldsWithActionOnChange = $this->getFieldsForAction('Change', 'param')) {
            foreach ($fieldsWithActionOnChange as $field) {
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
        while ($func = array_shift($this->changeIds['rowOperations'])) {
            $func();
        }
        self::recalcLog('..');
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

    function loadDataRow($fromConstructor = false, $force = false)
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
                if (is_array($v)) debug_print_backtrace();
                $v = json_decode($v, true);
            }
        }
        return $row;
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
                && !(!empty($idsFilter) && ((Field::init($field, $this)->isChannelChangeable('modify',
                        $channel)))) // если это запрос на подтверждение прав доступа и фильтр доступен ему на редактирование
            ) {

                if ($fVal_V === '*NONE*' || (is_array($fVal_V) && in_array('*NONE*', $fVal_V))) {
                    $issetBlockedFilters = true;
                    break;

                } elseif ($fVal_V === '*ALL*' || (is_array($fVal_V) && in_array('*ALL*',
                            $fVal_V)) || (!in_array($this->fields[$fName]['type'],
                            ['select', 'tree']) && $fVal_V === '')) {
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
                            && (
                                ($field['data']['withEmptyVal'] ?? null) || Field::isFieldListValues($this->fields[$field['column']]['type'],
                                    $this->fields[$field['column']]['multiple'] ?? false)
                            )
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
            $filteredIds = $this->loadRowsByParams($params, $order);
        }

        if (empty($idsFilter)) {
            $this->changeIds['filteredIds'] = $filteredIds;
        }

        return $filteredIds;
    }


    protected function _copyTableData(&$table, $settings)
    {

        if ($settings['copy_params'] != 'none' || $settings['copy_data'] != 'none') {
            $table['tbl'] = $this->tbl;
            if ($settings['copy_params'] == 'none') {
                unset($table['tbl']['params']);
            }


            if ($settings['copy_data'] != 'none') {
                $where = $this->elseWhere;
                if ($settings['copy_data'] == 'ids') {
                    $intervals = $this->_getIntervals($settings['intervals']);
                    $whereids = '';
                    foreach ($intervals as $i) {
                        if ($whereids != '') $whereids .= ' OR ';
                        if ($i[0] == $i[1])
                            $whereids .= 'id=' . $i[0];
                        else {
                            $whereids .= '(id>=' . $i[0] . ' AND id<=' . $i[1] . ')';
                        }
                    }
                    $where[] = '(' . $whereids . ')';
                }
                $table['tbl']['rows'] = $this->model->getAll($where);
            }
        }
    }

    protected function checkRightFillOrder($id_first, $id_last, $count)
    {
        return $count == Sql::getField('select count(*) from ' . $this->tableRow['name'] . ' where n>=(select n from ' . $this->tableRow['name'] . ' where id = ' . $id_first . ') AND n<=(select n from ' . $this->tableRow['name'] . ' where id = ' . $id_last . ') AND is_del = false');
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
                if (!is_int($id)) throw new errorException('Ошибка клиентской части. Получена строка вместо id');
            }
            $old_order_arrays = Sql::getAll('select n, id from ' . $this->tableRow['name'] . ' where id IN (' . implode(',',
                    $reorder) . ') order by n');
            if (!empty($this->tableRow['order_desc'])) {
                $reorder = array_reverse($reorder);
            }

            foreach ($old_order_arrays as $i => $orderRow) {
                if ($orderRow['id'] == $reorder[0]) {
                    array_splice($reorder, 0, 1);
                    unset($old_order_arrays[$i]);
                } else break;
            }
            if ($reorder) {
                $old_order_arrays_rev = array_reverse($old_order_arrays);
                $reorder_rev = array_reverse($reorder);
                foreach ($old_order_arrays_rev as $i => $orderRow) {
                    if ($orderRow['id'] == $reorder_rev[0]) {
                        array_splice($reorder_rev, 0, 1);
                        unset($old_order_arrays_rev[$i]);
                    } else break;
                }

                $old_order_arrays = [];
                foreach (array_reverse($old_order_arrays_rev) as $oldOrdRow) {
                    $old_order_arrays[] = $oldOrdRow['n'];
                }


                $reorder = array_reverse($reorder_rev);
                $orderMinN = $old_order_arrays[0];
                Sql::exec('update ' . $this->tableRow['name'] . ' set n = null where id IN (' . implode(',',
                        $reorder) . ')');

                foreach ($reorder as $i => $rId) {
                    Sql::exec('update ' . $this->tableRow['name'] . ' set n = ' . $old_order_arrays[$i] . ' where id = ' . $rId);
                }
                $this->tbl['rows'] = [];

            }

        }


        $modifiedIds = array_flip(array_merge(array_keys($modify),
            array_keys($setValuesToDefaults),
            array_keys($setValuesToPinned)));
        unset($modifiedIds['params']);
        $modifiedIds = array_flip($modifiedIds);


        switch ($calculate) {
            case aTable::CalcInterval['all_filtered']:
                $modifiedIds = array_unique(array_merge($modifiedIds, $this->getFilteredIds($channel, [])));
                break;
            case aTable::CalcInterval['all']:
                $modifiedIds = array_unique(array_merge($modifiedIds, $this->loadRowsByParams([])));
                break;
        }


        if ($duplicate) {
            if ($notLoadedDuplicates = array_diff($duplicate['ids'], array_keys($this->tbl['rows']))) {
                $notLoadedDuplicatesRows = $this->model->getAllIndexedById(['id' => $duplicate['ids']] + $this->elseWhere);
                $this->rowsOperations('Load', null, $notLoadedDuplicatesRows);
            }

            foreach ($duplicate['ids'] as $baseRowId) {
                $row = $this->duplicateRow($channel,
                    $this->tbl['rows'][$baseRowId],
                    ($duplicate['replaces'][$baseRowId] ?? []),
                    $addAfter
                );
                $modifiedIds[] = $row['id']; //Для пересчета строки при дублировании, чтобы не сыпались ошибки обращения к #id;
                if (is_null($orderMinN) || $orderMinN > $row['n']) $orderMinN = $row['n'];
                if ($addAfter) {
                    $addAfter = $row['id'];
                }
            }
        }

        if ($add) {
            if (!empty($this->tableRow['with_order_field'])) {
                $fIds = $channel != 'inner' ? $this->getFilteredIds($channel, []) : [];
                $afterN = null;
                if ("0" === (string)$addAfter) {
                    $afterN = 0;
                } elseif ($addAfter) {
                    $this->loadRowsByIds([$addAfter]);
                    if (!empty($this->tbl['rows'][$addAfter])) {
                        $afterN = $this->tbl['rows'][$addAfter]['n'];
                    } else throw new errorException('Строки с id ' . $addAfter . ' не существует. Возможно, она была удалена');
                }
            }

            foreach ($add as $rAdd) {
                if ($this->tableRow['with_order_field'] ?? false) {
                    if ((!is_null($afterN) || $this->issetActiveFilters($channel)) && $n = $this->getNextN($fIds,
                            $afterN)) {
                        $afterN = $rAdd['n'] = $n;
                    }
                }

                $row = $this->addRow($channel, $rAdd, false, $addWithId, 0, $isCheck);

                if ($this->tableRow['with_order_field'] ?? false) {
                    if (is_null($orderMinN) || $orderMinN > $row['n']) $orderMinN = $row['n'];

                }
                if (!$isCheck)
                    $modifiedIds[] = $row['id']; //Для пересчета строки при добавлении, чтобы не сыпались ошибки обращения к #id;
            }
        }
        if (!empty($this->tableRow['recalc_in_reorder'])) {
            if (!is_null($orderMinN)) {
                $modifiedIds = array_merge($modifiedIds,
                    Sql::getFieldArray('select id from ' . $this->tableRow['name'] . ' where n>=' . $orderMinN));
                $modifiedIds = array_unique($modifiedIds);

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
                    $modifyCalculated);

            }
        }
    }


    function normalizeN()
    {
        Sql::exec('drop index ' . $this->tableRow['name'] . '___ind___n');
        Sql::exec('update ' . $this->tableRow['name'] . ' l set n=n.nn FROM (SELECT id, n, row_number() OVER (ORDER BY n)  AS nn ' .
            'FROM ' . $this->tableRow['name'] . ') n WHERE l.id=n.id');
        Sql::exec('create unique index if not exists ' . $this->tableRow['name'] . '___ind___n  on ' . $this->tableRow['name'] . '(n)');

        $this->nTailLength = 0;

        $this->saveTable();
    }

    protected function getNewTblForRecalculate()
    {
        return [
            'rows' => $this->tbl['rows'],
            'params' => []
        ];
    }

    protected
    function rowsOperations($operation, $row = null, $rowsIndexedByIdOrChanges = [])
    {
        switch ($operation) {
            case 'Delete':

                $this->changeIds['deleted'][$row['id']] = null;
                foreach ($this->sortedFields['column'] as $field) {
                    if ($field['type'] === 'file') {
                        $this->loadRowsByIds([$row['id']]);
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

                if (empty($this->changeIds['changed'][$row['id']])) $this->changeIds['changed'][$row['id']] = [];

                $this->changeIds['changed'][$row['id']] += array_flip($rowsIndexedByIdOrChanges);
                $this->tbl['rows'][$row['id']] = $row;
                break;
        }

    }

    protected
    function loadModel()
    {
        $this->model = Model::init($this->tableRow['name']);
    }

    protected
    function modifyRow($channel, $modify = [], $setValuesToDefaults = [], $setValuesToPinned = [], $oldRow, $modifyCalculated = true, $saveIt = true)
    {

        $changedData = ['id' => $oldRow['id']];

        if (!empty($this->tableRow['with_order_field'])) {
            $changedData['n'] = $oldRow['n'];
        }
        foreach ($this->sortedFields['column'] as $k => $v) {
            $newVal = $modify[$k] ?? null;

            $oldRow[$k] = ($oldVal = $oldRow[$k] ?? null);

            $field = Field::init($v, $this);
            $changedFlag = $field->getModifyFlag(array_key_exists($k, $modify),
                $newVal,
                $oldVal,
                array_key_exists($k, $setValuesToDefaults),
                array_key_exists($k, $setValuesToPinned),
                $modifyCalculated);

            $changedData[$v['name']] = $field->modify(
                $channel,
                $changedFlag,
                $newVal,
                $oldRow,
                $changedData,
                $this->savedTbl,
                $this->tbl,
                !$saveIt);


        }

        unset($changedData['id']);
        unset($changedData['n']);

        if ($saveIt == false) return array_merge($oldRow, $changedData);

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
            foreach ($changedSaveData as &$fData) $fData = json_encode($fData, JSON_UNESCAPED_UNICODE);

            if ($result = $this->model->update($changedSaveData,
                ['id' => $oldRow['id']] + $this->elseWhere,
                0,
                $oldRow)
            ) {
                $row = $this->model->get(['id' => $oldRow['id']] + $this->elseWhere);
                $row = $this->decodeRow($row);
                if ($row != $oldRow) {
                    $this->isTableDataChanged = true;
                    $this->rowChanged($oldRow, $row, 'Change');

                    /******aLog  modify clear *****/
                    foreach ($row as $k => $v) {
                        if (!key_exists($k, $this->fields)) continue;

                        $Field = Field::init($this->fields[$k], $this);
                        $this->addToALogModify($Field,
                            $channel,
                            $this->tbl,
                            $row,
                            $row['id'],
                            $modify,
                            $setValuesToDefaults,
                            $setValuesToPinned,
                            $oldRow[$Field->getName()] ?? []);
                    }
                }
                /******aLog*****/

                return $row;
            }
        }
        return $oldRow;

    }

    protected
    function duplicateRow($channel, $baseRow, $replaces, $addAfter)
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
            if (is_null($field['default']) && empty($field['code']) && $field['type'] != "comments") {
                $newRowData[$field['name']] = $baseRow[$field['name']]['v'];
            }
        }
        if (!empty($this->tableRow['with_order_field'])) {
            if ($addAfter) {
                $this->loadRowsByIds([$addAfter]);
                if ($n = $this->getNextN(null, $this->tbl['rows'][$addAfter]['n'])) {
                    $newRowData['n'] = $n;
                }
            } else if ($n = $this->getNextN(null, $baseRow['n'])) {
                $newRowData['n'] = $n;
            }
        }

        /******Расчет дублированной строки для  REAL-таблиц********/

        $row = $this->addRow('inner', $newRowData, true, false, $baseRow['id']);
        $this->changeIds['duplicated'][$baseRow['id']] = $row['id'];
        return $row;
    }

    static function getNSize($n)
    {
        return strlen($n) - strpos($n, '.') - 1;
    }

    protected function getNextN($idRows = null, $prevN = null)
    {
        if (!empty($idRows) && is_null($prevN)) {
            $idRows = implode(', ', $idRows);

            if (empty($this->tableRow['order_desc'])) {
                $prevN = Sql::getField('select MAX(n) from ' . $this->tableRow['name']
                    . ' where id in (' . $idRows . ')');
            } else {
                $prevN = Sql::getField('select MIN(n) from ' . $this->tableRow['name']
                    . ' where id in (' . $idRows . ')');
            }
        }
        if (!is_null($prevN)) {
            if (!empty($this->tableRow['order_desc'])) {
                $nextN = Sql::getField($q = 'select MAX(n) from ' . $this->tableRow['name'] . ' where n<' . $prevN);
            } else {
                $nextN = Sql::getField($q = 'select MIN(n) from ' . $this->tableRow['name'] . ' where n>' . $prevN);
            }
            if ($nextN) {
                $scalePrev = static::getNSize($prevN);
                $scaleNext = static::getNSize($nextN);
                $scale = $scaleNext < $scalePrev ? $scalePrev : $scaleNext;

                $diff = bcsub($nextN, $prevN, $scale);
                $scaleDiff = static::getNSize($diff);
                $len = 4;

                while (bccomp($diff,
                        ($nPlus = '0.' . (str_repeat('0', $len - 1)) . '1'),
                        $scaleDiff > $len ? $scaleDiff : $len) != 1) {
                    $len += 4;
                }


                $n = bcadd($prevN, $nPlus, $len < $scalePrev ? $scalePrev : $len);
                $scaleN = static::getNSize($n);
                $scaleComp = $scaleN > $scaleNext ? $scaleN : $scaleNext;
                if (bccomp($n, $nextN, $scaleComp) != -1) {
                    throw new SqlExeption("Ошибка логики n: $n>=$nextN");
                }
                if ($this->nTailLength < $scaleComp) $this->nTailLength = $scaleComp;

            } else
                $n = bcadd($prevN, 1, 0);

        }
        if (!empty($n)) {
            return $n;
        }
    }

    protected
    function addRow($channel, $addData, $fromDuplicate = false, $addWithId = false, $duplicatedId = 0, $isCheck = false)
    {


        $changedData = ['id' => ''];

        if ($addWithId && ($id = (int)($addData['id'] ?? 0)) > 0) {
            if ($this->model->get(['id' => $id], 'id')) {
                throw new errorException('id ' . $id . ' в таблице уже существует. Нельзя добавить повторно');
            }
            $changedData['id'] = $id;
        }

        if (!empty($this->tableRow['with_order_field'])) {
            if (!empty($addData['n'])) {
                $changedData['n'] = $addData['n'];
            } else {
                if (empty($id)) {
                    $id = Sql::getField('SELECT nextval(\'' . $this->tableRow['name'] . '_id_seq\')');
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
                $this->tbl['rowInsered'] = $changedData;
                return false;
            }

            $changedSaveData = $changedData;

            foreach ($changedSaveData as $k => &$fData) {
                if ($k !== 'n') {
                    $fData = json_encode($fData, JSON_UNESCAPED_UNICODE);
                }
            }
            unset($fData);

            $this->isTableDataChanged = true;

            if ($resultId = $this->model->insert($changedSaveData)) {
                $row = $this->decodeRow($this->model->getById($resultId));
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

        $fields = $this->fields;

        if ($withoutDeleted && count($paramsWhere) == 1 && $paramsWhere[0]["field"] == 'id' && $paramsWhere[0]["operator"] == '=') $withoutDeleted = false;

        foreach ($paramsWhere as $wI) {

            if (!is_array($wI)) {
                $where[] = $wI;
                continue;
            }

            $field = $fieldName = $wI['field'];
            $operator = $wI['operator'];
            $value = $wI['value'];
            if (!array_key_exists($field, $fields) && !in_array($field, Model::serviceFields)) {
                throw new errorException('Поля [[' . $field . ']] в таблице [[' . $this->tableRow['name'] . ']] не существует');
            }

            $isMustBeInteger = ($fieldName === 'id' || $fieldName === 'n' || $fields[$fieldName]['type'] === 'number');

            if (in_array($field, Model::serviceFields)) {
                $field = '"' . $field . '"';
            } else {
                $field = "($field->>'v')";
                if ($isMustBeInteger)
                    $field .= '::NUMERIC';
            }

            /*Поиск в полях-листах*/
            if (Field::isFieldListValues($fields[$wI['field']]['type'] ?? null,
                $fields[$wI['field']]['multiple'] ?? false)) {
                if (!in_array($operator, ['=', '!=', '==', '!=='])) {
                    throw new errorException('Операторы для работы с листами только [[=]] и [[!=]]');
                }

                if ($operator == '!==') {
                    /*Сравнение с пустой строкой и с пустым листом*/
                    if (empty($value) && ($value === '' || is_array($value))) {
                        $where[] = "(" . $wI["field"] . "->>'v'" . " is NOT NULL OR " . $wI["field"] . "->>'v'!='[]')";
                    } /*Сравнение с листом*/
                    elseif (is_array($value)) {
                        $where[] = "(" . $wI["field"] . "->'v' != '" . json_encode($value,
                                JSON_UNESCAPED_UNICODE) . "'::JSONB )";
                    } /*Сравнение с числом или строкой*/
                    else {
                        if (is_bool($value)) $value = $value ? 'true' : 'false';

                        $where[] = "(" . $wI["field"] . "->>'v' != " . Sql::quote((string)$value) . ")";
                    }

                } elseif ($operator == '==') {
                    /*Сравнение с пустой строкой и с пустым листом*/
                    if (empty($value) && ($value === '' || is_array($value) || is_null($value))) {
                        $where[] = "(" . $wI["field"] . "->>'v'" . " is NULL OR " . $wI["field"] . "->>'v'='' OR " . $wI["field"] . "->>'v'='[]')";
                    } /*Сравнение с листом*/
                    elseif (is_array($value)) {
                        $where[] = "(" . $wI["field"] . "->'v' = '" . json_encode($value,
                                JSON_UNESCAPED_UNICODE) . "'::JSONB )";
                    } /*Сравнение с числом или строкой*/
                    else {
                        if (is_bool($value)) $value = $value ? 'true' : 'false';

                        $where[] = "(" . $wI["field"] . "->>'v' = " . Sql::quote((string)$value) . ")";
                    }

                } else {


                    /*Сравнение с пустой строкой*/
                    if (empty($value) && ($value === '' || is_null($value))) {
                        $where[] = "(" . $wI["field"] . "->>'v'" . " is NULL OR " . $wI["field"] . "->>'v'='' OR " . $wI["field"] . "->>'v'='[]' OR " . $wI["field"] . "->'v' @> '[\"\"]'::jsonb OR " . $wI["field"] . "->'v' @> '[null]'::jsonb ) = " . ($operator == '!=' ? 'false' : 'true');
                    } /*Сравнение с пустым листом*/
                    elseif (empty($value) && $value === []) {

                        $where[] = "(" . $wI["field"] . "->>'v'" . " is NULL OR " . $wI["field"] . "->>'v'='' OR " . $wI["field"] . "->>'v'='[]') = " . ($operator == '!=' ? 'false' : 'true');


                    } /*Сравнение с листом*/
                    else if (is_array($value)) {


                        if ($fields[$wI['field']]['type'] == 'listRow') {
                            $isAssoc = (array_keys($value) !== range(0, count($value) - 1));

                            $where_tmp = '';
                            foreach ($value as $k => $v) {
                                if ($where_tmp !== '')
                                    $where_tmp .= ' OR ';
                                if ($isAssoc) {
                                    if (is_numeric((string)$v)) {
                                        $where_tmp .= $wI['field'] . '->\'v\' @> ' . Sql::quote("{\"$k\":$v}") . '::jsonb OR ';
                                    }
                                    $where_tmp .= $wI['field'] . '->\'v\' @> ' . Sql::quote(json_encode([$k => (string)$v],
                                            JSON_UNESCAPED_UNICODE)) . '::jsonb';
                                } else {
                                    if (is_numeric((string)$v)) {
                                        $where_tmp .= $wI['field'] . '->\'v\' @> ' . Sql::quote("[$v]") . '::jsonb OR ';
                                    }
                                    $where_tmp .= $wI['field'] . '->\'v\' @> ' . Sql::quote(json_encode([(string)$v],
                                            JSON_UNESCAPED_UNICODE)) . '::jsonb';
                                }

                            }
                            $q = '(' . $where_tmp . ') = ' . ($operator == '!=' ? 'false' : 'true');

                        } else {
                            $where_tmp = '';
                            foreach ($value as $v) {
                                if ($where_tmp !== '') $where_tmp .= ' OR ';
                                $where_tmp .= $wI['field'] . '->\'v\' @> ' . Sql::quote(json_encode([(string)$v],
                                        JSON_UNESCAPED_UNICODE)) . '::jsonb';
                            }
                            $q = '(' . $where_tmp . ') = ' . ($operator == '!=' ? 'false' : 'true');
                        }


                        $where[] = $q;


                    } elseif (is_bool($value) || in_array((string)$value, ["true", "false"])) {
                        if (is_bool($value))
                            $value = $value ? "true" : "false";

                        $where[] = "(" . $wI["field"] . "->>'v' $operator '" . $value . "' OR " . $wI['field'] . "->'v' @> '[\"" . $value . "\"]'::jsonb $operator true  OR " . $wI['field'] . "->'v' @> '[" . $value . "]'::jsonb $operator true)";

                    } /*Сравнение с числом или строкой*/
                    else {
                        $q = $wI['field'] . '->\'v\' @> ' . Sql::quote(json_encode([(string)$value],
                                JSON_UNESCAPED_UNICODE)) . '::jsonb' . $operator . 'true';

                        $q .= ' OR ' . $wI['field'] . '->>\'v\' ' . $operator . Sql::quote((string)$value);

                        if ($fields[$wI['field']]['type'] == 'listRow' && is_numeric((string)$value)) {
                            $q .= ' OR ' . $wI['field'] . '->\'v\' @> ' . Sql::quote("[$value]") . '::jsonb' . $operator . 'true';
                        }


                        $where[] = $q;
                    }
                }
            } /* Поиск не в полях-листах */
            else {
                if (is_array($value)) {

                    switch ($operator) {
                        case '==':
                            $where[] = 'false';
                            break;
                        case '!==':
                            $where[] = 'true';
                            break;
                        case '<':
                        case '<=':
                        case '>=':
                        case '>':

                            throw new errorException('При сравнении с листом операторы  <=> не допустимы');

                            break;
                        default:

                            if (!in_array($operator, ['=', '!='])) {
                                throw new errorException('Операторы для работы с листами только [[=]] и [[!=]]');
                            }
                            if (empty($value)) {
                                if ($operator == '=') {
                                    $where[] = 'false';
                                } else {
                                    $where[] = 'true';
                                }
                            } else {
                                foreach ($value as $v) {
                                    if (is_array($v)) throw new errorException('В параметре where [[' . $wI['field'] . ']] получен лист, в качестве элемента которого содержится лист');
                                }
                                if (in_array('', $value, true) || in_array(null, $value, true)) {
                                    unset($value[array_search('', $value)]);
                                    $sqlSearch = 'false';
                                    if (!empty($value)) {
                                        $sqlSearch = $field . ' ' . ($operator == '=' ? 'IN' : 'NOT IN') . ' (' . implode(', ',
                                                Sql::quote($value, $isMustBeInteger)) . ')';
                                    }
                                    $where[] = '(' . $sqlSearch . ' OR ' . $field . ' IS ' . ($operator == '=' ? '' : 'NOT') . ' NULL )';

                                } else {

                                    if (empty($value)) $where[] = 'false';
                                    else {
                                        $where[] = '(' . $field . ' ' . ($operator == '=' ? 'IN' : 'NOT IN') . ' (' . implode(', ',
                                                Sql::quote($value,
                                                    $isMustBeInteger)) . ') ' . ($operator == '=' ? '' : 'OR ' . $field . ' IS NULL') . ') ';
                                    }
                                }
                            }
                    }

                } else {
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
                                $where[] = '(' . $field . '::text = \'\' OR ' . $field . ' is NULL)';
                                break;
                            case '!=':
                            case '>':
                                $where[] = '(' . $field . '::text != \'\' AND ' . $field . ' is NOT NULL)';
                                break;
                            case '>=':
                                $where[] = 'true';
                                break;
                            case '<':
                                $where[] = 'false';
                                break;
                        }

                    } else {
                        if ($operator == '!=') {
                            $operator = 'IS DISTINCT FROM';
                        }
                        if ($isMustBeInteger) $field = "($field)::NUMERIC";
                        $where[] = '' . $field . ' ' . $operator . ' ' . Sql::quote($value,
                                $isMustBeInteger) . (in_array($operator,
                                ['<', '<=']) ? ' OR (' . $field . '::text = \'\' OR ' . $field . ' is NULL)' : '');

                    }
                }
            }
        }

        if ($withoutDeleted) {
            $where[] = 'is_del = false';
        }

        return $where;
    }

}