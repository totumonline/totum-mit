<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 14.03.17
 * Time: 10:23
 */

namespace totum\tableTypes;

use Exception;
use totum\common\calculates\Calculate;
use totum\common\calculates\CalculateAction;
use totum\common\calculates\CalculcateFormat;
use totum\common\criticalErrorException;
use totum\common\errorException;
use totum\common\Field;
use totum\common\FieldModifyItem;
use totum\common\Cycle;
use totum\common\Lang\LangInterface;
use totum\common\Lang\RU;
use totum\common\logs\CalculateLog;
use totum\common\Model;
use totum\common\Totum;
use totum\common\User;
use totum\config\totum\tableTypes\traits\ActionsTrait;
use totum\fieldTypes\File;
use totum\fieldTypes\Select;
use totum\models\CalcsTablesVersions;
use totum\models\TmpTables;
use totum\models\UserV;
use totum\tableTypes\traits\WebInterfaceTrait;

abstract class aTable
{
    use WebInterfaceTrait;
    use ActionsTrait;

    protected $isTableAdding = false;

    protected const TABLES_IDS = [
        'tables' => 1,
        'tables_fields' => 2
    ];


    public const CALC_INTERVAL_TYPES = [
        'changed' => 1
        , 'all_filtered' => 2
        , 'all' => 3
        , 'no' => 4
    ];
    /**
     * @var Totum
     */
    protected $Totum;
    /**
     * @var array|bool|mixed|string|CalculateLog
     */
    protected $CalculateLog;
    /**
     * @var Model
     */
    protected $model;
    /**
     * @var User
     */
    protected $User;
    /**
     * @var array|bool|string
     */
    protected $recalculateWithALog = false;
    protected $isTableDataChanged = false;

    /**
     * @var Cycle
     */
    protected $Cycle;

    protected $tableRow;
    protected $updated;
    protected $loadedUpdated;
    protected $savedUpdated;
    protected $hash;
    protected $cachedSelects = [];
    protected $fields;
    protected $sortedFields;
    protected $orderFieldName = 'id';

    protected $filteredFields = [];

    public $isOnSaving = false;
    protected $tbl;
    protected $loadedTbl;
    protected $savedTbl;
    protected $filters;
    protected $changeIds = [
        'deleted' => [],
        'restored' => [],
        'added' => [],
        'changed' => [],
        'reorderedIds' => [],
        'rowOperations' => [],
        'rowOperationsPre' => [],
        'reordered' => false,
    ];
    protected $changeInOneRecalcIds = [
        'deleted' => [],
        'restored' => [],
        'added' => [],
        'changed' => [],
        'reorderedIds' => [],
    ];
    protected $onCalculating = false //Рассчитывается ли таблица - для некеширования запросов к ней
    ;
    /**
     * @var mixed|null
     */
    protected $extraData;
    /**
     * @var array|bool|int[]|mixed|string|string[]
     */
    protected $restoreView = false;
    /**
     * @var array|bool|int[]|mixed|string|string[]
     */
    protected $insertRowHash;
    /**
     * @var array|null
     */
    protected $insertRowSetData;
    /**
     * @var array|bool|int[]|mixed|string|string[]
     */
    protected mixed $lastFiltersChannel;


    protected function __construct(Totum $Totum, $tableRow, $extraData = null, $light = false, $hash = null)
    {
        $this->Totum = $Totum;
        $this->User = $Totum->getUser();
        $this->extraData = $extraData;

        $this->tableRow = $tableRow;

        $this->loadModel();

        if (!$light) {
            $this->initFields();
            $this->loadDataRow(true);
            $this->updated = $this->loadedUpdated = $this->savedUpdated = $this->getUpdated();
        }

        $this->hash = $hash;
        $this->tableRow['pagination'] = $this->tableRow['pagination'] ?? '0/0';
        $this->Totum = $Totum;


    }

    /**
     * @return array
     */
    public function getInAddRecalc(): array
    {
        return $this->inAddRecalc;
    }

    /**
     * @return array|null
     */
    public function getAnchorFilters()
    {
        return $this->anchorFilters;
    }

    public function isCalcsTableFromThisCyclesTable(mixed $table): bool
    {
        return false;
    }

    public function setNewTotum(Totum $Totum)
    {
        $this->Totum = $Totum;
    }

    public function setRestoreView(bool $true)
    {
        $this->restoreView = $true;
    }

    /**
     * @return string|void
     */
    public function getInsertRowHash()
    {
        return $this->insertRowHash;
    }

    /**
     * @param string $insertRowHash
     */
    public function setInsertRowHash($insertRowHash): void
    {
        $this->insertRowHash = $insertRowHash;
    }

    public function checkInsertRow($tableData, $data, $hashData, $setData = [], $clearField = null, $filtersData = [])
    {
        if ($tableData) {
            $this->checkTableUpdated($tableData);
        }

        if (is_array($hashData)) {
            $loadData = $hashData;
            $hash = $hashData['_ihash'];
        } elseif ($hash = $hashData) {
            $this->insertRowHash = $hash;
            $loadData = TmpTables::init($this->getTotum()->getConfig())->getByHash(
                    TmpTables::SERVICE_TABLES['insert_row'],
                    $this->getUser(),
                    $hash
                ) ?? [];
            if ($clearField) {
                unset($loadData[$clearField]);
            }
        } else {
            $loadData = [];
        }

        $this->insertRowSetData = array_merge(
            $filtersData,
            $loadData,
            $setData
        );

        $this->reCalculate(['channel' => 'web', 'add' => [$data], 'isCheck' => true]);


        $dataToSave = [];
        foreach ($this->tbl['rowInserted'] as $k => $v) {
            if (is_array($v)) {
                if (!empty($v['h']) || empty($this->getFields()[$k]['code'])
                    || (!empty($this->getFields()[$k]['code']) && !empty($this->getFields()[$k]['codeOnlyInAdd']) && key_exists(
                            $k,
                            $data + $loadData + $setData
                        ))) {
                    $dataToSave[$k] = $v['v'];
                }
            }
        }
        if ($this->tableRow['type'] === 'tmp') {
            $dataToSave['_hash'] = $this->hash;
        }

        TmpTables::init($this->Totum->getConfig())->saveByHash(
            TmpTables::SERVICE_TABLES['insert_row'],
            $this->User,
            $hash,
            $dataToSave
        );
        return $this->tbl['rowInserted'];
    }

    public function getLangObj(): LangInterface
    {
        return $this->Totum->getLangObj();
    }

    protected function execDefaultTableAction(mixed $codeAction, $loadedTbl, $tbl): void
    {
        $Code = new CalculateAction($codeAction);

        $Code->execAction('DEFAULT ACTION', [], [], $loadedTbl, $tbl, $this, 'exec',
            ['changes' => function () use ($tbl, $loadedTbl) {
                $changes = [];

                $getChangedFields = function ($newRow, $oldRow) {
                    $keys = [];
                    foreach ($newRow as $k => $_v) {
                        /*key_exists for $oldRow[$k] не использовать!*/
                        if (is_array($_v) && Calculate::compare('!==',
                                ($oldRow[$k]['v'] ?? null),
                                $_v['v'],
                                $this->getLangObj())) {
                            $keys[] = $k;
                        }
                    }
                    return $keys;
                };


                foreach (['deleted',
                             'restored',
                             'added',
                             'changed',
                             'reorderedIds'] as $cat) {
                    $changes[$cat] = match ($cat) {
                        'deleted', 'added', 'reorderedIds', 'restored' => array_keys($this->changeInOneRecalcIds[$cat]),
                        default => []
                    };
                }

                if (is_a($this, RealTables::class)) {
                    $changes['changed'] = $this->changeInOneRecalcIds['changed'];
                    array_walk($changes['changed'], function (&$v, $id) use ($getChangedFields, $tbl, $loadedTbl) {
                        if (key_exists('old', $v)) {
                            $v = $getChangedFields($v['new'], $v['old']);
                        } elseif ($v === true || empty($v)) {
                            $v = $getChangedFields($tbl['rows'][$id], $loadedTbl['rows']['id']);
                        } else {
                            $v = array_keys($v);
                        }
                    });
                } else {
                    foreach ($tbl['rows'] ?? [] as $id => $row) {
                        if (key_exists($id, $loadedTbl['rows'] ?? [])) {
                            $oldRow = $loadedTbl['rows'][$id];
                            if (Calculate::compare('!==', $oldRow, $row, $this->getLangObj())) {
                                if ($_ = $getChangedFields($row, $oldRow)) {
                                    $changes['changed'][$id] = $_;
                                }
                            }
                        }
                    }
                }

                $changes['changed']['params'] = $getChangedFields($tbl['params'], $loadedTbl['params']);
                return $changes;
            }]
        );
    }


    /**
     * @param bool $isTableDataChanged
     */
    protected function setIsTableDataChanged(bool $isTableDataChanged): void
    {
        $this->isTableDataChanged = $isTableDataChanged;
    }

    abstract protected function loadModel();

    abstract public function loadDataRow($fromConstructor = false, $force = false);

    /**
     * Возвращает loadedUpdated для текущей таблицы в зависимости от типа таблицы из разных мест
     *
     * @return string updated
     */
    public function getUpdated()
    {
        return $this->tableRow['updated'];
    }


    public function getTableRow()
    {
        return $this->tableRow;
    }

    public function getCycle()
    {
        return null;
    }

    /*В том числе из цикла, не внутренняя*/
    public function setVersion($version, $auto_recalc)
    {
        $this->tableRow['__version'] = $version;
        $this->tableRow['__auto_recalc'] = $auto_recalc;
    }

    abstract protected function getNewTblForRecalc();

    abstract protected function loadRowsByIds(array $ids);

    /**
     * @param CalculateLog|array|string $Log
     */
    public function addCalculateLogInstance($Log)
    {
        if (is_array($Log)) {
            $this->CalculateLog = $this->CalculateLog->getChildInstance($Log);
        } elseif ($Log === 'parent') {
            if (!($this->CalculateLog = $this->CalculateLog->getParent())) {
                debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
                die('Parent Log is empty - nesting error.');
            }
        } elseif (is_object($Log)) {
            $this->CalculateLog = $Log;
        } else {
            debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            errorException::criticalException($this->translate('Log is empty.'), $this);
        }

        return $this->CalculateLog;
    }

