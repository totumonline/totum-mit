<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 11.10.16
 * Time: 15:26
 */

namespace totum\models;


use totum\common\Auth;
use totum\common\errorException;
use totum\common\Log;
use totum\common\Model;
use totum\common\Cycle;
use totum\common\Sql;
use totum\common\TableFactory;
use totum\tableTypes\aTable;
use totum\tableTypes\calcsTable;
use totum\tableTypes\RealTables;
use totum\tableTypes\tableTypes;

class Table extends Model
{
    static $TableId = 1;
    static $tableTypes = [
        'simple' => 'Простая'
        , 'calcs' => 'Расчетная'
        , 'dinamic' => 'Динамическая'
        , 'inner' => 'Вложенная'
        , 'tmp' => 'Временная'
    ];
    static $tableRowsByName = [];
    static $tableRowsById = [];
    static $systemTables =
        ['tables' => [],
            'tables_fields' => [],
            'roles' => [],
            'users' => [],
            'tree' => [],
            'settings' => [],
            'table_categories' => []];

    /**
     * @param $action String insert|delete
     * @param $tableRow
     * @return bool
     */
    static function isUserCanAction($action, $tableRow)
    {
        switch ($action) {
            case 'insert':
                if ($tableRow['insertable'] && (Auth::$aUser->getTables()[$tableRow['id']] ?? null)) {
                    if (empty($tableRow['insert_roles']) || array_intersect($tableRow['insert_roles'],
                            Auth::$aUser->getRoles())) {
                        return true;
                    }
                }
                break;
            case 'delete':
                if ($tableRow['deleting'] !== 'none' && (Auth::$aUser->getTables()[$tableRow['id']] ?? null)) {
                    if (empty($tableRow['delete_roles']) || array_intersect($tableRow['delete_roles'],
                            Auth::$aUser->getRoles())) {
                        return true;
                    }
                }
                break;
            case 'duplicate':
                if ($tableRow['duplicating'] && (Auth::$aUser->getTables()[$tableRow['id']] ?? null)) {
                    if (empty($tableRow['duplicate_roles']) || array_intersect($tableRow['duplicate_roles'],
                            Auth::$aUser->getRoles())) {
                        return true;
                    }
                }
                break;
            case 'reorder':
                if ($tableRow['with_order_field'] && (Auth::$aUser->getTables()[$tableRow['id']] ?? null)) {
                    if (empty($tableRow['order_roles']) || array_intersect($tableRow['order_roles'],
                            Auth::$aUser->getRoles())) {
                        return true;
                    }
                }
                break;
            case 'csv':
                if (empty($tableRow['csv_roles']) || array_intersect($tableRow['csv_roles'],
                        Auth::$aUser->getRoles())) {
                    return true;
                }
                break;
            case 'csv_edit':
                if (!empty($tableRow['csv_edit_roles']) && array_intersect($tableRow['csv_edit_roles'],
                        Auth::$aUser->getRoles())) {
                    return true;
                }
                break;
        }
        return false;
    }

    function insert($vars, $returning = 'idFieldName', $ignore = false)
    {
        $vars['updated'] = aTable::getUpdatedJson();

        $name = json_decode($vars['name'], true)['v'];

        if (in_array($name, Model::reservedSqlWords)) {
            throw new errorException('[[' . $name . ']] не может быть названием таблицы');
        }

        $id = parent::insert($vars);
        if ($id && ($row = Table::getTableRowById($id))) {



            try {
                //Добавить к видимости роли Создатель - базовая настройка удаляется при пересчете - эту строку не трогать!
                Model::init('roles', 'id')->saveVars(1,
                    ['tables=jsonb_set(tables, \'{v}\', to_jsonb(array(select id::text from tables order by name->>\'v\')))']);
            } catch (\Exception $e) {

            }
        } else {
            throw new errorException('Ошибка с таблицей');
        }
        return $id;
    }

    static function getTableRowByName($name, $force = false)
    {
        if ($force) {
            return static::getTableRow(['name' => $name]);
        } else return static::$tableRowsByName[$name] ?? static::getTableRow(['name' => $name]);
    }

    static function getTableRowById($id, $force = false)
    {
        if ($force) {
            return static::getTableRow(['id' => $id]);
        } else return static::$tableRowsById[$id] ?? static::getTableRow(['id' => $id]);
    }

    static function getTableIdByName($name)
    {
        return (static::getTableRowByName($name)['id']) ?? null;
    }

