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
use totum\common\criticalErrorException;
use totum\common\errorException;
use totum\common\Field;
use totum\common\Lang\RU;
use totum\common\Model;
use totum\common\sql\Sql;
use totum\common\Totum;
use totum\models\Table;
use totum\models\TablesFields;
use totum\tableTypes\JsonTables;

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
                throw new errorException($this->translate('Export via csv is not available for [[%s]] field.',
                    'FieldParams'));
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
            throw new errorException($this->translate('The Parameters field type is valid only for the Tables Fields table'));
        }
        /*$val = json_decode('{"type": {"Val": "fieldParamsResult", "isOn": true}, "width": {"Val": 250, "isOn": true}, "showInWeb": {"Val": false, "isOn": true}}',
            true);*/

        $category = $row['category']['v'];
        $tableRow = $this->table->getTotum()->getTableRow($row['table_id']['v']);

        if ($category === 'footer' && !is_subclass_of(
                Totum::getTableClass($tableRow),
                JsonTables::class
            )) {
            throw new criticalErrorException($this->translate('You cannot create a [[footer]] field for [[non-calculated]] tables.'));
        }

        if (empty($val['type']['Val'])) {
            if (!$isCheck) {
                throw new criticalErrorException($this->translate('Fill in the parameter [[%s]].', 'type'));
            }
            throw new errorException($this->translate('Fill in the parameter [[%s]].', 'type'));
        }elseif (!$isCheck){
            if($row['category']['v']==='filter' && in_array($val['type']['Val'], ['link','text','comments','file','password','chart', 'uniq'])){
                throw new criticalErrorException($this->translate('The field type %s cannot be in the pre-filter', $val['type']['Val']));
            }

        }


        if ($val['type']['Val'] === 'text') {
            $val['viewTextMaxLength']['Val'] = (int)$val['viewTextMaxLength']['Val'];
        }
        elseif (!$isCheck && $val['type']['Val'] === 'number' && ($val['dectimalPlaces']['Val'] ?? 0) > 10) {
            throw new criticalErrorException($this->translate('Max value of %s is %s.', ['dectimalPlaces', '10']));
        }

        if ($row['name']['v'] === 'tree' && $row['category']['v'] === 'column' && ($val['treeViewType']['isOn'] ?? false) === true && $val['type']['Val'] === 'tree') {
            $val['multiple']['isOn'] = false;
            $val['multiple']['Val'] = false;
            $val['codeSelectIndividual']['isOn'] = false;
            $val['codeSelectIndividual']['Val'] = false;
        }


    }
}
