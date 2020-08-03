<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 14.03.17
 * Time: 10:23
 */

namespace totum\tableTypes;


use totum\common\aLog;
use totum\common\Auth;
use totum\common\Calculate;
use totum\common\CalculateAction;
use totum\common\CalculcateFormat;
use totum\common\Controller;
use totum\common\Crypt;
use totum\common\errorException;
use totum\common\Field;
use totum\common\FieldModifyItem;
use totum\common\IsTableChanged;
use totum\common\Log;
use totum\common\Cycle;
use totum\common\Mail;
use totum\common\Model;
use totum\common\reCalcLogItem;
use totum\common\Sql;
use totum\config\Conf;
use totum\fieldTypes\FieldParams;
use totum\fieldTypes\File;
use totum\fieldTypes\Select;
use totum\models\CalcsTableCycleVersion;
use totum\models\Table;
use totum\models\TablesFields;
use totum\models\UserV;

abstract class aTable extends _Table
{
    const CalcInterval = [
        'changed' => 1
        , 'all_filtered' => 2
        , 'all' => 3
        , 'no' => 4
    ];


    protected function __construct($tableRow, $extraData = null, $light = false, $hash = null)
    {
        parent::__construct($tableRow, $extraData, $light, $hash);
        $this->tableRow['pagination'] = $this->tableRow['pagination'] ?? '0/0';
    }

    static $isActionRecalculateDone = false;  //Выставляется в true, если был выполнен actionSet actionInsert или actionRecalculate
    static $recalcLog = [];


    protected $TablesFieldsVertion = null;//Активная версия

    protected $__cacheForActionFields = [];
    protected $filtersFromUser;
    protected $calculatedFilters;
    private $isNowTable = false;
    /**
     * @var array|bool|string
     */
    private $anchorFilters = [];
    /**
     * @var array|bool|string
     */
    protected $webIdInterval = [];


    static function init($tableRow, $extraData = null, $light = false)
    {
        return new static($tableRow, $extraData, $light, null);
    }

    static function getFooterColumns($columns)
    {

        $footerColumns = ['' => []];
        foreach ($columns as $k => $f) {
            if (!empty($f['column'])) {
                $footerColumns[$f['column']][$k] = $f;
            } else {
                $footerColumns[''][$k] = $f;
            }
        }
        return $footerColumns;
    }

    /**
     * @param array $anchorFilters
     */
    public function setAnchorFilters($anchorFilters): void
    {
        $this->anchorFilters = $anchorFilters;
    }

    function checkIsUserCanViewIds($ids, $channel, $loadForse = false)
    {
        if ($channel != 'inner') {
            $filtered = false;

            $filtersFields = $channel == 'web' ? $this->sortedFields["filter"] : $this->sortedXmlFields["filter"];

            if (!Auth::isCreator() && $filtersFields) {
                $this->reCalculateFilters($channel, $inVars['isEditFilters'] ?? false);

                foreach (array_intersect_key($this->tbl['params'],
                    $filtersFields) as $flName => $flValues) {
                    if (!is_null($fVal_V = $this->tbl['params'][$flName]['v']) //не "Все"
                        && !(is_array($fVal_V) && count($fVal_V) == 0)
                        && !($fVal_V === '*ALL*' && in_array($this->sortedFields['filter'][$flName]['type'],
                                ['tree', 'select'])
                        )
                        && (!Field::init($this->sortedFields['filter'][$flName],
                            $this)->isChannelChangeable('modify',
                            $channel)) // если фильтр не доступен ему на редактирование
                    ) {
                        $filtered = true;
                        break;
                    }

                }
            }
            if ($filtered) {
                $getFiltered = $this->getFilteredIds($channel, $ids);
                foreach ($ids as $id) {
                    if (!in_array($id, $getFiltered)) {
                        throw new errorException('Строка с id ' . $id . ' недоступна вам с текущими настроками фильтров.');
                    }
                }
            }

            if ($loadForse) {
                $this->loadRowsByIds($ids);
            }
        }
    }

    protected function checkIsUserCanModifyIds($modify, $removeIds, $channel)
    {
        unset($modify['params']);
        if ($channel != 'inner' && !Auth::isCreator()) {
            if ($idsToModify = array_merge(array_keys($modify), $removeIds)) {
                $filtered = false;

                $filtersFields = $channel == 'web' ? $this->sortedFields["filter"] : $this->sortedXmlFields["filter"];

                if (!$filtersFields) return;
                $this->reCalculateFilters($channel, $inVars['isEditFilters'] ?? false);

                foreach (array_intersect_key($this->tbl['params'],
                    $filtersFields) as $flName => $flValues) {
                    if (!is_null($fVal_V = $this->tbl['params'][$flName]['v']) //не "Все"
                        && !(is_array($fVal_V) && count($fVal_V) == 0)
                        && !($fVal_V === '*ALL*' && in_array($this->sortedFields['filter'][$flName]['type'],
                                ['tree', 'select'])
                        )
                        && (!Field::init($this->sortedFields['filter'][$flName],
                            $this)->isChannelChangeable('modify',
                            $channel)) // если фильтр не доступен ему на редактирование
                    ) {
                        $filtered = true;
                        break;
                    }

                }
                if ($filtered) {
                    $getFiltered = $this->getFilteredIds($channel, $idsToModify);
                    foreach ($idsToModify as $id) {
                        if (!in_array($id, $getFiltered)) {
                            throw new errorException('Строка с id ' . $id . ', которую вы попытались изменить/удалить не существует или недоступна вам с текущими настроками фильтров.');
                        }
                    }
                }

            }
        }

    }

    static function getUpdatedJson()
    {
        return json_encode(['dt' => date('Y-m-d H:i'), 'code' => mt_rand(), 'user' => Auth::getUserId()]);
    }

    function setNowTable()
    {
        $this->isNowTable = true;
    }

    function isNowTable()
    {
        return $this->isNowTable;
    }

    function getSavedUpdated()
    {
        return $this->savedUpdated;
    }

    abstract function createTable();

    function reCalculateFromOvers($inVars = [])
    {
        $this->reCalculate($inVars);
        $this->isTblUpdated(0);

    }

    public function __get($name)
    {
        switch ($name) {
            case 'updated':
                return $this->updated;
            case 'params':
                return $this->tbl['params'] ?? [];
            case 'columnVisibleFields':
                return $this->sortedVisibleFields['column'] ?? [];
            case 'addedIds':
                return array_keys($this->changeIds['added']);
            case 'deletedIds':
                return array_keys($this->changeIds['deleted']);
            case 'isOnSaving':
                return $this->isOnSaving;
        }

        $debug = debug_backtrace(0, 3);
        //array_splice($debug, 0, 1);
        throw new errorException('Запрошено несуществующее свойство [[' . $name . ']]' . print_r($debug, 1));
    }

    abstract function addField($field);

    function selectSourceTableAction($fieldName, $itemData)
    {
        if (empty($this->fields[$fieldName]['selectTableAction'])) {
            if (!empty($this->fields[$fieldName]['selectTable'])) {
                $this->fields[$fieldName]['selectTableAction'] = '=: linkToPanel(table: "' . $this->fields[$fieldName]['selectTable'] . '"; id: #' . $fieldName . ')';

            } else {
                throw new errorException('Поле не настроено');
            }
        }

        $CA = new CalculateAction($this->fields[$fieldName]['selectTableAction']);
        try {
            $CA->execAction($fieldName, $itemData, $itemData, $this->tbl, $this->tbl, $this);
        } catch (errorException $e) {
            if (Auth::isCreator()) {
                $e->addPath('Таблица [[' . $this->tableRow['name'] . ']]; Поле [[' . $this->fields[$fieldName]['title'] . ']]');
            } else {
                $e->addPath('Таблица [[' . $this->tableRow['title'] . ']]; Поле [[' . $fieldName . ']]');
            }
            throw $e;
        }

    }

    function getTableDataForPrint($ids, $fields)
    {

        $ids = $ids ?? [];

        $data = ['params' => $this->tbl['params'], 'rows' => []];

        $this->reCalculateFilters('web');
        $fIds = $this->getFilteredIds('web', []);
        $ids = array_unique(array_intersect($ids, $fIds));

        $this->loadRowsByIds($ids);

        foreach ($ids as $id) {
            $data['rows'][$id] = $this->tbl['rows'][$id];
        }
        $data['params'] = $this->tbl['params'];

        $data = $this->getValuesAndFormatsForClient($data, 'print', $fields);
        return $data;
    }

    function getTableDataForRefresh($offset = null, $onPage = null)
    {
        $data = ['params' => $this->tbl['params'], 'rows' => []];


        if (is_a($this, tmpTable::class)) {
            $this->reCalculateFromOvers();
        } else {
            $this->reCalculateFilters('web');

            if ($this->calculatedFilters['web'] ?? null) {
                $fIds = $this->getFilteredIds('web', []);
                $ids = $fIds;
                if ($this->changeIds['added']) $ids = array_merge($ids, array_keys($this->changeIds['added']));
            }
        }

        if (is_null($ids ?? null)) {
            $ids = $this->getByParams(['field' => 'id'], 'list');
        }
        $this->loadRowsByIds($ids);

        foreach ($ids as $id) {
            if (!empty($this->tbl['rows'][$id])) {
                $data['rows'][$id] = $this->tbl['rows'][$id];
            }
        }

        if (!empty($this->getTableRow()['new_row_in_sort'])) {
            $n = array_column($data['rows'], 'n');
            array_multisort($n, SORT_NUMERIC | SORT_ASC, $data['rows']);
            $data['nsorted_ids'] = array_column($data['rows'], 'id');
        }

        if ($offset !== null && $onPage > 0) {
            $orderFN = $this->orderFieldName;
            if (in_array($orderFN, ['id', 'n']) || !in_array($orderFN, ['tree', 'select'])) {
                $this->sortRowsBydefault($data['rows']);
            } else {
                $data = $this->Table->getValuesAndFormatsForClient($data, 'web');
                $this->sortRowsBydefault($data['rows']);
            }
            $allcount = count($data['rows']);
            $offset = $offset;

            $data['rows'] = array_slice($data['rows'], $offset, $onPage);
        }
        $data['rows'] = array_combine(array_column($data['rows'], 'id'), $data['rows']);

        $data = $this->getValuesAndFormatsForClient($data, 'web');


        $data['f'] = $this->getTableFormat();
        $data = ['chdata' => $data, 'updated' => $this->updated, 'refresh' => true, 'offset' => $offset ?? null, 'allCount' => $allcount ?? null];
        return $data;
    }

    function setWebIdInterval($ids)
    {
        if ($ids && is_array($ids)) {
            $this->webIdInterval = $ids;
        }
    }

