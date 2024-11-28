<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 21.03.17
 * Time: 10:21
 */

namespace totum\models;

use totum\common\Cycle;
use totum\common\Model;
use totum\common\sql\Sql;

class TablesCalcsConnects extends Model
{
    protected const VIEW_NAME = 'tables_calcs_connects__v';

    protected bool $isServiceTable = true;

    public function addConnects($tableId, array $sourceTableIds, $cycle_id = 0, $cycles_table_id = 0)
    {
        foreach ($sourceTableIds as $sourceTableId => $null) {
            $this->insertPrepared(
                [
                'table_id' => $tableId
                , 'cycle_id' => $cycle_id
                , 'cycles_table_id' => $cycles_table_id
                , 'source_table_id' => $sourceTableId
            ],
                false,
                true
            );
        }

        $this->deletePrepared([
            'table_id' => $tableId
            , 'cycle_id' => $cycle_id
            , 'cycles_table_id' => $cycles_table_id
            , '!source_table_id' => array_keys($sourceTableIds)
        ]);
    }

    public function duplicateCycleSources($tables, $cycleBaseId, $cycleNewId)
    {

        if (count($tables)){

            $prepared = $this->Sql->getPrepared('insert into ' . $this->table . ' (table_id, cycle_id, cycles_table_id, source_table_id)  ' .
                '(select table_id, ?, cycles_table_id, source_table_id from ' .
                $this->table . ' where table_id IN (' . str_repeat('?, ', count($tables) - 1) . '?) AND cycle_id=?)');

            $prepared->execute([$cycleNewId, ...$tables, $cycleBaseId]);
        }

    }

    public function getReceiverTables($table_id, $cycle_id/*=0*/, $cycles_table_id/*=0*/)
    {
        return $this->getColumn(
            'table_id',
            ['source_table_id' => $table_id, 'cycle_id' => $cycle_id, 'cycles_table_id' => $cycles_table_id]
        );
    }

    public function removeConnectsForCycle(Cycle $Cycle)
    {
        $this->deletePrepared(['cycles_table_id' => $Cycle->getCyclesTableId(), 'cycle_id' => $Cycle->getId()]);
    }
}
