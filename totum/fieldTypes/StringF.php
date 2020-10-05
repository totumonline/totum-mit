<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 18.08.17
 * Time: 17:42
 */

namespace totum\fieldTypes;

use totum\common\criticalErrorException;
use totum\common\errorException;
use totum\common\Field;

class StringF extends Field
{
    protected function modifyValue($modifyVal, $oldVal, $isCheck)
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
            "/" . str_replace(
                    '/',
                    '\/',
                    $this->data['regexp']
                ) . "/",
            $val
        )
        ) {
            errorException::criticalException(
                'Поле ' . $this->data['title'] . ' не соответствует формату "' . $this->data['regexp'] . '"',
                $this->table
            );
        }
    }
}
