<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 18.08.17
 * Time: 17:50
 */

namespace totum\fieldTypes;

use totum\common\Field;

class Checkbox extends Field
{
    public function getValueFromCsv($val)
    {
        switch ($val) {
            case '[0]':
            case '0':
                $val = false;
                break;
            case '[1]':
            case '1':
                $val = true;
                break;
            default:
                $val = null;
        }
        return $val;
    }

    public function addViewValues($viewType, array &$valArray, $row, $tbl = [])
    {
        parent::addViewValues($viewType, $valArray, $row, $tbl);

        switch ($viewType) {
            case 'print':
                switch ($valArray['v']) {
                    case true:
                        $val = '✓';
                        break;
                    case false:
                        $val = '-';
                        break;
                    default:
                        $val = '---';
                }
                $valArray['v'] = $val;

                break;
            case 'web':
                if (!is_bool($valArray['v'])) {
                    $valArray['e'] = $this->translate('Field data format error');
                }
                break;
            case 'csv':
                switch ($valArray['v']) {
                    case true:
                        $val = '[1]';
                        break;
                    case false:
                        $val = '[0]';
                        break;
                    default:
                        $val = '[_]';
                }
                $valArray['v'] = $val;
                break;
        }
    }

    protected function checkValByType(&$val, $row, $isCheck = false)
    {
        $val = ($val === 'true' || $val === true ? true : false);
    }
}
