<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 11.10.16
 * Time: 15:26
 */

namespace totum\models;

use Exception;
use totum\common\errorException;
use totum\common\Model;
use totum\common\Totum;
use totum\models\traits\WithTotumTrait;
use totum\tableTypes\aTable;
use totum\tableTypes\calcsTable;
use totum\tableTypes\RealTables;
use totum\tableTypes\tableTypes;

class Table extends Model
{
    /*static $tableTypes = [
        'simple' => 'Простая'
        , 'calcs' => 'Расчетная'
        , 'dinamic' => 'Динамическая'
        , 'inner' => 'Вложенная'
        , 'tmp' => 'Временная'
    ];*/
    protected static $systemTables =
        ['tables' => [],
            'tables_fields' => [],
            'roles' => [],
            'users' => [],
            'tree' => [],
            'settings' => [],
            'table_categories' => []];

    use WithTotumTrait;

    public function insertPrepared($vars, $returning = 'idFieldName', $ignore = false, $cacheIt = true)
    {
        $vars['updated'] = aTable::formUpdatedJson($this->Totum->getUser());

        $name = json_decode($vars['name'], true)['v'];

        if (in_array($name, Model::RESERVED_WORDS)) {
            throw new errorException('[[' . $name . ']] не может быть названием таблицы');
        }

        $id = parent::insertPrepared($vars, $returning, $ignore, $cacheIt);
        if ($id && ($row = $this->Totum->getTableRow($id))) {
            if ($row['type'] === 'calcs') {
                calcsTable::__createTable($row['name'], $this->Totum);
            } else {
                $table = $this->Totum->getTable($row);
                $table->createTable();
            }

        } else {
            throw new errorException('Ошибка с таблицей');
        }
        return $id;
    }

    public function update($params, $where, $oldRow = null): int
    {
        $this->Sql->transactionStart();

        if (empty($oldRow)) {
            if ($oldRow = static::executePrepared(true, $where, '*', null, '0,1')->fetch()) {
                foreach ($oldRow as &$_) {
                    $_ = json_decode($_, true);
                }
                unset($_);
            } else {
                return 0;
            }
        }
        if (array_key_exists('indexes', $params)) {
            $newIndexes = json_decode($params['indexes'], true)['v'];
            $oldIndexes = $oldRow['indexes']['v'] ?? [];
            sort($newIndexes);
            sort($oldIndexes);

            if ($oldIndexes !== $newIndexes) {
                $forDelete = array_diff($oldIndexes, $newIndexes);
                $forCreate = array_diff($newIndexes, $oldIndexes);
                $Table = $this->Totum->getTable($oldRow['id']);

                $fields = $Table->getFields();
                foreach ($fields as $field) {
                    if (in_array($field['name'], $forDelete)) {
                        $Table->removeIndex($field['name']);
                    } elseif (in_array($field['name'], $forCreate)) {
                        $Table->createIndex($field['name']);
                    }
                }
            }
            $params['indexes'] = json_encode(['v' => array_values(array_unique($newIndexes))]);
        }

        if (array_key_exists(
            'with_order_field',
            $params
        )) {
            $newWithOrderFields = !empty(json_decode(
                $params['with_order_field'],
                true
            )['v']);
            if (empty($oldRow['with_order_field']['v']) && $newWithOrderFields) {
                $tableRow = $this->Totum->getTableRow($oldRow['id']);
                if (Totum::isRealTable($tableRow)) {
                    /** @var RealTables $Table */
                    $Table = $this->Totum->getTable($tableRow);
                    $Table->addOrderField();
                }
            } elseif (!empty($oldRow['with_order_field']['v']) && !$newWithOrderFields) {
                $tableRow = $this->Totum->getTableRow($oldRow['id']);
                if (Totum::isRealTable($tableRow)) {
                    /** @var RealTables $Table */
                    $Table = $this->Totum->getTable($tableRow);
                    $Table->removeOrderField();
                }
            }
        }
        $r = parent::update($params, $where);

        $this->Totum->getConfig()->clearRowsCache();
        $this->Sql->transactionCommit();
        return $r;
    }


    public function delete($where, $ignore = 0)
    {
        if ($rows = $this->getAll($where, 'id, name, type')) {
            foreach ($rows as $tableRow) {
                $tableName = $tableRow['name'];
                $tableType = $tableRow['type'];

                if (key_exists($tableName, static::$systemTables)) {
                    throw new errorException('Нельзя удалять системные таблицы');
                }

                $this->Totum->getNamedModel(TablesFields::class)->delete(['table_id' => $tableRow['id']]);
                $this->Totum->getConfig()->clearRowsCache();


                switch ($tableType) {
                    case 'cycles':
                        $this->Totum->getTable($tableRow)->deleteTable();
                        break;
                }

                switch ($tableType) {
                    case 'tmp':
                        break;
                    case 'globcalcs':
                        NonProjectCalcs::init($this->Totum->getConfig(), true)->delete(['tbl_name' => $tableName]);
                        break;
                    default:
                        $this->Totum->getModel($tableName)->dropTable();

                        break;
                }

                parent::delete(['id' => $tableRow['id']]);
            }
        }
    }

    public function dulpicateTableFiedls(array $row, array $baseRow)
    {
        foreach ($row as $k => &$v) {
            if (is_array($v) && key_exists('v', $v)) {
                $v = $v['v'];
            }
        }
        if ($row['type'] === 'calcs') {
            $newVersion = $baseVersion = $this->Totum->getNamedModel(CalcsTablesVersions::class)->executePrepared(
                true,
                ['table_name' => $baseRow['name']['v'], 'is_default' => 'true'],
                'version',
                null,
                '0,1'
            )->fetchColumn(0);
        } else {
            $baseVersion = null;
            $newVersion = null;
        }

        $fields = $this->Totum->getNamedModel(TablesFields::class)->executePrepared(
            true,
            ['table_id' => $baseRow['id'], 'version' => $baseVersion],
            'category, name, data_src, id'
        )->fetchAll();

        $fieldsD = $this->Totum->getNamedModel(TablesFields::class)->executePrepared(
            true,
            ['table_id' => $row['id'], 'version' => $newVersion],
            'category, name, data_src, id'
        )->fetchAll();

        $replaces = [];
        $ids = [];
        $filters = [];

        foreach ($fields as $f) {
            if (empty($fieldsD[$f['name']])) {
                if ($f['category'] === 'filter') {
                    $filters[] = $f['id'];
                } else {
                    $ids[] = $f['id'];
                }
                $replaces[$f['id']]['table_id'] = $row['id'];
                $f['data_src'] = json_decode($f['data_src'], true);
            }
        }
        $ids = array_merge($ids, $filters);
        if ($ids) {
            $TableFields = $this->Totum->getTable('tables_fields');
            $TableFields->reCalculateFromOvers([
                'duplicate' => ['ids' => $ids, 'replaces' => $replaces]
            ]);
        }

        /*if ($duplicatedRow['type'] != 'calcs' && $duplicatedRow['type'] != 'tmp') {
            $Table = tableTypes::getTable($duplicatedRow);
            $Table->reCalculateFromOvers([]);
        }*/
    }
}