    public function calcLog($Log, $key = null, $result = null)
    {
        if (is_array($Log)) {
            $this->CalculateLog = $this->CalculateLog->getChildInstance($Log);
        } elseif ($key) {
            $Log->addParam($key, $result);
            $this->CalculateLog = $Log->getParent();
        } elseif (is_object($Log)) {
            $this->CalculateLog = $Log;
        }
        return $this->CalculateLog;
    }

    public static $recalcLog = [];

    protected $cacheForActionFields = [];
    protected $filtersFromUser = [];
    protected $calculatedFilters;

    /**
     * @var array|bool|string
     */
    private $anchorFilters = [];
    /**
     * @var array|bool|string
     */
    protected $webIdInterval = [];


    public static function init(Totum $Totum, $tableRow, $extraData = null, $light = false)
    {
        return new static($Totum, $tableRow, $extraData, $light, null);
    }

    public function reCreateFromDataBase(): aTable
    {
        return $this->Totum->getTable($this->getTableRow(), null, false, true);
    }

    /**
     * @param $action String
     * @return bool
     */
    public function isUserCanAction($action)
    {
        $tableRow = $this->tableRow;

        switch ($action) {
            case 'edit':
                return !!($this->User->getTables()[$tableRow['id']] ?? null);
            case 'insert':
                if ($tableRow['insertable'] && ($this->User->getTables()[$tableRow['id']] ?? null)) {
                    if (empty($tableRow['insert_roles']) || array_intersect(
                            $tableRow['insert_roles'],
                            $this->User->getRoles()
                        )) {
                        return true;
                    }
                }
                break;
            case 'delete':
                if ($tableRow['deleting'] !== 'none' && ($this->User->getTables()[$tableRow['id']] ?? null)) {
                    if (empty($tableRow['delete_roles']) || array_intersect(
                            $tableRow['delete_roles'],
                            $this->User->getRoles()
                        )) {
                        return true;
                    }
                }
                break;
            case 'restore':
                if ($tableRow['deleting'] === 'hide' && ($this->User->getTables()[$tableRow['id']] ?? null)) {
                    if ((empty($tableRow['restore_roles']) && $this->isUserCanAction('delete')) || array_intersect(
                            $tableRow['delete_roles'],
                            $this->User->getRoles()
                        )) {
                        return true;
                    }
                }
                break;
            case 'duplicate':
                if ($tableRow['duplicating'] && ($this->User->getTables()[$tableRow['id']] ?? null)) {
                    if (empty($tableRow['duplicate_roles']) || array_intersect(
                            $tableRow['duplicate_roles'],
                            $this->User->getRoles()
                        )) {
                        return true;
                    }
                }
                break;
            case 'reorder':
                if ($tableRow['with_order_field'] && ($this->User->getTables()[$tableRow['id']] ?? null)) {
                    if (empty($tableRow['order_roles']) || array_intersect(
                            $tableRow['order_roles'],
                            $this->User->getRoles()
                        )) {
                        return true;
                    }
                }
                break;
            case 'csv':
                if (empty($tableRow['csv_roles']) || array_intersect(
                        $tableRow['csv_roles'],
                        $this->User->getRoles()
                    )) {
                    return true;
                }
                break;
            case 'csv_edit':
                if (!empty($tableRow['csv_edit_roles']) && array_intersect(
                        $tableRow['csv_edit_roles'],
                        $this->User->getRoles()
                    )) {
                    return true;
                }
                break;
        }
        return false;
    }

    /**
     * TODO подумать все поля нужны не всегда
     *
     * @param false $force
     */
    public function initFields($force = false)
    {
        if ($this->tableRow['type'] === 'calcs' && is_null($this->tableRow['__version'] ?? null)) {
            /** @var Cycle $Cycle */
            $Cycle = $this->getCycle();
            list($version, $auto) = $Cycle->addVersionForCycle($this->tableRow['name']);
            $this->setVersion($version, $auto);
        }

        $this->fields = $this->loadFields(
            $this->tableRow['id'],
            $this->tableRow['__version'] ?? null,
            !$force,
            $this->Cycle ? $this->Cycle->getId() : 0
        );

        if (!empty($this->tableRow['order_field'])) {
            $this->orderFieldName = $this->tableRow['order_field'];
        } else {
            $this->orderFieldName = 'id';
        }

        $this->sortedFields = static::sortFields($this->fields);
    }

    /**
     * @return User
     */
    public function getUser(): User
    {
        return $this->User;
    }


    protected function loadFields(int $tableId, $version = null, $withCache = true, $cycleId = 0)
    {
        if (!$withCache
            || ($tableId !== static::TABLES_IDS['tables'] && $this->Totum->getTable(static::TABLES_IDS['tables'])->isOnSaving)
            || is_null($fields = $this->Totum->getFieldsCaches($tableId, $version))) {
            $fields = [];
            $where = ['table_id' => $tableId, 'version' => $version];
            $links = [];

            foreach ($this->Totum->getModel('tables_fields__v', true)->executePrepared(
                true,
                $where,
                'name, category, data, id, title, ord',
                'ord, name'
            ) as $f) {
                ;
                $f = $fields[$f['name']] = static::getFullField($f);

                if (array_key_exists('type', $f) && $f['type'] === 'link') {
                    $links[] = $f;
                }
            }

            foreach ($links as $f) {
                if ($linkTableRow = $this->Totum->getConfig()->getTableRow($f['linkTableName'])) {
                    $linkTableId = $linkTableRow['id'];

                    if ($tableId === $linkTableId) {
                        $fForLink = $fields[$f['linkFieldName']] ?? null;
                    } elseif ($linkTableRow['type'] === 'calcs') {
                        if ($this->Totum->getConfig()->getTableRow($tableId)['type'] === 'calcs') {
                            $_version = $this->Totum->getCycle(
                                $cycleId,
                                $linkTableRow['tree_node_id']
                            )->getVersionForTable($f['linkTableName'])[0];
                        } else {
                            $_version = CalcsTablesVersions::init($this->Totum->getConfig())->getDefaultVersion($f['linkTableName']);
                        }

                        $fForLink = ($this->loadFields($linkTableId, $_version)[$f['linkFieldName']]) ?? null;
                    } else {
                        $fForLink = ($this->loadFields($linkTableId)[$f['linkFieldName']]) ?? null;
                    }

                    if ($fForLink) {
                        $fieldFromLinkParams = [];
                        foreach (['type', 'dectimalPlaces', 'closeIframeAfterClick', 'dateFormat', 'codeSelect',
                                     'multiple', 'codeSelectIndividual', 'buttonText', 'unitType', 'currency',
                                     'textType', 'withEmptyVal', 'multySelectView', 'dateTime', 'printTextfull',
                                     'viewTextMaxLength', 'values', 'before', 'prefix', 'thousandthSeparator', 'dectimalSeparator', 'postfix'
                                 ] as $fV) {
                            if (isset($fForLink[$fV])) {
                                $fieldFromLinkParams[$fV] = $fForLink[$fV];
                            }
                        }
                        if ($fieldFromLinkParams['type'] === 'button') {
                            $fieldFromLinkParams['codeAction'] = $fForLink['codeAction'];
                        } elseif ($fieldFromLinkParams['type'] === 'file') {
                            $fields[$f['name']]['fileDuplicateOnCopy'] = false;
                        }

                        $fields[$f['name']] = array_merge($fields[$f['name']], $fieldFromLinkParams);
                    } else {
                        $fields[$f['name']]['linkFieldError'] = true;
                    }
                } else {
                    $fields[$f['name']]['linkFieldError'] = true;
                }
                $fields[$f['name']]['code'] = 'Select code';
                if ($fields[$f['name']]['type'] === 'link') {
                    $fields[$f['name']]['type'] = 'string';
                }
            }

            foreach ($fields as &$f) {
                if ($f['category'] === 'filter') {
                    if (empty($f['codeSelect']) && !empty($f['column']) && ($column = $fields[$f['column']] ?? null)) {
                        if (isset($column['codeSelect'])) {
                            $f['codeSelect'] = $column['codeSelect'];
                        } elseif (isset($column['values'])) {
                            $f['values'] = $column['values'];
                        }
                    }
                }
                switch ($f['type']) {
                    case 'number':
                        if (($f['currency'] ?? false)) {
                            $f['thousandthSeparator'] = $f['thousandthSeparator'] ?? $this->getTotum()->getConfig()->getSettings('numbers_format')['thousandthSeparator'] ?? ' ';
                            $f['dectimalSeparator'] = $f['dectimalSeparator'] ?? $this->getTotum()->getConfig()->getSettings('numbers_format')['dectimalSeparator'] ?? ',';
                        }
                        break;
                    case 'date':
                        if (empty($f['dateFormat'])) {
                            $f['dateFormat'] = $this->getTotum()->getConfig()->getSettings('dates_format') ?? 'd.m.y';

                            if (!empty($f['dateTime'])) {
                                $f['dateFormat'] .= ' H:i';
                            }
                        }
                        break;
                }
            }
            unset($f);
            $this->Totum->setFieldsCaches($tableId, $version, $fields);
        }

        return $fields;
    }

    public static function getFullField($fieldRow)
    {
        $data = json_decode($fieldRow['data'], true);
        return array_merge(
            $data ?? [],
            ['category' => $fieldRow['category'], 'name' => $fieldRow['name'], 'ord' => $fieldRow['ord'], 'id' => $fieldRow['id'], 'title' => $fieldRow['title']]
        );
    }

    public static function getFooterColumns($columns)
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

    public function checkIsUserCanViewIds($channel, $ids, $removed = false)
    {
        $getFiltered = [];
        if ($channel !== 'inner') {
            $getFiltered = $this->loadFilteredRows($channel, $ids, $removed);
            foreach ($ids as $id) {
                if (!in_array($id, $getFiltered)) {
                    errorException::criticalException($this->translate('The row %s does not exist or is not available for your role.',
                        (string)$id),
                        $this
                    );
                }
            }
        }
        return $getFiltered;
    }

