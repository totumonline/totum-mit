<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 24.03.17
 * Time: 15:23
 */

namespace totum\common;

use PDO;
use totum\common\Lang\RU;
use totum\models\CalcsTableCycleVersion;
use totum\models\CalcsTablesVersions;
use totum\models\Table;
use totum\models\TablesCalcsConnects;
use totum\tableTypes\aTable;
use totum\tableTypes\calcsTable;
use totum\tableTypes\cyclesTable;

class Cycle
{
    protected int $cycleId;
    protected int $cyclesTableId;
    protected $cyclesTableRow;
    protected $tables = [];
    protected $tableVersions = [];

    protected $calcTables;
    protected $cacheVersions = [];

    /* Создавать cycle нужно только из объекта Тотум*/
    /**
     * @var Totum
     */
    protected $Totum;

    public function __construct($id, $cyclesTableId, Totum $Totum)
    {
        $this->cycleId = $id;
        $this->cyclesTableId = $cyclesTableId;
        $this->Totum = $Totum;
    }

    public function __toString()
    {
        return (string)$this->cycleId;
    }

    public static function create($cyclesTableId, $cycleId, Totum $Totum)
    {
        $Cycle = $Totum->getCycle($cycleId, $cyclesTableId);
        $tables = $Cycle->getTableIds();
        foreach ($tables as $tableId) {
            $tableRow = $Totum->getTableRow($tableId);
            if (!$Cycle->getVersionForTable($tableRow['name'])) {
                $Cycle->addVersionForCycle($tableRow['name']);
            }
        }

        $Cycle->afterCreate();

        return $Cycle;
    }

    public function getVersionForTable($tableName)
    {
        if (empty($this->cacheVersions[$tableName]['table_name'])) {
            $this->cacheVersions[$tableName] = CalcsTableCycleVersion::init($this->Totum->getConfig())->executePrepared(
                true,
                ['table_name' => $tableName, 'cycle' => $this->getId()],
                'version, auto_recalc'
            )->fetch(PDO::FETCH_NUM);
        }
        return $this->cacheVersions[$tableName];
    }

    public function addVersionForCycle($tableName)
    {
        $cycleId = $this->getId();
        $defaults = CalcsTablesVersions::init($this->Totum->getConfig())->getDefaultVersion($tableName, true);
        $this->Totum->getTable('calcstable_cycle_version')->reCalculateFromOvers(
            ['add' => [
                ['table_name' => $tableName, 'cycle' => $cycleId, 'version' => $defaults['version'], 'ord' => $defaults['default_ord'], 'auto_recalc' => $defaults['default_auto_recalc'] === 'false' ? false : true]
            ]]/*,
            Log: $this->getCyclesTable()->getCalculateLog()*/
        );

        return $this->cacheVersions[$tableName] = [$defaults['version'], 'true'];
    }

    protected function removeVersionsForCycle()
    {
        $cycleId = $this->getId();
        $calcsVersionsTable = $this->Totum->getTable('calcstable_cycle_version');
        $calcsVersionsTable->reCalculateFromOvers(
            ['remove' => $calcsVersionsTable->getByParams(
                [
                    'field' => 'id',
                    'where' => [
                        ['field' => 'cycles_table', 'operator' => '=', 'value' => $this->getCyclesTableId()],
                        ['field' => 'cycle', 'operator' => '=', 'value' => $cycleId],
                    ]
                ],
                'list'
            )

            ]
        );
    }

    public static function duplicate($cyclesTableID, $oldId, $newId, Totum $Totum)
    {
        $Cycle = $Totum->getCycle($newId, $cyclesTableID);
        $tables = $Cycle->getTableIds();

        if (empty($tables)) {
            $tableRow = $Totum->getTableRow($cyclesTableID);
            throw new errorException($Totum->getConfig()->getLangObj()->translate('There is no calculation table in [[%s]] cycles table.',
                $tableRow['title']));
        }

        /** @var TablesCalcsConnects $modelTablesCalcsConnects */
        $modelTablesCalcsConnects = TablesCalcsConnects::init($Totum->getConfig());
        $modelTablesCalcsConnects->duplicateCycleSources($tables, $oldId, $newId);

        $updates = [];


        foreach ($tables as &$tId) {
            $cycleTableRow = $Totum->getTableRow($tId);
            $cycleTableName = $cycleTableRow['name'];
            $model = $Totum->getModel($cycleTableName, true);
            $cycleTableDataRow = $model->getPrepared(['cycle_id' => $oldId]);
            /** @var calcsTable $tId */
            $tId = $Cycle->getTable($cycleTableRow);
            $updates[$tId->getTableRow()['id']] = $cycleTableDataRow['updated'] ?? '{}';
            $tId->setDuplicatedTbl(json_decode($cycleTableDataRow['tbl'], true), null /*Важно!*/);
        }


        foreach ($tables as $table) {
            /** @var calcsTable $table */
            $table->reCalculateFromOvers(
                [],
                $Totum->getTable($cyclesTableID)->getCalculateLog()
            );
        }
        $Cycle->saveTables(true, true);
    }

