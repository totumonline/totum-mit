<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 19.09.17
 * Time: 18:51
 */

namespace totum\fieldTypes;

use totum\common\Auth;
use totum\common\calculates\Calculate;
use totum\common\errorException;
use totum\common\Field;
use totum\common\Lang\RU;
use totum\common\Model;
use totum\common\sql\Sql;
use totum\common\Totum;
use totum\models\Table;
use totum\models\TablesFields;
use totum\tableTypes\JsonTables;
use totum\tableTypes\tableTypes;

class FieldParams extends Field
{
    private static $fieldDatas;
    /**
     * @var array
     */
    private $inVars;

    public function addViewValues($viewType, array &$valArray, $row, $tbl = [])
    {
        parent::addViewValues($viewType, $valArray, $row, $tbl);
        switch ($viewType) {
            case 'csv':
                throw new errorException($this->translate('Export via csv is not available for [[%s]] field.', 'FieldParams'));
                break;
            case 'web':
                $valArray['v'] = $this->translate('Settings field.');
                break;
            case 'edit':

                break;
        }
    }

    public function getValueFromCsv($val)
    {
        throw new errorException($this->translate('Import from csv is not available for [[%s]] field.', 'FieldParams'));
        /*return $val = json_decode(base64_decode($val), true);*/
    }


    public function modify($channel, $changeFlag, $newVal, $oldRow, $row = [], $oldTbl = [], $tbl = [], $isCheck = false)
    {
        $this->inVars = [];

        $r = parent::modify(
            $channel,
            $changeFlag,
            $newVal,
            $oldRow,
            $row,
            $oldTbl,
            $tbl,
            $isCheck
        );
        return $r;
    }

    public function add($channel, $inNewVal, $row = [], $oldTbl = [], $tbl = [], $isCheck = false, $vars = [])
    {
        $this->inVars = $vars;

        $r = parent::add(
            $channel,
            $inNewVal,
            $row,
            $oldTbl,
            $tbl,
            $isCheck,
            $vars
        );
        return $r;
    }

    final protected function checkValByType(&$val, $row, $isCheck = false)
    {
        if ($this->table->getTableRow()['id'] !== 2) {
            throw new errorException('Тип поля Параметры допустим только для таблицы Состав полей');
        }
        /*$val = json_decode('{"type": {"Val": "fieldParamsResult", "isOn": true}, "width": {"Val": 250, "isOn": true}, "showInWeb": {"Val": false, "isOn": true}}',
            true);*/

        if (empty($val['type']['Val'])) {
            throw new errorException($this->translate('Fill in the parameter [[%s]].', 'type'));
        }

        $category = $row['category']['v'];
        $tableRow = $this->table->getTotum()->getTableRow($row['table_id']['v']);

        if ($val['type']['Val'] === 'text') {
            $val['viewTextMaxLength']['Val'] = (int)$val['viewTextMaxLength']['Val'];
        }

        if ($category === 'footer' && !is_subclass_of(
            Totum::getTableClass($tableRow),
            JsonTables::class
        )) {
            throw new errorException($this->translate('You cannot create a [[footer]] field for [[non-calculated]] tables.'));
        }
    }
}