    static function recalcLog($AddPath, $data = '')
    {
        if (static::$recalcLogPointer) {
            if ($AddPath === '..') {
                static::$recalcLogPointer = static::$recalcLogPointer->getParent();
            } else {
                static::$recalcLogPointer = static::$recalcLogPointer->getChild($AddPath, $data === 'Пересчет');
            }
        }
    }

    function getTableDataForInterface($withoutData = false, $withoutRowsData = false)
    {
        $table = $this->tableRow;

        try {

            $inVars = ['calculate' => aTable::CalcInterval['changed']
                , 'channel' => 'web'
                , 'isTableAdding' => ($this->tableRow['type'] === 'tmp' && $this->isTableAdding)
            ];
            Sql::transactionStart();
            $oldTable = $this->tbl;
            $updated = $this->updated;
            $this->reCalculate($inVars);
            $this->isTblUpdated();
            Sql::transactionCommit();

        } catch (errorException $e) {

            Sql::transactionRollBack();
            $table['error'] = $e->getMessage() . ' <br/> ' . $e->getPathMess();

            $this->tbl = $oldTable;
            $this->updated = $updated;
            $this->reCalculateFilters('web', false, true);
        }


        if (!$withoutData) {
            if ($withoutRowsData) {
                $data = ['params' => $this->tbl['params']];
            } else {
                $data = $this->getFilteredData('web');
            }
        }


        if (empty($data)) {
            $table['f'] = [];
            $table['data'] = [];
            $table['params'] = [];
        } else {
            $data = $this->getValuesAndFormatsForClient($data, 'web');
            $this->sortRowsBydefault($data['rows']);
            $table['f'] = $this->getTableFormat();
            $table['data'] = $data['rows'];
            $table['params'] = $data['params'];
        }

        $_filters = [];
        $table['fields'] = $this->visibleFields;

        foreach ($this->sortedVisibleFields['filter'] as $k => $field) {
            $_filters[$k] = $this->tbl['params'][$k]['v'] ?? null;
            /*Не выводить поля скрытых фильтров*/
            if (!Auth::isCreator() && !empty($field['webRoles']) && count(array_intersect($field['webRoles'],
                    Auth::$aUser->getRoles())) == 0) {
                unset($table['fields'][$k]);
            }
        }
        $table['filtersString'] = Crypt::getCrypted(json_encode($_filters, JSON_UNESCAPED_UNICODE));

        $table['hidden_fields'] = $this->hiddenFields;

        foreach ($table['fields'] as $k => &$f) {
            if ($f['logButton'] = $f['logging'] ?? true) {
                if ($f['type'] == 'button') {
                    $f['logButton'] = false;
                } else {
                    if (!empty($f['logRoles']) && !array_intersect(Auth::$aUser->getRoles(), $f['logRoles']))
                        $f['logButton'] = false;
                }
            }
        }
        $this->addLinkToSelectTableSinFields($table['fields']);

        $table['data'] = array_values($table['data']);
        $table['readOnly'] = $this->readOnly;
        $table['deleting'] = !$this->readOnly && Table::isUserCanAction('delete', $this->tableRow);
        $table['adding'] = !$this->readOnly && Table::isUserCanAction('insert', $this->tableRow);
        $table['duplicating'] = !$this->readOnly && Table::isUserCanAction('duplicate', $this->tableRow);
        $table['withCsvButtons'] = Table::isUserCanAction('csv', $this->tableRow);
        $table['withCsvEditButtons'] = Table::isUserCanAction('csv_edit', $this->tableRow);
        $table['updated'] = $this->updated;
        return $table;
    }


    /*Устаревшая*/
    function getDataForXml()
    {
        $table = $this->tableRow;

        $this->reCalculate(['calculate' => aTable::CalcInterval['changed'], 'channel' => 'xml']);

        $this->isTblUpdated();
        $data = $this->getFilteredData('xml');


        $data = $this->getValuesAndFormatsForClient($data, 'xml');

        $table['data'] = $data['rows'];
        $table['params'] = $data['params'];
        $table['fields'] = $this->sortedXmlFields;
        $table['updated'] = $this->updated;
        return $table;
    }

    abstract public function saveTable();

    public function markTableChanged()
    {
        if ($this->tableRow['actual'] !== 'none') {
            $isChanged = new IsTableChanged($this->tableRow['id'],
                (is_a($this, calcsTable::class) ? $this->Cycle->getId() : 0));
            $updated = json_decode($this->updated, true);
            $isChanged->setChanged($updated['code'],
                date_create_from_format('Y-m-d H:i:s', $updated['dt'] . ':00')->format('U'));
        }
    }

    function modify($tableData, array $data)
    {
        $modify = $data['modify'] ?? [];
        $remove = $data['remove'] ?? [];
        $add = $data['add'] ?? null;
        $duplicate = $data['duplicate'] ?? [];
        $reorder = $data['reorder'] ?? [];

        if ($add && !Table::isUserCanAction('insert',
                $this->tableRow)) throw new errorException('Добавление в эту таблицу вам запрещено');
        if ($remove && !Table::isUserCanAction('delete',
                $this->tableRow)) throw new errorException('Удаление из этой таблицы вам запрещено');
        if ($duplicate && !Table::isUserCanAction('duplicate',
                $this->tableRow)) throw new errorException('Дублирование в этой таблице вам запрещено');
        if ($reorder && !Table::isUserCanAction('reorder',
                $this->tableRow)) throw new errorException('Сортировка в этой таблице вам запрещена');


        $click = $data['click'] ?? [];
        $refresh = $data['refresh'] ?? [];


        $this->checkTableUpdated($tableData);

        $inVars = [];
        $inVars['modify'] = [];
        $inVars['channel'] = $data['channel'] ?? 'web';


        if (array_intersect_key($this->sortedVisibleFields['filter'],
            $modify['params'] ?? [])) {

            $inVars["isEditFilters"] = true;

            foreach ($this->sortedVisibleFields['filter'] as $fName => $field) {
                if (array_key_exists($fName, $modify["params"] ?? null)) {
                    if (empty($field['webRoles']) || array_intersect($field['webRoles'], Auth::$aUser->getRoles())) {
                        if (empty($modify['setValuesToDefaults'])) {
                            $this->filtersFromUser[$fName] = $modify["params"][$fName];
                        }
                    }
                }
            }

        }


        if (!empty($modify['setValuesToDefaults'])) {
            unset($modify['setValuesToDefaults']);
            $inVars['setValuesToDefaults'] = $modify;
        } else {
            $inVars['modify'] = $modify;
        }


        $inVars['add'] = !is_null($add) ? [$add] : [];
        $inVars['remove'] = $remove;
        $inVars['duplicate'] = $duplicate;
        $inVars['reorder'] = $reorder;

        if (!empty($data['addAfter'])) {
            $inVars['addAfter'] = $data['addAfter'];
        }


        $inVars['calculate'] = aTable::CalcInterval['changed'];
        if ($refresh) {
            $inVars['modify'] = $inVars['modify'] + array_flip($refresh);
        }
        foreach ($inVars['modify'] as $itemId => &$editData) {//Для  saveRow
            if ($itemId == 'params') continue;
            if (!is_array($editData)) {//Для  refresh
                $editData = [];
                continue;
            }

            foreach ($editData as $k => &$v) {
                if (is_array($v) && array_key_exists('v', $v)) {
                    if (array_key_exists('h', $v)) {
                        if ($v['h'] == false) {
                            $inVars['setValuesToDefaults'][$itemId][$k] = true;
                            unset($editData[$k]);
                            continue;
                        }
                    }
                    $v = $v['v'];
                }
            }
        }
        unset($editData);
        $return = ['chdata' => []];

        if ($click) {

            if ($click['item'] === 'params') {
                $row = $this->tbl['params'];
            } else {
                $this->loadRowsByIds([$click['item']]);
                $row = $this->tbl['rows'][$click['item']] ?? null;
                if (!$row || !empty($row['is_del'])) throw new errorException('Таблица была изменена. Обновите таблицу для проведения изменений');
            }

            try {


                foreach (($this->filtersFromUser ?? []) as $f => $v) {
                    $this->tbl['params'][$f] = ['v' => $v];
                }

                Field::init($this->fields[$click['fieldName']], $this)->action($row,
                    $row,
                    $this->tbl,
                    $this->tbl,
                    ['ids' => $click['checked_ids'] ?? []]);

                //$this->reCalculate(['channel' => 'inner']);


            } catch (\ErrorException $e) {
                throw $e;
            }

            $return['ok'] = 1;
        } else {
            $this->reCalculate($inVars);

            if (!empty($inVars['isEditFilters'])) {

                $filters = [
                    'params' => []
                ];
                $_filters = [];
                foreach ($this->sortedVisibleFields['filter'] as $fName => $sortedVisibleField) {
                    $filters['params'][$fName] = $this->tbl['params'][$fName];
                    $_filters[$fName] = $filters['params'][$fName]['v'];
                }
                $changedData = $this->getValuesAndFormatsForClient($filters, 'web');
                $changedData['filtersString'] = Crypt::getCrypted(json_encode($_filters, JSON_UNESCAPED_UNICODE));

                return $changedData;
            }
        }
        $this->isTblUpdated(0);

        if ($this->tableRow['type'] == 'tmp' || Controller::isSomeTableChanged() || !empty($refresh)) {

            $pageIds = json_decode($_POST['ids'], true);
            $return['chdata']['rows'] = [];

            if ($this->changeIds['added']) {
                $return['chdata']['rows'] = array_intersect_key($this->tbl['rows'], $this->changeIds['added']);
            }

            if ($this->changeIds['deleted']) {
                $return['chdata']['deleted'] = array_keys($this->changeIds['deleted']);
            }
            $modify = $inVars['modify'];
            unset($modify['params']);

            if ($this->changeIds['changed'] += $modify) {

                //Подумать - а не дублируется ли с тем блоком, что ниже
                $selectOrFormatColumns = [];
                foreach ($this->sortedVisibleFields['column'] as $k => $v) {
                    if ((($v['type'] == 'select' || $v['type'] == 'tree') && !empty($v['codeSelectIndividual'])) || !empty($v['format'])) {
                        $selectOrFormatColumns[$k] = true;
                    }
                }

                foreach ($this->changeIds['changed'] as $id => $changes) {

                    if (empty($this->tbl['rows'][$id]) || !in_array($id, $pageIds)) continue;

                    if (empty($changes)) {
                        $return['chdata']['rows'][$id] = $this->tbl['rows'][$id];
                        continue;
                    }
                    $return['chdata']['rows'][$id] = ($return['chdata']['rows'][$id] ?? []) + array_intersect_key($this->tbl['rows'][$id],
                            $changes) + array_intersect_key($this->tbl['rows'][$id], $selectOrFormatColumns);
                    foreach ($changes as $k => $null) {
                        if (is_array($return['chdata']['rows'][$id][$k])) {
                            $return['chdata']['rows'][$id][$k]['changed'] = true;
                        }
                    }
                }

            }

            //Отправка на клиент селектов и форматов
            {
                $selectOrFormatColumns = [];
                foreach ($this->sortedVisibleFields['column'] as $k => $v) {
                    if (in_array($v['type'], ['select', 'tree']) || !empty($v['format'])) {
                        $selectOrFormatColumns[] = $k;
                    }
                }
                if ($selectOrFormatColumns && !empty($pageIds)) {

                    $selectOrFormatColumns[] = 'id';
                    $selectOrFormatColumns = array_flip($selectOrFormatColumns);

                    $this->loadRowsByIds($pageIds);

                    foreach ($pageIds as $id) {
                        if (!empty($this->tbl['rows'][$id])) {
                            $return['chdata']['rows'][$id] = ($return['chdata']['rows'][$id] ?? []) + array_intersect_key($this->tbl['rows'][$id],
                                    $selectOrFormatColumns);
                        }
                    }
                }
            }


            $return['chdata']['params'] = $this->tbl['params'];
            $return['chdata']['f'] = $this->getTableFormat();

            if (!empty($return['chdata']['rows'])) {
                foreach ($return['chdata']['rows'] as $id => &$row) {
                    $row['id'] = $id;
                }
                unset($row);
            }

            $return['chdata']['params'] = $return['chdata']['params'] ?? [];

            $return['chdata'] = $this->getValuesAndFormatsForClient($return['chdata'], 'web');

            if (empty($return['chdata']['params'])) unset($return['chdata']['params']);

            $return['updated'] = $this->savedUpdated;
        }


        return $return;
    }

