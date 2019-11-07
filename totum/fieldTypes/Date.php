<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 18.08.17
 * Time: 17:15
 */

namespace totum\fieldTypes;


use totum\common\Calculate;
use totum\common\errorException;
use totum\common\Field;
use totum\tableTypes\aTable;

class Date extends Field
{
    protected function __construct($fieldData, aTable $table)
    {
        parent::__construct($fieldData, $table);
    }

    /*function addViewValues($viewType, array &$valArray, $row, $tbl = [])
    {
        parent::addViewValues($viewType, $valArray, $row, $tbl);

        if ($valArray['v']) {
            if ($date = Calculate::getDateObject($valArray['v'])) {
                if (empty($this->data['dateTime'])) {
                    $valArray['v'] = $date->format('d.m.y');
                } else {
                    $valArray['v'] = $date->format('d.m.y H:i');
                }
            }
        }
        if (!empty($valArray['c'])) {
            if ($date = Calculate::getDateObject($valArray['c'])) {
                if (empty($this->data['dateTime'])) {
                    $valArray['c'] = $date->format('d.m.y');
                } else {
                    $valArray['c'] = $date->format('d.m.y H:i');
                }
            }
        }
    }*/

    public function addViewValues($viewType, array &$valArray, $row, $tbl = [])
    {
        parent::addViewValues($viewType, $valArray, $row, $tbl);
        if ($viewType == 'print'){
            $date = date_create($valArray['v']);

            if(!empty($this->data['dateFormat'])){
                $val = $date->format($this->data['dateFormat']);
            }
            else if (empty($this->data['dateTime'])) {
                $val = $date->format('d.m.y');
            } else {
                $val = $date->format('d.m.y H:i');
            }
            $valArray['v']=$val;
        }
    }

    function getValueFromCsv($val)
    {
        $val = Calculate::getDateObject($val);
        if (!empty($this->data['dateTime'])) {
            $val = $val->format('Y-m-d H:i');
        } else  $val = $val->format('Y-m-d');

        return $val;
    }

    protected function getDefaultValue()
    {
        if (!empty($this->data['default'])) {
            if ($defDate = Calculate::getDateObject($this->data['default'])) {
                return $defDate->format('Y-m-d'.($this->data['dateTime']?' H:i':''));
            }
        }
    }

    protected function checkValByType(&$val, $row, $isCheck = false)
    {
        if ($val) {
            if ($date = Calculate::getDateObject($val)) {
                if (empty($this->data['dateTime'])) {
                    $val = $date->format('Y-m-d');
                } else {
                    $val = $date->format('Y-m-d H:i');
                }
            } else {
                throw new errorException('Ошибка формата введенной даты');
            }
        }
    }
}