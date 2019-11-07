<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 24.03.17
 * Time: 15:23
 */

namespace totum\common;


use totum\models\CalcsTableCycleVersion;
use totum\models\Table;
use totum\models\TablesCalcsConnects;
use totum\models\TablesFields;
use totum\tableTypes\aTable;
use totum\tableTypes\calcsTable;
use totum\tableTypes\globcalcsTable;
use totum\tableTypes\tableTypes;

class Cycle
{
    protected static $projects;
    protected $id, $cyclesTableId;
    protected $row, $accessRow;
    protected $tables = [], $tableVersions = [];
    private $calcTables;

    static function clearProjects()
    {
        static::$projects = [];
    }

    protected function __construct($id, $cyclesTableId)
    {
        $this->id = $id;
        $this->cyclesTableId = $cyclesTableId;
    }

    public function __toString()
    {
        return (string)$this->id;
    }

    static function init($id, $cyclesTableId)
    {
        $id = (int)$id;
        $cyclesTableId = (int)$cyclesTableId;
        $hashKey = $cyclesTableId . ':' . $id;

        return static::$projects[$hashKey] ?? (static::$projects[$hashKey] = new static($id, $cyclesTableId));
    }

    static function create($cyclesTableId, $cycleId)
    {
        Sql::transactionStart();




        $Cycle = static::init($cycleId, $cyclesTableId);
        $tables = $Cycle->getTables();
        foreach ($tables as $tableId){
            $tableRow = Table::getTableRowById($tableId);
            CalcsTableCycleVersion::addVersionForCycle($tableRow['name'], $cycleId);
        }

        $Cycle->afterCreate();


        Sql::transactionCommit();
        return $Cycle;

    }

    public static function duplicate($cyclesTableID, $oldId, $newId)
    {

        $Cycle = Cycle::init($newId, $cyclesTableID);
        $tables = $Cycle->getTables();

        /** @var TablesCalcsConnects $modelTablesCalcsConnects */
        $modelTablesCalcsConnects = TablesCalcsConnects::init();
        $modelTablesCalcsConnects->duplicateCycleSources($tables, $oldId, $newId);


        foreach ($tables as &$tId) {
            $cycleTableRow = Table::getTableRowById($tId);
            $cycleTableName = $cycleTableRow['name'];
            $model = Model::init($cycleTableName);
            $cycleTableDataRow = $model->get(['cycle_id' => $oldId]);

            $model->insert(['updated' => $cycleTableDataRow['updated'], 'cycle_id' => $newId]);
            /** @var calcsTable $tId */
            $tId = $Cycle->getTable($cycleTableRow);
            $tId->setDuplicatedTbl(json_decode($cycleTableDataRow['tbl'], true), $cycleTableDataRow['updated']);
        }


        foreach ($tables as $table) {
            /** @var calcsTable $table */
            $table->updateFromSource(0);
        }
        $Cycle->saveTables();

        foreach ($tables as $table) {
            /** @var calcsTable $table */
            if(!$table->getSavedUpdated()){
                $table->saveTable(true);
            }
        }

        $Cycle->reCalculateCyclesTableRow();
    }

    function delete($isGroup = false)
    {
        foreach ($this->getListTables() as $tableId) {
            $tableRow = Table::getTableRowById($tableId);
            if ($tableRow) {
                Model::init($tableRow['name'])->delete(['cycle_id' => $this->getId()]);
            }
        }
        TablesCalcsConnects::init()->removeConnectsForCycle($this);
    }

    function getTables()
    {
        if (is_null($this->calcTables)) {
            $this->calcTables = array_keys(Table::init()->getAllIndexedById(['tree_node_id' => $this->getCyclesTableId(), 'type' => 'calcs', 'is_del' => false],
                'id',
                '(sort->>\'v\')::int'));

        }
        return $this->calcTables;
    }

    function getFirstTableId()
    {
        if (count($this->getTables()) > 0) return $this->getTables()[0];
        return null;
    }

    function getRow()
    {
        $this->loadRow();
        return $this->row;
    }

