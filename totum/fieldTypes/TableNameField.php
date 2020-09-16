<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 28.08.17
 * Time: 19:00
 */

namespace totum\fieldTypes;


use totum\tableTypes\aTable;

class TableNameField extends Unic
{
    protected function calculate(&$newVal, $oldRow, $row, $oldTbl, $tbl, $vars, $calcInit)
    {
        $newVal['c'] = $this->CalculateCode->exec($this->data,
            $newVal,
            $oldRow,
            $row,
            $oldTbl,
            $tbl,
            $this->table);

        if ($newVal['h'] ?? null) ;
        else $newVal['v'] = $newVal['c'];
    }
}