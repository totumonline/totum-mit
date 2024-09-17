<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 20.03.17
 * Time: 13:26
 */

namespace totum\tableTypes;

use totum\common\errorException;
use totum\common\Cycle;
use totum\common\Lang\RU;
use totum\common\Totum;
use totum\models\Table;
use totum\models\TablesCalcsConnects;

class calcsTable extends JsonTables
{
    protected $sourceTables = [];
    /**
     * @var array|bool|mixed|string
     */

    public function __construct(Totum $Totum, $tableRow, Cycle $Cycle, $light = false)
    {
        $this->Cycle = $Cycle;
        if (!$Cycle->getRow()) {
            if ($this->CalculateLog) {
                $this->CalculateLog->addParam('backtrace', debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS));
            }
            $this->Totum = $Totum;
            throw new errorException($this->translate('Cycle [[%s]] in table [[%s]] is not found.',
                [$Cycle->getId(), $Totum->getTableRow($Cycle->getCyclesTableId())['title']]));
        }
        parent::__construct($Totum, $tableRow, $Cycle, $light);
    }

    public function reCreateFromDataBase(): aTable
    {
        return $this->Cycle->getTable($this->tableRow, false, true);
    }

    public function saveTable($force = false, $log = null)
    {
        if (!$this->isTableDataChanged && !$force && key_exists('params', $this->savedTbl)) {
            return;
        }

        $updateWhere = [
            'cycle_id' => $this->Cycle->getId()
        ];
        if (!in_array($this->getTableRow()['actual'], ['disable', 'disablenotice', 'disablerefresh'])) {
            $updateWhere['updated'] = $this->savedUpdated;
        }

        if (!$this->model->update(
            [
                'tbl' => $this->getPreparedTbl(),
                'updated' => $this->updated
            ],
            $updateWhere
        )
        ) {
            errorException::tableUpdatedException($this);
        }

        if ($log) {
            $this->CalculateLog = $log;
        }

        $saved = $this->savedTbl;
        $this->savedUpdated = $this->updated;
        $this->setIsTableDataChanged(false);
        $this->savedTbl = $this->getTblForSave();
        $this->onSaveTable($this->tbl, $saved);
        $this->saveSourceTables();

        $this->Totum->tableChanged($this->tableRow['name']);

        return true;
    }

    protected function saveSourceTables()
    {

        /** @var TablesCalcsConnects $model */
        $model = TablesCalcsConnects::init($this->Totum->getConfig());
        $model->addConnects(
            $this->tableRow['id'],
            $this->sourceTables,
            $this->Cycle->getId(),
            $this->Cycle->getCyclesTableId()
        );
    }

    public static function __createTable(string $name, Totum $Totum)
    {
        $fields = [];
        $fields[] = 'id SERIAL PRIMARY KEY NOT NULL';
        $fields[] = 'is_del BOOLEAN NOT NULL DEFAULT FALSE ';
        $fields[] = 'tbl JSONB NOT NULL DEFAULT $${}$$ ';
        $fields[] = 'updated JSONB NOT NULL';
        $fields[] = 'cycle_id INTEGER';


        $model = $Totum->getModel($name);
        $model->createTable($fields);
        $model->createIndex('cycle_id', true);

        $Totum->getTable('calcstable_versions')->reCalculateFromOvers([
            'add' => [
                ['table_name' => $name, 'version' => 'v1', 'is_default' => true]
            ]
        ]);
    }

    public function createTable(int $duplicatedId)
    {
        static::__createTable($this->tableRow['name'], $this->Totum);
    }

    protected function createCalcsTable()
    {
        $this->Totum->getModel($this->tableRow['name'])->insertPrepared(['cycle_id' => $this->Cycle->getId(), 'updated' => $this->getUpdatedJson()]);
    }

    protected function addInSourceTables($SourceTableRow)
    {
        if ($SourceTableRow['id'] !== $this->tableRow['id']) {
            $this->sourceTables[$SourceTableRow['id']] = true;
        }
    }

    public function loadDataRow($fromConstructor = false, $force = false)
    {
        if ((empty($this->dataRow) || $force) && $this->Cycle->getId() > 0) {
            if ($this->dataRow = $this->model->get(['cycle_id' => $this->Cycle->getId()])) {
                $this->tbl = json_decode($this->dataRow['tbl'], true);
                $this->indexRows();
                $this->loadedTbl = $this->savedTbl = $this->tbl;

                $this->updated = $this->dataRow['updated'];
            } elseif ((int)$this->tableRow['tree_node_id'] === $this->Cycle->getCyclesTableId()) {
                $this->createCalcsTable();
                $this->loadDataRow();
            } else {
                throw new errorException($this->translate('The calculation table is not connected to %s cycles table',
                    $this->tableRow['title']));
            }
        }
    }

    protected function loadModel()
    {
        $this->model = $this->Totum->getModel($this->tableRow['name']);
        $this->modelConnects = TablesCalcsConnects::init($this->Totum->getConfig());
    }

    protected function updateReceiverTables($level = 0)
    {
        ++$level;

        $receiverTables = [];
        $updateds = [];
        foreach ($this->modelConnects->getReceiverTables(
            $this->tableRow['id'],
            $this->Cycle->getId(),
            $this->Cycle->getCyclesTableId()
        ) as $receiverTableId) {
            /** @var JsonTables $aTable */
            $aTable = $this->Cycle->getTable($receiverTableId);
            if ('true' === $aTable->getTableRow()['__auto_recalc'] ?? 'true') {
                $receiverTables[$receiverTableId] = $aTable;
                $updateds[$receiverTableId] = $aTable->getSavedUpdated();
            }
        }

        if ($receiverTables) {
            $Log = $this->calcLog(['name' => 'UPDATE RECEIVER TABLES']);
            foreach ($receiverTables as $receiverTableId => $aTable) {
                if ($updateds[$receiverTableId] === $aTable->getSavedUpdated()) {
                    $aTable->reCalculateFromOvers([], $this->CalculateLog, $level);
                }
            }
            $this->calcLog($Log, 'result', 'done');
        }
    }

    public function getLastUpdated($force = false)
    {
        if ($force) {
            return $this->model->getField('updated', ['cycle_id' => $this->Cycle->getId()]);
        }
        return $this->updated;
    }
}