    abstract function checkEditRow($data, $tableData = null);

    function getChangedString($code)
    {
        if (is_a($this, JsonTables::class)) {
            $this->loadDataRow(false, true);
        } else {
            $this->tableRow = Table::getTableRowById($this->tableRow['id'], true);
        }
        $updated = json_decode($this->getUpdated(), true);

        if ($updated['code'] != $code) {
            return ['username' => UserV::init()->getById($updated['user'])['fio'], 'dt' => $updated['dt'], 'code' => $updated['code']];
        } else return ['no' => true];
    }

    function checkTableIsChanged($code)
    {
        if (is_a($this, JsonTables::class)) {
            $this->loadDataRow();
        }
        $updated = json_decode($this->updated, true);
        if ($updated['code'] != $code) {
            return ['username' => UserV::init()->getById($updated['user'])['fio'], 'dt' => $updated['dt']];
        } else return ['no' => true];

    }

    function addLinkToSelectTableSinFields(&$fields)
    {

        foreach ($this->visibleFields as $f) {
            if (!in_array($f['type'], ['select', 'tree']) || $f['category'] == 'filter') continue;
            if (!empty($f['selectTable'])) {
                if ($table = Table::getTableRowByName($f['selectTable'])) {
                    if (array_key_exists($table['id'], Auth::$aUser->getTables())) {
                        if (Auth::$aUser->getTables()[$table['id']] == 1) {
                            $fields[$f['name']]['changeSelectTable'] = 1;
                            if ($table['insertable'] == true) {
                                $fields[$f['name']]['changeSelectTable'] = 2;
                            }
                        }
                    }

                    $fields[$f['name']]['selectTableId'] = $table['id'];

                    if ($table['type'] != 'calcs') {
                        $fields[$f['name']]['linkToSelectTable'] = ['link' => '/Table/' . $table['top'] . '/' . $table['id'], 'title' => $table['title']];
                    } else {
                        $topTable = Table::getTableRowByid($table['tree_node_id']);
                        $fields[$f['name']]['linkToSelectTable'] =
                            ['link' => '/Table/' . $topTable['top'] . '/' . $topTable['id'] . '/' . $this->Cycle->getId() . '/' . $table['id']
                                , 'title' => $table['title']
                            ];
                    }

                }
            }
        }

    }

    function getByParams($params, $returnType = 'field')
    {
        if (!in_array($returnType, ['field', 'list', 'row', 'rows', 'where', 'row&table'])) {

            echo 'Получен параметр returnType - [[' . $returnType . ']]; Системная ошибка. Программист извещен, но можно позвонить еще: ';
            $dbg = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            echo $dbg[0]['file'] . '-' . $dbg[0]['line'];
            Mail::send(Conf::adminEmail,
                'Ошибка returnType - ' . $returnType,
                '<div style="white-space: pre-wrap">' . print_r($dbg, 1) . '</div>');
            die;

        }


        $this->loadDataRow();

        $fields = $this->fields;

        $params['field'] = (array)($params['field'] ?? []);
        $params['sfield'] = (array)($params['sfield'] ?? []);
        $params['pfield'] = (array)($params['pfield'] ?? []);

        $Field = $params['field'][0] ?? $params['sfield'][0] ?? null;
        if (empty($Field)) throw new errorException('Не указано поле для выборки');

        if (in_array($returnType, ['list', 'field']) && count($params['field']) > 1) {
            throw new errorException('Указано больше одного поля field/sfield');
        }

        $fieldsOrder = $params['fieldOrder'] ?? array_merge($params['field'], $params['sfield'], $params['pfield']);
        $params['fieldOrder'] = $fieldsOrder;

        if (!empty($params['sfield'])) {
            $params['field'] = array_merge($params['field'], $params['sfield']);
        }
        if (!empty($params['pfield'])) {
            $params['field'] = array_merge($params['field'], $params['pfield']);
        }

        foreach ($params['field'] as $fName) {
            if (!array_key_exists($fName, $fields) && !in_array($fName, Model::serviceFields)) {
                throw new errorException('Поля [[' . $fName . ']] в таблице [[' . $this->tableRow['name'] . ']] не существует');
            }
        }

        $sectionReplaces = function ($row) use ($params) {


            $rowReturn = [];
            foreach ($params['fieldOrder'] as $fName) {

                if (!array_key_exists($fName,
                    $row)) {

                    // debug_print_backtrace(0, 3);
                    throw new errorException('Поле [[' . $fName . ']] не найдено');
                }

                //sfield
                if (Model::isServiceField($fName)) {
                    $rowReturn[$fName] = $row[$fName];
                } //field
                else if (in_array($fName, $params['sfield'])) {

                    $Field = Field::init($this->fields[$fName], $this);
                    $selectValue = $Field->getSelectValue($row[$fName]['v'] ?? null,
                        $row,
                        $this->tbl);
                    $rowReturn[$fName] = $selectValue;
                } //id||n||is_del
                else
                    $rowReturn[$fName] = $row[$fName]['v'];
            }

            return $rowReturn;
        };

        if (!empty($fields[$Field]) && $fields[$Field]['category'] != 'column') {
            switch ($returnType) {
                case 'field':
                    return $sectionReplaces($this->tbl['params'])[$Field];
                case 'list':
                    return [$sectionReplaces($this->tbl['params'])[$Field]];
                case 'row':
                    return $sectionReplaces($this->tbl['params']);
            }
        }

        $r = $this->getByParamsFromRows($params, $returnType, $sectionReplaces);

        if (!empty($params['pfield'])) {
            $previewdatas = [];
            foreach ($params['pfield'] as $pName) {
                $previewdatas[$pName] = $this->fields[$pName]['type'];
            }
            $r['previewdata'] = $previewdatas;
        }

        return $r;
    }

    function __call($name, $arguments)
    {
        throw new errorException('Функция ' . $name . ' не предусмотрена для этого типа таблиц');
    }

    function getFields()
    {
        return $this->fields;
    }

    function getSortedFields()
    {
        return $this->sortedFields;
    }

    function getFieldsFiltered($name)
    {
        return $this->$name;
    }

    function getValuesAndFormatsForClient($data, $viewType = 'web', $fields = null)
    {
        $isWebViewType = in_array($viewType, ['web', 'edit', 'csv', 'print']);
        $isWithList = in_array($viewType, ['web', 'edit']);
        $isWithFormat = in_array($viewType, ['web', 'edit']);

        if ($viewType === 'web' && is_null($fields)) {
            if (!empty($_COOKIE['tableViewFields' . $this->tableRow['id']])) {
                $fields = explode(',', $_COOKIE['tableViewFields' . $this->tableRow['id']]);
            }
        }


        $sortedFields = [];

        if ($isWebViewType) {
            $sortedFields = $this->sortedVisibleFields;
            if (!Auth::isCreator()) {
                foreach ($this->sortedVisibleFields['filter'] as $field) {
                    if (!empty($field['webRoles']) && !count(array_intersect($field['webRoles'],
                            Auth::$aUser->getRoles()))) {
                        unset($sortedFields['filter'][$field['name']]);
                    }
                }
            }

        } elseif ($viewType == 'xml') {
            $sortedFields = $this->sortedXmlFields;
        }

        if (!is_null($fields)) {
            foreach ($sortedFields as $category => &$_fields) {
                $_fields = array_filter($_fields,
                    function ($fName) use ($fields) {
                        if (in_array($fName, $fields)) return true;
                    },
                    ARRAY_FILTER_USE_KEY);
            }
            unset($_fields);

        }

        if ($isWithFormat && $this->tableRow['row_format'] !== '' && $this->tableRow['row_format'] !== 'f1=:') {
            $RowFormatCalculate = new CalculcateFormat($this->tableRow['row_format']);
        }


        $ids = array_unique(array_merge($this->webIdInterval, array_column($data['rows'], "id")));

        foreach (($data['rows'] ?? []) as $i => $row) {

            $newRow = ['id' => ($row['id'] ?? null)];
            if (array_key_exists('n', $row)) {
                $newRow['n'] = $row['n'];
                if (!empty($this->getTableRow()['new_row_in_sort']) && key_exists($row['id'],
                        $this->changeIds['added'])) {
                    if ($this->getTableRow()['order_desc']) {
                        $newRow['__after'] = $this->getByParams(['field' => 'id', 'where' => [
                            ['field' => 'n', 'operator' => '>', 'value' => $row['n']],
                            ['field' => 'id', 'operator' => '=', 'value' => $ids]
                        ], 'order' => [['field' => 'n', 'ad' => 'asc']]],
                            'field');
                    } else {
                        $newRow['__after'] = $this->getByParams(['field' => 'id', 'where' => [
                            ['field' => 'n', 'operator' => '<', 'value' => $row['n']],
                            ['field' => 'id', 'operator' => '=', 'value' => $ids]
                        ], 'order' => [['field' => 'n', 'ad' => 'desc']]],
                            'field');
                    }

                }
            }
            if (!empty($row['InsDel'])) {
                $newRow['InsDel'] = true;
            }

//if (empty($row['id'])) debug_print_backtrace();
            foreach ($sortedFields['column'] as $f) {

                if (empty($row[$f['name']])) continue;

                $newRow[$f['name']] = $row[$f['name']];

                Field::init($f, $this)->addViewValues($viewType,
                    $newRow[$f['name']],
                    $this->tbl['rows'][$row['id'] ?? ''] ?? $row,
                    $this->tbl);

                if ($isWithFormat) {
                    Field::init($f, $this)->addFormat(
                        $newRow[$f['name']],
                        $this->tbl['rows'][$row['id'] ?? ''] ?? $row,
                        $this->tbl);
                }


            }

            if ($isWithFormat && !empty($RowFormatCalculate)) {
                $newRow['f'] = $RowFormatCalculate->getFormat(
                    'ROW',
                    $this->tbl['rows'][$row['id']] ?? $row,
                    $this->tbl,
                    $this
                );
                Controller::addLogVar($this, [$newRow['id'], 'row'], 'f', $RowFormatCalculate->getLogVar());
            } else {
                $newRow['f'] = [];
            }
            $data['rows'][$i] = $newRow;
        }
        if (!empty($data['params'])) {
            $filteredParams = [];
            foreach (['param', 'footer', 'filter'] as $category) {
                foreach ($sortedFields[$category] ?? [] as $f) {
                    if (empty($data['params'][$f['name']])) continue;

                    $Field = Field::init($f, $this);

                    if ($isWithFormat) {
                        $Field->addFormat(
                            $data['params'][$f['name']],
                            $this->tbl['params'],
                            $this->tbl);
                    }

                    $Field->addViewValues($viewType,
                        $data['params'][$f['name']],
                        $this->tbl['params'],
                        $this->tbl);


                    if ($isWithList && $f['category'] == 'filter' && in_array($f['type'], ['select', 'tree'])) {
                        /** @var Select $Field */
                        $data['params'][$f['name']]['list'] = $Field->cropSelectListForWeb(
                            $Field->calculateSelectList($f,
                                $this->tbl['params'],
                                $this->tbl),
                            $data['params'][$f['name']]['v'],
                            ''
                        );
                    }
                    $filteredParams[$f['name']] = $data['params'][$f['name']];
                }
            }
            $data['params'] = $filteredParams;
        }

        return $data;
    }

