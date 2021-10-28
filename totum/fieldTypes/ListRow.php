<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 18.08.17
 * Time: 18:01
 */

namespace totum\fieldTypes;

use totum\common\Field;
use totum\common\Lang\RU;

class ListRow extends Field
{
    public function addViewValues($viewType, array &$valArray, $row, $tbl = [])
    {
        parent::addViewValues($viewType, $valArray, $row, $tbl);
        if ($viewType === 'web' && array_key_exists('c', $valArray)) {
            $valArray['c'] = $this->translate('Changed');
        }

        switch ($viewType) {
            case 'web':
                if ($this->data['category'] !== "filter") {
                    $string = json_encode($valArray['v'], JSON_UNESCAPED_UNICODE);
                    /*if($this->data['name']==='not_loaded'){
                        var_dump($string); die;
                    }*/
                    if ($this->table->getTableRow()['type'] !== 'tmp' && !empty($this->data['viewTextMaxLength']) && $isBig = mb_strlen($string) > $this->data['viewTextMaxLength']) {
                        $valArray['v'] = mb_substr($string, 0, $this->data['viewTextMaxLength']) . '...';
                    }
                }
                break;
            case 'print':
            case 'csv':
                $valArray['v'] = base64_encode(json_encode($valArray['v'], JSON_UNESCAPED_UNICODE));
                break;
        }
    }

    public function getValueFromCsv($val)
    {
        return $val = json_decode(base64_decode($val), true);
    }
}
