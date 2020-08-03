<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 20.10.16
 * Time: 19:31
 */

namespace totum\models;


use totum\common\Auth;
use totum\common\errorException;
use totum\common\Field;
use totum\common\Log;
use totum\common\Model;
use totum\common\Sql;
use totum\fieldTypes\Comments;
use totum\tableTypes\calcsTable;
use totum\tableTypes\JsonTables;
use totum\tableTypes\RealTables;
use totum\tableTypes\tableTypes;
use totum\tableTypes\tmpTable;

class TablesFields extends Model
{
    const TableId = 2;
    static $cache;
    protected static $TableFieldsTable;

    static function clearCache()
    {
        static::$cache = [];
    }

    static function getFullField($fieldRow)
    {
        $data = json_decode($fieldRow['data'], true);
        return array_merge($data??[],
            ['category' => $fieldRow['category'], 'name' => $fieldRow['name'], 'ord' => $fieldRow['ord'], 'id' => $fieldRow['id'], 'title' => $fieldRow['title']]);
    }

    static function getFields($tableId, $version = null, $withCache = true, $cycleId=0)
    {
        $cache = $tableId.'/'.$version;

        if (empty(static::$cache[$cache]) || !$withCache || ($tableId != static::TableId && tableTypes::getTable(Table::getTableRowById(static::TableId))->isOnSaving)) {


            $fields = [];
            $where = ['table_id' => $tableId, 'version'=>$version];
            $links=[];
            foreach (Model::initService('tables_fields__v')->getAll($where,
                'name, category, data, id, title, ord',
                'ord, id') as $f) {
                $f = $fields[$f['name']] = static::getFullField($f);

                if (array_key_exists('type', $f) && $f['type'] == 'link') {
                    $links[]= $f ;

                } elseif ($tableId == static::TableId && $f['name'] == 'data_src') {
                    if (Auth::isCreator()) {
                        $fields[$f['name']]['jsonFields']['fieldSettings']['editRoles']['values']
                            = $fields[$f['name']]['jsonFields']['fieldSettings']['addRoles']['values']
                            = $fields[$f['name']]['jsonFields']['fieldSettings']['logRoles']['values']
                            = $fields[$f['name']]['jsonFields']['fieldSettings']['webRoles']['values']
                            = $fields[$f['name']]['jsonFields']['fieldSettings']['xmlRoles']['values']
                            = $fields[$f['name']]['jsonFields']['fieldSettings']['xmlEditRoles']['values']
                            = Model::init('roles')->getFieldIndexedById('title',
                            ['is_del' => false],
                            'title->>\'v\'');
                        $fields[$f['name']]['jsonFields']['fieldSettings']['selectTable']['values'] = Model::init('tables')->getFieldIndexedByField(
                            ['is_del' => false],
                            'name',
                            'title',
                            'title->>\'v\'');

                    }
                }
            }

            foreach ($links as $f){
                if ($linkTableRow = Table::getTableRowByName($f['linkTableName'])) {
                    $linkTableId = $linkTableRow['id'];

                    if ($tableId == $linkTableId) $fForLink = $fields[$f['linkFieldName']] ?? null;
                    else {
                        if($linkTableRow['type']=='calcs'){
                            if(Table::getTableRowById($tableId)['type']=='calcs'){
                                $_version=CalcsTableCycleVersion::getVersionForCycle($f['linkTableName'], $cycleId)[0];
                            }else{
                                $_version=CalcsTableCycleVersion::getDefaultVersion($f['linkTableName']);
                            }
                            $fForLink = static::getFields($linkTableId, $_version)[$f['linkFieldName']];
                        }else{
                            $fForLink = static::getFields($linkTableId)[$f['linkFieldName']];
                        }
                    }

                    if ($fForLink) {
                        $fieldFromLinkParams = [];
                        foreach (['type', 'dectimalPlaces', 'closeIframeAfterClick', 'dateFormat', 'codeSelect', 'multiple', 'codeSelectIndividual', 'buttonText', 'unitType', 'currency', 'textType', 'withEmptyVal', 'multySelectView', 'dateTime', 'printTextfull', 'viewTextMaxLength', 'values'] as $fV) {
                            if (isset($fForLink[$fV])) {
                                $fieldFromLinkParams[$fV] = $fForLink[$fV];
                            }
                        }
                        if ($fieldFromLinkParams['type'] === 'button') {
                            $fieldFromLinkParams['codeAction'] = $fForLink['codeAction'];
                        } elseif ($fieldFromLinkParams['type'] == 'file') {
                            $fields[$f['name']]['fileDuplicateOnCopy'] = false;
                        }

                        $fields[$f['name']] = array_merge($fields[$f['name']], $fieldFromLinkParams);

                    }
                    else
                        $fields[$f['name']]['linkFieldError'] = true;

                } else {
                    $fields[$f['name']]['linkFieldError'] = true;
                }
                $fields[$f['name']]['code'] = 'Код селекта';
                if($fields[$f['name']]['type']=='link')
                    $fields[$f['name']]['type'] = 'string';

            }

            foreach ($fields as &$f) {
                if ($f['category'] == 'filter') {
                    if (empty($f['codeSelect']) && !empty($f['column']) && ($column = $fields[$f['column']] ?? null)) {
                        if (isset($column['codeSelect'])) {
                            $f['codeSelect'] = $column['codeSelect'];
                        } elseif (isset($column['values'])) {
                            $f['values'] = $column['values'];
                        }
                    }
                }
            }

            static::$cache[$cache] = $fields;

        }

        return static::$cache[$cache];
    }

