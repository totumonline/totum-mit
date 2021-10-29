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
                "/" . str_replace(
                    '/',
                    '\/',
                    $this->data['regexp']
                ) . "/",
                $val
            )
        ) {
            errorException::criticalException(
                $this->data['regexpErrorText'] ?? $this->translate('The value of %s field must match the format: %s',
                    [$this->data['title'], $this->data['regexp']]),
                $this->table
            );
        }

        if (is_numeric($val)) {
            $val = Calculate::rtrimZeros(bcadd($val, 1, 10));
        }

    }
}
