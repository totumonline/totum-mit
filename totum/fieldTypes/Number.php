<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 18.08.17
 * Time: 17:21
 */

namespace totum\fieldTypes;

use totum\common\criticalErrorException;
use totum\common\errorException;
use totum\common\Field;
use totum\tableTypes\aTable;

class Number extends Field
{
    protected function __construct($fieldData, aTable $table)
    {
        parent::__construct($fieldData, $table);

        $this->data['dectimalPlaces'] = $this->data['dectimalPlaces'] ?? 0;
    }

    protected static function modifyNumberValue($modifyVal, $oldValue)
    {
        if (($modifyVal==="" || is_null($modifyVal)) && empty($sign)) {
            return $modifyVal;
        }

        if (is_object($modifyVal)) {
            $sign = $modifyVal->sign;
            $diffVal=$modifyVal->val;
            $percent=$modifyVal->percent;
            if (!$percent && preg_match('/%$/', $diffVal)) {
                $diffVal=substr($diffVal, 0, -1);
                $percent=true;
            }
            if (!is_numeric(strval($diffVal))) {
                throw new errorException('В числовое поле должно идти число, а не [['.strval($diffVal).']]');
            }
            if ($percent) {
                $diffVal = floatval($oldValue) / 100 * floatval($diffVal);
            }
            if ($sign=='-') {
                $sign='+';
                $diffVal*=-1;
            }
        } elseif (preg_match(
            '/^(\-)([\d]+(\.[\d]+)?)(%)$/',
            $modifyVal,
            $matches
        ) || preg_match(
                    '/^(\-|\+|\/|:|\*)(\-?[\d]+(\.[\d]+)?)(%?)$/',
                    $modifyVal,
                    $matches
                )
        ) {
            if (!empty($matches[4]) && $matches[4] == '%') {
                $diffVal = floatval($oldValue) / 100 * floatval($matches[2]);
                if ($matches[1] == '-') {
                    $diffVal *= -1;
                }
                $sign = '+';
            } else {
                $sign = $matches[1];
                $diffVal = floatval($matches[2]);
            }
        }
        switch ($sign??'') {
                 /*case '-':
                      $modifyVal = floatval($oldValue) - floatval($diffVal);
                      break;*/
                case '+':
                    $modifyVal = floatval($oldValue) + floatval($diffVal);
                    break;
                case '*':
                    $modifyVal = floatval($oldValue) * floatval($diffVal);
                    break;
                case '\\':
                case '/':
                case ':':
                    $modifyVal = floatval($oldValue) / floatval($diffVal);
                    break;
            }

        return $modifyVal;
    }

    public function getValueFromCsv($val)
    {
        return $val = str_replace(',', '.', $val);
    }

    public function addViewValues($viewType, array &$valArray, $row, $tbl = [])
    {
        parent::addViewValues($viewType, $valArray, $row, $tbl);
        if (!is_null($valArray['v'])) {
            switch ($viewType) {
                case 'print':
                    if ($this->data['currency'] && !is_null($valArray['v'])) {
                        $valArray['v'] = number_format($valArray['v'], $this->data['dectimalPlaces'], ',', ' ');
                    }


                    if (!is_null($valArray['v']) && !empty($this->data['unitType'])) {
                        $valArray['v'].=' '.$this->data['unitType'];
                    }
                    break;
                case 'csv':
                    $valArray['v'] = str_replace('.', ',', $valArray['v']);
                    break;
            }
        }
    }

    protected function getDefaultValue()
    {
        return str_replace(',', '.', $this->data['default'] ?? '');
    }

    protected function modifyValue($modifyVal, $oldVal, $isCheck)
    {
        $modifyVal = $this->modifyNumberValue($modifyVal, $oldVal);

        if (!is_null($modifyVal) && $modifyVal !== '') {
            $modifyVal = round($modifyVal, $this->data['dectimalPlaces']);
        }

        return $modifyVal;
    }

    protected function checkValByType(&$val, $row, $isCheck = false)
    {
        if (is_null($val) || $val === '') {
            return;
        }

        if (is_string($val)) {
            if (!is_numeric($val)) {
                throw new errorException('Поле [[' . $this->data['title'] . ']] должно содержать число, а не [[' . $val . ']]');
            }
        } elseif (!is_float($val) && !is_int($val)) {
            throw new errorException('Поле [[' . $this->data['title'] . ']] должно содержать число, а не [[' . $val . ']]');
        }
        if (!empty($this->data['regexp']) && !preg_match(
            "/" . str_replace(
            '/',
            '\/',
            $this->data['regexp']
        ) . "/",
            $val
        )
        ) {
            errorException::criticalException('Поле ' . $this->data['title'] . ' не соответствует формату "' . $this->data['regexp'] . '"', $this->table);
        }


        $func = 'round';
        if (!empty($this->data['round'])) {
            switch ($this->data['round']) {
                case 'up':
                    $func = 'ceil';
                    break;
                case 'down':
                    $func = 'floor';
                    break;
            }
        }
        $fig = (int)str_pad('1', $this->data['dectimalPlaces'] + 1, '0');

        $step = 1;
        if (!empty($this->data['step'])) {
            $step = $this->data['step'] * $fig;
            $val /= $step;
        }

        if (fmod($val * $fig, 1) < 0.000000000001) {
            $func = 'round';
        }

        $val = $func($val * $fig) / $fig * $step;
        $val = bcadd($val, 0, $this->data['dectimalPlaces']);
    }
}
