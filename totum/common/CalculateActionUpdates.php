<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 26.06.2018
 * Time: 15:44
 */

namespace totum\common;


use totum\fieldTypes\File;
use totum\models\Table;
use totum\models\TablesFields;
use totum\tableTypes\aTable;
use totum\tableTypes\RealTables;
use totum\tableTypes\tableTypes;

class CalculateActionUpdates extends CalculateAction
{
    const connectedTables = [
        'updates' => 1,
        'plan_obnovleniya' => 1,
        'plan_obnovleniya_fields' => 1,
        'plan_vigruzki_data' => 1,
        'plan_obnovleniya_data' => 1,
        'plan_obnovleniya_roles' => 1,
        'settings' => 1,
        'vigruzka' => 1,
        'zagruzka' => 1,
    ];
    const systemTablesExportFields =
        ['tables' => ['type', 'tree_node_id', 'title', 'insertable', 'deleting', 'description', 'description',
            'main_field', 'actual', 'indexes', 'cycles_access_type', 'row_format', 'duplicating', 'panel', 'delete_timer',
            'with_order_field', 'recalc_in_reorder', 'order_desc', 'table_format', 'calculate_by_columns'],
            'tables_fields' => [
                'category',
                'data_src',
                'ord',
                'title'
            ]
        ];
    const rolesTablesParams = ['csv_edit_roles',
        'csv_roles',
        'delete_roles',
        'duplicate_roles',
        'edit_roles',
        'insert_roles',
        'order_roles',
        'read_roles',
        'tree_off_roles'];
    const rolesFieldsSrcParams = ['addRoles', 'logRoles', 'webRoles', 'xmlRoles', 'editRoles', 'xmlEditRoles'];
    const tableCodeParams = ['row_format', 'table_format'];
    const dataSrcCodes = ['code', 'codeSelect', 'codeAction', 'format'];


    private static function getFilteredSchema($Schema, $tables, $tables_params, $fieldsRows, $fields_params)
    {
        $fieldsInParams = [];

        foreach ($fieldsRows as $fieldInParam) {
            $fieldsInParams[$fieldInParam['name_tablicy_v_sheme']][$fieldInParam['name']] = $fieldInParam;
        }

        $fields_params_flip = array_flip($fields_params);
        $tables_params_flip = array_flip($tables_params);
        $tableReplaces = [];
        $fieldsReplaces = [];
        $schema = [];

        foreach ($Schema as $tableName => $data) {
            if (array_key_exists($tableName, $tables)) {

                if (($inName = $tables[$tableName]['in_name']) != $tableName) {
                    $tableReplaces[$tableName] = $tables[$tableName]['in_name'];

                    $tableData =& $schema[$tables[$tableName]['in_name']];
                } else {
                    $tableData =& $schema[$tableName];
                }


                /*Фильтруем параметры таблиц*/
                $tableData['table'] = array_intersect_key($data['table'], $tables_params_flip);

                /*Подключаем Категория и tree_node_id из таблицы Загрузки*/
                if (!$tables[$tableName]['id']) {
                    $tableData['table']['tree_node_id'] = $tables[$tableName]['polojenie_v_dereveciklah'];
                    $tableData['table']['category'] = $tables[$tableName]['kategoriya'];
                }

                $tableData['fields'] = [];
                foreach ($data['fields'] as $fName => $field) {
                    if (array_key_exists($fName, $fieldsInParams[$inName])) {
                        /*Фильтруем параметры полей*/
                        $field = array_intersect_key($field, $fields_params_flip);

                        if ($fieldsInParams[$inName][$fName]['in_name'] != $fName) {
                            $tableData['fields'][$fieldsInParams[$inName][$fName]['in_name']] = $field;
                            $fieldsReplaces[$inName][$fName] = $fieldsInParams[$inName][$fName]['in_name'];
                        } else {
                            $tableData['fields'][$fName] = $field;
                        }
                    }
                }
            }
        }
        return [$tableReplaces, $fieldsReplaces, $schema];
    }

    protected function __prepareJson($fileName)
    {
        if (!$fileName || !is_file($file = File::getFile($fileName))) throw new errorException('Файл обновления не найден');
        if (($json = gzdecode(file_get_contents($file))) && ($json = json_decode($json, true))) {
            return $json;
        } else {
            throw new errorException('Структура файла не корректна');
        }
    }

    protected function funcUpdatePreparedDatas($params)
    {
        if ($params = $this->getParamsArray($params)) {
            if (!$array = $this->__prepareJson($params['summary']['file'] ?? null)) throw new errorException('Данные обновления не подгружены');

            $tables = $params['summary']['tables'];
            $rows = [];
            foreach ($array['Schemas'] as $tableName => $data) {
                if (!array_key_exists($tableName, $tables)) continue;
                if (!empty($data['data'])) {
                    $rows[] = [
                        'title' => $data['table']['title'],
                        'fields_select' => array_map(function ($k, $v) {
                            return ['value' => $v, 'title' => $k];
                        },
                            array_keys($data['data']['fields']),
                            $data['data']['fields']),
                        'fields' => array_keys($data['data']['fields']),
                        'ids' => $data['data']['ids']
                    ];
                }
            }
            return [
                "rows" => $rows,
                "title" => "Загрузка «" . ($array["Title"] ?? 'Безымянная выгрузка') . "» - Данные",
            ];
        }
    }

    protected function funcUpdatePreparedRoles($params)
    {
        if ($params = $this->getParamsArray($params)) {
            if (!$array = $this->__prepareJson($params['summary']['file'] ?? null)) throw new errorException('Данные обновления не подгружены');

            $summary = $params['summary'];

            /*Фильтруем схему, чтобы не выбирать лишние роли*/
            list($tableReplaces, $fieldsReplaces, $schema) = static::getFilteredSchema($array['Schema'],
                $summary['tables'],
                $summary['table_params'],
                $summary['fields'],
                $summary['field_params']);

            /*TODO проверяем настройки ролей в таблицах и полях*/
            $roles = $this->getRolesFromSchema($schema);

            $rows = [];
            $roles_select = [];
            foreach ($array['Roles'] as $id => $title) {
                if (!in_array($id, $roles)) continue;
                $rows[] = ['rol_v_zagruzke' => $id];
                $roles_select[] = ['title' => $title, 'value' => $id];
            }

            return [
                "rows" => $rows,
                "roles_select" => $roles_select,
                "title" => "Загрузка «" . ($array["Title"] ?? 'Безымянная выгрузка') . "» - Сопоставление ролей",
            ];
        }
    }

    protected function funcUpdatePreparedTables($params)
    {
        if ($params = $this->getParamsArray($params)) {
            if (!$array = $this->__prepareJson($params['summary']['file'] ?? null)) throw new errorException('Данные обновления не подгружены');
            $rows = [];
            $tablesParams = [];
            foreach ($array['Schema'] as $tableName => $data) {
                $rows[] = [
                    'checked' => true, 'title' => $data['table']['title'], 'tip_tablicy' => $data['table']['type'],
                    'polojenie_v_dereveciklah' => $data['table']['tree_node_id'], 'name' => $tableName,
                    'kategoriya' => $data['table']['category']
                ];
                if (empty($tablesParams)) {
                    $params = $data['table'];
                    unset($params['tree_node_id']);
                    unset($params['category']);
                    $tablesParams = array_keys($params);
                }
            }
            return ["rows" => $rows,
                "descr" => ($array["Description"] ?? ''),
                "title" => "Загрузка «" . ($array["Title"] ?? 'Безымянная выгрузка') . "» - Выбор таблиц"
                , "tables_params" => $tablesParams
            ];
        }
        throw new errorException('Ошибка параметров функции');
    }

