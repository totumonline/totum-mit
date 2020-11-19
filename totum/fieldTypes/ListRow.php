<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 18.08.17
 * Time: 18:01
 */

namespace totum\fieldTypes;

use totum\common\Field;

class ListRow extends Field
{
    public function addViewValues($viewType, array &$valArray, $row, $tbl = [])
    {
        parent::addViewValues($viewType, $valArray, $row, $tbl);
        if ($viewType === 'web' && array_key_exists('c', $valArray)) {
            $valArray['c'] = 'Изменено';
        }

        switch ($viewType) {
            case 'web':
                if ($this->data['category'] !== "filter") {
                    $string = json_encode($valArray['v'], JSON_UNESCAPED_UNICODE);
                    if ($this->table->getTableRow()['type'] !== 'tmp' && ($isBig = mb_strlen($string) > ($this->data['viewTextMaxLength']?? 500))) {
                        $valArray['v'] = mb_substr($string, 0, $this->data['viewTextMaxLength'] ?? 500) . '...';
                    }
                }
                break;
            case 'print':
            case 'csv':
                $valArray['v'] = base64_encode(json_encode($valArray['v'], JSON_UNESCAPED_UNICODE));
                break;
            case 'xml':
                $valArray['v'] = json_encode($valArray['v'], JSON_UNESCAPED_UNICODE);
                break;
        }
    }

    public function getValueFromCsv($val)
    {
        return $val = json_decode(base64_decode($val), true);
    }
}