    public function delete()
    {
        foreach ($this->getTableIds() as $tableId) {
            $tableRow = $this->Totum->getTableRow($tableId);
            if ($tableRow) {
                $this->Totum->getModel($tableRow['name'])->deletePrepared(['cycle_id' => $this->getId()]);
            }
        }
        TablesCalcsConnects::init($this->Totum->getConfig())->removeConnectsForCycle($this);
        $this->removeVersionsForCycle();
    }

    public function getTableIds()
    {
        if (is_null($this->calcTables)) {
            $this->calcTables = array_keys(Table::init($this->Totum->getConfig())->getAllIndexedById(
                ['tree_node_id' => $this->getCyclesTableId(), 'type' => 'calcs', 'is_del' => false],
                'id',
                '(sort->>\'v\')::int'
            ));
        }
        return $this->calcTables;
    }

    public function getFirstTableId()
    {
        if (count($this->getTableIds()) > 0) {
            return $this->getTableIds()[0];
        }
        return null;
    }

    public function getRow()
    {
        $this->loadRow();
        return $this->cyclesTableRow;
    }

    public function getRowName()
    {
        $CyclesTableRow = $this->Totum->getTableRow($this->getCyclesTableId());
        $mainFieldName = 'id';
        if ($CyclesTableRow['main_field']) {
            $mainFieldName = $CyclesTableRow['main_field'];
        }

        if ($mainFieldName !== 'id') {
            $CyclesTable = $this->Totum->getTable($CyclesTableRow);
            $fData = $CyclesTable->getFields()[$mainFieldName];
            if (in_array($fData['type'], ['select', 'tree'])) {
                $sValue = Field::init($fData, $CyclesTable)->getSelectValue(
                    $this->getRow()[$mainFieldName]['v'],
                    $this->getRow()
                );
            }
        }
        return $sValue ?? ($this->getRow() ? ($this->getRow()[$mainFieldName]['v'] ?? $this->getRow()['id']) : '');
    }

    public function getId()
    {
        return $this->cycleId;
    }

    public function getCyclesTableId()
    {
        return $this->cyclesTableId;
    }

    /**
     * @return bool
     */
    public function loadRow(): bool
    {
        if (is_null($this->cyclesTableRow)) {
            if ($this->cycleId && $this->cyclesTableId) {
                $cyclesTableRow = $this->Totum->getTableRow($this->cyclesTableId);
                if (!$cyclesTableRow || $cyclesTableRow['type'] !== 'cycles') {
                    throw new errorException($this->Totum->getLangObj()->translate('The cycles table is specified incorrectly.'));
                }
                if ($row = $this->Totum->getModel($cyclesTableRow['name'])->get(['id' => $this->cycleId, 'is_del' => false])) {
                    foreach ($row as $k => &$v) {
                        if (!in_array($k, Model::serviceFields)) {
                            $v = json_decode($v, true);
                        }
                    }
                    $this->cyclesTableRow = $row;
                }
            }
        }
        if (!empty($this->cyclesTableRow)) {
            return true;
        }
        return false;
    }

