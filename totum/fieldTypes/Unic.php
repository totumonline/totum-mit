<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 25.08.17
 * Time: 13:03
 */

namespace totum\fieldTypes;


use totum\common\errorException;
use totum\common\Field;

class Unic extends Field
{
    protected function checkValByType(&$val, $row, $isCheck = false)
    {
        if (!$isCheck && !is_null($val) && $val!=='') {
            $where = [
                ['field' => $this->data['name'], 'operator' => '=', 'value' => $val]
            ];
            if ($row['id']??null){
                $where[]=['field' => 'id', 'operator' => '!=', 'value' => $row['id']];
            }

            if ($id_duble = $this->table->getByParams(
                ['field' => 'id', 'table' => $this->table->getTableRow()['name'], 'where' => $where])) {

                throw new errorException('Значение должно быть уникальным [[['.$id_duble.'] - ['.$row['id'].']]]');
            }

        }
    }


}