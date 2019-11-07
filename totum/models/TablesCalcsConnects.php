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
use totum\common\Sql;

class TablesCalcsConnects extends Model
{
    const tableV = 'tables_calcs_connects__v';

    protected $isServiceTable = true;

    function addConnects($tableId, $cycle_id = 0, $cycles_table_id = 0, array $sourceTableIds)
    {
        
        foreach ($sourceTableIds as $sourceTableId=>$null) {
            $this->insert([
                'table_id' => $tableId
                , 'cycle_id' => $cycle_id
                , 'cycles_table_id' => $cycles_table_id
                , 'source_table_id' => $sourceTableId
            ],
                false, true);
        }
        $this->delete([
            'table_id' => $tableId
            , 'cycle_id' => $cycle_id
            , 'cycles_table_id' => $cycles_table_id
            , 'source_table_idNOTIN' => array_keys($sourceTableIds)
        ]);
    }

    function duplicateCycleSources($tables, $cycleBaseId, $cycleNewId){
        Sql::exec('insert into '.$this->table.' (table_id, cycle_id, cycles_table_id, source_table_id)  '.
            '(select table_id, '.$cycleNewId.', cycles_table_id, source_table_id from '.$this->table.' where cycle_id IN ('.implode(',', $tables).') AND cycle_id='.$cycleBaseId.')');
    }


    function getSourceTables($table_id, $cycle_id/*=0*/, $cycles_table_id/*=0*/)
    {
        //TODO недоделано
        return Model::initService(static::tableV)->getAllIndexedByField(['table_id' => $table_id, 'cycle_id' => $cycle_id, 'cycles_table_id' => $cycles_table_id],
            'source_table_id',
            'source_table_id');
    }

    function getReceiverTables($table_id, $cycle_id/*=0*/, $cycles_table_id/*=0*/)
    {
        return $this->getField('table_id',
            ['source_table_id' => $table_id, 'cycle_id' => $cycle_id, 'cycles_table_id' => $cycles_table_id],
            null,
            null);
    }

    function removeConnectsForCycle(Cycle $Cycle)
    {
        $this->delete(['cycles_table_id' => $Cycle->getCyclesTableId(), 'cycle_id' => $Cycle->getId()]);
    }

}