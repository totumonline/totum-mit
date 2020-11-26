<?php


namespace totum\moduls\Table;

use totum\common\calculates\CalculateAction;
use totum\common\errorException;
use totum\models\Table;
use totum\models\TablesFields;

class AdminTableActions extends WriteTableActions
{
    /**
     * Подгрузка преднастроенных графиков в настройки поля График
     *
     * @throws errorException
     */
    public function getChartTypes()
    {
        if (!$this->Totum->getTableRow('ttm__charts')) {
            throw new errorException('Таблица графиков не надена');
        }
        $result['chartTypes'] = [];
        foreach ($this->Totum->getModel('ttm__charts')->executePrepared(
            true,
            [],
            implode(', ', ['type', 'title', 'default_options', 'format'])
        ) as $row) {
            $row['default_options'] = json_decode($row['default_options'], true);
            $row['format'] = json_decode($row['format'], true);
            $result['chartTypes'][] = $row;
        }
        return $result;
    }

    public function getAllTables()
    {
        $tables = [];
        $fields = TablesFields::init($this->Totum->getConfig())->getAll(
            ['is_del' => false],
            'name, table_id, title, data, category'
        );

        foreach (Table::init($this->Totum->getConfig())->getAll(
            ['is_del' => false],
            'name, id, title'
        ) as $tRow) {
            $tFields = [];
            $fieldsForSobaka = [];
            foreach ($fields as $v) {
                if ((int)$v['table_id'] === $tRow['id']) {
                    $tFields[$v['name']] = $v['title'];
                    if (!in_array($v['category'], ['filter', 'column']) && json_decode(
                            $v['data'],
                            true
                        )['type'] !== 'button') {
                        $fieldsForSobaka[] = $v['name'];
                    }
                }
            }
            $tables[$tRow['name']] = ['t' => $tRow['title'], 'f' => $tFields, '@' => $fieldsForSobaka];
        }

        return ['tables' => $tables];
    }

    public function refresh_cycles()
    {
        $ids = !empty($this->post['refreash_ids']) ? json_decode($this->post['refreash_ids'], true) : [];
        $tables = [];
        foreach ($ids as $id) {
            $Cycle = $this->Totum->getCycle($id, $this->Table->getTableRow()['id']);

            if (empty($tables)) {
                $tables = $Cycle->getTableIds();
                foreach ($tables as &$t) {
                    $t = $this->Totum->getTableRow($t);
                }
                unset($t);
            }
            foreach ($tables as $inTable) {
                $CalcsTable = $Cycle->getTable($inTable);
                $CalcsTable->reCalculateFromOvers();
            }
        }

        return $this->refresh();
    }

    public function renameField()
    {
        if (empty($this->post['name'])) {
            throw new errorException('Нужно выбрать поле');
        }
        $name = $this->post['name'];
        if (empty($this->Table->getFields()[$name])) {
            throw new errorException('Поле в таблице не найдено');
        }
        $code = <<<CODE
=: linkToDataTable(table: 'ttm__change_field_name'; title: 'Изменение name поля'; width: 800; height: "80vh"; params:\$#row; refresh: 'strong';)
CODE;

        $calc = new CalculateAction($code);
        $calc->execAction(
            'CODE_TABLE_ACTION_renameField',
            [],
            [],
            [],
            [],
            $this->Table,
            'exec',
            ['row' => ['table_name' => $this->Table->getTableRow()['name'], 'field_name' => $name]]
        );
    }

    public function addEyeGroupSet()
    {
        if (empty(trim($this->post['name']))) {
            throw new errorException('Имя сета должно быть не пустым');
        }
        if (empty($this->post['fields'])) {
            throw new errorException('Сет не должен быть пустым');
        }

        $set = $this->Table->changeFieldsSets(function ($set) {
            $set[] = ['name' => trim($this->post['name']), 'fields' => $this->post['fields']];
            return $set;
        });

        return ['sets' => $set];
    }

    public function removeEyeGroupSet()
    {
        $set = $this->Table->changeFieldsSets(function ($set) {
            array_splice($set, $this->post['index'], 1);
            return $set;
        });

        return ['sets' => $set];
    }

    public function leftEyeGroupSet()
    {
        $set = $this->Table->changeFieldsSets(function ($set) {
            if ($this->post['index'] > 0) {
                $setItem = array_splice($set, $this->post['index'], 1);
                array_splice($set, $this->post['index'] - 1, 0, $setItem);
            }
            return $set;
        });
        return ['sets' => $set];
    }

    public function getTableData()
    {
        $data = parent::getTableData();
        $data['isCreatorView'] = true;
        return $data;
    }

    public function calcFieldsLog()
    {
        $CA = new CalculateAction('= : linkToDataTable(title:$#title; table: \'calc_fields_log\'; width: 1000; height: "80vh"; params: $#row; refresh: false; header: true; footer: true)');

        $Vars = ['row' => ['data' => $this->post['calc_fields_data']], 'title' => $this->post['name']];
        $CA->execAction(
            'KOD',
            [],
            [],
            [],
            [],
            $this->Totum->getTable('tables'),
            'exec',
            $Vars
        );
    }
}
