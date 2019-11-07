<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 20.03.17
 * Time: 13:26
 */

namespace totum\tableTypes;


use totum\common\Controller;
use totum\common\errorException;
use totum\common\Model;
use totum\common\Cycle;
use totum\common\Sql;
use totum\models\TablesCalcsConnects;

class calcsTable extends JsonTables
{
    function __construct($tableRow, Cycle $Cycle, $light = false)
    {

        if (!$Cycle->getRow()) {
            /*debug_print_backtrace();
            die;*/
            throw new errorException('Цикла с id [[' . $Cycle->getId() . ']] в таблице циклов [[' . $Cycle->getCyclesTableId() . ']] не существует');
        }
        parent::__construct($tableRow, $Cycle, $light);

    }

    public function saveTable($force = false)
    {
        if (!$this->isTableDataChanged && !$force) return;

        $updateWhere = [
            'cycle_id' => $this->Cycle->getId()
        ];
        if ($this->getTableRow()['actual'] != 'disable') {
            $updateWhere['updated'] = $this->savedUpdated;
        }

        if (!$this->model->update([
            'tbl' => $this->getPreparedTbl(),
            'updated' => $this->updated
        ],
            $updateWhere)
        ) {
            errorException::tableUpdatedException($this);
        }


        $saved = $this->savedTbl;
        $this->savedUpdated = $this->updated;
        $this->isTableDataChanged = false;
        $this->savedTbl = $this->getTblForSave();
        $this->markTableChanged();
        $this->onSaveTable($this->tbl, $saved);
        $this->saveSourceTables();

        Controller::setSomeTableChanged();

        return true;
    }

    static function __createTable($name)
    {
        $fields = [];
        $fields[] = 'id SERIAL PRIMARY KEY NOT NULL';
        $fields[] = 'is_del BOOLEAN NOT NULL DEFAULT FALSE ';
        $fields[] = 'tbl JSONB NOT NULL DEFAULT $${}$$ ';
        $fields[] = 'updated JSONB NOT NULL';
        $fields[] = 'cycle_id INTEGER';

        $fields = '(' . implode(',', $fields) . ')';
        Sql::exec('CREATE TABLE ' . $name . $fields);
        Sql::exec('CREATE UNIQUE INDEX ' . $name . '_cycle_id_uindex ON ' . $name . ' (cycle_id)');

        tableTypes::getTableByName('calcstable_versions')->reCalculateFromOvers([
            'add' => [
                ['table_name' => $name, 'version' => 'v1', 'is_default' => true]
            ]
        ]);

    }

    function createTable()
    {
        /*if (empty($this->tableRow['tree_node_id']) || !($cyclesTableRow = Table::getTableRowById($this->tableRow['tree_node_id'])) || $cyclesTableRow['type'] != 'cycles') {
            throw new errorException('Необходимо заполнить таблицу циклов');
        };*/

        $this->__createTable($this->tableRow['name']);
    }

    function createCalcsTable()
    {
        Model::init($this->tableRow['name'])->insert(['cycle_id' => $this->Cycle->getId(), 'updated' => static::getUpdatedJson()]);
        $this->onCreateTable();
    }

    protected function addInSourceTables($SourceTableRow)
    {
        if ($SourceTableRow['id'] != $this->tableRow['id'])
            $this->source_tables[$SourceTableRow['id']] = true;
    }

    function loadDataRow($fromConstructor = false, $force = false)
    {
        if ((empty($this->dataRow) || $force) && $this->Cycle->getId() > 0) {
            if ($this->dataRow = $this->model->get(['cycle_id' => $this->Cycle->getId()])) {
                $this->tbl = json_decode($this->dataRow['tbl'], true);
                $this->indexRows();
                $this->loadedTbl = $this->savedTbl = $this->tbl;

                $this->updated = $this->dataRow['updated'];
            } else {

                if ($this->tableRow['tree_node_id'] == $this->Cycle->getCyclesTableId()) {
                    $this->createCalcsTable();
                    $this->loadDataRow();
                } else
                    throw new errorException('Рассчетная таблица не подключена к этой таблице циклов');
            }
        }
    }

    protected function loadModel()
    {
        $this->model = Model::init($this->tableRow['name']);
        $this->modelConnects = TablesCalcsConnects::init();
    }


}