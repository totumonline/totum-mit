<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 25.08.17
 * Time: 13:03
 */

namespace totum\fieldTypes;

use totum\common\criticalErrorException;
use totum\common\errorException;
use totum\common\Field;
use totum\common\Lang\RU;

class Unic extends Field
{
    public function addViewValues($viewType, array &$valArray, $row, $tbl = [])
    {
        parent::addViewValues($viewType, $valArray, $row, $tbl);
        if (!is_null($valArray['v'])) {
            if ($viewType == 'web') {
                if (is_array($valArray['v'])) {
                    $valArray['e'] = $this->translate('Field data type error');
                }
            }
        }
    }

    protected function checkValByType(&$val, $row, $isCheck = false)
    {
        if (!$isCheck) {
            if (is_array($val)) {
                $val = null;
            }
            if (!is_null($val) && $val !== '') {

                if (!empty($this->data['regexp']) && !preg_match(
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

                $where = [
                    ['field' => $this->data['name'], 'operator' => '=', 'value' => $val]
                ];
                if ($row['id'] ?? null) {
                    $where[] = ['field' => 'id', 'operator' => '!=', 'value' => $row['id']];
                }

                if ($id_duble = $this->table->getByParams(
                    ['field' => 'id', 'where' => $where]
                )) {
                    errorException::criticalException(
                        $this->translate('The value must be unique. Duplication in rows: [[%s - %s]]',
                            [$id_duble, $row['id']]),
                        $this->table
                    );
                }
            }
        }
    }
}
