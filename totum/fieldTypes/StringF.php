<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 18.08.17
 * Time: 17:42
 */

namespace totum\fieldTypes;

use totum\common\calculates\Calculate;
use totum\common\criticalErrorException;
use totum\common\errorException;
use totum\common\Field;
use totum\common\Lang\RU;

class StringF extends Field
{
    protected function modifyValue($modifyVal, $oldVal, $isCheck, $row)
    {
        if (is_object($modifyVal)) {
            if ($modifyVal->sign === '+') {
                $modifyVal = $oldVal . (string)$modifyVal->val;
            } else {
                $modifyVal = (string)$modifyVal->val;
            }
        }
        return $modifyVal;
    }

    protected function checkValByType(&$val, $row, $isCheck = false)
    {
        if (!empty($this->data['regexp']) && $val !== '' && !is_null($val) && !preg_match(
                '/' . str_replace(
                    '/',
                    '\/',
                    $this->data['regexp']
                ) . '/u',
                $val
            )
        ) {
            errorException::criticalException(
                $this->data['regexpErrorText'] ?? $this->translate('The value of %s field must match the format: %s',
                    [$this->data['title'], $this->data['regexp']]),
                $this->table
            );
        }

        if (is_numeric($val) && !is_string($val)) {
            $val = strval($val);
        } elseif (is_array($val)) {
            $val = json_encode($val, JSON_UNESCAPED_UNICODE);
        }
    }

    public function addViewValues($viewType, array &$valArray, $row, $tbl = [])
    {
        parent::addViewValues($viewType, $valArray, $row, $tbl);
        if (!is_null($valArray['v'])) {
            if ($viewType == 'web') {
                if (!($this->data['dynamic'] ?? false) && is_array($valArray['v'])){
                    $valArray['e']=$this->translate('Field data type error');
                }
            }
        }
    }
}