    protected function funcUpdatesReadFileStructure($params)
    {
        $params = $this->getParamsArray($params);

        if (empty($params['file']) || !is_file($filename = File::getFile($params['file']))) {
            throw new errorException('Файл не найден ' . $params['file']);
        }
        if (!($filedata = gzdecode(file_get_contents($filename)))) {
            throw new errorException('Файл не корректного формата');
        }

        $getTables = function ($tables) {
            $tablesOut = [];
            foreach ($tables as $name => $data) {
                $fields = $data['base']['fields'] ?? array_combine(array_keys($data['fields']),
                        array_keys($data['fields']));
                $fieldsOut = [];
                array_walk($fields,
                    function ($v, $k) use (&$fieldsOut) {
                        array_push($fieldsOut, ['name' => $k, 'in_name' => $v]);
                    });

                $table = [
                    'name' => $data['base']['name'] ?? $name,
                    'in_name' => $name,
                    'table_params' => array_keys($data['table']),
                    'type' => $data['table']['type'],
                    'tree_node_id' => $data['table']['tree_node_id'],
                    'category' => $data['table']['category'],
                    'fields_data' => $fieldsOut,
                    'with_data' => !empty($data['data']),
                    'key_field' => $data['base']['key'] ?? null
                ];

                $table['data_fields'] = $data['base']['data_fields'] ?? [];

                if (!empty($data['data']['rows']) || $this->aTable->getTableRow()['name'] !== 'zagruzka') {
                    if ($this->aTable->getTableRow()['name'] === 'zagruzka') {
                        $table['rows_data'] = count($data['data']['rows']) . ' ' . Formats::numbersRusPadegRod(count($data['data']['rows']),
                                ['строка', 'строк', 'строки']);
                    } else {
                        $table['rows_data'] = $data['base']['rows'] ?? '1-';
                    }
                }
                $table['data_fields'] = array_unique($data['data_fields']);
                $table['data_fields_base'] = $data['base']['data_fields'];
                if ($table['key_field'] === 'id')
                    $table['data_fields_base']['id'] = 'id';

                $tablesOut[] = $table;
            }
            return $tablesOut;
        };
        $filedata = json_decode($filedata, true);
        return [
            'title' => $filedata['Title'] ?? 'Новая выгрузка',
            'description' => $filedata['Description'] ?? '',
            'code' => $filedata['code'] ?? '=:',
            'tables' => $getTables($filedata['Schema'] ?? $filedata['Tables'] ?? []),
            'roles' => $filedata['Roles'],
        ];


    }

