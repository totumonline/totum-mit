<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 20.03.17
 * Time: 13:26
 */

namespace totum\tableTypes;


use totum\common\Auth;
use totum\common\CalculateAction;
use totum\common\Controller;
use totum\common\errorException;
use totum\common\Field;
use totum\common\Model;
use totum\common\Cycle;
use totum\common\Sql;
use totum\common\SqlExeption;
use totum\models\Table;
use totum\models\TablesFields;

class cyclesTable extends RealTables
{
    function createTable()
    {
        $fields = [];
        $fields[] = 'id SERIAL PRIMARY KEY NOT NULL';
        $fields[] = 'is_del BOOLEAN NOT NULL DEFAULT FALSE ';

        $fields = '(' . implode(',', $fields) . ')';
        Sql::exec('CREATE TABLE ' . $this->tableRow['name'] . $fields);

        $tablesFields = tableTypes::getTable(Table::getTableRowById(TablesFields::TableId));
        $tablesFields->reCalculateFromOvers(['add' => [
                0 => [
                    'table_id' => $this->tableRow['id']
                    , 'name' => 'creator_id'
                    , 'category' => 'column'
                    , 'ord' => '10'
                    , "title" => "Доступ пользователю"
                    , 'data_src' => [
                        "type" => ['Val' => "select", 'isOn' => true]
                        , "width" => ['Val' => 100, 'isOn' => true]
                        , "filterable" => ['Val' => true, 'isOn' => true]
                        , "showInWeb" => ['Val' => true, 'isOn' => true]
                        , "editable" => ['Val' => false, 'isOn' => true]
                        , "linkFieldName" => ['Val' => "creator_id", 'isOn' => true]
                        , "code" => ['Val' => "=: listCreate(item: \$user)\nuser: nowUser()", 'isOn' => true]
                        , "codeOnlyInAdd" => ['Val' => true, 'isOn' => true]
                        , "webRoles" => ['Val' => ["1"], 'isOn' => true]
                        , "codeSelect" => ['Val' => "=:SelectListAssoc(table: 'users';field: 'fio';)", 'isOn' => true]
                        , "multiple" => ['Val' => true, 'isOn' => true]
                    ]
                ],
                2 => [
                    'table_id' => $this->tableRow['id']
                    , 'name' => 'button_to_cycle'
                    , 'category' => 'column'
                    , 'ord' => '30'
                    , "title" => "Кнопка в цикл"
                    , 'data_src' => [
                        "type" => ['Val' => "button", 'isOn' => true]
                        , "width" => ['Val' => 100, 'isOn' => true]
                        , "showInWeb" => ['Val' => true, 'isOn' => true]
                        , "buttonText" => ['Val' => 'Открыть', 'isOn' => true]
                        , "codeAction" => ['Val' => "= : linkToTable(table: \$table; cycle: #id; target: 'self' )\n"
                            . 'table: select(table: \'tables\';  field: \'id\' ; where: \'type\'="calcs"; where: \'tree_node_id\'=$nt; order: \'sort\' )' . "\n"
                            . 'nt: nowTableId()', 'isOn' => true]
                    ]
                ]
            ]
            ]
        );

        if ($this->getTableRow()['with_order_field']) {
            $this->addOrderField();
        }
    }

    function deleteTable()
    {
        $ids = array_keys($this->model->getAllIndexedById([], 'id'));
        foreach ($ids as $id) {
            try {
                $cycle = Cycle::init($id, $this->tableRow['id']);
                $cycle->delete(true);
            } catch (SqlExeption $e) {
                throw new errorException('Сначала нужно удалить таблицу циклов, а потом расчетные таблицы внутри нее');
            }
        }
    }

    function removeRows($remove, $isInnerChannel)
    {
        if ($this->tableRow['deleting'] === 'delete' || $isInnerChannel) {
            $this->loadRowsByIds($remove);
            foreach ($remove as $id) {
                if (!empty($this->tbl['rows'][$id])) {
                    $cycle = Cycle::init($id, $this->tableRow['id']);
                    $cycle->delete(true);
                }
            }
        }

        parent::removeRows($remove, $isInnerChannel);
    }

    function getUserCycles($userId)
    {
        return $this->loadRowsByParams([]);
        //  var_dump(Sql::$lastQuery); die;
    }

    function loadRowsByParams($params, $order = null)
    {
        /*Вопрос насколько тут нужно проверять вебность интерфейса*/
        if (!Auth::isCreator() && $this->tableRow['cycles_access_type'] == 1 && Auth::$aUser->getInterface() == 'web') {

            $params[] = ['field' => 'creator_id', 'operator' => '=', 'value' => Auth::$aUser->getConnectedUsers()];
        }
        return parent::loadRowsByParams($params, $order);
    }

    protected function addRow($channel, $addData, $fromDuplicate = false, $addWithId = false, $duplicatedId = 0, $isCheck = false)
    {
        $addedRow = parent::addRow($channel, $addData, $fromDuplicate, $addWithId, $duplicatedId, $isCheck);

        if (!$fromDuplicate && !$isCheck) {
            $this->changeIds['rowOperations'][] = function () use ($addedRow, $channel) {
                $Cycle = Cycle::create($this->tableRow['id'], $addedRow['id']);
                if ($channel == 'web' && $Cycle->getFirstTableId()) {
                    $action = new CalculateAction('=: linkToTable(table: ' . $Cycle->getFirstTableId() . '; cycle: ' . $addedRow['id'] . ')');
                    $action->execAction('addingRow', [], [], [], [], $this);
                }
            };
        }
        return $addedRow;
    }

    protected function duplicateRow($channel, $baseRow, $replaces, $addAfter)
    {
        $newRow = parent::duplicateRow($channel, $baseRow, $replaces, $addAfter);

        tableTypes::getTableByName('calcstable_cycle_version')->actionDuplicate(
            ['cycle' => $newRow['id']],
            [
                ['field' => 'cycles_table', 'operator' => '=', 'value' => $this->tableRow['id']]
                , ['field' => 'cycle', 'operator' => '=', 'value' => $baseRow['id']]
            ]
        );
        $this->changeIds['rowOperations'][] = function () use ($baseRow, $newRow) {
            Cycle::duplicate($this->tableRow['id'], $baseRow['id'], $newRow['id']);
        };

        $newRow = $this->model->getById($newRow['id']);

        return $newRow;
    }
}