    function checkUnic($fieldName, $fieldVal)
    {

        if ($this->getByParams(['field' => 'id', 'where' => [['field' => $fieldName, 'operator' => '=', 'value' => $fieldVal]]],
            'field')) {
            return ['ok' => false];
        } else {
            return ['ok' => true];
        }

    }

    function getValue($data)
    {
        if (empty($data['fieldName'])) throw new errorException('Не задано имя поля');
        if (empty($field = $this->visibleFields[$data['fieldName']])) throw new errorException('Доступ к полю запрещен');
        if (empty($data['rowId']) && $field['category'] == 'column') throw new errorException('Не задана строка');

        $techFilter = ['id' => ''];

        if (!empty($data['rowId'])) {
            $techFilter['id'] = $data['rowId'];
        }

        if (!empty($data['rowId'])) {
            $this->loadRowsByIds([$data['rowId']]);
            if ($row = ($this->tbl['rows'][$data['rowId']] ?? null)) {
                $val = $row[$field['name']];
                if (!isset($val)) throw new errorException('Ошибка доступа к полю');
            } else throw new errorException('Ошибка доступа к полю');

        } else {
            $row = $this->tbl['params'];
            if (!isset($row[$field['name']]))
                throw new errorException('Ошибка доступа к полю');
            $val = $row[$field['name']];
        }
        if (is_string($val)) $val = json_decode($val, true);

        return ['value' => Field::init($field, $this)->getFullValue($val['v'], $data['rowId'] ?? null)];
    }

    function actionInsert($data, $dataList = null, $after = null)
    {
        $added = $this->changeIds['added'];
        if ($dataList) {
            $this->reCalculate(['add' => $dataList, 'addAfter' => $after]);

            $this->isTblUpdated(0);
        } else if (!is_null($data) && is_array($data)) {
            $this->reCalculate(['add' => [$data], 'addAfter' => $after]);

            $this->isTblUpdated(0);
        }
        return array_keys(array_diff_key($this->changeIds['added'], $added));
    }

    function actionSet($params, $where, $limit = null)
    {
        // Log::sql('SET'.json_encode($params, JSON_UNESCAPED_UNICODE));

        $modify = $this->getModifyForActionSet($params, $where, $limit);

        if ($modify) {
            $this->reCalculate(
                [
                    'modify' => $modify
                ]);
            $this->isTblUpdated(0);
        }
    }

