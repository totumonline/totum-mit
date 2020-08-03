<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 18.08.17
 * Time: 17:21
 */

namespace totum\fieldTypes;


use totum\common\Field;

class Password extends Field
{
    function getModifiedLogValue($val){
        return "---";
    }
    function getLogValue($val, $row, $tbl = [])
    {
        return "---";
    }
    function addViewValues($viewType, array &$valArray, $row, $tbl = [])
    {
        if ($viewType != 'edit') {
            $valArray['v'] = '';
        }
    }

    protected function modifyValue($modifyVal, $oldVal, $isCheck)
    {
        if ($modifyVal === '') $modifyVal = $oldVal;
        return $modifyVal;
    }

    protected function checkValByType(&$val, $row, $isCheck = false)
    {
        if (!$isCheck && strlen($val) !== 32) {
            $val = md5($val);
        }
    }
}