    /**
     * @param $tableRow
     * @param bool $light
     * @return aTable
     */
    public function getTable($tableRow, $light = false, $force = false)
    {
        if (!is_array($tableRow)) {
            $tableRow = $this->Totum->getTableRow($tableRow);
        }

        if ($tableRow['type'] !== 'calcs') {
            errorException::criticalException(
                $this->Totum->getLangObj()->translate('[[%s]] is available only for the calculation table in the cycle.',
                    'Cycle->getTable'),
                $this->getCyclesTable()
            );
        }
        if ((int)$tableRow['tree_node_id'] !== $this->getCyclesTableId()) {
            errorException::criticalException(
                $this->Totum->getLangObj()->translate('The [[%s]] parameter is not correct.', 'tree_node_id'),
                $this->getCyclesTable()
            );
        }

        list($tableRow['__version'], $tableRow['__auto_recalc']) = $this->getVersionForTable($tableRow['name']);

        if (!$force && key_exists($tableRow['name'], $this->tables)) {
            if (($this->tables[$tableRow['name']]->getTableRow()['__version'] ?? null) !== ($tableRow['__version'] ?? null)) {
                $this->tables[$tableRow['name']]->setVersion($tableRow['__version'], $tableRow['__auto_recalc']);
                $this->tables[$tableRow['name']]->initFields();
            }
        } else {
            $this->tables[$tableRow['name']] = calcsTable::init(
                $this->Totum,
                $tableRow,
                $this,
                $light
            );

            $this->tables[$tableRow['name']]->addCalculateLogInstance($this->Totum->getCalculateLog()->getChildInstance(['table' => $this->tables[$tableRow['name']]]));
        }


        return $this->tables[$tableRow['name']];
    }

    public function getViewTablesWithOrds()
    {
        $data = CalcsTableCycleVersion::init($this->Totum->getConfig())->getAll(
            ['cycles_table' => $this->getCyclesTableId(), 'cycle' => $this->getId()],
            'table_name,ord,sort'
        );
        $dataWithOrd = [];
        foreach ($data as $r) {
            $ord = $r['ord'] ?? $r['sort'];
            $dec = 1;
            while (key_exists($ord, $dataWithOrd)) {
                $ord += 5 * (1 / (10 ** $dec));
                $ord = (string) $ord;
                $dec++;

            }
            $dataWithOrd[$ord] = $r['table_name'];
        }
        ksort($dataWithOrd, SORT_NUMERIC);

        foreach ($this->getTableIds() as $id) {
            $row = $this->Totum->getTableRow($id);
            if (!in_array($row['name'], $dataWithOrd)) {
                $dataWithOrd[] = $row['name'];
            }
        }

        return $dataWithOrd;
    }

    public function saveTables($forceReCalculateCyclesTableRow = false, $forceSaveTables = false, $log = null)
    {
        $isChanged = false;
        /** @var calcsTable $t */
        foreach ($this->tables as $t) {
            if ($t->saveTable($forceSaveTables)) {
                $isChanged = true;
            }
        }
        if ($forceReCalculateCyclesTableRow || $isChanged) {
            $this->reCalculateCyclesRow($log);
        }
    }

    public function reCalculateCyclesRow($log)
    {
        if ($this->getId()) {
            $CyclesTable = $this->getCyclesTable();
            $CyclesTable->reCalculateFromOvers([
                'modify' => [$this->getId() => []],
            ], $log);
        }
    }

    public function getCyclesTable(): cyclesTable
    {
        return $this->Totum->getTable($this->getCyclesTableId());
    }

    public function recalculate($isAdding = false)
    {
        $tablesIds = $this->getTableIds();
        $tablesUpdates = [];

        $tables = [];
        foreach ($tablesIds as $t) {
            $t = $this->getTable($t);
            $tablesUpdates[$t->getTableRow()['id']] = $t->getLastUpdated();
            $tables[] = $t;
        }
        unset($t);

        $cyclesTable = $this->Totum->getTable($this->cyclesTableId);

        $Log = $cyclesTable->calcLog(['name' => 'RECALC', 'table' => 'cycle ' . $this->getId()]);

        foreach ($tables as $t) {
            if ($tablesUpdates[$t->getTableRow()['id']] === $t->getLastUpdated()) {
                /** @var calcsTable $t */
                if ($isAdding) {
                    $t->setIsTableAdding(true);
                }
                $t->reCalculateFromOvers(
                    [],
                    $Log
                );

            }
        }

        $this->saveTables(true, log: $Log);

        $cyclesTable->calcLog($Log, 'result', 'done');
    }

    protected function afterCreate()
    {
        $this->recalculate(true);
    }
}
