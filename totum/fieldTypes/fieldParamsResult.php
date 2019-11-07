<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 12.07.2018
 * Time: 11:12
 */

namespace totum\fieldTypes;


use totum\common\Calculate;
use totum\common\errorException;
use totum\common\Field;

class fieldParamsResult extends Field
{

    protected function checkValByType(&$val, $row, $isCheck = false)
    {
        if(!$isCheck){
            if(!empty($row['data_src']['v']['Field'])){
                $val=FieldParams::getTmpDataByStr($row['data_src']['v']['Field']) ?? $val;
            }
        }
    }

    /*Легаси для старых баз*/
    function calculate(&$newVal, $oldRow, $row, $oldTbl, $tbl = [], $vars=[])
    {
        if (!empty($oldRow['id']) && $oldRow['id']==4){
            $newVal=['v'=>["type"=>"fieldParamsResult", "showInWeb"=>false]];
            return;
        }
    }

    function getValueFromCsv($val)
    {
        throw new errorException('Для работы с полями есть таблица Обновления');
        /*return $val = json_decode(base64_decode($val), true);*/
    }

}