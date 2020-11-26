<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 20.03.17
 * Time: 13:26
 */

namespace totum\tableTypes;

use totum\common\calculates\CalculateAction;
use totum\common\errorException;
use totum\common\Cycle;
use totum\common\sql\SqlException;

class cyclesTable extends RealTables
{
    protected function reCalculate($inVars = [])
    {
        if (in_array($inVars['channel'] ?? null, ['web', 'edit'])) {
            if ($this->getTableRow()['cycles_access_type'] === '1' && isset($this->fields['creator_id']) && !$this->getUser()->isCreator()) {
                $where = '';
                foreach ($this->User->getConnectedUsers() as $uId) {
                    if ($where !== '') {
                        $where .= ' OR ';
                    }
                    $where .= 'creator_id->\'v\' @>\'["' . $uId . '"]\'::JSONB';
                }
                $this->elseWhere[] = $where;
            }
        }
        parent::reCalculate($inVars);
    }

    public function createTable()
    {
        parent::createTable();

        $tablesFields = $this->Totum->getTable('tables_fields');
        $tablesFields->reCalculateFromOvers(
            ['add' => [
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
    }

    public function deleteTable()
    {
        $ids = array_keys($this->model->getAllIndexedById([], 'id'));
        foreach ($ids as $id) {
            try {
                $this->Totum->deleteCycle($id, $this->tableRow['id']);
            } catch (SqlException $e) {
                throw new errorException('Сначала нужно удалить таблицу циклов, а потом расчетные таблицы внутри нее');
            }
        }
    }

    public function removeRows($remove, $isInnerChannel)
    {
        if ($this->tableRow['deleting'] === 'delete' || $isInnerChannel) {
            $this->loadRowsByIds($remove);
            foreach ($remove as $id) {
                if (!empty($this->tbl['rows'][$id])) {
                    $this->Totum->deleteCycle($id, $this->tableRow['id']);
                }
            }
        }

        parent::removeRows($remove, $isInnerChannel);
    }

    public function getUserCyclesCount()
    {
        return $this->model->executePrepared(
            true,
            ['creator_id' => $this->User->getConnectedUsers()],
            'count(*)'
        )->fetchColumn(0);
    }

    public function getUserCycles($userId)
    {
        return $this->loadRowsByParams([]);
    }

    protected function loadRowsByParams($params, $order = null, $offset = 0, $limit = null)
    {
        /*Вопрос насколько тут нужно проверять вебность интерфейса*/
        if (!$this->User->isCreator() && $this->tableRow['cycles_access_type'] === '1' && $this->User->getInterface() === 'web') {
            $params[] = ['field' => 'creator_id', 'operator' => '=', 'value' => $this->User->getConnectedUsers()];
        }
        return parent::loadRowsByParams($params, $order, $offset, $limit);
    }

    protected function addRow($channel, $addData, $fromDuplicate = false, $addWithId = false, $duplicatedId = 0, $isCheck = false)
    {
        $addedRow = parent::addRow($channel, $addData, $fromDuplicate, $addWithId, $duplicatedId, $isCheck);

        if (!$fromDuplicate && !$isCheck) {
            $this->changeIds['rowOperations'][] = function () use ($addedRow, $channel) {
                $Cycle = Cycle::create($this->tableRow['id'], $addedRow['id'], $this->Totum);
                if ($channel === 'web' && $Cycle->getFirstTableId()) {
                    $action = new CalculateAction('=: linkToTable(table: ' . $Cycle->getFirstTableId() . '; cycle: ' . $addedRow['id'] . ')');
                    $action->execAction('addingRow', [], [], [], [], $this, 'add');
                }
            };
        }
        return $addedRow;
    }

    protected function duplicateRow($channel, $baseRow, $replaces, $addAfter)
    {
        $newRow = parent::duplicateRow($channel, $baseRow, $replaces, $addAfter);

        $this->Totum->getTable('calcstable_cycle_version')->actionDuplicate(
            ['cycle' => $newRow['id']],
            [
                ['field' => 'cycles_table', 'operator' => '=', 'value' => $this->tableRow['id']]
                , ['field' => 'cycle', 'operator' => '=', 'value' => $baseRow['id']]
            ]
        );

        $this->changeIds['rowOperations'][] = function () use ($baseRow, $newRow) {
            $Log=$this->calcLog(['name'=>'DUPLICATE CYCLE']);
            Cycle::duplicate($this->tableRow['id'], $baseRow['id'], $newRow['id'], $this->Totum);
            $this->calcLog($Log, 'result', 'done');
        };

        $newRow = $this->model->getById($newRow['id']);

        return $newRow;
    }
}