    public function getVisibleFields(string $channel, $sorted = false)
    {
        if ($sorted) {
            if (!key_exists($channel, $this->filteredFields) || !key_exists(
                    'sorted',
                    $this->filteredFields[$channel]
                )) {
                $this->filteredFields[$channel]['sorted'] = static::sortFields($this->getVisibleFields($channel));
            }
            return $this->filteredFields[$channel]['sorted'];
        }
        if (key_exists($channel, $this->filteredFields)) {
            return $this->filteredFields[$channel]['simple'];
        }

        switch ($channel) {
            case 'web':

                $this->filteredFields[$channel] = ['simple' => []];
                $columnsFooters = [];
                foreach ($this->fields as $fName => $field) {
                    if ($this->isField(
                        'visible',
                        $channel,
                        $field
                    )) {
                        $this->filteredFields[$channel]['simple'][$fName] = $field;

                        if ($field['category'] === 'footer' && !empty($field['column'])) {
                            $columnsFooters[] = $field;
                        }
                    } elseif ($fName === $this->tableRow['main_field']) {
                        $field['showInWeb'] = false;
                        $this->filteredFields[$channel]['simple'][$fName] = $field;
                    }
                }
                foreach ($columnsFooters as $f) {
                    if (empty($this->filteredFields[$channel]['simple'][$f['column']])) {
                        unset($this->filteredFields[$channel]['simple'][$f['name']]);
                    }
                }
                return $this->filteredFields[$channel]['simple'];

            case
            'xml':

                $this->filteredFields[$channel] = ['simple' => []];
                $columnsFooters = [];
                foreach ($this->fields as $fName => $field) {
                    if ($this->isField('visible', $channel, $field)) {
                        $this->filteredFields[$channel]['simple'][$fName] = $field;

                        if ($field['category'] === 'footer' && !empty($field['column'])) {
                            $columnsFooters[] = $field;
                        }
                    }
                }
                foreach ($columnsFooters as $f) {
                    if (empty($this->filteredFields[$channel]['simple'][$f['column']])) {
                        unset($this->filteredFields[$channel]['simple'][$f['name']]);
                    }
                }
                return $this->filteredFields[$channel]['simple'];
            default:
                debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
                throw new errorException('Incorrect channel');
        }
    }

    /**
     * @return CalculateLog
     */
    public function getCalculateLog()
    {
        if (empty($this->CalculateLog)) {
            debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        }
        return $this->CalculateLog;
    }

    public function getUpdatedJson()
    {
        return static::formUpdatedJson($this->Totum->getUser());
    }

    public static function formUpdatedJson(User $User)
    {
        return json_encode(['dt' => date('Y-m-d H:i'), 'code' => mt_rand(), 'user' => $User->getId()]);
    }

    public function getSavedUpdated()
    {
        return $this->savedUpdated;
    }

    abstract public function createTable(int $duplicatedId);

    public function reCalculateFromOvers($inVars = [], $Log = null, $level = 0)
    {
        if ($Log) {
            $this->addCalculateLogInstance($Log);
        }

        $Log = $this->calcLog(["name" => 'RECALC', 'table' => $this, 'inVars' => $inVars]);

        try {
            if ($level > 20) {
                throw new errorException($this->translate('More than 20 nesting levels of table changes. Most likely a recalculation loop'));
            }
            $this->reCalculate($inVars);
            $result = $this->isTblUpdated($level);
            $this->calcLog($Log, 'result', $result ? 'changed' : 'no changed');
        } catch (Exception $exception) {
            $this->calcLog($Log, 'error', $exception->getMessage());
            throw $exception;
        }
    }

    public function __get($name)
    {
        switch ($name) {
            case 'updated':
                return $this->updated;
            case 'params':
                return $this->tbl['params'] ?? [];
            case 'addedIds':
                return array_keys($this->changeIds['added']);
            case 'deletedIds':
                return array_keys($this->changeIds['deleted']);
            case 'isOnSaving':
                return $this->isOnSaving;
        }

        $debug = debug_backtrace(0, 3);
        //array_splice($debug, 0, 1);
        throw new errorException('Requested [[' . $name . ']] property does not exist' . print_r($debug, 1));
    }

    abstract public function addField($field);