    protected static function getCorrectRow($row)
    {
        if ($row) {
            $row['__i_select'] = date('i');
            static::$tableRowsByName[$row['name']] = $row;
            static::$tableRowsById[$row['id']] = $row;
        }
        return $row;
    }

    static protected function getTableRow($where)
    {
        $row = Model::init('tables')->get($where);
        foreach ($row as $k => &$v) {
            if (!in_array($k, Model::serviceFields)) {
                if (is_array($v)) debug_print_backtrace();
                if (!array_key_exists('v', json_decode($v, true))) {
                    var_dump($row, $k);
                    die;
                }
                $v = json_decode($v, true)['v'];
            }
        };
        unset($v);

        return static::getCorrectRow($row);
    }

    function update($params, $where, $ignore = 0, $oldValue = null): Int
    {
        sql::transactionStart();
        if (empty($oldValue)) {
            $oldValue = static::get($where);
            foreach ($oldValue as &$_) {
                $_ = json_decode($_, true);
            }
            unset($_);

        }
        if (array_key_exists('indexes', $params)) {
            $newIndexes = json_decode($params['indexes'], true)['v'];
            $oldIndexes = $oldValue['indexes']['v'] ?? [];
            sort($newIndexes);
            sort($oldIndexes);

            if ($oldIndexes !== $newIndexes) {
                $forDelete = array_diff($oldIndexes, $newIndexes);
                $forCreate = array_diff($newIndexes, $oldIndexes);

                $fields = TablesFields::getFields($oldValue['id']);
                $Table = tableTypes::getTable(Table::getTableRowById($oldValue['id']));
                foreach ($fields as $field) {
                    if (in_array($field['name'], $forDelete)) {
                        $Table->removeIndex($field['name']);
                    } elseif (in_array($field['name'], $forCreate)) {
                        $Table->createIndex($field['name']);
                    }
                }
            }
        }

        if (array_key_exists('with_order_field',
            $params)) {
            $newWithOrderFields = !empty(json_decode($params['with_order_field'],
                    true)['v']);
            if (empty($oldValue['with_order_field']['v']) && $newWithOrderFields) {
                $tableRow = Table::getTableRowById($oldValue['id']);
                if (tableTypes::isRealTable($tableRow)) {
                    /** @var RealTables $Table */
                    $Table = tableTypes::getTable($tableRow);
                    $Table->addOrderField();
                }
            } elseif (!empty($oldValue['with_order_field']['v']) && !$newWithOrderFields) {
                $tableRow = Table::getTableRowById($oldValue['id']);
                if (tableTypes::isRealTable($tableRow)) {
                    /** @var RealTables $Table */
                    $Table = tableTypes::getTable($tableRow);
                    $Table->removeOrderField();
                }
            }
        }
        $r = parent::update($params, $where, $ignore);

        static::$tableRowsByName = [];
        static::$tableRowsById = [];
        sql::transactionCommit();
        return $r;
    }


    function delete($where, $ignore = 0)
    {
        if ($rows = Model::init('tables')->getAll($where, 'id, name, type')) {
            Sql::transactionStart();

            foreach ($rows as $tableRow) {
                $tableName = $tableRow['name'];
                $tableType = $tableRow['type'];

                if (array_key_exists($tableName, static::$systemTables)) {
                    throw new errorException('Нельзя удалять системные таблицы');
                }

                switch ($tableType) {
                    case 'cycles':
                        tableTypes::getTable($tableRow)->deleteTable();
                        break;
                }

                switch ($tableType) {
                    case 'tmp';
                        break;
                    case 'globcalcs':
                        Sql::exec('DELETE FROM ' . array_flip(Model::$tablesModels)['NonProjectCalcs'] . ' WHERE tbl_name=\'' . $tableName . '\' ');
                        break;
                    default:
                        Sql::exec('DROP TABLE if exists ' . $tableName . ' CASCADE');
                        break;
                }

                Sql::exec('DELETE FROM ' . array_flip(Model::$tablesModels)['TablesFields'] . ' WHERE table_id->>\'v\'=\'' . $tableRow['id'] . '\' ');
                unset(static::$tableRowsById[$tableRow['id']]);
                unset(static::$tableRowsByName[$tableRow['name']]);
                parent::delete(['id' => $tableRow['id']]);
            }

            Sql::transactionCommit();
        }
    }

}