    function actionDuplicate($fields, $where, $limit = null, $after = null)
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
            $this->reCalculate(
                [
                    'duplicate' => $duplicate, 'addAfter' => $after
                ]);
            $this->isTblUpdated(0);
            return array_keys(array_diff_key($this->changeIds['added'], $added));
        }
        return [];
    }

    function actionDelete($where, $limit = null)
    {
        $remove = $this->getRemoveForActionDeleteDuplicate($where, $limit);
        if ($remove) {
            $this->reCalculate(
                [
                    'remove' => $remove
                ]);
            $this->isTblUpdated(0);
        }
    }

    function actionClear($fields, $where, $limit = null)
    {

        $setValuesToDefaults = $this->getModifyForActionClear($fields, $where, $limit);
        if ($setValuesToDefaults) {
            $this->reCalculate(
                [
                    'setValuesToDefaults' => $setValuesToDefaults
                ]);
            $this->isTblUpdated(0);
        }
    }

    function actionPin($fields, $where, $limit = null)
    {

        $setFieldPinned = $this->getModifyForActionClear($fields, $where, $limit);

        if ($setFieldPinned) {
            $this->reCalculate(
                [
                    'setValuesToPinned' => $setFieldPinned
                ]);
            $this->isTblUpdated(0);
        }
    }

    function getEditSelect($data, $q, $parentId = null)
    {


        $fields = $this->fields;

        if (!($field = $fields[$data['field']] ?? null))
            throw new errorException('Не найдено поле [[' . $data['field'] . ']]. Возможно изменилась структура таблицы. Перегрузите страницу');

        $this->loadDataRow();
        if (!is_null($this->filtersFromUser)) {
            $this->reCalculate(['modify' => ['params' => $this->filtersFromUser], 'channel' => 'web']);
        }


        $row = $data['item'];

        if ($field['category'] == 'column' && !isset($row['id'])) {
            $row['id'] = null;
        }
        foreach ($row as $k => &$v) {
            if ($k != 'id') {
                $v = ['v' => $v];
            }
        }
        if ($field['category'] == 'column') {
            if (array_key_exists('id', $row) && !is_null($row['id'])) {
                $this->loadRowsByIds([$row['id']]);
                $row = $row + $this->tbl['rows'][$row['id']];
            } else {
                $row = $row + $this->checkInsertRow($data['item'], null);
            }
        } else {
            $row = $row + $this->tbl['params'];
        }


        if (!in_array($field['type'],
            ['select', 'tree'])) throw new errorException('Ошибка - поле не типа select/tree');

        /** @var Select $Field */
        $Field = Field::init($field, $this);

        $list = $Field->calculateSelectList($row[$field['name']], $row, $this->tbl);
        $Field->addInControllerSelectLog($row);

        return $Field->cropSelectListForWeb($list, $row[$field['name']]['v'], $q, $parentId);
    }

    function getSelectTableByParams($params)
    {
        if (empty($params['table'])) throw new errorException('Не задан параметр таблица');

        if (ctype_digit($params['table'])) $sourceTableRow = Table::getTableRowById($params['table']);
        else $sourceTableRow = Table::getTableRowByName($params['table']);

        if (!$sourceTableRow) throw new errorException('Таблица [[' . $params['table'] . ']] не найдена');


        if ($sourceTableRow['type'] === 'tmp') {
            if ($this->getTableRow()['id'] == $sourceTableRow['id']) {
                $SourceTable = $this;
            } elseif (!empty($params['hash'])) {
                $SourceTable = tableTypes::getTable($sourceTableRow, $params['hash']);
            } else {
                throw new errorException('Не заполнен параметр [[hash]]');
            }

        } elseif ($this->getTableRow()['type'] === 'calcs'
            && $sourceTableRow['type'] === 'calcs'
            && $sourceTableRow['tree_node_id'] === $this->getTableRow()['tree_node_id']
            && (empty($params['cycle']) || $this->Cycle->getId() == $params['cycle'])

        ) {
            /** @var Cycle $Cycle */
            $Cycle = $this->Cycle;
            $SourceTable = $Cycle->getTable($sourceTableRow);

        }//Из чужого цикла
        elseif ($sourceTableRow['type'] === 'calcs') {

            if (empty($params['cycle'])) {
                throw new errorException('Не передан параметр [[cycle]]');
            }

            $SourceCycle = Cycle::init($params['cycle'], $sourceTableRow['tree_node_id']);
            $SourceTable = $SourceCycle->getTable($sourceTableRow);

        } else {
            $SourceTable = tableTypes::getTable($sourceTableRow);
        }

        return $SourceTable;
    }

    function getSelectByParams($params, $returnType = 'field', $rowId = null, $toSource = false)
    {
        if (empty($params['table'])) throw new errorException('Не задан параметр таблица');

        if (ctype_digit($params['table'])) $sourceTableRow = Table::getTableRowById($params['table']);
        else $sourceTableRow = Table::getTableRowByName($params['table']);

        if (!$sourceTableRow) throw new errorException('Таблица [[' . $params['table'] . ']] не найдена');

        if (in_array($returnType,
                ['field']) && empty($params['field']) && empty($params['sfield'])
        ) {
            throw new errorException('Не задан параметр поле');
        }

        if ($sourceTableRow['type'] === 'tmp') {
            if ($this->getTableRow()['id'] == $sourceTableRow['id']) {
                $SourceTable = $this;
            } elseif (!empty($params['hash'])) {
                $SourceTable = tableTypes::getTable($sourceTableRow, $params['hash']);
            } else {
                throw new errorException('Не заполнен параметр [[hash]]');
            }

        } elseif ($this->getTableRow()['type'] === 'calcs'
            && $sourceTableRow['type'] === 'calcs'
            && $sourceTableRow['tree_node_id'] === $this->getTableRow()['tree_node_id']
            && (empty($params['cycle']) || $this->Cycle->getId() == $params['cycle'])

        ) {
//TODO Проверить что будет если ошибочные данные

            /** @var Cycle $Cycle */
            $Cycle = $this->Cycle;
            $SourceTable = $Cycle->getTable($sourceTableRow);

            if ($toSource) {
                $this->addInSourceTables($sourceTableRow);
            }

        }//Из чужого цикла
        elseif ($sourceTableRow['type'] === 'calcs') {

            if (empty($params['cycle'])) {
                if ($this->tableRow['type'] === 'cycles' && $sourceTableRow['tree_node_id'] == $this->tableRow['id'] && $rowId) {
                    $params['cycle'] = $rowId;
                } else {
                    throw new errorException('Не передан параметр [[cycle]]');
                }
            }

            if (in_array($returnType, ['list', 'rows']) && is_array($params['cycle'])) {
                $list = [];
                foreach ($params['cycle'] as $cycle) {
                    $SourceCycle = Cycle::init($cycle, $sourceTableRow['tree_node_id']);
                    $SourceTable = $SourceCycle->getTable($sourceTableRow);
                    $list = array_merge($list, $SourceTable->getByParamsCached($params, $returnType, $this));
                }
                return $list;
            } elseif (!ctype_digit(strval($params['cycle']))) throw new errorException('Параметр [[cycle]] должен быть числом');
            else {

                $SourceCycle = Cycle::init($params['cycle'], $sourceTableRow['tree_node_id']);
                $SourceTable = $SourceCycle->getTable($sourceTableRow);

            }

        } else {
            $SourceTable = tableTypes::getTable($sourceTableRow);
        }

        if ($returnType == 'table') {
            $replaceFilesInTblWithContent = function ($tbl, $fields) {
                $replaceFileDataWithContent = function (&$filesArray) {
                    if (!empty($filesArray['v']) && is_array($filesArray['v'])) {
                        foreach ($filesArray['v'] as &$fileData) {
                            $fileData['filestringbase64'] = base64_encode(File::getContent($fileData['file']));
                            unset($fileData['file']);
                            unset($fileData['size']);
                        }
                        unset($fileData);
                    }
                };

                foreach ($tbl['params'] as $k => &$v) {
                    if ($fields[$k]['type'] == 'file') {
                        $replaceFileDataWithContent($v);
                    }
                }
                foreach ($tbl['rows'] as &$row) {
                    foreach ($row as $k => &$v) {
                        if ($fields[$k]['type'] == 'file') {
                            $replaceFileDataWithContent($v);
                        }
                    }
                    unset($v);
                }
                unset($row);

                return $tbl;
            };

            if (is_a($SourceTable, RealTables::class)) {
                $params['ids'] = (array)$params['ids'] ?? [];
                $fields = array_flip($params['fields'] ?? []);
                $rows = [];
                $SourceTable->loadRowsByIds($params['ids']);
                $tbl = $SourceTable->getTbl();
                $tbl['rows'] = array_intersect_key($tbl['rows'], array_flip($params['ids']));

                unset($tbl['params']['__nTailLength']);

                $tbl['params'] = array_intersect_key($tbl['params'], $fields);


                foreach ($tbl['rows'] as $_row) {
                    if ($_row['is_del'] && !key_exists('is_del', $fields)) continue;

                    $row = [];
                    foreach ($_row as $k => $v) {
                        if (key_exists($k, $fields)) {
                            $row[$k] = $v;
                        }
                        if (is_a($SourceTable, cyclesTable::class)) {
                            $row['_tables'] = [];
                            $cycle = Cycle::init($_row['id'], $SourceTable->getTableRow()['id']);
                            foreach ($cycle->getTables() as $inTableID) {
                                $sourceInTable = $cycle->getTable(Table::getTableRowById($inTableID));
                                $row['_tables'][$sourceInTable->getTableRow()['name']] = ['tbl' => $replaceFilesInTblWithContent($sourceInTable->getTbl(),
                                    $sourceInTable->getFields()), 'version' => $sourceInTable->getTableRow()['__version']];
                            }
                        }
                    }
                    if ($row) {
                        $rows[] = $row;
                    }
                }

                $tbl['rows'] = $rows;
                return $replaceFilesInTblWithContent($tbl, $SourceTable->getFields());
            }
            return $replaceFilesInTblWithContent($SourceTable->getTbl(), $SourceTable->getFields());
        }


        if (strpos($returnType, '&table')) {
            $returnType = str_replace('&table', '', $returnType);
            return [$SourceTable->getByParamsCached($params, $returnType, $this), $SourceTable];
        } else if ($returnType === 'treeChildren') {

            static::$recalcLogPointer->addSelects($this, $SourceTable);

            return $SourceTable->getChildrenIds($params['id'], $params['parent'], $params['bfield'] ?? 'id');
        } else {

            return $SourceTable->getByParamsCached($params, $returnType, $this);
        }
    }

    function getByParamsCached($params, $returnType, $fromTable)
    {
        if ($this->onCanculating) {

            if (static::$recalcLogPointer)
                static::$recalcLogPointer->addSelects($fromTable, $this);

            return $this->getByParams($params,
                $returnType);
        }

        $hash = $returnType . serialize($params);
        if (empty($this->cachedSelects[$hash])) {
            if (static::$recalcLogPointer) static::$recalcLogPointer->addSelects($fromTable, $this);
            $this->cachedSelects[$hash] = $this->getByParams($params,
                $returnType);
        }
        return $this->cachedSelects[$hash];
    }

    function checkInsertRowForClient($addData, $tableData = null, $editedFields = [])
    {
        if ($tableData) {
            $this->checkTableUpdated($tableData);
        }
        $data = ['rows' => [$this->checkInsertRow($addData, $editedFields)]];

        $data = $this->getValuesAndFormatsForClient($data, 'edit');
        $changedData = $data['rows'][0];

        return ['row' => $changedData];
    }

    abstract function checkInsertRow($data, $param);

    function csvImport($tableData, $csvString, $answers)
    {
        $this->checkTableUpdated($tableData);
        $import = [];

        if ($errorAndQuestions = $this->prepareCsvImport($import, $csvString, $answers)) {
            return $errorAndQuestions;
        }
        $table = ['ok' => 1];

        $this->reCalculate(
            ['channel' => 'web', 'modifyCalculated' => ($import['codedFields'] == 2 ? 'all' : 'handled')
                , 'add' => ($import['add'] ?? [])
                , 'modify' => ($import['modify'] ?? [])
                , 'remove' => ($import['remove'] ?? [])
            ]
        );
        $oldUpdated = $this->updated;
        $this->isTblUpdated(0);
        if ($oldUpdated != $this->updated) {
            $table['updated'] = $this->updated;
        }

        Controller::addLinkLocation($_SERVER['REQUEST_URI'], 'self', 'reload');
        return $table;
    }

    function csvExport($tableData, $idsString, $visibleFields)
    {
        $this->checkTableUpdated($tableData);

        if ($idsString && $idsString != '[]') {
            $ids = json_decode($idsString, true);
            if ($this->sortedFields['filter']) {
                $this->reCalculate();
            }
            $filteredIds = $this->getFilteredIds('web', []);
            $ids = array_intersect($ids, $filteredIds);
            $this->loadRowsByIds($ids);

            $oldRows = $this->tbl['rows'];
            $this->tbl['rows'] = [];

            foreach ($ids as $id) {
                $this->tbl['rows'][$id] = $oldRows[$id];
            }


        }
        foreach ($this->filtersFromUser as $f => $val) {
            $this->tbl['params'][$f] = ['v' => $val];
        }
        $csv = $this->getCsvArray($visibleFields);

        ob_start();
        $out = fopen('php://output', 'w');
        foreach ($csv as $fields) {

            fputcsv($out, $fields, ";", '"', '\\');
        }
        fclose($out);

        return ['csv' => ob_get_clean()];
    }

    public function setFilters($filtersIn, $isCrypled = true)
    {
        $filters = [];

        if ($isCrypled) {
            if ($filtersIn) {
                if ($filtersDecrypt = Crypt::getDeCrypted(strval($filtersIn))) {
                    $filters = json_decode($filtersDecrypt, true);
                } elseif (in_array($this->tableRow['id'], [1, 2]) && is_array($filtersIn)) {
                    $filters = $filtersIn;
                }
            }

        } else $filters = $filtersIn;


        if ($filters) {
            $this->filtersFromUser = [];
            foreach ($filters as $fName => $val) {
                if ($fName == 'id' || ($this->fields[$fName]['category'] ?? null) === 'filter') {
                    $this->filtersFromUser[$fName] = $val;
                }
            }
        }

    }

    abstract function getChildrenIds($id, $parentField, $bfield);

    public function getTbl()
    {
        return $this->tbl;
    }

    /**
     * @return array
     */
    public function getSortedXmlFields()
    {
        return $this->sortedXmlFields;
    }

    /**
     * @return array
     */
    public function getChangeIds(): array
    {
        return $this->changeIds;
    }

    /**
     * @param array|bool|string $withALog
     */
    public function setWithALogTrue()
    {
        $this->recalculateWithALog = true;
    }

    abstract protected function getFilteredIds($channel, $idsFilter = []);

    protected function getTableFormat()
    {
        $tFormat = [];
        if ($this->tableRow['table_format'] && $this->tableRow['table_format'] != 'f1=:') {
            $calc = new CalculcateFormat($this->tableRow['table_format']);
            $tFormat = $calc->getFormat('TABLE', [], $this->tbl, $this);
            Controller::addLogVar($this, ['table_format'], 'f', $calc->getLogVar());
        }
        return $tFormat;
    }

    abstract protected function getByParamsFromRows($params, $returnType, $sectionReplaces);

    protected function cropSelectListForWeb($checkedVals, $list, $isMulti, $q = '', $selectLength = 50, $topForChecked = true)
    {
        $checkedNum = 0;

        //Наверх выбранные;
        if (!empty($checkedVals)) {
            if ($isMulti) {
                foreach ((array)$checkedVals as $mm) {
                    if (array_key_exists($mm, $list) && $list[$mm][1] == 0) {
                        $v = $list[$mm];
                        unset($list[$mm]);
                        $list = [$mm => $v] + $list;
                        $checkedNum++;
                    }
                }
            } else {
                $mm = $checkedVals;
                if (array_key_exists($mm, $list) && $list[$mm][1] == 0) {
                    $v = $list[$mm];
                    unset($list[$mm]);
                    $list = [$mm => $v] + $list;
                    $checkedNum++;
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
            if (($v[1] ?? 0) == 1) unset($list[$k]);
        }

        if (count($list) > ($selectLength + $checkedNum)) {
            foreach ($list as $k => $v) {

                if ($i < $checkedNum) {
                    $addInArrays($k, $v);
                } else {
                    if ($q) {
                        if (preg_match('/' . preg_quote($q, '/') . '/ui', $v[0])) {
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

        return ['list' => $listMain, 'indexed' => $objMain, 'sliced' => $isSliced];
    }

    static function isDifferentFieldData($v1, $v2)
    {
        if (is_array($v1) && is_array($v2)) {
            if (count($v1) != count($v2)) return true;
            foreach ($v1 as $k => $_v1) {
                if (!key_exists($k, $v2)) return true;
                if (self::isDifferentFieldData($_v1, $v2[$k])) return true;
            }
            return false;
        } elseif (!is_array($v1) && !is_array($v2)) {
            if (is_numeric(strval($v1)) && is_numeric(strval($v2))) {
                return $v1 != $v2;
            }
            return ($v1 ?? '') !== ($v2 ?? '');
        } else return true;
    }

    protected function checkIsModified($oldVal, $newVal)
    {
        if (!$this->isTableDataChanged) {
            if ($oldVal !== $newVal) {
                if (static::isDifferentFieldData($oldVal, $newVal)) {
                    $this->isTableDataChanged = true;
                }
            }
        }
    }

    function reCalculateFilters($channel, $isEditFilters = false, $forse = false)
    {
        if ($channel == 'inner') return;

        if (!$forse && ($this->calculatedFilters[$channel] ?? false)) {
            $this->tbl['params'] = array_merge($this->calculatedFilters[$channel], $this->tbl['params']);
        } else {
            $columns = $this->sortedFields['filter'] ?? [];

            foreach ($columns as $column) {

                if (key_exists($column['name'], $this->anchorFilters)) {
                    $this->tbl['params'][$column['name']] = ["v" => $this->anchorFilters[$column['name']]];
                    continue;
                }

                switch ($channel) {
                    case 'xml':
                        if (!array_key_exists($column['name'], $this->sortedXmlFields['filter'])) continue 2;
                        break;
                    case 'web':
                        if (empty($this->fields[$column['name']]['showInWeb'])) continue 2;
                        break;
                }

                if ($isEditFilters) {

                    /** @var Field $Field */
                    $Field = Field::init($column, $this);

                    $oldVal = $Field->add(
                        $channel,
                        $this->filtersFromUser[$column['name']] ?? null,
                        $this->tbl['params'],
                        $this->tbl,
                        $this->tbl
                    );
                    $newVal = $this->filtersFromUser[$column['name']] ?? null;

                    $changedFlag = $Field->getModifyFlag(array_key_exists($column['name'],
                        $this->filtersFromUser ?? []),
                        $newVal,
                        $oldVal,
                        array_key_exists($column['name'], $setValuesToDefaults['params'] ?? []),
                        array_key_exists($column['name'], $setValuesToPinned['params'] ?? []),
                        true);

                    $this->tbl['params'][$column['name']] = $Field->modify(
                        $channel,
                        $changedFlag,
                        $newVal,
                        $this->tbl['params'],
                        $this->tbl['params'],
                        $this->tbl,
                        $this->tbl,
                        false
                    );


                } else {
                    $this->tbl['params'][$column['name']] = Field::init($column, $this)->add(
                        $channel,
                        $this->filtersFromUser[$column['name']] ?? null,
                        $this->tbl['params'],
                        $this->tbl,
                        $this->tbl
                    );

                }
            }
            $this->calculatedFilters[$channel] = [];
            foreach ($columns as $column) {
                $this->calculatedFilters[$channel][$column['name']] = $this->tbl['params'][$column['name']] ?? null;
            }

        }
    }


    protected function addToALogAdd(Field $Field, $channel, $newTbl, $thisRow, $modified)
    {
        if ($this->tableRow['type'] != 'tmp' && $Field->isLogging()) {
            /*Пользователь может изменять*/
            $logIt = false;
            switch ($channel) {
                case 'web':
                    $logIt = $Field->isWebChangeable('insert');
                    break;
                case 'xml':
                    $logIt = $Field->isXmlChangeable('insert');
                    break;
                case 'inner':
                    $logIt = $this->recalculateWithALog;
                    break;
            }
            if ($logIt && key_exists($Field->getName(), $modified)) {
                //Если рассчитываемое и несовпадающее с рассчетным
                if (key_exists('c',
                        $thisRow[$Field->getName()]) || !$Field->getData('code') || $Field->getData('codeOnlyInAdd')) {
                    aLog::add($this->tableRow['id'],
                        !empty($this->Cycle) ? $this->Cycle->getId() : null,
                        $thisRow['id'],
                        [$Field->getName() => [$Field->getLogValue($thisRow[$Field->getName()]['v'],
                            $thisRow,
                            $newTbl), $channel == 'inner' ? 'скрипт' : null]]);
                }
            }
        }
    }

    protected function addToALogModify(Field $Field, $channel, $newTbl, $thisRow, $rowId, $modified, $setValuesToDefaults, $setValuesToPinned, $oldVal)
    {
        if ($this->tableRow['type'] != 'tmp' && $Field->isLogging()) {
            /*Пользователь может изменять*/
            $logIt = false;
            switch ($channel) {
                case 'web':
                    $logIt = $Field->isWebChangeable('modify');
                    break;
                case 'xml':
                    $logIt = $Field->isXmlChangeable('modify');
                    break;
                case 'inner':
                    $logIt = $this->recalculateWithALog;
                    break;
            }
            if ($logIt) {
                /*Изменили*/
                if (key_exists($Field->getName(), $setValuesToDefaults)) {
                    aLog::clear(
                        $this->tableRow['id'],
                        !empty($this->Cycle) ? $this->Cycle->getId() : null,
                        $rowId,
                        [$Field->getName() => [$Field->getLogValue($thisRow[$Field->getName()]['v'],
                            $thisRow,
                            $newTbl), $channel == 'inner' ? 'скрипт' : null]]
                    );
                } elseif (key_exists($Field->getName(), $setValuesToPinned)) {
                    aLog::pin(
                        $this->tableRow['id'],
                        !empty($this->Cycle) ? $this->Cycle->getId() : null,
                        $rowId,
                        [$Field->getName() => [$Field->getLogValue($thisRow[$Field->getName()]['v'],
                            $thisRow,
                            $newTbl), $channel == 'inner' ? 'скрипт' : null]]
                    );
                } elseif (key_exists($Field->getName(),
                        $modified) && ($thisRow[$Field->getName()]['v'] !== $oldVal['v'] || ($thisRow[$Field->getName()]['h'] ?? null) !== ($oldVal['h'] ?? null))) {
                    $funcName = 'modify';
                    if (($thisRow[$Field->getName()]['h'] ?? null) === true && !($oldVal['h'] ?? null)) {
                        $funcName = 'pin';
                    }


                    aLog::$funcName(
                        $this->tableRow['id'],
                        !empty($this->Cycle) ? $this->Cycle->getId() : null,
                        $rowId,
                        [$Field->getName() => [
                            $Field->getLogValue($thisRow[$Field->getName()]['v'],
                                $thisRow,
                                $newTbl)
                            ,
                            $channel == 'inner' ? 'скрипт' : $Field->getModifiedLogValue($modified[$Field->getName()])]]
                    );
                } elseif (key_exists($Field->getName(),
                    $setValuesToDefaults)) {
                    aLog::clear(
                        $this->tableRow['id'],
                        !empty($this->Cycle) ? $this->Cycle->getId() : null,
                        $rowId,
                        [$Field->getName() =>
                            [
                                $Field->getLogValue($thisRow[$Field->getName()]['v'],
                                    $thisRow,
                                    $newTbl), $channel == 'inner' ? 'скрипт' : null
                            ]
                        ]
                    );
                }

            }

        }
    }

    abstract protected function isTblUpdated($level = 0, $force = false);

    protected function __getCheckSectionField($params)
    {
        $tableRow = $this->getTableRow();
        if (empty($this->fields[$params['section']])) {

            throw new errorException('Поля [[' . $params['section'] . ']] в таблице [[' . $tableRow['name'] . ']] не существует');
        }

        $sectionField = $this->fields[$params['section']];
        if ($sectionField['category'] !== 'column')
            throw new errorException('Полe [[' . $params['section'] . ']] в таблице [[' . $tableRow['name'] . ']] не колонка');
        return $sectionField;
    }

    protected function addInSourceTables($SourceTableRow)
    {

    }


    /* abstract function actionInsert($rowParams);
    abstract function actionSet($params, $where = [], $limit = null);*/

    abstract protected function onSaveTable($tbl, $savedTbl);

    protected function getParamsWithoutFilters()
    {
        $params = $this->tbl['params'];
        foreach ($this->sortedFields['filter'] ?? [] as $fName => $field) {
            unset($params[$fName]);
        }
        return $params;
    }

    protected function getTblForSave()
    {
        $tbl = $this->tbl;
        foreach ($this->sortedFields['filter'] ?? [] as $filterField) {
            unset($tbl['params'][$filterField['name']]);
        }
        return $tbl;
    }


    public function getFilteredData($channel)
    {
        $tbl = ['params' => $this->tbl['params'], 'rows' => []];


        if (is_null($this->changeIds['filteredIds'])) {

            $this->getFilteredIds($channel, []);
        }
        foreach ($this->changeIds['filteredIds'] as $id) {
            $tbl['rows'][] = $this->tbl['rows'][$id];
        }
        return $tbl;

    }

    function getLastUpdated()
    {
        return $this->updated;
    }


    protected function getRemoveForActionDeleteDuplicate($where, $limit)
    {

        $getParams = ['where' => $where, 'field' => 'id'];
        if ($limit == 1) {
            if ($id = $this->getByParams($getParams, 'field'))
                $remove = [$id];
            else return false;

        } else {
            $remove = $this->getByParams($getParams, 'list');
        }
        return $remove;
    }

    protected function getModifyForActionSet($params, $where, $limit)
    {

        $rowParams = [];
        $pParams = [];
        foreach ($params as $f => $value) {
            if ($this->fields[$f]['category'] == 'column') {
                $rowParams[$f] = $value;
            } else {
                $pParams[$f] = $value;
            }
        }

        $modify = [];

        if (!empty($rowParams)) {
            $getParams = ['where' => $where, 'field' => 'id'];
            if ($limit == 1) {

                if ($id = $this->getByParams($getParams, 'field'))
                    $return = [$id];
                else return false;

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

    function getModifyForActionSetExtended($params, $where)
    {

        $rowParams = [];
        $modify = [];
        $wheres = [];
        $modifys = [];


        $maxCount = 0;
        foreach ($where as $i => $_w) {
            if (is_array($_w['value']) && count($_w['value']) > $maxCount) $maxCount = count($_w['value']);
        }
        foreach ($params as $f => $valueList) {
            if (is_array($valueList) && count($valueList) > $maxCount) $maxCount = count($valueList);
        }


        foreach ($where as $i => $_w) {
            if (is_array($whereList = $_w['value']) && array_key_exists(0,
                    $whereList) && count($whereList) != $maxCount) throw new errorException('В параметре where необходимо использовать лист по количеству изменяемых строк либо не лист');

            if (is_array($whereList) && array_key_exists(0, $whereList)) {
                foreach ($whereList as $ii => $_wVal) {
                    $wheres[$ii][$i] = ['value' => $_wVal] + $_w;
                }

            } else {
                for ($ii = 0; $ii < $maxCount; $ii++) {
                    $wheres[$ii][$i] = $_w;
                }
            }

        }
        foreach ($params as $f => $valueList) {
            if ($this->fields[$f]['category'] != 'column') {
                throw new errorException('Функция используется для изменения строчной части таблицы');
            }

            if (is_object($valueList)) {
                if (is_array($valueList->val)) {
                    if (is_array($valueList->val) && array_key_exists(0,
                            $valueList->val) && count($valueList->val) != $maxCount) throw new errorException('В параметре field необходимо использовать лист по количеству изменяемых строк либо не лист');
                    foreach ($valueList->val as $ii => $val) {
                        $newObj = new FieldModifyItem($valueList->sign, $val, $valueList->percent);
                        $modifys[$ii][$f] = $newObj;
                    }
                    continue;
                }

            }
            if (is_array($valueList) && array_key_exists(0,
                    $valueList) && count($valueList) != $maxCount) throw new errorException('В параметре field необходимо использовать лист по количеству изменяемых строк либо не лист');

            if (is_array($valueList) && array_key_exists(0, $valueList)) {
                foreach ($valueList as $ii => $_wl) {
                    $modifys[$ii][$f] = $_wl;
                }

            } else {
                for ($ii = 0; $ii < $maxCount; $ii++) {
                    $modifys[$ii][$f] = $valueList;
                }
            }


        }
        foreach ($wheres as $i => $where) {
            $return = $this->getByParams(['where' => $where, 'field' => 'id'], 'list');
            foreach ($return as $id) {
                $modify[$id] = $modifys[$i];
            }
        }

        return $modify;
    }

    protected function getModifyForActionClear($fields, $where, $limit)
    {

        $rowParams = [];
        $pParams = [];
        foreach ($fields as $f) {
            if ($this->fields[$f]['category'] == 'column') {
                $rowParams[$f] = null;
            } else {
                $pParams[$f] = null;
            }
        }

        $setValuesToDefaults = [];

        if (!empty($rowParams)) {
            $getParams = ['where' => $where, 'field' => 'id'];
            if ($limit == 1) {

                if ($id = $this->getByParams($getParams, 'field'))
                    $return = [$id];
                else return false;

            } else {
                $return = $this->getByParams($getParams, 'list');
            }

            foreach ($return as $id) {
                $setValuesToDefaults[$id] = $rowParams;
            }

        }
        if (!empty($pParams)) {
            $setValuesToDefaults ['params'] = $pParams;
        }
        return $setValuesToDefaults;
    }


    protected function getStructureUpdatedJSON()
    {

        $jsonUpdated = tableTypes::getTable(Table::getTableRowById(TablesFields::TableId))->updated;
        $jsonUpdated = json_decode($jsonUpdated, true);
        return $jsonUpdated;
    }

    protected function prepareCsvImport(&$import, $csvString, $answers)
    {
        $import['modify'] = [];
        $import['add'] = [];
        $import['remove'] = [];

        $NotCorrectFormat = 'Неверный формат файла: ';
        $question = [];
        $checkQuestion = function ($number, $isTrue, $text) use ($answers, &$question) {
            if (!isset($answers[$number]) && $isTrue) {
                $question = ['question' => [$number, $text]];
                return true;
            }
            return false;
        };


        $rowNumName = 0;
        $rowNumCodes = 1;
        $rowNumProject = 2;
        $rowNumSectionHandl = 4;
        $rowNumSectionHeader = 8;
        $rowNumFilter = 13;
        $rowNumSectionRows = 16;

        $csvString = stream_get_contents(fopen($csvString, 'r'));

        if (!mb_check_encoding($csvString, 'utf-8') && mb_check_encoding($csvString, 'windows-1251')) {
            $csvString = mb_convert_encoding($csvString, 'utf-8', 'windows-1251');
        }
        if (!mb_check_encoding($csvString, 'utf-8')) {
            return ['error' => 'Неверная кодировка файла (должно быть utf-8 или windows-1251)'];
        }

        if (preg_match('/(\s+)"?от/', $csvString, $match)) {
            $delim = $match[1];
        } else {
            return ['error' => 'Ошибка определения разделителя строк'];
        }

        $csvArray = explode($delim, $csvString);
        foreach ($csvArray as &$row) {
            $row = str_getcsv(trim($row), ';', '"', '\\');
            foreach ($row as &$c) {
                $c = trim($c);
            }
        }

//Проверка та ли таблица
        if ($checkQuestion(1,
            $csvArray[$rowNumName][0] != $this->tableRow['title'],
            'Файл таблицы [[' . $csvArray[$rowNumName][0] . ']] вы пытаетесь загрузить в таблицу [[' . $this->tableRow['title'] . ']]')) {
            return $question;
        };

//Не была ли таблица изменена
        if (!isset($csvArray[$rowNumCodes][1]) || !preg_match('/^code:(\d+)$/',
                $csvArray[$rowNumCodes][1],
                $matchCode)
        ) return ['error' => $NotCorrectFormat . 'в строке ' . ($rowNumCodes + 1) . ' отсутствует код изменения таблицы'];
        else {
            $updated = json_decode($this->updated, true);
            if ($checkQuestion(2, $matchCode[1] != $updated['code'], 'Таблица была изменена')) {
                return $question;
            };
        }
//Не была ли  изменена структура
        if (!isset($csvArray[$rowNumCodes][2]) || !preg_match('/^structureCode:(\d+)$/',
                $csvArray[$rowNumCodes][2],
                $matchCode)
        ) return ['error' => $NotCorrectFormat . 'в строке ' . ($rowNumCodes + 1) . ' отсутствует код изменения структуры'];
        else {
            if ($checkQuestion(3,
                $matchCode[1] != $this->getStructureUpdatedJSON()['code'],
                'Была изменена структура таблицы. Возможно несовпадение порядка полей.')) {
                return $question;
            };
        }

//Тот ли проект
        if (!isset($csvArray[$rowNumProject][0]) || !preg_match('/^(\d+|Вне циклов)$/',
                $csvArray[$rowNumProject][0],
                $matchCode)
        ) return ['error' => $NotCorrectFormat . 'в строке ' . ($rowNumProject + 1) . ' отсутствует указание на цикл'];
        else {
            if ($checkQuestion(4,
                (isset($this->Cycle) && $this->Cycle->getId() ? $this->Cycle->getId() : 'Вне циклов') != $matchCode[1],
                'Таблица из другого цикла или вне циклов')) {
                return $question;
            };
        }


//Ручные значения
        if (($string = $csvArray[$rowNumSectionHandl][0] ?? '') != 'Ручные значения') return ['error' => $NotCorrectFormat . 'в строке ' . ($rowNumSectionHandl + 1) . ' отсутствует заголовок секции Ручные значения'];
        if (!in_array(($string = strtolower($csvArray[$rowNumSectionHandl + 2][0] ?? '')),
            [0, 1, 2])
        ) return ['error' => $NotCorrectFormat . 'в строке ' . ($rowNumSectionHandl + 1) . ' отсутствует 0/1/2 переключатель редактирования'];
        $import['codedFields'] = $string;

        $getCsvVal = function ($val, $field) {
            return Field::init($field, $this)->getValueFromCsv($val);
        };

//Хэдер
        if (($string = $csvArray[$rowNumSectionHeader][0] ?? '') != 'Хедер') return ['error' => $NotCorrectFormat . 'в строке ' . ($rowNumSectionHeader + 1) . ' отсутствует заголовок секции Хедер'];
        $headerFields = $csvArray[$rowNumSectionHeader + 2];

        foreach ($headerFields as $i => $fieldName) {
            if (!$fieldName) continue;
            if (($field = $this->fields[$fieldName]) && !in_array($field['type'], ['comments', 'button'])) {
                if ($import['codedFields'] == 0 && !empty($field['code']) && empty($field['codeOnlyInAdd'])) ;
                else {
                    $import['modify']['params'][$field['name']] = $getCsvVal($csvArray[$rowNumSectionHeader + 3][$i],
                        $field);

                }
            }
        }

//Фильтр
        if (($string = $csvArray[$rowNumFilter][0] ?? '') != 'Фильтр') return ['error' => $NotCorrectFormat . 'в строке ' . ($rowNumFilter + 1) . ' отсутствует заголовок секции Фильтр'];
        if (!empty($this->sortedVisibleFields["filter"])) {
            if (empty($filterData = $csvArray[$rowNumFilter + 1][0]))
                return ['error' => $NotCorrectFormat . 'в строке ' . ($rowNumFilter + 2) . ' отсутствуют данные о фильтрах'];
            $this->setFilters($filterData, true);
        }


//Строчная часть
        if (($string = $csvArray[$rowNumSectionRows][0] ?? '') != 'Строчная часть') return ['error' => $NotCorrectFormat . 'в строке ' . ($rowNumSectionRows + 1) . ' отсутствует заголовок секции Строчная часть'];
        $numRow = $rowNumSectionRows + 3;
        $rowCount = count($csvArray);

        $rowFields = $csvArray[$rowNumSectionRows + 2];

        while ($numRow < $rowCount && (count($csvArray[$numRow]) > 1) && ($csvArray[$numRow][1] ?? '') !== 'f0H') {
            $csvRow = $csvArray[$numRow];

            $isDel = ($csvRow[0] ?? '') !== '';

//Проверка на пустые строки в импорте
            $isAllFieldsEmpty = true;
            foreach ($csvRow as $k => $v) {
                if ($v !== '') {
                    $isAllFieldsEmpty = false;
                    break;
                }
            }
            $numRow++;

            if ($isAllFieldsEmpty) break;

            $id = $csvRow[1];
            $csvRowColumns = [];
            if ($isDel && $id) {
                $import['remove'][] = $id;
            } else {

                foreach ($rowFields as $i => $fieldName) {
                    if ($i < 2) continue;
                    if (($field = ($this->fields[$fieldName] ?? null)) && !in_array($field['type'],
                            ['comments', 'button'])) {
                        if (!empty($field['code']) && empty($field['codeOnlyInAdd'])) {
                            if ($import['codedFields'] == 0) continue;
                            if ($import['codedFields'] == 1 && empty($id)) continue;
                        }

                        $val = $csvRow[$i] ?? '';
                        if (!in_array($field['type'], ['comments', 'button'])) {
                            $csvRowColumns[$field['name']] = $getCsvVal($val, $field);
                        }
                    }
                }

                if ($id) {
                    $import['modify'][$id] = $csvRowColumns;
                } else {
                    $import['add'][] = $csvRowColumns;
                }
            }
        }
//Футеры колонок
        if (preg_match('/f\d+H/', $csvArray[$numRow][1] ?? '')) {
            while (preg_match('/f\d+H/', $csvArray[$numRow][1] ?? '')) {
                //Переводим на строку с names
                $numRow++;
                foreach ($rowFields as $i => $fName) {
                    if ($i < 2) continue;
                    if ($footerName = ($csvArray[$numRow][$i] ?? null)) {
                        if (($field = ($this->fields[$footerName] ?? null)) && !in_array($field['type'],
                                ['comments', 'button'])) {
                            if ($field['category'] == 'footer') {
                                if ($import['codedFields'] == 0 && !empty($field['code']) && empty($field['codeOnlyInAdd'])) continue;
                                $val = $csvArray[$numRow + 1][$i];
                                $import['modify']['params'][$field['name']] = $getCsvVal($val, $field);
                            }
                        }
                    }
                }
                $numRow = $numRow + 2;
            }
            $numRow++;
        }


//Футер
        if (is_a($this, JsonTables::class)) {
            if (($string = $csvArray[$numRow][0] ?? '') != 'Футер') return ['error' => $NotCorrectFormat . 'в строке через одну после Строчной части отсутствует заголовок секции Футер' . var_export($csvArray[$numRow],
                    1)];
            $numRow += 2;

            foreach ($csvArray[$numRow] ?? [] as $i => $fieldName) {
                if (!$fieldName) continue;
                if (($field = ($this->fields[$footerName] ?? null)) && !in_array($field['type'],
                        ['comments', 'button'])) {
                    if ($import['codedFields'] == 0 && !empty($field['code']) && empty($field['codeOnlyInAdd'])) continue;
                    $import['modify']['params'][$field['name']] = $getCsvVal($csvArray[$numRow + 1][$i], $field);
                }
            }
        }
    }

    protected function getCsvArray($visibleFields)
    {


        $csv = [];

//Название таблицы
        $csv[] = [$this->tableRow['title']];
//Апдейтед
        $updated = json_decode($this->updated, true);
        $csv[] = ['от ' . date_create($updated['dt'])->format('d.m H:i') . '', 'code:' . $updated['code'] . '', 'structureCode:' . $this->getStructureUpdatedJSON()['code']];


//id Проекта    Название проекта
        if ($this->tableRow['type'] == 'calcs') {
            $csv[] = [$this->Cycle->getId(), $this->Cycle->getRowName()];
        } else {
            $csv[] = ['Вне циклов'];
        }

        $csv[] = ["", "", ""];

        $csv[] = ['Ручные значения'];
        $csv[] = ['[0: рассчитываемые поля не обрабатываем] [1: меняем значения рассчитываемых полей уже выставленных в ручное] [2: меняем рассчитываемые поля]'];
        $csv[] = [0];

        $csv[] = ["", "", ""];


        $addRowsByCategory = function ($categoriFields, $categoryTitle) use (&$csv, $visibleFields) {
            $csv[] = [$categoryTitle];

            $paramNames = [];
            $paramValues = [];
            $paramTitles = [];

            foreach ($categoriFields as $field) {
                if (!in_array($field['name'], $visibleFields)) continue;
                $valArray = $this->tbl['params'][$field['name']];

                Field::init($field, $this)->addViewValues('csv', $valArray, $this->tbl['params'], $this->tbl);
                $val = $valArray['v'];

                $paramTitles[] = '' . $field['title'] . '';
                $paramNames[] = '' . $field['name'] . '';
                $paramValues[] = '' . $val . '';
            }

            $csv[] = $paramTitles;
            $csv[] = $paramNames;
            $csv[] = $paramValues;
            $csv[] = ["", "", ""];
        };
        $addFilter = function ($categoriFields) use (&$csv) {
            $csv[] = ["Фильтр"];
            $_filters = [];
            foreach ($categoriFields as $field) {
                $_filters[$field['name']] = $this->tbl['params'][$field['name']]['v'] ?? null;
            }
            $csv[] = [empty($_filters) ? '' : Crypt::getCrypted(json_encode($_filters, JSON_UNESCAPED_UNICODE))];
            $csv[] = ["", "", ""];
        };


        /******Хэдер******/
        $addRowsByCategory($this->sortedVisibleFields['param'], 'Хедер');
        /******Фильтр******/
        $addFilter($this->sortedVisibleFields['filter']);


        /******Строчная часть******/
        $csv[] = ['Строчная часть'];

        $paramTitles = ['Удаление', 'id'];
        $paramNames = ['', ''];
        $rowParams = [];
        foreach ($this->sortedVisibleFields['column'] as $k => $field) {
            if (!in_array($field['name'], $visibleFields)) continue;

            $paramTitles[] = $field['title'];
            $paramNames[] = $field['name'];
            $rowParams[] = $k;
        }
        $csv[] = $paramTitles;
        $csv[] = $paramNames;
        foreach ($this->tbl['rows'] as $row) {
            $csvRow = ['', $row['id']];
            foreach ($rowParams as $fName) {

                $valArray = $row[$fName];
                Field::init($this->fields[$fName], $this)->addViewValues('csv', $valArray, $row, $this->tbl);
                $val = $valArray['v'];
                $csvRow [] = $val;
            }
            $csv[] = $csvRow;
        }
        /******Футеры колонок - только в json-таблицах******/
        if (is_a($this, JsonTables::class)) {
            $columnsFooters = [];
            $withoutColumnsFooters = [];
            $maxCountInColumn = 0;
            foreach ($this->sortedVisibleFields['footer'] as $field) {

                if (!empty($field['column'])) {
                    if (empty($columnsFooters[$field['column']])) $columnsFooters[$field['column']] = [];
                    $columnsFooters[$field['column']][] = $field;
                    if (count($columnsFooters[$field['column']]) > $maxCountInColumn) $maxCountInColumn++;
                } else {
                    $withoutColumnsFooters[] = $field;
                }
            }


            for ($iFooter = 0; $iFooter < $maxCountInColumn; $iFooter++) {
                $iFooterCsvHead = ['', 'f' . $iFooter . 'H'];
                $iFooterCsvName = ['', 'f' . $iFooter . 'N'];
                $iFooterCsvVals = ['', 'f' . $iFooter . 'V'];
                foreach ($rowParams as $fName) {
                    if (isset($columnsFooters[$fName][$iFooter])) {
                        $field = $columnsFooters[$fName][$iFooter];

                        if (!in_array($field['name'], $visibleFields)) continue;

                        $valArray = $this->tbl['params'][$field['name']];
                        Field::init($field, $this)->addViewValues('csv', $valArray, $this->tbl['params'], $this->tbl);
                        $val = $valArray['v'];

                        $iFooterCsvHead [] = $field['title'];
                        $iFooterCsvName [] = $field['name'];
                        $iFooterCsvVals [] = $val;
                    } else {
                        $iFooterCsvHead [] = '';
                        $iFooterCsvName [] = '';
                        $iFooterCsvVals [] = '';
                    }
                }
                $csv[] = $iFooterCsvHead;
                $csv[] = $iFooterCsvName;
                $csv[] = $iFooterCsvVals;
            }

            $csv[] = ["", "", ""];
            $addRowsByCategory($withoutColumnsFooters, 'Футер');

        }

        return $csv;
    }

    protected function checkTableUpdated($tableData = null)
    {
        if (is_null($tableData)) return;

        $updated = $this->updated;
        if ($this->tableRow['actual'] === 'strong' && $tableData && ($tableData['updated'] ?? null) && json_decode($updated,
                true) != $tableData['updated']
        ) {
            throw new errorException('Таблица была изменена. Обновите таблицу для проведения изменений');
        }
    }

    protected function getFieldsForAction($action, $fieldCategory)
    {
        $key = $action . ':' . $fieldCategory;
        if (!array_key_exists($key, $this->__cacheForActionFields)) {
            $fieldsForAction = [];
            foreach ($this->sortedFields[$fieldCategory] ?? [] as $field) {
                if (!empty($field['CodeActionOn' . $action])) {
                    $fieldsForAction[] = $field;
                }
            }

            $this->__cacheForActionFields[$key] = $fieldsForAction;
        }
        return $this->__cacheForActionFields[$key];
    }

    function isJsonTable()
    {
        return is_a($this, JsonTables::class);
    }

    protected function isParamsChanged()
    {
        return $this->loadedTbl['params'] != $this->getParamsWithoutFilters();
    }

    abstract protected function _copyTableData(&$table, $settings);

    protected function _getIntervals($ids)
    {
        $ids = str_replace(' ', '', $ids);
        $intervals = [];
        foreach (explode(',', $ids) as $interval) {
            if ($interval == '') continue;
            elseif (preg_match('/^\d+$/', $interval)) {
                $intervals[] = [$interval, $interval];
            } elseif (preg_match('/^(\d+)-(\d+)$/', $interval, $matches)) {
                $intervals[] = [$matches[1], $matches[2]];
            } else {
                throw new errorException('Некорректный интервал [[' . $interval . ']]');
            }
        }
        return $intervals;
    }

    abstract protected function checkRightFillOrder($id_first, $id_last, $count);

    protected function issetActiveFilters($channel)
    {
        $isActiveFilter = function ($field) {
            if (!is_null($this->tbl['params'][$field['name']] ?? null)) {
                $filterVal = $this->tbl['params'][$field['name']];
                if ($field['type'] == 'select') {
                    if (in_array('*ALL*', (array)$filterVal)
                        || in_array('*NONE*', (array)$filterVal)
                    ) {
                        return false;
                    }
                    return true;

                } elseif ($filterVal != '') {
                    return true;
                }
            }

        };

        switch ($channel) {
            case 'web':
            case 'edit':
                foreach ($this->fields as $field) {
                    if ($field['category'] === 'filter' && $field['showInWeb'] == true) {
                        return $isActiveFilter($field);
                    }
                };
                break;
            case 'xml':
                foreach ($this->fields as $field) {
                    if ($field['category'] === 'filter' && $field['showInXml'] == true) {
                        return $isActiveFilter($field);
                    }
                };
                break;
            default:
                return [];
        }
    }

    /**
     * @return string
     */
    public function getOrderFieldName(): string
    {
        return $this->orderFieldName;
    }

    function sortRowsByDefault(&$rows)
    {
        if ($this->tableRow['order_field']
            && $this->orderFieldName != 'id'
            && $this->orderFieldName != 'n'
            && ($orderField = ($this->fields[$this->orderFieldName]))
            && (!tableTypes::isRealTable($this->tableRow) || in_array($orderField['type'], ['select', 'tree']))
        ) {
            $sortArray = [];

            switch ($orderField['type']) {
                case  'select':
                case  'tree':
                    $getOrderElement = function ($row) {
                        return $row[$this->orderFieldName]['v_'][0] ?? $row[$this->orderFieldName]['v'];
                    };
                    break;
                case  'date':
                    $getOrderElement = function ($row) {
                        if ($Datetime = Calculate::getDateObject($row[$this->orderFieldName]['v'])) {
                            return $Datetime->format('Y-m-d H:i:s');
                        }
                        return null;
                    };
                    break;
                default:
                    $getOrderElement = function ($row) {
                        return $row[$this->orderFieldName]['v'];
                    };
            }

            foreach ($rows as $row) {
                $sortArray[] = $getOrderElement($row);
            }
            array_multisort($sortArray, $rows, SORT_NATURAL);
        }
        if (!empty($this->tableRow['order_desc'])) {
            $rows = array_reverse($rows);
        }

    }

}