    public function selectSourceTableAction($fieldName, $itemData)
    {
        if (empty($this->fields[$fieldName]['selectTableAction'])) {
            if (!empty($this->fields[$fieldName]['selectTable'])) {
                $row2 = '';
                if ($this->fields[$fieldName]['selectTableBaseField'] ?? false) {
                    $param = '$selId';
                    $row2 = "\n" . <<<CODE
selId: select(table: '{$this->fields[$fieldName]['selectTable']}'; field: 'id'; where: '{$this->fields[$fieldName]['selectTableBaseField']}' = #{$fieldName})
CODE;;
                } else {
                    $param = '#' . $fieldName;
                }
                $this->fields[$fieldName]['selectTableAction'] = '=: linkToPanel(table: "' . $this->fields[$fieldName]['selectTable'] . '"; id: ' . $param . ')' . $row2;
            } else {
                throw new errorException($this->translate('The field is not configured.'));
            }
        }

        $CA = new CalculateAction($this->fields[$fieldName]['selectTableAction']);
        try {
            $CA->execAction($fieldName, $itemData, $itemData, $this->tbl, $this->tbl, $this, 'exec');
        } catch (errorException $e) {
            $e->addPath($this->translate('field [[%s]] of [[%s]] table',
                [$this->fields[$fieldName]['title'], $this->tableRow['name']]));
            throw $e;
        }

    }

    public function getTotum()
    {
        return $this->Totum;
    }


    public static function sortFields($fields)
    {
        $sortedFields = ['column' => [], 'param' => [], 'footer' => [], 'filter' => []];
        foreach ($fields as $k => $v) {
            $sortedFields[$v['category']][$k] = $v;
        }
        return $sortedFields;
    }

    public function setWebIdInterval($ids)
    {
        if ($ids && is_array($ids)) {
            $this->webIdInterval = $ids;
        }
    }

    protected $inAddRecalc = [];

    protected function reCalculate($inVars = [])
    {
        $this->onCalculating = true;
        $this->cachedSelects = [];

        $add = [];

        $modify = [];
        $setValuesToDefaults = [];
        $setValuesToPinned = [];
        $remove = [];
        $restore = [];
        $isTableAdding = $this->isTableAdding;

        $addAfter = null;
        $addWithId = false;
        $isCheck = false;
        $modifyCalculated = true;
        $duplicate = [];
        $calculate = 'changed';
        $inAddRecalc = [];

        $channel = 'inner';
        $reorder = [];
        $default = [
            'modify'
            , 'setValuesToDefaults'
            , 'inAddRecalc'
            , 'add'
            , 'remove'
            , 'restore'
            , 'isTableAdding'
            , 'isCheck'
            , 'modifyCalculated'
            , 'addAfter'
            , 'addWithId'
            , 'setValuesToPinned'
            , 'isEditFilters'
            , 'addFilters'
            , 'duplicate'
            , 'calculate'
            , 'channel'
            , 'reorder'
        ];


        $inVars = array_intersect_key($inVars, array_flip($default));
        extract($inVars);

        $this->inAddRecalc = $inAddRecalc;

        $this->setIsTableDataChanged(!!$isTableAdding);
        $modify['params'] = $modify['params'] ?? [];

        if (($this->tableRow['deleting'] ?? null) === 'none' && !empty($remove) && $channel !== 'inner') {
            throw new errorException($this->translate('You are not allowed to delete from this table'));
        }

        $oldTbl = $this->tbl;


        $this->changeInOneRecalcIds = [
            'deleted' => [],
            'restored' => [],
            'added' => [],
            'changed' => [],
            'reorderedIds' => [],
        ];


        $newTbl = $this->getNewTblForRecalc();

        $this->tbl = &$newTbl;


        foreach (['param', 'filter', 'column'] as $category) {
            if (!($columns = $this->sortedFields[$category] ?? []) && $category !== "column") {
                continue;
            }


            try {
                switch ($category) {
                    case 'filter':
                        $Log = $this->calcLog(["recalculate" => $category]);

                        $this->reCalculateFilters(
                            $channel,
                            false,
                            $inVars['addFilters'] ?? false,
                            $modify,
                            $setValuesToDefaults
                        );
                        $this->calcLog($Log, 'result', 'done');
                        break;
                    case 'column':
                        $modifyIds = $modify;
                        unset($modifyIds['params']);

                        if ($channel !== 'inner') {

                            /* Жесткая проверка для каналов удаление/восстановление/редактирование/сброс к рассчетному*/
                            $defaults = $setValuesToDefaults;
                            unset($defaults['params']);

                            if ($ids = array_merge(array_keys($modifyIds),
                                $remove ?? [],
                                array_keys($defaults))) {

                                $this->tbl['rows'] = $oldTbl['rows'];
                                $this->checkIsUserCanViewIds($channel, $ids);
                                $this->tbl['rows'] = [];
                            }
                            if ($restore) {
                                $this->tbl['rows'] = $oldTbl['rows'];
                                $this->checkIsUserCanViewIds($channel, $restore, true);
                                $this->tbl['rows'] = [];
                            }
                        }

                        $this->reCalculateRows(
                            $calculate,
                            $channel,
                            $isCheck,
                            $modifyCalculated,
                            $isTableAdding,
                            $remove,
                            $restore,
                            $add,
                            $modify,
                            $setValuesToDefaults,
                            $setValuesToPinned,
                            $duplicate,
                            $reorder,
                            $addAfter,
                            $addWithId
                        );


                        break;
                    default:
                        $Log = $this->calcLog(["recalculate" => $category]);

                        foreach ($columns as $column) {
                            if ($isTableAdding) {
                                $newTbl['params'][$column['name']] = Field::init($column, $this)->add(
                                    $channel,
                                    $modify['params'][$column['name']] ?? null,
                                    $newTbl['params'],
                                    $oldTbl,
                                    $newTbl
                                );
                            } else {
                                $newVal = $modify['params'][$column['name']] ?? null;
                                $oldVal = $oldTbl['params'][$column['name']] ?? null;


                                /** @var Field $Field */
                                $Field = Field::init($column, $this);

                                $changedFlag = $Field->getModifyFlag(
                                    array_key_exists(
                                        $column['name'],
                                        $modify['params'] ?? []
                                    ),
                                    $newVal,
                                    $oldVal,
                                    array_key_exists($column['name'], $setValuesToDefaults['params'] ?? []),
                                    array_key_exists($column['name'], $setValuesToPinned['params'] ?? []),
                                    $modifyCalculated
                                );

                                $newTbl['params'][$column['name']] = $Field->modify(
                                    $channel,
                                    $changedFlag,
                                    $newVal,
                                    $oldTbl['params'] ?? [],
                                    $newTbl['params'],
                                    $oldTbl,
                                    $newTbl,
                                    $isCheck
                                );

                                $this->checkIsModified($oldVal, $newTbl['params'][$column['name']]);

                                $this->addToALogModify(
                                    $Field,
                                    $channel,
                                    $newTbl,
                                    $newTbl['params'],
                                    null,
                                    $modify['params'] ?? [],
                                    $setValuesToDefaults['params'] ?? [],
                                    $setValuesToPinned['params'] ?? [],
                                    $oldVal
                                );
                            }
                        }
                        $this->calcLog($Log, 'result', 'done');
                        break;
                }
            } catch (Exception $exception) {
                throw $exception;
            }
        }

        $this->inAddRecalc = [];
        $this->onCalculating = false;
        $this->recalculateWithALog = false;
    }


    abstract public function saveTable();

    public function getChangedString($code)
    {
        $updated = json_decode($this->getLastUpdated(true), true);

        if ((string)$updated['code'] !== $code) {
            return ['username' => $this->Totum->getNamedModel(UserV::class)->getFio($updated['user'],
                true), 'dt' => $updated['dt'], 'code' => $updated['code']];
        } else {
            return ['no' => true];
        }
    }

    public function getByParams($params, $returnType = 'field')
    {
        $this->loadDataRow();

        $fields = $this->fields;

        $params['field'] = (array)($params['field'] ?? []);
        $params['sfield'] = (array)($params['sfield'] ?? []);
        $params['pfield'] = (array)($params['pfield'] ?? []);

        foreach ($params['field'] ?? [] as $i => $fName) {
            switch ($fName) {
                case '*ALL*':
                    unset($params['field'][$i]);
                    $params['field'] = array_merge($params['field'], array_keys($this->getSortedFields()['column']));
                    $params['field'] = array_unique($params['field']);
                    break;
                case '*HEADER*':
                    unset($params['field'][$i]);
                    $params['field'] = array_merge($params['field'], array_keys($this->getSortedFields()['param']));
                    $params['field'] = array_unique($params['field']);
                    break;
                case '*FOOTER*':
                    unset($params['field'][$i]);
                    $params['field'] = array_merge($params['field'], array_keys($this->getSortedFields()['footer']));
                    $params['field'] = array_unique($params['field']);
                    break;
            }
        }

        $Field = $params['field'][0] ?? $params['sfield'][0] ?? null;
        if (empty($Field)) {
            throw new errorException($this->translate('No select field specified'));
        }

        if (in_array($returnType, ['list', 'field']) && count($params['field']) > 1) {
            throw new errorException($this->translate('More than one field/sfield is specified'));
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
            if (!is_string($fName)) {
                throw new errorException($this->translate('Not correct field name in query to [[%s]] table.',
                    [$this->tableRow['name']]));
            }
            if (!array_key_exists($fName, $fields) && !in_array($fName, Model::serviceFields)) {
                throw new errorException($this->translate('The [[%s]] field is not found in the [[%s]] table.',
                    [$fName, $this->tableRow['name']]));
            }
        }

        $sectionReplaces = function ($row) use ($params) {
            $rowReturn = [];
            foreach ($params['fieldOrder'] as $fName) {
                if (!array_key_exists(
                    $fName,
                    $row
                )) {

                    // debug_print_backtrace(0, 3);
                    throw new errorException($this->translate('Field [[%s]] is not found.', $fName));
                }

                //sfield
                if (Model::isServiceField($fName)) {
                    $rowReturn[$fName] = $row[$fName];
                } //field
                elseif (in_array($fName, $params['sfield'])) {
                    $Field = Field::init($this->fields[$fName], $this);
                    $selectValue = $Field->getSelectValue(
                        $row[$fName]['v'] ?? null,
                        $row,
                        $this->tbl
                    );
                    $rowReturn[$fName] = $selectValue;
                } //id||n||is_del
                else {
                    $rowReturn[$fName] = $row[$fName]['v'];
                }
            }

            return $rowReturn;
        };

        if (!empty($fields[$Field]) && $fields[$Field]['category'] !== 'column') {
            switch ($returnType) {
                case 'field':
                    if (!key_exists('params', $this->tbl)) {
                        return null;
                    }
                    return $sectionReplaces($this->tbl['params'] ?? [])[$Field] ?? null;
                case 'list':
                    if (!key_exists('params', $this->tbl)) {
                        return [];
                    }
                    return [$sectionReplaces($this->tbl['params'])[$Field]];
                case 'row':
                    if (!key_exists('params', $this->tbl)) {
                        return [];
                    }
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

    public function __call($name, $arguments)
    {
        throw new errorException($this->translate($this->translate('The %s function is not provided for this type of tables',
            $name)));
    }

    public function getFields()
    {
        return $this->fields;
    }

    public function getSortedFields()
    {
        return $this->sortedFields;
    }


    public function getRowsForFormat($rowIds)
    {
        return function () use ($rowIds): array {
            $rows = [];

            $this->getTotum()->getMessenger()->formatUseRows(true);

            if ($rowIds) {
                $rowIds = $this->loadFilteredRows('web', $rowIds);
                foreach ($rowIds as $id) {
                    $row = $this->getTbl()['rows'][$id];
                    unset($row['_E']);
                    foreach ($row as $k => $v) {
                        if (Model::isServiceField($k)) {
                            $row[$k] = $v;
                        } else {
                            $row[$k] = $v['v'] ?? null;
                        }
                    }
                    $rows[] = $row;
                }
            }
            return $rows;
        };
    }


    /**
     * @param $data
     * @param string $viewType
     * @param array|null $fieldNames list of field names
     * @return mixed
     * @throws errorException
     */
    public function getValuesAndFormatsForClient($data, string $viewType, array $pageIds, array $fieldNames = null)
    {

        $isWebViewType = in_array($viewType, ['web', 'edit', 'csv', 'print']);
        $isWithList = in_array($viewType, ['web', 'edit']);
        $isWithFormat = in_array($viewType, ['web', 'edit']);

        if ($isWebViewType) {
            $visibleFields = $this->getVisibleFields('web');
        } elseif ($viewType === 'xml') {
            $visibleFields = $this->getVisibleFields('xml');
        } else {
            $visibleFields = $this->fields;
        }

        if (is_array($fieldNames)) {
            $visibleFields = array_intersect_key($visibleFields, array_combine($fieldNames, $fieldNames));
        }
        $sortedFields = static::sortFields($visibleFields);

        if ($isWithFormat && $this->tableRow['row_format'] !== '' && $this->tableRow['row_format'] !== 'f1=:') {
            $RowFormatCalculate = new CalculcateFormat($this->tableRow['row_format']);
        }
        $data['rows'] = ($data['rows'] ?? []);

        $ids = array_unique(array_merge($this->webIdInterval, array_column($data['rows'], 'id')));


        $savedColumnVals = [];
        $getColumnVals = function ($fName) use ($data, &$savedColumnVals) {
            if (!key_exists($fName, $savedColumnVals)) {
                $savedColumnVals[$fName] = [];
                $indexedVals = [];
                if (!empty($this->fields[$fName]['multiple'])) {
                    foreach ($data['rows'] as $row) {
                        if (!key_exists($fName, $row)) {
                            continue;
                        }
                        foreach ((array)$row[$fName]['v'] as $v) {
                            if (!key_exists($v, $indexedVals)) {
                                $indexedVals[$v] = 1;
                            }
                        }
                        if (key_exists('c', $row[$fName])) {
                            foreach ((array)$row[$fName]['c'] as $v) {
                                if (!key_exists($v, $indexedVals)) {
                                    $indexedVals[$v] = 1;
                                }
                            }
                        }
                    }
                } else {
                    foreach ($data['rows'] as $row) {
                        if (empty($row[$fName])) {
                            continue;
                        }
                        if (!is_array($row[$fName]['v']) && !key_exists($row[$fName]['v'], $indexedVals)) {
                            $indexedVals[$row[$fName]['v']] = 1;
                        }
                        if (key_exists('c', $row[$fName])) {
                            if (!key_exists($row[$fName]['c'], $indexedVals)) {
                                $indexedVals[$row[$fName]['c']] = 1;
                            }
                        }
                    }
                }
                $savedColumnVals[$fName] = array_keys($indexedVals);
            }
            return $savedColumnVals[$fName];
        };

        foreach ($data['rows'] as $i => $row) {

            $newRow = ['id' => ($row['id'] ?? null)];
            $rowIn = $this->tbl['rows'][$row['id'] ?? ''] ?? $row;

            if (array_key_exists('n', $row)) {
                $newRow['n'] = $row['n'];
                if (!empty($this->getTableRow()['new_row_in_sort']) && key_exists(
                        $row['id'],
                        $this->changeIds['added']
                    )) {
                    if ($this->getTableRow()['order_desc']) {
                        $newRow['__after'] = $this->getByParams(
                            ['field' => 'id',
                                'where' => [
                                    ['field' => 'n', 'operator' => '>', 'value' => $row['n']],
                                    ['field' => 'id', 'operator' => '=', 'value' => $ids]
                                ], 'order' => [['field' => 'n', 'ad' => 'asc']]],
                            'field'
                        );
                    } else {
                        $newRow['__after'] = $this->getByParams(
                            ['field' => 'id',
                                'where' => [
                                    ['field' => 'n', 'operator' => '<', 'value' => $row['n']],
                                    ['field' => 'id', 'operator' => '=', 'value' => $ids]
                                ], 'order' => [['field' => 'n', 'ad' => 'desc']]],
                            'field'
                        );
                    }
                }
            }
            if (!empty($row['InsDel'])) {
                $newRow['InsDel'] = true;
            }

            //if (empty($row['id'])) debug_print_backtrace();
            foreach ($sortedFields['column'] as $f) {
                if (empty($row[$f['name']])) {
                    continue;
                }


                if (!empty($f['notLoaded']) && $viewType === 'web') {
                    $rowIn[$f['name']]['v'] = '**NOT_LOADED**';
                }

                $newRow[$f['name']] = $row[$f['name']];
                if ($f['type'] === 'select') {
                    $newRow[$f['name']]['columnVals'] = function () use ($f, $getColumnVals) {
                        return $getColumnVals($f['name']);
                    };
                }

                Field::init($f, $this)->addViewValues(
                    $viewType,
                    $newRow[$f['name']],
                    $rowIn,
                    $this->tbl
                );

                unset($newRow[$f['name']]['columnVals']);

                if ($isWithFormat) {
                    Field::init($f, $this)->addFormat(
                        $newRow[$f['name']],
                        $rowIn,
                        $this->tbl,
                        $pageIds
                    );
                }
            }

            if ($isWithFormat && !empty($RowFormatCalculate)) {
                $Log = $this->calcLog(['itemId' => $row['id'] ?? null, 'cType' => 'format', 'name' => 'row']);

                $newRow['f'] = $RowFormatCalculate->getFormat(
                    'ROW',
                    $rowIn,
                    $this->tbl,
                    $this
                );
                $this->calcLog($Log, 'result', $newRow['f']);
            } else {
                $newRow['f'] = [];
            }
            $data['rows'][$i] = $newRow;
        }
        if (!empty($data['params'])) {
            $filteredParams = [];
            foreach (['param', 'footer', 'filter'] as $category) {
                foreach ($sortedFields[$category] ?? [] as $f) {
                    if (empty($data['params'][$f['name']])) {
                        continue;
                    }

                    $Field = Field::init($f, $this);

                    if ($isWithFormat) {
                        $Field->addFormat(
                            $data['params'][$f['name']],
                            $this->tbl['params'],
                            $this->tbl,
                            $pageIds
                        );
                    }

                    $Field->addViewValues(
                        $viewType,
                        $data['params'][$f['name']],
                        $this->tbl['params'],
                        $this->tbl
                    );


                    if ($isWithList && $f['category'] === 'filter' && in_array($f['type'], ['select', 'tree'])) {
                        /** @var Select $Field */
                        $data['params'][$f['name']]['list'] = $Field->cropSelectListForWeb(
                            $Field->calculateSelectList(
                                $f,
                                $this->tbl['params'],
                                $this->tbl
                            ),
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

    public function checkUnic($fieldName, $fieldVal)
    {
        if ($this->getByParams(
            ['field' => 'id', 'where' => [['field' => $fieldName, 'operator' => '=', 'value' => $fieldVal]]],
            'field'
        )) {
            return ['ok' => false];
        } else {
            return ['ok' => true];
        }
    }


    public function getSelectByParams($params, $returnType = 'field', $rowId = null, $toSource = false)
    {
        if (empty($params['table'])) {
            throw new errorException($this->translate('Fill in the parameter [[%s]].', 'table'));
        }

        if (in_array(
                $returnType,
                ['field']
            ) && empty($params['field']) && empty($params['sfield'])
        ) {
            throw new errorException($this->translate('Fill in the parameter [[%s]].', 'field/sfield'));
        }

        $sourceTableRow = $this->Totum->getTableRow($params['table']);
        if (!$sourceTableRow) {
            throw new errorException($this->translate('Table [[%s]] is not found.', $params['table']));
        }

        if ($sourceTableRow['type'] === 'tmp') {
            if ($this->getTableRow()['id'] === $sourceTableRow['id']) {
                $SourceTable = $this;
            } elseif (!empty($params['hash'])) {
                $SourceTable = $this->getTotum()->getTable($sourceTableRow, $params['hash']);
            } else {
                throw new errorException($this->translate('Fill in the parameter [[%s]].', 'hash'));
            }
        } elseif ($this->getTableRow()['type'] === 'calcs'
            && $sourceTableRow['type'] === 'calcs'
            && $sourceTableRow['tree_node_id'] === $this->getTableRow()['tree_node_id']
            && (empty($params['cycle']) || $this->Cycle->getId() === (int)$params['cycle'])

        ) {
            //TODO Проверить что будет если ошибочные данные

            /** @var Cycle $Cycle */
            $Cycle = $this->Cycle;
            $SourceTable = $Cycle->getTable($sourceTableRow);
        }//Из чужого цикла
        elseif ($sourceTableRow['type'] === 'calcs') {
            if (empty($params['cycle'])) {
                if ($this->tableRow['type'] === 'cycles' && (int)$sourceTableRow['tree_node_id'] === $this->tableRow['id'] && $rowId) {
                    $params['cycle'] = $rowId;
                } else {
                    if ($returnType === 'field') {
                        return null;
                    } else {
                        return [];
                    }
                }
            }

            if (in_array($returnType, ['list', 'rows']) && is_array($params['cycle'])) {
                $list = [];
                foreach ($params['cycle'] as $cycle) {
                    $SourceCycle = $this->Totum->getCycle($cycle, $sourceTableRow['tree_node_id']);
                    $SourceTable = $SourceCycle->getTable($sourceTableRow);
                    $list = array_merge($list, $SourceTable->getByParamsCached($params, $returnType, $this));
                }
                return $list;
            } else {
                if (is_array($params['cycle'])) {
                    $params['cycle'] = array_shift($params['cycle']);
                }
                if (!ctype_digit(strval($params['cycle']))) {
                    throw new errorException($this->translate('The %s parameter must be a number.', 'cycle'));
                } else {
                    $SourceCycle = $this->Totum->getCycle($params['cycle'], $sourceTableRow['tree_node_id']);
                    $SourceTable = $SourceCycle->getTable($sourceTableRow);
                }
            }
        } else {
            $SourceTable = $this->Totum->getTable($sourceTableRow);
        }

        if ($toSource && is_a($this, calcsTable::class) && is_a(
                $SourceTable,
                calcsTable::class
            ) && $this->Cycle === $SourceTable->getCycle()) {
            /** @var calcsTable $this */
            $this->addInSourceTables($sourceTableRow);
        }

        if ($returnType === 'table') {
            $replaceFilesInTblWithContent = function ($tbl, $fields) {
                $replaceFileDataWithContent = function (&$filesArray) {
                    if (!empty($filesArray['v']) && is_array($filesArray['v'])) {
                        foreach ($filesArray['v'] as &$fileData) {
                            $fileData['filestringbase64'] = base64_encode(File::getContent(
                                $fileData['file'],
                                $this->Totum->getConfig()
                            ));
                            unset($fileData['file']);
                            unset($fileData['size']);
                        }
                        unset($fileData);
                    }
                };

                foreach ($tbl['params'] as $k => &$v) {
                    if ($fields[$k]['type'] === 'file') {
                        $replaceFileDataWithContent($v);
                    }
                }
                foreach ($tbl['rows'] as &$row) {
                    foreach ($row as $k => &$v) {
                        if (($fields[$k]['type'] ?? null) === 'file') {
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

                if ($SourceTable->getTableRow()['with_order_field'] ?? false) {
                    array_multisort(array_column($tbl['rows'], 'n'), $tbl['rows']);
                }

                foreach ($tbl['rows'] as $_row) {
                    if ($_row['is_del'] && !key_exists('is_del', $fields)) {
                        continue;
                    }

                    $row = [];
                    foreach ($_row as $k => $v) {
                        if (key_exists($k, $fields)) {
                            $row[$k] = $v;
                        }
                        if (is_a($SourceTable, cyclesTable::class)) {
                            $row['_tables'] = [];
                            $cycle = $this->Totum->getCycle($_row['id'], $SourceTable->getTableRow()['id']);
                            foreach ($cycle->getTableIds() as $inTableID) {
                                $sourceInTable = $cycle->getTable($inTableID);
                                $row['_tables'][$sourceInTable->getTableRow()['name']] = ['tbl' => $replaceFilesInTblWithContent(
                                    $sourceInTable->getTbl(),
                                    $sourceInTable->getFields()
                                ), 'version' => $sourceInTable->getTableRow()['__version']];
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
        } elseif ($returnType === 'treeChildren') {
            return $SourceTable->getChildrenIds($params['id'], $params['parent'], $params['bfield'] ?? 'id');
        } else {
            return $SourceTable->getByParamsCached($params, $returnType, $this);
        }
    }

    protected function getByParamsCached($params, $returnType, aTable $fromTable)
    {
        $fromCache = false;

        if ($this->onCalculating) {
            $res = $this->getByParams($params, $returnType);
        } elseif (empty($this->cachedSelects[$hash = $returnType . serialize($params)])) {
            $res = $this->cachedSelects[$hash] = $this->getByParams($params, $returnType);
        } else {
            $fromCache = true;
            $res = $this->cachedSelects[$hash];
        }


        $p['table'] = $this;
        $p['action'] = "select";
        $p['cached'] = $fromCache;
        $p['result'] = $res;
        $p['inVars'] = $params;
        $fromTable->getCalculateLog()->getChildInstance($p);

        return $res;
    }

    public function setFilters(array $permittedFilters)
    {
        $this->filtersFromUser = [];
        foreach ($permittedFilters as $fName => $val) {
            if ($fName === 'id' || ($this->fields[$fName]['category'] ?? null) === 'filter') {
                $this->filtersFromUser[$fName] = $val;
            }
        }
    }

    abstract public function getChildrenIds($id, $parentField, $bfield);

    public function getTbl()
    {
        return $this->tbl;
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
    public function setWithALogTrue($logText)
    {
        $this->recalculateWithALog = is_string($logText) && $logText !== "true" ? $logText : true;
    }

    abstract protected function loadRowsByParams($params, $order = null, $offset = 0, $limit = null);


    public function loadFilteredRows($channel, $idsFilter = [], $removed = false): array
    {
        $filteredIds = [];
        $this->reCalculateFilters($channel);
        $params = $this->filtersParamsForLoadRows($channel, $idsFilter, true);

        if ($params !== false) {
            if ($removed) {
                $params[] = ['field' => 'is_del', 'operator' => '=', 'value' => true];
            }
            $filteredIds = $this->loadRowsByParams($params, $this->orderParamsForLoadRows());
        }
        return $filteredIds;
    }

    /**
     * Database ordering
     *
     * @param bool $asStr
     * @return array[]|string
     */
    public function orderParamsForLoadRows($asStr = false)
    {
        $sortFieldName = 'id';
        if ($this->tableRow['order_field'] === 'n') {
            $sortFieldName = 'n';
        } elseif ($this->tableRow['order_field'] && $this->tableRow['order_field'] !== 'id') {
            if (!in_array($this->fields[$this->orderFieldName]['type'], ['select', 'tree'])) {
                $sortFieldName = $this->orderFieldName;
            }
        }
        $direction = $this->tableRow['order_desc'] ? 'desc' : 'asc';
        if ($asStr) {
            $idsPin = '';
            if (key_exists($sortFieldName, $this->fields)) {
                if ($this->fields[$sortFieldName]['type'] === 'number') {
                    $sortFieldName = "($sortFieldName->>'v')::NUMERIC";
                } else {
                    $sortFieldName = "$sortFieldName->>'v'";
                }
                $idsPin = ", id $direction";
                if ($direction === 'desc') {
                    $direction .= ' NULLS LAST';
                } else {
                    $direction .= ' NULLS FIRST';
                }
            }
            return "$sortFieldName $direction $idsPin";
        }
        $order = [['field' => $sortFieldName, 'ad' => $direction]];
        if (key_exists($sortFieldName, $this->fields)) {
            $order[] = ['field' => 'id', 'ad' => $direction];
        }
        return $order;
    }

    /**
     * @param $channel
     * @param array $idsFilter
     * @param bool $onlyBlockedFilters
     * @return array|false
     * @throws errorException
     */
    public function filtersParamsForLoadRows($channel, $idsFilter = null, $onlyBlockedFilters = false): bool|array
    {
        $params = [];
        $issetBlockedFilters = false;

        if (!is_null($idsFilter)) {
            $params[] = ['field' => 'id', 'operator' => '=', 'value' => $idsFilter];
        }

        if ($channel == 'web' && !$this->User->isCreator() && $this->tableRow['cycles_access_type'] === '1') {
            $params[] = ['field' => 'creator_id', 'operator' => '=', 'value' => $this->User->getConnectedUsers()];
        }

        foreach ($this->sortedFields['filter'] ?? [] as $fName => $field) {
            if (!$this->isField('filterable', $channel, $field)) {
                continue;
            }
            if ($onlyBlockedFilters && $this->isField('editable', $channel, $field)) {
                continue;
            }

            if (!empty($field['column']) //определена колонка
                && (isset($this->sortedFields['column'][$field['column']]) || $field['column'] === 'id') //определена колонка и она существует в таблице
                && !is_null($fVal_V = $this->tbl['params'][$fName]['v']) //не "Все"
                && !(is_array($fVal_V) && count($fVal_V) === 0) //Не ничего не выбрано - не Все в мульти
                && !(!empty($idsFilter) && ((Field::init($field, $this)->isChannelChangeable(
                        'modify',
                        $channel
                    )))) // если это запрос на подтверждение прав доступа и фильтр доступен ему на редактирование
            ) {
                if ($fVal_V === '*NONE*' || (is_array($fVal_V) && in_array('*NONE*', $fVal_V))) {
                    $issetBlockedFilters = true;
                    break;
                } elseif ($fVal_V === '*ALL*' || (is_array($fVal_V) && in_array(
                            '*ALL*',
                            $fVal_V
                        )) || (!in_array(
                            $this->fields[$fName]['type'],
                            ['select', 'tree']
                        ) && $fVal_V === '')) {
                    continue;
                } else {
                    $param = [];
                    $param['field'] = $field['column'];
                    $param['value'] = $fVal_V;
                    $param['operator'] = '=';

                    if (!empty($this->fields[$fName]['intervalFilter'])) {
                        switch ($this->fields[$fName]['intervalFilter']) {
                            case  'start':
                                $param['operator'] = '>=';
                                break;
                            case  'end':
                                $param['operator'] = '<=';
                                break;
                        }
                    } //Для вебного Выбрать Пустое в мультиселекте
                    elseif (($fVal_V === [""] || $fVal_V === "")
                        && $channel === 'web'
                        && in_array($field['type'], ['select', 'tree'])
                        && (!empty($this->fields[$field['column']]['multiple']) || !empty($field['selectFilterWithEmpty']))
                        /*
                          && in_array($this->fields[$field['column']]['type'], ['select', 'tree'])
                        ($field['data']['withEmptyVal'] ?? null) || Field::isFieldListValues($this->fields[$field['column']]['type'],
                            $this->fields[$field['column']]['multiple'] ?? false)*/
                    ) {
                        $param['value'] = "";
                    }
                    $params[] = $param;
                }
            }
        }
        return $issetBlockedFilters ? false : $params;
    }

    abstract protected function getByParamsFromRows($params, $returnType, $sectionReplaces);

    protected function cropSelectListForWeb($checkedVals, $list, $isMulti, $q = '', $selectLength = 50, $topForChecked = true)
    {
        $checkedNum = 0;

        //Наверх выбранные;
        if (!empty($checkedVals)) {
            if ($isMulti) {
                foreach ((array)$checkedVals as $mm) {
                    if (array_key_exists($mm, $list) && $list[$mm][1] === 0) {
                        $v = $list[$mm];
                        unset($list[$mm]);
                        $list = [$mm => $v] + $list;
                        $checkedNum++;
                    }
                }
            } else {
                $mm = $checkedVals;
                if (array_key_exists($mm, $list) && $list[$mm][1] === 0) {
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
            if (($v[1] ?? 0) === 1) {
                unset($list[$k]);
            }
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

    protected static function isDifferentFieldData($v1, $v2)
    {
        if (is_array($v1) && is_array($v2)) {
            if (count($v1) !== count($v2)) {
                return true;
            }
            foreach ($v1 as $k => $_v1) {
                if (!key_exists($k, $v2)) {
                    return true;
                }
                if (self::isDifferentFieldData($_v1, $v2[$k])) {
                    return true;
                }
            }
            return false;
        } elseif (!is_array($v1) && !is_array($v2)) {
            if (is_numeric(strval($v1)) && is_numeric(strval($v2))) {
                return strval($v1) !== strval($v2);
            }
            return ($v1 ?? '') !== ($v2 ?? '');
        } else {
            return true;
        }
    }

    protected function checkIsModified($oldVal, $newVal)
    {
        if (!$this->isTableDataChanged) {
            if ($oldVal !== $newVal) {
                if (static::isDifferentFieldData($oldVal, $newVal)) {
                    $this->setIsTableDataChanged(true);
                }
            }
        }
    }


    /**
     * @param $channel
     * @param false $forse
     * @param bool $addFilters
     * @param array $modify
     * @param array $setValuesToDefaults
     * @throws errorException
     */
    public function reCalculateFilters(
        $channel,
        $forse = false,
        $addFilters = false,
        $modify = [],
        $setValuesToDefaults = []
    ) {
        $params = $modify['params'] ?? [];

        if ($channel === 'inner') {
            return;
        }
        switch ($channel) {
            case 'web':
                $channelParam = 'showInWeb';
                break;
            case 'xml':
                $channelParam = 'showInXml';
                break;
            default:
                throw new errorException('Channel ' . $channel . ' not defined in reCalculateFilters');
        }

        if (!$forse && key_exists($channel, $this->calculatedFilters ?? []) && $this->lastFiltersChannel === $channel) {
            $this->tbl['params'] = array_merge($this->calculatedFilters[$channel], $this->tbl['params']);
        } else {
            $this->calculatedFilters[$channel] = [];

            foreach ($this->sortedFields['filter'] as $fName => $field) {
                if (!($field[$channelParam] ?? false)) {
                    continue;
                }

                /** @var Field $Field */
                $Field = Field::init($field, $this);

                if (is_array($this->anchorFilters) && key_exists($field['name'], $this->anchorFilters)) {
                    $this->tbl['params'][$field['name']] = $Field->modify(
                        'inner',
                        Field::CHANGED_FLAGS['changed'],
                        $this->anchorFilters[$field['name']],
                        [],
                        $this->tbl['params'],
                        $this->tbl,
                        $this->tbl,
                        false
                    );
                } else {
                    if ($addFilters !== false || !$Field->isWebChangeable('insert') || !key_exists(
                            $field['name'],
                            $params
                        )) {
                        $this->tbl['params'][$field['name']] = $Field->add(
                            'inner',
                            $addFilters[$field['name']] ?? null,
                            $this->tbl['params'],
                            $this->tbl,
                            $this->tbl
                        );
                    } else {
                        $changeFlag = $Field->getModifyFlag(
                            in_array($fName, $params),
                            $params[$fName] ?? null,
                            null,
                            in_array($field['name'], $setValuesToDefaults),
                            false,
                            true
                        );

                        $this->tbl['params'][$field['name']] = $Field->modify(
                            'inner',
                            $changeFlag,
                            $params[$field['name']] ?? null,
                            [],
                            $this->tbl['params'],
                            $this->tbl,
                            $this->tbl,
                            false
                        );
                        if (key_exists('h', $this->tbl['params'][$field['name']]) && !key_exists(
                                'c',
                                $this->tbl['params'][$field['name']]
                            )) {
                            unset($this->tbl['params'][$field['name']]['h']);
                        }
                    }
                }
                $this->calculatedFilters[$channel][$field['name']] = $this->tbl['params'][$field['name']] ?? ['v' => null];

            }
        }
        $this->lastFiltersChannel = $channel;
    }


    protected function addToALogAdd(Field $Field, $channel, $newTbl, $thisRow, $modified)
    {
        if ($this->tableRow['type'] !== 'tmp' && $Field->isLogging()) {
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
                if (key_exists(
                        'c',
                        $thisRow[$Field->getName()]
                    ) || !$Field->getData('code') || $Field->getData('codeOnlyInAdd')) {
                    $this->Totum->totumActionsLogger()->add(
                        $this->tableRow['id'],
                        !empty($this->Cycle) ? $this->Cycle->getId() : null,
                        $thisRow['id'],
                        [$Field->getName() => [$Field->getLogValue(
                            $thisRow[$Field->getName()]['v'],
                            $thisRow,
                            $newTbl
                        ), $channel === 'inner' ? (is_bool($logIt) ? $this->translate('script') : $logIt) : null]]
                    );
                }
            }
        }
    }

    protected function addToALogModify(Field $Field, $channel, $newTbl, $thisRow, $rowId, $modified, $setValuesToDefaults, $setValuesToPinned, $oldVal)
    {
        if ($this->tableRow['type'] !== 'tmp' && $Field->isLogging()) {
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
                    $this->Totum->totumActionsLogger()->clear(
                        $this->tableRow['id'],
                        !empty($this->Cycle) ? $this->Cycle->getId() : null,
                        $rowId,
                        [$Field->getName() => [$Field->getLogValue(
                            $thisRow[$Field->getName()]['v'],
                            $thisRow,
                            $newTbl
                        ), $channel === 'inner' ? (is_bool($logIt) ? $this->translate('script') : $logIt) : null]]
                    );
                } elseif (key_exists($Field->getName(), $setValuesToPinned)) {
                    $this->Totum->totumActionsLogger()->pin(
                        $this->tableRow['id'],
                        !empty($this->Cycle) ? $this->Cycle->getId() : null,
                        $rowId,
                        [$Field->getName() => [$Field->getLogValue(
                            $thisRow[$Field->getName()]['v'],
                            $thisRow,
                            $newTbl
                        ), $channel === 'inner' ? (is_bool($logIt) ? $this->translate('script') : $logIt) : null]]
                    );
                } elseif (key_exists(
                        $Field->getName(),
                        $modified
                    ) && ($oldVal && ($thisRow[$Field->getName()]['v'] !== $oldVal['v'] || ($thisRow[$Field->getName()]['h'] ?? null) !== ($oldVal['h'] ?? null)))) {
                    $funcName = 'modify';

                    if (($thisRow[$Field->getName()]['h'] ?? null) === true && !($oldVal['h'] ?? null)) {
                        $funcName = 'pin';
                    }


                    $this->Totum->totumActionsLogger()->$funcName(
                        $this->tableRow['id'],
                        !empty($this->Cycle) ? $this->Cycle->getId() : null,
                        $rowId,
                        [$Field->getName() => [
                            $Field->getLogValue(
                                $thisRow[$Field->getName()]['v'],
                                $thisRow,
                                $newTbl
                            )
                            ,
                            $channel === 'inner' ? (is_bool($logIt) ? $this->translate('script') : $logIt) : $Field->getModifiedLogValue($modified[$Field->getName()])]]
                    );
                } elseif (key_exists(
                    $Field->getName(),
                    $setValuesToDefaults
                )) {
                    $this->Totum->totumActionsLogger()->clear(
                        $this->tableRow['id'],
                        !empty($this->Cycle) ? $this->Cycle->getId() : null,
                        $rowId,
                        [$Field->getName() =>
                            [
                                $Field->getLogValue(
                                    $thisRow[$Field->getName()]['v'],
                                    $thisRow,
                                    $newTbl
                                ), $channel === 'inner' ? (is_bool($logIt) ? $this->translate('script') : $logIt) : null
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
            throw new errorException($this->translate('The [[%s]] field is not found in the [[%s]] table.',
                [$params['section'], $tableRow['name']]));
        }

        $sectionField = $this->fields[$params['section']];
        if ($sectionField['category'] !== 'column') {
            throw new errorException($this->translate('Field [[%s]] in table [[%s]] is not a column',
                [$params['section'], $tableRow['name']]));
        }
        return $sectionField;
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

    public function countByParams($params, $orders = null, $untilId = 0)
    {
        if ($this->restoreView) {
            $params[] = ['field' => 'is_del', 'operator' => '=', 'value' => true];
        }
        $orders = $orders ?? $this->orderParamsForLoadRows(false);
        $ids = $this->getByParams(
            [
                'where' => $params, 'order' => $orders, 'field' => 'id'
            ],
            'list'
        );
        if ($untilId) {
            $index = array_search($untilId, $ids);
            if ($index !== false) {
                return $index + 1;
            }
        }
        return count($ids);
    }

    public function getSortedFilteredRows($channel, $viewType, $idsFilter = [], $lastId = 0, $prevLastId = 0, $onPage = null, $onlyFields = null)
    {
        $this->reCalculateFilters($channel);

        if (is_array($lastId) && key_exists('offset', $lastId)) {
            $offset = $lastId['offset'];
            $lastId = 0;
        } else {
            $offset = null;
        }

        $result = [
            'rows' => [],
            'offset' => 0,
            'allCount' => 0
        ];

        $params = $this->filtersParamsForLoadRows($channel);

        if ($params === false) {
            return $result;
        } else {
            $getRows = function ($filteredIds) {
                $rows = [];
                foreach ($filteredIds as $id) {
                    $row = $this->tbl['rows'][$id];
                    if ($this->restoreView && is_a($this, JsonTables::class)) {
                        foreach ($this->sortedFields['column'] as $fName => $field) {
                            if (empty($row[$fName])) {
                                $row[$fName] = ['v' => null];
                            }
                        }
                    }
                    $rows[] = $row;
                }
                return $rows;
            };

            $Log = $this->calcLog(['name' => 'SELECTS AND FORMATS ROWS']);

            if ($idsFilter) {
                $params[] = ['field' => 'id', 'operator' => '=', 'value' => $idsFilter];
            }
            if ($this->restoreView) {
                $params[] = ['field' => 'is_del', 'operator' => '=', 'value' => true];
            }

            if (!is_null($onPage)) {
                $orderFN = $this->getOrderFieldName();

                if (is_subclass_of($this, JsonTables::class) ||
                    (key_exists($orderFN, $this->fields)
                        && in_array($this->fields[$orderFN]['type'], ['tree', 'select']))) {
                    $filteredIds = $this->loadRowsByParams($params, $this->orderParamsForLoadRows());
                    $allCount = count($filteredIds);

                    $rows = $getRows($filteredIds);

                    $slice = function (&$rows, $pageCount) use ($onPage, $allCount, $lastId, $prevLastId, $offset) {
                        if ((int)$pageCount === 0) {
                            return 0;
                        }
                        if ($prevLastId) {
                            if ($prevLastId === -1) {
                                $offset = count($rows);
                            } else {
                                foreach ($rows as $i => $row) {
                                    if ($row['id'] === $prevLastId) {
                                        $offset = $i;
                                    }
                                }
                            }

                            if ($offset < $pageCount) {
                                if ((explode('/', $this->tableRow['pagination'])[2] ?? '') === 'desc') {
                                    $pageCount = $offset;
                                }
                                $offset = 0;
                            } else {
                                $offset -= $pageCount;
                            }
                        } elseif ($lastId !== 0 && is_null($offset)) {
                            if (is_array($lastId)) {
                                foreach ($rows as $i => $row) {
                                    if (in_array($row['id'], $lastId)) {
                                        $offset = $i;
                                        break;
                                    }
                                }
                            } elseif ($lastId === 'last') {
                                $offset = $allCount - ($allCount % $onPage ? $allCount % $onPage : $onPage);
                            } elseif ($lastId === 'desc') {
                                $offset = $allCount - $onPage;
                            } else {
                                $lastId = (int)$lastId;
                                $offset = 0;
                                foreach ($rows as $i => $row) {
                                    if ($row['id'] === $lastId) {
                                        $offset = $i + 1;
                                    }
                                }
                            }
                        }
                        $rows = array_slice($rows, $offset, $pageCount);
                        return $offset;
                    };

                    if (key_exists($orderFN, $this->fields) && in_array(
                            $this->fields[$orderFN]['type'],
                            ['tree', 'select']
                        )) {
                        $rows = $this->getValuesAndFormatsForClient(['rows' => $rows],
                            $viewType,
                            array_column($rows, 'id'))['rows'];
                        $this->sortRowsBydefault($rows);
                        $offset = $slice($rows, $onPage);
                    } else {
                        $offset = $slice($rows, $onPage);
                        $rows = $this->getValuesAndFormatsForClient(['rows' => $rows],
                            $viewType,
                            array_column($rows, 'id'))['rows'];
                    }
                } else {
                    $allCount = $this->countByParams($params);

                    if ($allCount > $onPage) {
                        if ($prevLastId) {
                            if ($prevLastId === -1) {
                                $offset = $offset ?? $allCount;
                                if ((explode('/', $this->tableRow['pagination'])[2] ?? '') === 'last') {
                                    $offset = $offset ?? $allCount - ($allCount % $onPage ? $allCount % $onPage : $onPage) + $onPage;
                                }
                            } else {
                                $offset = $offset ?? $this->countByParams(
                                        $params,
                                        $this->orderParamsForLoadRows(true),
                                        $prevLastId
                                    ) - 1;
                            }

                            if ($offset < $onPage) {
                                if ((explode('/', $this->tableRow['pagination'])[2] ?? '') === 'desc') {
                                    $onPage = $offset;
                                }
                                $offset = 0;
                            } else {
                                $offset -= $onPage;
                            }
                            /*$offset -= $onPage;*/
                            if ($offset < 0) {
                                $offset = 0;
                            }
                        } elseif ($lastId === 'last') {
                            $offset = $offset ?? $allCount - ($allCount % $onPage ? $allCount % $onPage : $onPage);
                        } elseif ($lastId === 'desc') {
                            $offset = $offset ?? $allCount - $onPage;
                        } elseif (is_array($lastId) || $lastId > 0) {
                            $offset = $offset ?? $this->countByParams(
                                    $params,
                                    $this->orderParamsForLoadRows(true),
                                    $lastId
                                );
                        }

                        $filteredIds = $this->loadRowsByParams(
                            $params,
                            $this->orderParamsForLoadRows(),
                            $offset,
                            $onPage
                        );
                        $rows = $getRows($filteredIds);
                    } else {
                        $filteredIds = $this->loadRowsByParams($params, $this->orderParamsForLoadRows());
                        $rows = $getRows($filteredIds);
                    }
                    $rows = $this->getValuesAndFormatsForClient(['rows' => $rows],
                        $viewType,
                        array_column($rows, 'id'))['rows'];
                }

                $result = ['rows' => $rows, 'offset' => (int)$offset, 'allCount' => $allCount];
            } else {
                if (!is_null($onlyFields)) {
                    $cropFieldsInRows = function ($rows) use ($onlyFields) {
                        $onlyFields[] = 'id';
                        $onlyFields = array_flip($onlyFields);
                        foreach ($rows as &$row) {
                            $row = array_intersect_key($row, $onlyFields);
                        }
                        unset($row);
                        return $rows;
                    };
                } else {
                    $cropFieldsInRows = function ($rows) {
                        return $rows;
                    };
                }

                $filteredIds = $this->loadRowsByParams($params, $this->orderParamsForLoadRows());
                $rows = $cropFieldsInRows($getRows($filteredIds));
                $rows = $this->getValuesAndFormatsForClient(['rows' => $rows],
                    $viewType,
                    array_column($rows, 'id'))['rows'];
                $this->sortRowsBydefault($rows);

                $result = ['rows' => $rows, 'offset' => 0, 'allCount' => count($filteredIds)];
            }
            $this->calcLog($Log, 'result', 'done');

            return $result;
        }
    }

    public function sortRowsByDefault(&$rows)
    {
        $tableRow = $this->getTableRow();
        $orderFieldName = $this->getOrderFieldName();

        $fields = $this->getFields();

        if ($tableRow['order_field']
            && $orderFieldName !== 'id'
            && $orderFieldName !== 'n'
            && ($orderField = ($fields[$orderFieldName]))
            && in_array($orderField['type'], ['select', 'tree'])
        ) {
            $sortArray = [];
            foreach ($rows as $row) {
                $sortArray[] = $row[$orderFieldName]['v_'][0] ?? $row[$orderFieldName]['v'];
            }
            array_multisort(
                $sortArray,
                SORT_NATURAL,
                $rows
            );
            if (!empty($tableRow['order_desc'])) {
                $rows = array_reverse($rows);
            }
        }
    }

    abstract public function getLastUpdated($force = false);

    protected function getRemoveForActionDeleteDuplicate($where, $limit)
    {
        $getParams = ['where' => $where, 'field' => 'id'];
        if ((int)$limit === 1) {
            if ($id = $this->getByParams($getParams, 'field')) {
                $remove = [$id];
            } else {
                return false;
            }
        } else {
            $remove = $this->getByParams($getParams, 'list');
        }
        return $remove;
    }

    public function getModifyForActionSetExtended($params, $where)
    {
        $modify = [];
        $wheres = [];
        $modifys = [];

        $maxCount = 0;
        foreach ($where as $i => $_w) {
            if (is_array($_w['value']) && count($_w['value']) > $maxCount) {
                $maxCount = count($_w['value']);
            }
        }
        foreach ($params as $f => $valueList) {
            if (is_array($valueList) && count($valueList) > $maxCount) {
                $maxCount = count($valueList);
            }
        }


        foreach ($where as $i => $_w) {
            if (is_array($whereList = $_w['value']) && array_key_exists(
                    0,
                    $whereList
                ) && count($whereList) !== $maxCount) {
                throw new errorException($this->translate('In the %s parameter you must use a list by the number of rows to be changed or not a list.',
                    'where'));
            }

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
            if (($this->fields[$f]['category'] ?? null) !== 'column') {
                throw new errorException($this->translate('The function is used to change the rows part of the table.'));
            }

            if (is_object($valueList)) {
                if (is_array($valueList->val)) {
                    if (is_array($valueList->val) && array_key_exists(
                            0,
                            $valueList->val
                        ) && count($valueList->val) !== $maxCount) {
                        throw new errorException($this->translate('In the %s parameter you must use a list by the number of rows to be changed or not a list.',
                            'field'));
                    }
                    foreach ($valueList->val as $ii => $val) {
                        $newObj = new FieldModifyItem($valueList->sign, $val, $valueList->percent);
                        $modifys[$ii][$f] = $newObj;
                    }
                    continue;
                }
            }
            if (is_array($valueList) && array_key_exists(
                    0,
                    $valueList
                ) && count($valueList) !== $maxCount) {
                throw new errorException($this->translate('In the %s parameter you must use a list by the number of rows to be changed or not a list.',
                    'field'));
            }

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


    protected function checkTableUpdated($tableData = null)
    {
        if (is_null($tableData)) {
            return;
        }

        $updated = $this->updated;
        if ($this->tableRow['actual'] === 'strong' && $tableData && ($tableData['updated'] ?? null) && json_decode(
                $updated,
                true
            ) != $tableData['updated']
        ) {
            throw new errorException($this->translate('Table [[%s]] was changed. Update the table to make the changes.',
                $this->tableRow['title']));
        }
    }

    protected function getFieldsForAction($action, $fieldCategory)
    {
        $key = $action . ':' . $fieldCategory;
        if (!array_key_exists($key, $this->cacheForActionFields)) {
            $fieldsForAction = [];
            foreach ($this->sortedFields[$fieldCategory] ?? [] as $field) {
                if (!empty($field['CodeActionOn' . $action])) {
                    $fieldsForAction[] = $field;
                }
            }

            $this->cacheForActionFields[$key] = $fieldsForAction;
        }
        return $this->cacheForActionFields[$key];
    }

    public function isJsonTable()
    {
        return is_a($this, JsonTables::class);
    }

    abstract protected function _copyTableData(&$table, $settings);

    protected function _getIntervals($ids)
    {
        $ids = str_replace(' ', '', $ids);
        $intervals = [];
        foreach (explode(',', $ids) as $interval) {
            if ($interval === '') {
                continue;
            } elseif (preg_match('/^\d+$/', $interval)) {
                $intervals[] = [$interval, $interval];
            } elseif (preg_match('/^(\d+)-(\d+)$/', $interval, $matches)) {
                $intervals[] = [$matches[1], $matches[2]];
            } else {
                throw new errorException($this->translate('Incorrect interval [[%s]]', $interval));
            }
        }
        return $intervals;
    }

    protected function issetActiveFilters($channel)
    {
        $isActiveFilter = function ($field) {
            if (!is_null($this->tbl['params'][$field['name']] ?? null)) {
                $filterVal = $this->tbl['params'][$field['name']];
                if ($field['type'] === 'select') {
                    if (in_array('*ALL*', (array)$filterVal)
                        || in_array('*NONE*', (array)$filterVal)
                    ) {
                        return false;
                    }
                    return true;
                } elseif ($filterVal !== '') {
                    return true;
                }
            }
        };

        switch ($channel) {
            case 'web':
            case 'edit':
                foreach ($this->fields as $field) {
                    if ($field['category'] === 'filter' && $field['showInWeb'] === true) {
                        return $isActiveFilter($field);
                    }
                }
                break;
            case 'xml':
                foreach ($this->fields as $field) {
                    if ($field['category'] === 'filter' && $field['showInXml'] === true) {
                        return $isActiveFilter($field);
                    }
                }
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

    abstract protected function reCalculateRows(
        $calculate,
        $channel,
        $isCheck,
        $modifyCalculated,
        $isTableAdding,
        $remove,
        $restore,
        $add,
        $modify,
        $setValuesToDefaults,
        $setValuesToPinned,
        $duplicate,
        $reorder,
        $addAfter,
        $addWithId
    );

    /**
     * @param string $property visible|insertable|editable|filterable
     * @param string $channel web|xml
     * @param $field
     * @return mixed
     * @throws errorException
     */
    public function isField($property, $channel, array|string $field)
    {
        if (is_string($field)) {
            if (!key_exists($field, $this->fields)) {
                throw new errorException($this->translate('The %s field in %s table does not exist',
                    [$field, $this->getTableRow()['title']]));
            }
            $field = $this->fields[$field];
        }

        $User = $this->Totum->getUser();
        $userRoles = $User->getRoles();
        $isInRoles = function ($roles) use ($User, $userRoles) {
            return (empty($roles) || array_intersect($roles, $userRoles));
        };

        switch ($channel) {
            case 'web':
                $visible = $field['showInWeb'] && ($User->isCreator() || $isInRoles($field['webRoles'] ?? []));
                switch ($property) {
                    case 'visible':
                        return $visible;
                    case 'filterable':
                        return $field['showInWeb'];
                    case 'editFilterByUser':
                        return $field['showInWeb'] && ($field['editable'] ?? false) && $isInRoles($field['webRoles'] ?? []) && $isInRoles($field['editRoles'] ?? []);
                    case 'insertable':
                        return $visible && ($field['insertable'] ?? false) && $isInRoles($field['addRoles'] ?? []);
                    case 'editable':
                        /*Для фильтра ограничения видимости по ролям не отключают редактирование*/
                        if ($field['category'] === 'filter') {
                            return ($field['showInWeb'] ?? false) && ($field['editable'] ?? false) && $isInRoles($field['editRoles'] ?? []);
                        }
                        return $visible && ($field['editable'] ?? false) && $isInRoles($field['editRoles'] ?? []);
                }
                break;
            case 'xml':
                $visible = ($field['showInXml'] ?? null) && $isInRoles($field['xmlRoles'] ?? []);

                return match ($property) {
                    'visible' => $visible,
                    'insertable' => $visible && $field['apiInsertable'],
                    'filterable' => $field['showInXml'],
                    'editable' => $visible && $field['apiEditable'] && $isInRoles($field['xmlEditRoles'] ?? []),
                    default => throw new errorException('In channel ' . $channel . ' not supported action ' . $property),
                };
                break;
            case 'inner':
                return match ($property) {
                    'filterable' => false,
                    default => true,
                };
                break;
            default:
                throw new errorException('Channel ' . $channel . ' not supported in function isField');
        }
        return false;
    }

    protected function translate(string $str, mixed $vars = []): string
    {
        return $this->getTotum()->getLangObj()->translate($str, $vars);
    }
}