    protected function funcUpdatesCreateFile($params)
    {
        if ($params = $this->getParamsArray($params)) {

            $data = $params['data'];
            $code = $data['code'];
            $title = $data['h_nazvanie_vygruzki'];
            $json = [
                'Title' => $title,
                'Description' => $data['h_opisanie_vygruzki'],
                'Schema' => [],
                'code' => $code,
            ];


            $tablesReplaces = [];
            $fieldReplaces = [];
            $totumFields = [];
            $Schema = &$json['Schema'];

            /*Список полей в таблице Полей*/
            $fieldsFields = implode(',',
                array_diff(array_keys(Model::init('tables_fields')->get([])),
                    ['is_del', 'id', 'data', 'table_name', 'table_id', 'version']));


            foreach ($data['tables'] as $table) {

                if ($table['name'] != $table['in_name']) {
                    $tablesReplaces[$table['name']] = $table['in_name'];
                }
                foreach ($table['fields_data'] as $fRow) {
                    if ($fRow['name'] != $fRow['in_name']) {
                        $fieldReplaces[$table['in_name']][$fRow['name']] = $fRow['in_name'];
                    }
                }


                $tableJson = &$Schema[$table['name']];
                $tableJson['base'] = [];

                $fieldsJson = &$tableJson['fields'];

                $tableFields = &$tableJson['table'];
                $tableRow = Table::getTableRowByName($table['name']);


                $table_id = $tableRow['id'];
                $version = $table['version'];
                if ($tableRow['type'] == "calcs" && !$version) {
                    throw new errorException('Не выбрана версия расчетной таблицы в цикле');
                }

                foreach ($tableRow as $k => $val) {
                    if (in_array($k, $table['table_params'])) {
                        $tableFields[$k] = $val;
                        if ($k === 'tree_node_id' && $tableRow['type'] == 'calcs') {
                            $tableFields[$k] = Model::init('tables')->getField('name', ['id' => $tableFields[$k]]);
                        }
                    }
                }
                $tableJson['base']['fields'] = [];

                $fieldNames = array_map(function ($v) {
                    return $v['name'];
                },
                    $table['fields_data']);

                $fRows = Model::init('tables_fields')->getAll(['table_id' => $table_id, 'name' => $fieldNames, 'version' => $version],
                    $fieldsFields);

                foreach ($fRows as $fInRow) {

                    $fInRow['data_src'] = json_decode($fInRow['data_src'], true);

                    if ($fInRow['data_src']['type']["Val"] === 'text' && $fInRow['data_src']['textType']["Val"] === 'totum') {
                        $totumFields[$table['name']][$fInRow['name']] = $fInRow['category'];
                    }
                    $fieldsJson[$fInRow['name']] = $fInRow;
                    $tableJson['base']['fields'][$fInRow['name']] = $fieldReplaces[$table['in_name']][$fInRow['name']] ?? $fInRow['name'];
                    unset($fieldsJson[$fInRow['name']]['name']);
                }
            }

            $addTableData = function (aTable $donorTable, $settingsRow, &$tableJsonData, &$tableJsonBase) use ($fieldReplaces, $data, &$addTableData, &$Schema) {

                $tableJsonData = ['params' => []];
                $TableFields = $donorTable->getFields();
                /*Выгружаем данные параметров*/
                foreach ($TableFields as $f) {
                    if ($f['category'] !== 'column' && in_array($f['name'], $settingsRow['data_fields'])) {
                        $fName = $f['name'];
                        /* Выгружаем только нерасчетные                          или фиксированные */
                        if (empty($f['code']) || !empty($f['codeOnlyInAdd']) || !empty($donorTable->getTbl()['params'][$f['name']]['h'])) {
                            if ($f['type'] === 'file') {
                                $tableJsonData['params'][$fName] = File::getDataForUpdates($donorTable->getTbl()['params'][$f['name']]['v']);
                            } else {
                                $tableJsonData['params'][$fName] = $donorTable->getTbl()['params'][$f['name']]['v'];
                            }
                        }
                    }
                }

                if (!empty($settingsRow['rows_data'])) {
                    $tableJsonBase['key'] = $settingsRow['key_field'];
                    $tableJsonBase['rows'] = $settingsRow['rows_data'];
                    $tableJsonBase['data_fields'] = [];

                    foreach ($settingsRow['data_fields'] as $dFName) {
                        $tableJsonBase['data_fields'][$dFName] = $TableFields[$dFName]['title'];
                    }
                    $ids = [];
                    $splits = explode(',', str_replace(' ', '', $settingsRow['rows_data']));
                    foreach ($splits as $split) {
                        if (ctype_digit($split)) $ids[] = $split;
                        elseif (preg_match('/^(\d+)\-(\d*)$/', $split, $matches)) {
                            if (!($maxId = $matches[2])) {
                                $maxId = $donorTable->getByParams(['order' => [['field' => 'id', 'ad' => 'desc']], 'field' => 'id'],
                                    'field');
                            }
                            if ($matches[1] <= $maxId) {
                                for ($i = (int)$matches[1]; $i <= $maxId; $i++) {
                                    $ids[] = $i;
                                }
                            }
                        }
                    }
                    if ($ids) {

                        ((function ($ids) {
                            $this->loadRowsByIds($ids);
                        })->bindTo($donorTable, $donorTable))($ids);

                        $tableJsonData['rows'] = [];

                        if ($donorTable->getTableRow()['type'] === 'cycles') {
                            $_caclsTables = array_keys(Table::init()->getAllIndexedByField(['tree_node_id' => $donorTable->getTableRow()['id'], 'type' => 'calcs'],
                                'name',
                                'name'));
                            $caclsTables = [];
                            foreach ($data['tables'] as $table) {
                                if (in_array($table['name'], $_caclsTables)) {
                                    $caclsTables[$table['name']] = $table;
                                }
                            }
                        }
                        foreach ($donorTable->getTbl()['rows'] as $row) {
                            if (in_array($row['id'], $ids)) {

                                $key = $settingsRow['key_field'] == 'id' ? $row['id'] : $row[$settingsRow['key_field']]['v'];
                                $tableJsonData['rows'][$key] = [];

                                foreach ($donorTable->getFields() as $f) {
                                    if ($f['category'] === 'column' && in_array($f['name'],
                                            $settingsRow['data_fields'])) {

                                        $fName = $f['name'];

                                        /* Выгружаем только нерасчетные                          или фиксированные */
                                        if (empty($f['code']) || !empty($f['codeOnlyInAdd']) || !empty($row[$f['name']]['h'])) {
                                            $tableJsonData['rows'][$key][$fName] = $row[$f['name']]['v'];
                                        }
                                    }
                                }
                                if ($donorTable->getTableRow()['type'] === 'cycles') {
                                    $tableJsonData['rows'][$key]['__calcs'] = [];
                                    $Cycle = Cycle::init($row['id'], $donorTable->getTableRow()['id']);
                                    foreach ($caclsTables as $caclsTableRow) {
                                        if ($caclsTableRow['with_data']) {
                                            $tableJsonData['rows'][$key]['__calcs'][$caclsTableRow['name']] = $Cycle->getTable(Table::getTableRowByName($caclsTableRow['name']))->getTbl();
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

            };

            foreach ($data['tables'] as $table) {
                if ($table['with_data']) {
                    $row = Table::getTableRowByName($table['name']);
                    if ($row['type'] != "calcs") {
                        $Table = tableTypes::getTable($row);
                        $tableJson = &$Schema[$table['name']];
                        $addTableData($Table, $table, $tableJson['data'], $tableJson['base']);
                    }
                }
            }

            $roles = $this->getRolesFromSchema($Schema);
            if ($roles) {
                $json['Roles'] = Model::init('roles')->getFieldIndexedByField(['id' => $roles], 'id', 'title');
            } else $json['Roles'] = [];

            if (!empty($tablesReplaces) || !empty($fieldReplaces)) {
                $Schema = $this->changeNamesInSchema($Schema, $tablesReplaces, $fieldReplaces, $totumFields);
            }
            $filestring = gzencode(json_encode($json, JSON_UNESCAPED_UNICODE));
            tableTypes::getTableByName('updates')->reCalculateFromOvers(['add' => [
                ['nazvanie' => $title,
                    'tip' => 'out',
                    'fayl' => [['name' => static::translit($title) . '.json.gz', 'filestring' => $filestring]],
                    'teh' => '',
                    'opisanie' => $data['description'] ?? ''
                ]
            ]]);


        }
    }

    /**
     * @param $schema
     * @return array [1, 2, 4]
     */
    protected function getRolesFromSchema($schema)
    {
        $roles = [];
        foreach ($schema as $tableName => $data) {
            foreach ($data['table'] as $k => $v) {
                if (in_array($k, static::rolesTablesParams)) {
                    $roles += array_flip($v);
                }
            }
            foreach ($data['fields'] as $fieldParams) {
                foreach ($fieldParams['data_src'] as $k => $v) {
                    if (in_array($k, static::rolesFieldsSrcParams)) {
                        $roles += array_flip($v['Val']);
                    }
                }
            }
            foreach ($data['data']['params'] as $fName => $val) {
                if ($data['fields'][$fName]['data_src']['type']['Val'] === 'text' && $data['fields'][$fName]['data_src']['textType']['Val'] === 'totum') {
                    if (preg_match_all('/UserInRoles\(([^)]+)\)/i', $val, $matches)) {
                        foreach ($matches[1] as $match) {
                            if (preg_match_all('/role:\s"?(\d+)"?/', $match, $_roles)) {
                                foreach ($_roles[1] as $role) {
                                    $roles[$role] = 1;
                                }
                            }
                        }
                    }
                }
            }
        }
        return array_keys($roles);
    }

    protected function funcUpdatesApply($params)
    {
        $zagruzkaTbl = $this->aTable->getTableRow()['name'] === 'zagruzka' ? $this->aTable->getTbl() : [];

        if ($zagruzkaTbl) {
            $file = $zagruzkaTbl['params']['sozdat_na_osnove_fayla']['v'];
        } else {
            $params = $this->getParamsArray($params);
            $file = $params['file'][0]['file'];
        }

        if (empty($file) || !is_file($filename = File::getFile($file))) {
            throw new errorException('Файл не найден ');
        }
        if (!($filedata = gzdecode(file_get_contents($filename))) || !($filedata = json_decode($filedata, true))) {
            throw new errorException('Файл не корректного формата');
        }


        $tableTables = tableTypes::getTableByName('tables');
        $tableFieldsModel = tableTypes::getTableByName('tables_fields');
        $tablesIdAndType = Model::init('tables')->getAllIndexedByField([], 'name, id, type', 'name');

        $fieldsModify = [];
        $fieldsAdd = [];

        $description = '';

        $tableIdTableName = [];


        if ($zagruzkaTbl) {

            $tablesReplaces = [];
            $tablesFieldsReplaces = [];
            $totumFields = [];
            $rolesReplaces = [];

            if ($zagruzkaTbl) {
                foreach ($zagruzkaTbl['params']['in_roles']['v'] as $r => $rr) {
                    if ($r != $rr) {
                        $rolesReplaces[$r] = $rr;
                    }
                }
            }

            $Schema = [];
            $withDataTables = [];
            foreach ($zagruzkaTbl['rows'] as $row) {
                if ($row['with_data']) {
                    $withDataTables[] = $row['name'];
                }
            }
            foreach ($zagruzkaTbl['rows'] as $tableZagruzkaRow) {
                $tableZagruzkaRow = Model::getClearValuesRow($tableZagruzkaRow);
                if ($tableZagruzkaRow['in_name'] != $tableZagruzkaRow['name']) {
                    $tablesReplaces[$tableZagruzkaRow['name']] = $tableZagruzkaRow['in_name'];
                }
                $Schema[$tableZagruzkaRow['name']] = [
                    'base' => $filedata['Schema'][$tableZagruzkaRow['name']]['base'],
                    'fields' => [],
                    'table' => [],
                    'data' => []
                ];


                $tableZagruzkaRow['fieldsList'] = [];

                foreach ($tableZagruzkaRow['fields_data'] as $v) {
                    if ($v['name'] != $v['in_name']) {
                        $tablesFieldsReplaces[$tableZagruzkaRow['in_name']][$v['name']] = $v['in_name'];
                    }
                    $Schema[$tableZagruzkaRow['name']]['fields'][$v['name']] = $filedata['Schema'][$tableZagruzkaRow['name']]['fields'][$v['name']];
                    $field = $filedata['Schema'][$tableZagruzkaRow['name']]['fields'][$v['name']];
                    if ($field['data_src']['type']['Val'] == 'text' && $field['data_src']['textType']['Val'] == 'totum') {
                        $totumFields[$field['name']] = $field['category'];
                    }
                }

                foreach ($tableZagruzkaRow['table_params'] as $fName) {
                    if ($tableZagruzkaRow['ne_nastraivat_roli'] && in_array($fName,
                            static::rolesTablesParams)) continue;
                    $Schema[$tableZagruzkaRow['name']]['table'][$fName] = $filedata['Schema'][$tableZagruzkaRow['name']]['table'][$fName];
                }

                $Schema[$tableZagruzkaRow['name']]['table']['tree_node_id'] = $tableZagruzkaRow['tree_node_id'];
                $Schema[$tableZagruzkaRow['name']]['table']['category'] = $tableZagruzkaRow['category'];

                if ($tableZagruzkaRow['with_data']) {
                    foreach ($filedata['Schema'][$tableZagruzkaRow['name']]['data']['params'] ?? [] as $fName => $val) {
                        if (in_array($fName, $tableZagruzkaRow['data_fields'])) {
                            $Schema[$tableZagruzkaRow['name']]['data']['params'][$fName] = $val;
                        }
                    }
                    if ($tableZagruzkaRow['rows_data_checkbox']) {

                        $Schema[$tableZagruzkaRow['name']]['settings']['onlyInsert'] = $tableZagruzkaRow['onlyInsert'];

                        foreach ($filedata['Schema'][$tableZagruzkaRow['name']]['data']['rows'] ?? [] as $key => $row) {
                            $Schema[$tableZagruzkaRow['name']]['data']['rows'][$key] = [];
                            foreach ($row as $fName => $val) {
                                if ($fName === '__calcs') {
                                    foreach ($val as $caclName => $tbl) {
                                        if (in_array($caclName, $withDataTables)) {
                                            $Schema[$tableZagruzkaRow['name']]['data']['rows'][$key]['__calcs'][$caclName] = $tbl;
                                        }
                                    }
                                } else {
                                    if (in_array($fName, $tableZagruzkaRow['data_fields'])) {
                                        $Schema[$tableZagruzkaRow['name']]['data']['rows'][$key][$fName] = $val;
                                    }
                                }
                            }
                        }
                    }
                }

            }
            $Schema = $this->changeNamesInSchema($Schema,
                $tablesReplaces,
                $tablesFieldsReplaces,
                $totumFields,
                $rolesReplaces);
        } else {
            $Schema = $filedata['Schema'];
        }


        $processTable = function ($TableName, $data) use ($tablesIdAndType, &$tableTables, &$fieldsModify, &$fieldsAdd, &$tableIdTableName) {

            $dataTable = $data['table'];
            //Обновить/создать таблицу в схеме
            if ($table_id = ($tablesIdAndType[$TableName]['id'] ?? null)) {
                if ($data['table']) {
                    unset($dataTable['type']);
                    $tableTables->reCalculateFromOvers(['modify' => [$table_id => $dataTable]]);
                }
            } else {
                $tableRow = $data['table'];

                if (!$tableRow['type'])
                    throw new errorException('Невозможно создать таблицу из этой выгрузки - она только для обновления');

                $tableRow['name'] = $TableName;

                $tableTables->reCalculateFromOvers(['add' => [$tableRow]]);
                $table_id = Model::init('tables')->getField('id', ['name' => $TableName]);
            }
            $tableRow = Table::getTableRowById($table_id);

            $tableIdTableName[$table_id] = $TableName;

            if ($tableRow['type'] == 'calcs') {
                $versionsTable=tableTypes::getTableByName('calcstable_versions');
                $addedId=$versionsTable->actionInsert(['table_name'=>$tableRow['name']])[0];
                $version=$versionsTable->getByParams(['field' => 'version', 'where' => [['field' => 'id', 'operator' => '=', 'value' => $addedId]]],
                    'field');

                foreach ($data['fields'] as $fInName => $fieldData_in_file) {
                        $fieldRow = $fieldData_in_file;
                        $fieldRow['table_id'] = $table_id;
                        $fieldRow['name'] = $fInName;
                        $fieldRow['version'] = $version;
                        $fieldsAdd[] = $fieldRow;
                }

            } else {
                //Обновить/создать поля в схеме
                $tableFields = [];
                foreach (Model::init('tables_fields')->getAll(
                    ['table_id' => $table_id],
                    'id, name') as $_f) {
                    $tableFields[$_f['name']] = $_f;
                }
                foreach ($data['fields'] as $fInName => $fieldData_in_file) {
                    if ($fieldInSchema = ($tableFields[$fInName] ?? null)) {
                        $fieldsModify[$fieldInSchema['id']] = $fieldData_in_file;
                    } else {
                        $fieldRow = $fieldData_in_file;
                        $fieldRow['table_id'] = $table_id;
                        $fieldRow['name'] = $fInName;
                        $fieldsAdd[] = $fieldRow;
                    }
                }
            }
        };


        $calcsTables = [];
        foreach ($Schema as $tName => $data) {
            if ("calcs" === ($data['table']['type'] ?? $tablesIdAndType[$tName]['type'] ?? null))
                $calcsTables[$tName] = $data;
            else {
                $processTable($tName, $data);
            }
        }

        /* Замена name на table_id в tree_node_id в расчетных таблицах в циклах */
        $TableNameTableId = array_flip($tableIdTableName);
        foreach ($calcsTables as $tName => $data) {
            if (array_key_exists("tree_node_id", $data['table']))
                $data['table']["tree_node_id"] = $TableNameTableId[$tablesReplaces[$data['table']["tree_node_id"]] ?? $data['table']["tree_node_id"]];
            $processTable($tName, $data);
        }

        $tableFieldsModel->reCalculateFromOvers(['modify' => $fieldsModify, 'add' => $fieldsAdd]);


        $loadTableData = function ($name, $fileTableData) {
            if (empty($fileTableData['data'])) return;


            $tableRow = Table::getTableRowByName($name);

            if ($tableRow['type'] === 'tmp') return;
            if ($tableRow['type'] === 'calcs') return;

            $Table = tableTypes::getTable($tableRow);

            $inData = ['modify' => ['params' => []], 'add' => []];
            foreach ($fileTableData['data']['params'] ?? [] as $fName => $v) {
                $inData['modify']['params'][$fName] = $v;
            }

            if (!empty($fileTableData['data']['rows'])) {
                $keyField = $fileTableData['base']['key'];
                $fields = [$keyField];
                if ($keyField !== 'id') $fields[] = 'id';

                $_inTableRows = $Table->getByParams([
                    'where' => array([
                        'field' => $keyField,
                        'operator' => '=',
                        'value' => array_keys($fileTableData['data']['rows'])]),
                    'field' => $fields],
                    'rows');
                $inTableRows = [];

                foreach ($_inTableRows as $_r) {
                    $inTableRows[$_r[$keyField]] = $_r['id'];
                }

                foreach ($fileTableData['data']['rows'] as $key => $datarow) {

                    unset($datarow['__calcs']);

                    if (!array_key_exists($key, $inTableRows)) {
                        $addRow = [];
                        foreach ($datarow as $fName => $v) {
                            $addRow[$fName] = $v;
                        }

                        if ($keyField === 'id') {
                            $addRow['id'] = $key;
                            $inData['addWithId'] = true;
                        }
                        $inData['add'][] = $addRow;
                    } elseif (empty($fileTableData['settings']['onlyInsert'])) {

                        foreach ($datarow as $fName => $v) {
                            $inData['modify'][$inTableRows[$key]][$fName] = $v;
                        }
                    }
                }
            };
            $Table->reCalculateFromOvers($inData);

            if ($Table->getTableRow()['type'] === 'cycles') {
                $_inTableRows = $Table->getByParams([
                    'where' => array([
                        'field' => $keyField,
                        'operator' => '=',
                        'value' => array_keys($fileTableData['data']['rows'])]),
                    'field' => $fields],
                    'rows');
                $inTableRows = [];

                foreach ($_inTableRows as $_r) {
                    $inTableRows[$_r[$keyField]] = $_r['id'];
                }

                $caclsTables = array_keys(Table::init()->getAllIndexedByField(['tree_node_id' => $Table->getTableRow()['id'], 'type' => 'calcs'],
                    'name, id',
                    'name'));

                foreach ($fileTableData['data']['rows'] as $key => $datarow) {
                    if (!empty($datarow['__calcs']) && !empty($inTableRows[$key])) {
                        $Cycle = Cycle::init($inTableRows[$key], $Table->getTableRow()['id']);
                        $Cycle->saveTables(true);

                        foreach ($datarow['__calcs'] as $nameCalcsTable => $data) {
                            if (in_array($nameCalcsTable, $caclsTables)) {
                                $calcsRow = Table::getTableRowByName($nameCalcsTable);
                                $CalcsTable = $Cycle->getTable($calcsRow);
                                $CalcsTable->setDuplicatedTbl($data);
                            }
                        }
                        $Cycle->saveTables(true);
                    }
                }
            }

        };


        /*Загрузка данных*/
        foreach ($Schema as $tableName => $data) {
            $loadTableData($tableName, $data);
        }

        /* код */
        if (!empty($code = $zagruzkaTbl['params']['code']['v'] ?? $filedata['code']) && trim($code) != '=:') {

            $CA = new CalculateActionUpdates($code);
            $CA->exec(['name' => 'without_field'], [], [], [], [], [], $tableTables);
            if ($CA->error) {
                throw new errorException('При выполнении кода после обновления произошла ошибка: >>>>' . $CA->error . '>>>>. Изменения не произведены');
                die;
            }

        }

        /* Формирование описания изменений */

        if ($addedTables = tableTypes::getTableByName('tables')->getChangeIds()['added']) {
            $description .= "\n Добавлены таблицы:";
            foreach (Model::init('tables')->getAll(['id' => array_keys($addedTables)], 'name') as $_row) {
                $description .= ' ' . $_row['name'] . ',';
            }
            $description = substr($description, 0, -1);
        }
        if ($modify = tableTypes::getTableByName('tables')->getChangeIds()['changed']) {
            if ($ClearModify = array_diff_key($modify, $addedTables)) {

                if ($ClearModify) {
                    $__names = '';
                    foreach (Model::init('tables')->getAll(['id' => array_keys($ClearModify)],
                        'name') as $_row) {
                        $__names .= ' ' . $_row['name'] . ',';
                    }
                    if ($__names) {
                        $description .= "\n Изменено описание таблиц:" . $__names;
                        $description = substr($description, 0, -1);
                    }
                    unset($__names);
                }
            }
        }

        if ($added = tableTypes::getTableByName('tables_fields')->getChangeIds()['added']) {
            $where = ['id' => array_keys($added)];
            if ($addedTables) {
                $where[] = '(table_id->>\'v\')::int NOT IN (' . implode(',',
                        array_keys($addedTables)) . ')';
            }

            if ($rows = Model::init('tables_fields')->getAll($where, 'table_id, name')) {

                $description .= "\n Добавлены поля:";

                foreach ($rows as $tableZagruzkaRow) {
                    $description .= ' ' . $tableZagruzkaRow['name'] . '(т.' . $tableIdTableName[$tableZagruzkaRow['table_id']] . '),';
                }
                $description = substr($description, 0, -1);
            }
        }
        if ($changed = tableTypes::getTableByName('tables_fields')->getChangeIds()['changed']) {
            if ($ClearModify = array_diff_key($changed, $added)) {
                $description .= "\n Изменены поля таблиц:";
                $_tables = [];
                foreach (Model::init('tables_fields')->getAll(['id' => array_keys($ClearModify)],
                    'table_id, name') as $tableZagruzkaRow) {
                    $_tables[$tableIdTableName[$tableZagruzkaRow['table_id']]][] = $tableZagruzkaRow['name'];
                }
                foreach ($_tables as $tTitle => $fields) {
                    $description .= "\n" . $tTitle . '(' . implode(',', $fields) . ')';
                }
            }
        }

        if ($deleted = tableTypes::getTableByName('tables_fields')->getChangeIds()['deleted']) {
            $description .= "\n Удалены поля c id: " . implode(', ', array_keys($deleted));
        }
        if ($deleted = tableTypes::getTableByName('tables')->getChangeIds()['deleted']) {
            $description .= "\n Удалены таблицы c id: " . implode(', ', array_keys($deleted));
        }

        $description = substr($description, 1);

        if (!$description) $description = 'Изменений не произведено';

        if ($tRowUpdates = Table::getTableRowByName('updates', true)) {
            $tableUpdates = tableTypes::getTableByName('updates');

            $tableUpdates->initFields(true);

            $tableUpdates->reCalculateFromOvers(['add' => [
                    [
                        'nazvanie' => $zagruzkaTbl['params']['h_nazvanie_vygruzki']['v'] ?? 'Без названия',

                        'tip' => 'in',
                        'fayl' => [['file' => $file, 'name' => $file]],
                        'teh' => $description,
                        'opisanie' => $InSchema['Description'] ?? 'Без описания',
                    ]
                ]]
            );
        }

    }


    /**
     * @param $params
     * @throws errorException
     */
    protected function funcUpdatePrepared($params)
    {
        $tablesModels = Model::init('tables')->getAllIndexedByField([], 'name, id, type', 'name');

        if ($params = $this->getParamsArray($params)) {
            if (!empty($params['file'])) {
                if (!$InSchema = $this->__prepareJson($params['file'][0]['file'] ?? null))
                    throw new errorException('Данные обновления не подгружены');

                $code = $InSchema['code'];
                $schema = $InSchema['Schema'];
            } else {

                $summary = $params['summary'];
                if (!$InSchema = $this->__prepareJson($summary['file'] ?? null))
                    throw new errorException('Данные обновления не подгружены');

                $code = $summary['code'];

                list($tableReplaces, $fieldsReplaces, $schema) = static::getFilteredSchema($InSchema['Schema'],
                    $summary['tables'],
                    $summary['table_params'],
                    $summary['fields'],
                    $summary['field_params']);

                if ($tableReplaces || $fieldsReplaces) {
                    $schema = $this->changeNamesInSchema($schema, $tableReplaces, $fieldsReplaces);
                }
                $roles = $summary['roles'];
                $rolesReplace = [];
                foreach ($roles as $roleRow) {
                    if ($roleRow['rol_v_sheme'] != (array)$roleRow['rol_v_zagruzke']) {
                        $rolesReplace[$roleRow['rol_v_zagruzke']] = $roleRow['rol_v_sheme'];
                    }
                }
                if ($rolesReplace) {
                    $schema = $this->changeRolesInSchema($schema, $rolesReplace);
                }

            }

            $tableTables = tableTypes::getTableByName('tables');
            $tableFieldsModel = tableTypes::getTableByName('tables_fields');
            $fieldsModify = [];
            $fieldsAdd = [];

            $description = '';

            $tableIdTableName = [];


            $processTable = function ($TableInName, $data) use ($tablesModels, &$tableTables, &$fieldsModify, &$fieldsAdd, &$tableIdTableName) {


                //Обновить/создать таблицу в схеме
                if ($table_id = ($tablesModels[$TableInName]['id'] ?? null)) {

                    if ($data['table']) {

                        $dataTable = array_diff_key($data['table'], array_flip(static::rolesTablesParams));
                        unset($dataTable['category']);
                        unset($dataTable['tree_node_id']);
                        unset($dataTable['type']);
                        $tableTables->reCalculateFromOvers(['modify' => [$table_id => $dataTable]]);
                    }
                } else {
                    $tableRow = $data['table'];
                    if (!$tableRow['type'])
                        throw new errorException('Невозможно создать таблицу из этой выгрузки - она только для обновления');

                    $tableRow['name'] = $TableInName;

                    $tableTables->reCalculateFromOvers(['add' => [$tableRow]]);


                    $table_id = Model::init('tables')->getField('id', ['name' => $TableInName]);
                }
                $tableIdTableName[$table_id] = $TableInName;

                //Обновить/создать поля в схеме
                $tableFields = [];
                foreach (Model::init('tables_fields')->getAll(['table_id' => $table_id],
                    '*') as $_f) {
                    foreach ($_f as $k => &$v) {
                        if (!Model::isServiceField($k)) $v = json_decode($v, true)['v'];
                    }
                    unset($v);
                    $tableFields[$_f['name']] = $_f;
                }

                foreach ($data['fields'] as $fieldInName => $fieldData_in_file) {
                    if ($fieldInSchema = ($tableFields[$fieldInName] ?? null)) {

                        $fieldsModify[$fieldInSchema['id']] = $fieldData_in_file;

                    } else {
                        $fieldRow = $fieldData_in_file;
                        $fieldRow['table_id'] = $table_id;
                        $fieldRow['name'] = $fieldInName;
                        $fieldsAdd[] = $fieldRow;
                    }
                }
            };

            $calcsTables = [];
            foreach ($schema as $TableInName => $data) {
                if ("calcs" === ($data['table']['type'] ?? $tablesModels[$TableInName]['type'] ?? null))
                    $calcsTables[$TableInName] = $data;
                else {

                    $processTable($TableInName, $data);
                }
            }


            /* Замена name на table_id в tree_node_id в расчетных таблицах в циклах */
            $TableNameTableId = array_flip($tableIdTableName);
            foreach ($calcsTables as $TableInName => $data) {
                if (array_key_exists("tree_node_id", $data['table']))
                    $data['table']["tree_node_id"] = $TableNameTableId[$data['table']["tree_node_id"]];
                $processTable($TableInName, $data);
            }


            $tableFieldsModel->reCalculateFromOvers(['modify' => $fieldsModify, 'add' => $fieldsAdd]);


            /*Загрузка данных*/
            foreach ($schema as $TableInName => $data) {

                if (!empty($data['data'])) {

                    $Table = tableTypes::getTableByName($TableInName, true);
                    $funcAddVal = function ($fName, $fVal, &$array) {
                        $array[$fName] = $fVal;
                    };

                    $inData = ['modify' => ['params' => []], 'add' => []];
                    foreach ($data['data']['params'] as $fName => $v) {
                        $funcAddVal($fName, $v, $inData['modify']['params']);
                    }
                    foreach ($data['data']['rows'] ?? [] as $datarow) {
                        $addRow = [];
                        foreach ($datarow as $fName => $v) {
                            $funcAddVal($fName, $v, $addRow);
                        }
                        if ($addRow) {
                            $inData['add'][] = $addRow;
                        }
                    }
                    $Table->reCalculateFromOvers($inData);
                }

            }


            if (!empty($code) && trim($code) != '=:') {

                $CA = new CalculateActionUpdates($code);
                $CA->exec(['name' => 'without_field'], [], [], [], [], [], $tableTables);
                if ($CA->error) {
                    throw new errorException('При выполнении кода после обновления произошла ошибка: >>>>' . $CA->error . '>>>>. Изменения не произведены');
                    die;
                }

            }

            if ($addedTables = tableTypes::getTableByName('tables')->getChangeIds()['added']) {
                $description .= "\n Добавлены таблицы:";
                foreach (Model::init('tables')->getAll(['id' => array_keys($addedTables)], 'name') as $_row) {
                    $description .= ' ' . $_row['name'] . ',';
                }
                $description = substr($description, 0, -1);
            }
            if ($modify = tableTypes::getTableByName('tables')->getChangeIds()['changed']) {
                if ($ClearModify = array_diff_key($modify, $addedTables)) {

                    /* $ClearModifyFromRolesAfterRecalcs = array_filter($ClearModify,
                         function ($valArray) {
                             if (array_diff_key($valArray,
                                 array_flip(["edit_roles", "read_roles", "tree_off_roles"]))) return true;
                         });*/

                    if ($ClearModify) {
                        $description .= "\n Изменено описание таблиц:";
                        foreach (Model::init('tables')->getAll(['id' => array_keys($ClearModifyFromRolesAfterRecalcs)],
                            'name') as $_row) {
                            $description .= ' ' . $_row['name'] . ',';
                        }
                        $description = substr($description, 0, -1);
                    }
                }
            }

            if ($added = tableTypes::getTableByName('tables_fields')->getChangeIds()['added']) {
                $where = ['id' => array_keys($added)];
                if ($addedTables) {
                    $where[] = '(table_id->>\'v\')::int NOT IN (' . implode(',',
                            array_keys($addedTables)) . ')';
                }

                if ($rows = Model::init('tables_fields')->getAll($where, 'table_id, name')) {

                    $description .= "\n Добавлены поля:";

                    foreach ($rows as $row) {
                        $description .= ' ' . $row['name'] . '(т.' . $tableIdTableName[$row['table_id']] . '),';
                    }
                    $description = substr($description, 0, -1);
                }
            }
            if ($changed = tableTypes::getTableByName('tables_fields')->getChangeIds()['changed']) {
                if ($ClearModify = array_diff_key($changed, $added)) {
                    $description .= "\n Изменены поля:";
                    foreach (Model::init('tables_fields')->getAll(['id' => array_keys($ClearModify)],
                        'table_id, name') as $row) {
                        $description .= ' ' . $row['name'] . '(т.' . $tableIdTableName[$row['table_id']] . '),';
                    }
                    $description = substr($description, 0, -1);
                }
            }

            if ($deleted = tableTypes::getTableByName('tables_fields')->getChangeIds()['deleted']) {
                $description .= "\n Удалены поля c id: " . implode(', ', array_keys($deleted));
            }
            if ($deleted = tableTypes::getTableByName('tables')->getChangeIds()['deleted']) {
                $description .= "\n Удалены таблицы c id: " . implode(', ', array_keys($deleted));
            }

            $description = substr($description, 1);

            if (!$description) $description = 'Изменений не произведено';

            if ($tRowUpdates = Table::getTableRowByName('updates', true)) {
                $tableUpdates = tableTypes::getTableByName('updates');

                $tableUpdates->initFields(true);

                $tableUpdates->reCalculateFromOvers(['add' => [
                        [
                            'nazvanie' => $InSchema['Title'] ?? 'Без названия',

                            'tip' => 'in',
                            'fayl' => (is_array($params['file']) ? $params['file'] : [['name' => $summary['filename'], 'file' => $summary['file']]]),
                            'teh' => $description,
                            'opisanie' => $InSchema['Description'] ?? 'Без описания',
                        ]
                    ]]
                );
            }

        }
    }

    protected function changeNames($code, $tablesNames, $fieldsNames, $nowTableName)
    {
        $codeReplased = preg_replace_callback('/\(.*?table:\s*(\'(.*?)\'|\$\#ntn|\$\#nti).*?\)/',
            function ($matches) use ($tablesNames, $fieldsNames, $nowTableName) {
                $resultCode = $matches[0];

                if ($matches[1] == '$#ntn' || $matches[1] == '$#nti') {
                    $newTableName = $nowTableName;
                } elseif ($newTableName = ($tablesNames[$tableOldName = $matches[2]] ?? null)) {
                    $resultCode = preg_replace('/table:\s\'' . $tableOldName . '\'/',
                        'table: \'' . $tablesNames[$tableOldName] . '\'',
                        $resultCode);
                } else {
                    $newTableName = $tableOldName;
                }

                if ($fieldReplacesInTable = ($fieldsNames[$newTableName] ?? null)) {

                    $resultCode = preg_replace_callback('/(tfield|sfield|field|bfield|where|order|preview|parent|section|filter):\s*\'([a-z][a-z0-9]{1,40})\'/',
                        function ($matches) use ($fieldReplacesInTable) {
                            return $matches[1] . ': \'' . ($fieldReplacesInTable[$matches[2]] ?? $matches[2]) . '\'';
                        },
                        $resultCode);

                }
                return $resultCode;
            },
            $code);

        $codeReplased = preg_replace_callback('/@([a-z0-9_]{3,})\.([a-z0-9_]{2,})/'
            ,
            function ($matches) use ($tablesNames, $fieldsNames, $nowTableName) {

                $tableName = $tablesNames[$matches[1]] ?? $matches[1];
                $fieldname = $fieldsNames[$tableName][$matches[2]] ?? $matches[2];

                return '@' . $tableName . '.' . $fieldname;
            },
            $codeReplased);

        if (!empty($fieldsNames[$nowTableName])) {
            $codeReplased = preg_replace_callback('/#([a-z]+\.)?([a-z0-9_]{2,})/'
                ,
                function ($matches) use ($tablesNames, $fieldsNames, $nowTableName) {
                    $fieldname = $fieldsNames[$nowTableName][$matches[2]] ?? $matches[2];
                    return '#' . $matches[1] . $fieldname;
                },
                $codeReplased);
        }


        return $codeReplased;
    }

    protected function funcUpdatePreparedFields($params)
    {
        if ($params = $this->getParamsArray($params)) {
            $tables = [];
            $tablesModels = Model::init('tables')->getAllIndexedByField([], 'name, id', 'name');
            foreach ($params['tables'] as $table) {
                $tables[$table['name']] = array_intersect_key($table,
                    array_flip(['in_name', 'polojenie_v_dereveciklah', 'kategoriya', 'title']));
                $tables[$table['name']]['id'] = $tablesModels[$table['in_name']]['id'] ?? null;
            }
            unset($table);
            if (!$array = $this->__prepareJson($params['summary']['file'] ?? null)) throw new errorException('Данные обновления не подгружены');

            $rows = [];
            foreach ($array['Schema'] as $tableName => $data) {
                if (!array_key_exists($tableName, $tables)) continue;
                foreach ($data['fields'] as $file_f_name => $field) {
                    $rows[] = [
                        'table_name' => $tableName,
                        'name_tablicy_v_sheme' => $tables[$tableName]['in_name'],
                        'field_title' => $field['title'],
                        'name' => $file_f_name
                    ];
                }
            }
            return ["rows" => $rows,
                "title" => "Загрузка «" . ($array["Title"] ?? 'Безымянная выгрузка') . "» - Выбор полей в схеме таблиц",
                "tables" => $tables,
                "field_params" => array_keys($field ?? []),
            ];
        }
        throw new errorException('Ошибка параметров функции');
    }

    protected function funcUpdateTablesIndexes()
    {
        $fileds = Model::init('tables_fields')->getAllIndexedById([], 'id, name');

        foreach ($tableRows = Model::init('tables')->getAll([],
            'id, name, indexes, main_field, order_field') as $tableRow) {
            if ($indexes = json_decode($tableRow['indexes'], true)) {
                if (is_numeric($indexes[0])) {
                    foreach ($indexes as &$index) {
                        $index = '"' . $fileds[$index]['name'] . '"';
                    }
                    unset($index);
                    Sql::exec('update tables set indexes=\'{"v":[' . implode(',',
                            $indexes) . ']}\' where id=' . $tableRow['id']);
                }
            }
            if ($main_field = json_decode($tableRow['main_field'], true)) {
                if (is_numeric($main_field)) {
                    Sql::exec('update tables set main_field=\'{"v":"' . $fileds[$main_field]['name'] . '"}\' where id=' . $tableRow['id']);
                }
            }
            if ($order_field = json_decode($tableRow['order_field'], true)) {
                if (is_numeric($order_field)) {
                    Sql::exec('update tables set order_field=\'{"v":"' . $fileds[$order_field]['name'] . '"}\' where id=' . $tableRow['id']);
                }
            }
        }
    }

    protected function changeNamesInSchema($Schema, $tablesReplaces, $fieldReplaces, $totumFields, $rolesReplace = [])
    {
        $SchemaNew = [];


        $replaceInCalcsTbl = function ($tbl, $tName) use ($totumFields, $tablesReplaces, $fieldReplaces) {
            $_tName = $tablesReplaces[$tName] ?? $tName;
            $newData = [];
            foreach ($data['params'] ?? [] as $fName => $val) {
                $_fName = $fieldReplaces[$_tName][$fName] ?? $fName;
                if (array_key_exists($fName, $totumFields[$tName])) {
                    $val['v'] = $this->changeNames($val['v'],
                        $tablesReplaces,
                        $fieldReplaces,
                        '____');
                }
                $newData['params'][$_fName] = $val;
            }
            foreach ($data['rows'] ?? [] as $key => $row) {
                $newData['rows'][$key] = [];
                foreach ($row as $fName => $val) {
                    $_fName = $fieldReplaces[$_tName][$fName] ?? $fName;
                    if (array_key_exists($fName, $totumFields[$tName])) {
                        $val['v'] = $this->changeNames($val['v'],
                            $tablesReplaces,
                            $fieldReplaces,
                            '____');
                    }
                    $newData['rows'][$key][$_fName] = $val;
                }
            }
            return $newData;
        };

        $replaceInDatas = function ($data, $tName) use ($totumFields, $tablesReplaces, $fieldReplaces, $replaceInCalcsTbl) {

            $_tName = $tablesReplaces[$tName] ?? $tName;
            $newData = [];
            foreach ($data['params'] ?? [] as $fName => $val) {
                $_fName = $fieldReplaces[$_tName][$fName] ?? $fName;
                if (array_key_exists($fName, $totumFields[$tName])) {
                    $val = $this->changeNames($val,
                        $tablesReplaces,
                        $fieldReplaces,
                        '____');
                }
                $newData['params'][$_fName] = $val;
            }
            foreach ($data['rows'] ?? [] as $key => $row) {
                $newData['rows'][$key] = [];
                foreach ($row as $fName => $val) {
                    if ($fName === '__calcs') {
                        foreach ($val as $calcsName => $calcsData) {
                            $newData['rows'][$key][$tablesReplaces[$calcsName] ?? $calcsName] = $replaceInCalcsTbl($calcsData,
                                $calcsName);
                        }
                    } else {
                        $_fName = $fieldReplaces[$_tName][$fName] ?? $fName;
                        if (array_key_exists($fName, $totumFields[$tName])) {
                            $val = $this->changeNames($val,
                                $tablesReplaces,
                                $fieldReplaces,
                                '____');
                        }
                        $newData['rows'][$key][$_fName] = $val;
                    }
                }
            }
            return $newData;
        };

        foreach ($Schema as $tName => $tableData) {

            $_tName = $tablesReplaces[$tName] ?? $tName;

            /*table*/
            if (!empty($tableData['table']['row_format'])) $tableData['table']['row_format'] = $this->changeNames($tableData['table']['row_format'],
                $tablesReplaces,
                $fieldReplaces,
                $_tName);
            if (!empty($tableData['table']['table_format'])) $tableData['table']['table_format'] = $this->changeNames($tableData['table']['table_format'],
                $tablesReplaces,
                $fieldReplaces,
                $_tName);
            if (!empty($tableData['table']['tree_node_id']) && !ctype_digit($tableData['table']['tree_node_id']) && $tablesReplaces[$tableData['table']['tree_node_id']])
                $tableData['table']['tree_node_id'] = $tablesReplaces[$tableData['table']['tree_node_id']];

            if (!empty($tableData['table']['indexes'])) {
                foreach ($tableData['table']['indexes'] as &$index) {
                    $index = $fieldReplaces[$_tName][$index] ?? $index;
                }
            }
            if (!empty($tableData['table']['main_field'])) {
                $tableData['table']['main_field'] = $fieldReplaces[$_tName][$tableData['table']['main_field']] ?? $tableData['table']['main_field'];
            }
            if (!empty($tableData['table']['order_field'])) {
                $tableData['table']['order_field'] = $fieldReplaces[$_tName][$tableData['table']['order_field']] ?? $tableData['table']['order_field'];
            }

            if ($rolesReplace) {
                $this->changeRolesInTableSchema($tableData, $rolesReplace);
            }


            /*fields*/
            $fields = $tableData['fields'];
            $tableData['fields'] = [];
            foreach ($fields as $fName => $field) {

                foreach (static::dataSrcCodes as $cName) {
                    if (!empty($field['data_src'][$cName]["Val"])) {
                        $field['data_src'][$cName]["Val"] = $this->changeNames($field['data_src'][$cName]["Val"],
                            $tablesReplaces,
                            $fieldReplaces,
                            $_tName);
                        if ($rolesReplace) {
                            $field['data_src'][$cName]["Val"] = $this->changeRolesInCode($field['data_src'][$cName]["Val"],
                                $rolesReplace);
                        }
                    }
                }
                foreach (static::rolesFieldsSrcParams as $cName) {
                    if (!empty($field['data_src'][$cName]["Val"]) && is_array($field['data_src'][$cName]["Val"])) {
                        $field['data_src'][$cName]["Val"] = $this->changeRolesInList($field['data_src'][$cName]["Val"],
                            $rolesReplace);
                    }
                }
                $tableData['fields'][$fieldReplaces[$_tName][$fName] ?? $fName] = $field;
            }

            /*data*/
            if (!empty($tableData['data'])) {
                $data = $tableData['data'];
                $tableData['data'] = $replaceInDatas($data, $tName);

            }
            $SchemaNew[$_tName] = $tableData;
        }


        return $SchemaNew;
    }

    private function changeRolesInTableSchema(&$tableData, $rolesReplace)
    {
        foreach ($tableData['table'] as $k => &$v) {

            if (in_array($k, static::rolesTablesParams)) {
                $v = $this->changeRolesInList($v, $rolesReplace);
            } elseif (in_array($k, static::tableCodeParams)) {
                $v = $this->changeRolesInCode($v, $rolesReplace);
            }
        }
        unset($v);


        foreach ($tableData['fields'] as $fName => &$field) {
            foreach ($field['data_src'] as $param => &$val) {
                if (!empty($val["Val"])) {
                    if (in_array($param, static::rolesFieldsSrcParams)) {
                        $val["Val"] = $this->changeRolesInList($val["Val"], $rolesReplace);
                    } elseif (in_array($param, static::dataSrcCodes)) {
                        $val["Val"] = $this->changeRolesInCode($val["Val"], $rolesReplace);
                    }
                }
            }
            unset($val);
        }
        unset($field);
    }

    private function changeRolesInSchema($schema, $rolesReplace)
    {
        foreach ($schema as $tName => &$tableData) {
            $this->changeRolesInTableSchema($tableData, $rolesReplace);

        }
        unset($tableData);
        return $schema;
    }

    private function changeRolesInCode($code, $rolesReplace)
    {


        $r = preg_replace_callback('/((?i:userInRoles))\(([^\)]+)\)/',
            function ($matches) use ($rolesReplace) {

                return $matches[1] . '(' . preg_replace_callback('/role:\s*(\d+)/',
                        function ($matches) use ($rolesReplace) {
                            $roles = '';
                            if ($rolesReplace[$matches[1]] ?? null) {
                                foreach ($rolesReplace[$matches[1]] as $role) {
                                    if ($roles != '') $roles .= '; ';
                                    $roles .= 'role: ' . $role;
                                }
                                return $roles;
                            }
                            return 'role: ' . $matches[1];

                        },
                        $matches[2]) . ')';
            },
            $code);

        return $r;
    }

    private function changeRolesInList($list, $rolesReplace)
    {
        $newList = [];
        foreach ($list as $_rol) {
            if (!empty($rolesReplace[$_rol])) {
                $newList[] = $rolesReplace[$_rol];
            } else {
                $newList[] = $_rol;
            }
        }
        return $newList;
    }
}