    function delete($where, $ignore = 0)
    {
        if ($rows = $this->getAll($where)) {
            Sql::transactionStart();
            if (parent::delete($where, $ignore)) {
                foreach ($rows as $fieldRow) {
                    $fieldRow['name'] = json_decode($fieldRow['name'], true)['v'];
                    $fieldRow['table_id'] = json_decode($fieldRow['table_id'], true)['v'];
                    $fieldRow['category'] = json_decode($fieldRow['category'], true)['v'];

                    if (in_array($fieldRow['name'], Model::serviceFields)) {
                        throw new errorException('Нельзя удалять системные поля');
                    }
                    $tableRow = Table::getTableRowById($fieldRow['table_id']);
                    if ($tableRow['type'] != 'calcs') {
                        tableTypes::getTable($tableRow, null)->deleteField($fieldRow);
                    }

                    $fieldData = json_decode($fieldRow['data'], true)['v'];
                    if ($fieldData['type'] === 'comments') {
                        Comments::removeViewedForField($tableRow['id'], $fieldRow['name']);
                    }
                }
            }
            Sql::transactionCommit();
        }
    }

    function update($params, $where, $ignore = 0, $oldValue = null): Int
    {
        /*if (array_key_exists('data_src', $params)) {
            $this->checkParams($params,
                $oldValue['table_id']['v'],
                $oldValue['data_src']['v'],
                $oldValue['category']['v']);
        }*/

        $r = parent::update($params, $where, $ignore);

        $table = Table::getTableRowById($oldValue['table_id']['v']);
        if ($table && $table['type'] != 'tmp' && $table['type'] != 'calcs') {
            $Table = tableTypes::getTable($table);
            if (!empty($params['category'])) {
                $newCategory = json_decode($params['category'], true)['v'];
                if (!empty($params['category']) && $newCategory != $oldValue['category']['v']) {

                    if (is_subclass_of(tableTypes::getTableClass($table), RealTables::class)) {

                        if ($oldValue['category']['v'] === 'column') {
                            $clearOldValue = array_map(function ($v) {
                                if (is_array($v)) return $v['v']; else return $v;
                            },
                                $oldValue);
                            $Table->deleteField($clearOldValue);
                        } elseif ($newCategory === 'column') {
                            $Table->addField($oldValue['id']);
                        }
                    }
                }
            }
            $Table->initFields(true);
        }

        return $r;
    }