    function getRowName()
    {
        $CyclesTableRow = Table::getTableRowById($this->getCyclesTableId());
        $fields = TablesFields::getFields($this->getCyclesTableId());
        $mainFieldName = 'id';
        if ($CyclesTableRow['main_field']) {
            $mainFieldName = $CyclesTableRow['main_field'];
        }

        if ($mainFieldName != 'id') {
            $CyclesTable = tableTypes::getTable($CyclesTableRow);
            $fData = $CyclesTable->getFields()[$mainFieldName];
            if (in_array($fData['type'], ['select', 'tree'])) {
                $sValue = Field::init($fData, $CyclesTable)->getSelectValue($this->getRow()[$mainFieldName]['v'],
                    $this->getRow());
            }
        }
        return $sValue ?? $this->getRow()[$mainFieldName]['v'] ?? $this->getRow()['id'];
    }

    function getId()
    {
        return $this->id;
    }

    function getCyclesTableId()
    {
        return $this->cyclesTableId;
    }

    /**
     * @return bool
     */
    function loadRow()
    {
        if (is_null($this->row)) {
            if ($this->id && $this->cyclesTableId) {
                $cycleTableRow = Table::getTableRowById($this->cyclesTableId);
                if (!$cycleTableRow || $cycleTableRow['type'] != 'cycles') throw new errorException('Таблица циклов не найдена');
                if ($row = Model::init($cycleTableRow['name'])->get(['id' => $this->id, 'is_del' => false])) {
                    foreach ($row as $k => &$v) {
                        if (!in_array($k, Model::serviceFields)) {
                            $v = json_decode($v, true);
                        }
                    }
                    $this->setRow($row);
                }
            } else return false;
        }
        if (!empty($this->row))
            return true;
    }

    function setRow($row)
    {
        $this->row = $row;
    }

    /**
     * @param $tableRow
     * @param bool $light
     * @return aTable
     */
    function getTable($tableRow, $light = false)
    {
        if ($tableRow['type'] == 'globcalcs') {
            $model = globcalcsTable::class;
        } else {
            $model = calcsTable::class;
            if ($tableRow['tree_node_id'] != $this->getCyclesTableId())
                throw new errorException('Ошибка обращения к таблице не своей циклической таблицы');

            list($tableRow['__version'], $tableRow['__auto_recalc']) = CalcsTableCycleVersion::getVersionForCycle($tableRow['name'], $this->id);
        }

        if (key_exists($tableRow['name'], $this->tables)){
            if($this->tables[$tableRow['name']]->getTableRow()['__version']!==$tableRow['__version']){
                $this->tables[$tableRow['name']]->setVersion($tableRow['__version'], $tableRow['__auto_recalc']);
                $this->tables[$tableRow['name']]->initFields();
            }

        }else $this->tables[$tableRow['name']] = $model::init($tableRow,
            $this,
            $light);

        return $this->tables[$tableRow['name']];
    }

    function getListTables()
    {
        return $this->getTables();
    }

    function saveTables($forceReCalculateCyclesTableRow = false)
    {
        $isChanged = false;
        /** @var calcsTable $t */
        foreach ($this->tables as $t) {
            if ($t->saveTable()) {
                $isChanged = true;

            }
        }

        if ($forceReCalculateCyclesTableRow || $isChanged) {
            $this->reCalculateCyclesTableRow();
        }

    }

    function reCalculateCyclesTableRow()
    {

        if ($this->getId()) {
            $CyclesTable = $this->getCyclesTable();

            $CyclesTable->reCalculateFromOvers([
                'modify' => [$this->getId() => []],
            ]);
        }
    }

    function getCyclesTable()
    {
        return tableTypes::getTable(Table::getTableRowById($this->getCyclesTableId()));
    }

    function recalculate($isAdding=false){

        $tables = $this->getTables();
        $tablesUpdates=[];

        foreach ($tables as &$t) {
            $t = $this->getTable(Table::getTableRowById($t));
            $tablesUpdates[$t->getTableRow()["id"]] = $t->getLastUpdated();
        }
        unset($t);

        foreach ($tables as $t) {
            if ($tablesUpdates[$t->getTableRow()["id"]]==$t->getLastUpdated()){
                $t->updateFromSource(1, $isAdding);
            }
        }
        $this->saveTables(true);

    }

    protected function afterCreate()
    {
        $this->recalculate(true);
    }

    protected function loadAccessRow()
    {
        if (is_null($this->accessRow)) {
            $this->accessRow = Model::initService('cycles_access__v')->get(['cycles_table_id' => $this->getCyclesTableId(), 'cycle_id' => $this->getId()]);
        }

    }

}