    function insert($vars, $returning = 'idFieldName', $ignore = false): Int
    {
        if (!array_key_exists('data_src', $vars) || !($newData = json_decode($vars['data_src'],
                true)['v'])
        ) throw new errorException('Необходимо заполнить параметры');

        $decodedVars = array_map(function ($v) {
            return json_decode($v, true)['v'];
        },
            $vars);

        if (!$decodedVars['table_id']) {
            throw new errorException('Выберите таблицу');
        }
        if ($decodedVars['name'] == 'new_field') {
            throw new errorException('Name поля не может быть new_field');
        }
        if (in_array($decodedVars['name'], Model::serviceFields)) {
            throw new errorException('[[' . $decodedVars['name'] . ']] - название технического поля. Выберите другое имя');
        }
        if (in_array($decodedVars['name'], Model::reservedSqlWords)) {

            throw new errorException('[[' . $decodedVars['name'] . ']] - слово зарезервировано в sql. Выберите другое имя');
        }
        if (!is_array($vars['table_id'])) {
            $tableRowId = json_decode($vars['table_id'], true)['v'];
        } else {
            $tableRowId = $vars['table_id']['v'];
        }


        $name = $decodedVars['name'];
        $category = $decodedVars['category'];

        if (!empty(static::getFields($tableRowId, $decodedVars['version'])[$name]))
            throw new errorException('Поле [[' . $name . ']] уже существует в этой таблице.');

        /*$this->checkParams($vars, $tableRowId);*/

        Sql::transactionStart();
        if ($id = parent::insert($vars)) {
            $tableRow = Table::getTableRowById($tableRowId);

            if (isset($_POST['tableData']['afterField'])) {
                $this->update([
                    'ord=jsonb_build_object($$v$$,  ((ord->>\'v\')::integer+10)::text)'
                ],
                    [
                        'table_id' => $tableRowId
                        , 'category' => $category
                        , 'id!=' . $id
                        , '(ord->>\'v\')::integer>' . (int)$_POST['tableData']['afterField']
                    ]);
            }

            if ($tableRow['type'] !== 'calcs') {
                $table = tableTypes::getTable($tableRow, null);
                $table->addField($id);
                $table->initFields(true);
            }
        }
        Sql::transactionCommit();
        return $id;
    }

    function getFieldNameById($fieldId)
    {
        $where = ['id' => $fieldId];
        return Model::initService('tables_fields__v')->getField('name', $where);
    }

    function getFieldData($fieldName, $tableId)
    {
        $where = ['table_id' => $tableId, 'name' => $fieldName];
        return Model::initService('tables_fields__v')->get($where, '*', 'ord');
    }

    protected function checkParams(&$params, $tableRowId, $oldDataSrc = null, $category = null)
    {

        if (empty($params['data_src'])) return;
        if (is_null($category)) $category = json_decode($params['category'], true)['v'];

        $newData = json_decode($params['data_src'], true)['v'];

        $tableRow = Table::getTableRowById($tableRowId);


        if ($category == 'filter') {
            if (!in_array($tableRow['type'], ['calcs', 'globcalcs'])) {

                //Для реальных таблиц проставить индексы через изменение таблицы "Список таблиц"

                $oldColumnName = $oldDataSrc['column']["Val"] ?? '';
                $newColumnName = $newData['column']["Val"] ?? '';

                if ($newColumnName != $oldColumnName) {
                    $fields = TablesFields::getFields($tableRowId, null, false);

                    if ($newColumnName != '') {
                        if (empty($fields[$newColumnName]) || $fields[$newColumnName]['category'] != 'column') ;
                        else {
                            tableTypes::getTable(Table::getTableRowById(Table::$TableId))->reCalculateFromOvers(
                                ['modify' => [$tableRow['id'] => ['indexes' => '+' . $newColumnName]]]
                            );
                        }
                    }

                    if (!empty($fields[$oldColumnName])) {
                        $countWithColumn = 0;
                        foreach ($fields as $k => $v) {
                            if ($v['category'] === 'filter' && ($v['column'] ?? '') === $oldColumnName) $countWithColumn++;
                        }
                        if ($countWithColumn < 2) {
                            tableTypes::getTable(Table::getTableRowById(Table::$TableId))->reCalculateFromOvers(
                                ['modify' => [$tableRow['id'] => ['indexes' => '-' . $newColumnName]]]
                            );
                        }
                    }
                }

            }
        }
        if ($newData['type']['Val'] == 'text') {
            $newData['viewTextMaxLength']['Val'] = (int)$newData['viewTextMaxLength']['Val'];
            if ($newData['viewTextMaxLength']['Val'] > 500) throw new errorException('Ограничение размера видимости текста в веб - не больше 500 символов');
        }

        if ($category == 'footer' && !is_subclass_of(tableTypes::getTableClass($tableRow), JsonTables::class)) {
            throw new errorException('Нельзя создать поле [[футера]] [[не для рассчетных]] таблиц');
        }

    }

}