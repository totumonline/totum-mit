<?php


namespace totum\moduls\Table;

use totum\common\calculates\CalculateAction;
use totum\common\errorException;
use totum\common\Lang\RU;
use totum\models\CalcsTableCycleVersion;
use totum\models\CalcsTablesVersions;
use totum\models\Table;
use totum\models\TablesFields;
use totum\tableTypes\aTable;

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
            throw new errorException($this->translate('Table [[%s]] is not found.', 'ttm__charts'));
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

    public function getTableParam()
    {
        if (!empty($this->post['tableId']) && $row = $this->Totum->getTableRow($this->post['tableId'])) {
            return [$this->post['param'] => $row[$this->post['param']]];
        }
        throw new errorException($this->translate('Table is not found.') . ' ' . $this->translate('May be insert row has expired.'));
    }

    public function bugFinder()
    {
        $this->Totum->getConfig()->getSql(true)->transactionStart();
        set_time_limit($this->post['timeLimit']);

        $Table = $this->Table;
        $fieldName = null;
        $fieldId = null;
        if ($this->post['type']['pl'] ?? false) {
            (function ($category, $type, $fieldNum) use(&$fieldName, &$fieldId) {
                /** @var aTable $this */
                $num = 1;
                foreach ($this->fields as $name => $field) {
                    if ($field['category'] === $category) {
                        if ($field[$type] ?? false) {
                            if ($fieldNum) {
                                if ($fieldNum < $num) {
                                    break;
                                }
                            }
                            $fieldName = $name;
                            $fieldId = $field['id'];
                            unset($this->fields[$name][$type]);
                            $num++;
                        }
                    }
                }
                $this->sortedFields = static::sortFields($this->fields);
            })->bindTo($Table, $Table)($this->post['type']['pl'], $this->post['type']['code'], $this->post['fieldNum'] ?? false);
        }

        $Actions = new WriteTableActions($this->Request, $this->modulePath, $Table, null);
        match ($this->post['pageType']??false){
            'main'=>$Actions->getFullTableData(true),
            default=> $Actions->loadPage(true)
        };
        return ['ok' => 1, 'fieldName' => $fieldName, 'fieldId' => $fieldId];
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
            'name, id, title, type'
        ) as $tRow) {
            $tFields = [];
            foreach ($fields as $v) {
                if ((int)$v['table_id'] === $tRow['id']) {
                    $data = json_decode(
                        $v['data'],
                        true
                    );
                    $tFields[$v['name']] = [$v['title'], substr($data['type'],
                        0,
                        3), $v['category'][0] . ($v['category'][0] === 'f' ? $v['category'][1] : '')];
                }
            }
            $tables[$tRow['name']] = ['t' => $tRow['title'], 'f' => $tFields];
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

        return $this->getTableClientChangedData([], true);
    }

    public function renameField()
    {
        if (empty($this->post['name'])) {
            throw new errorException($this->translate('Fill in the %s field', 'FIELD'));
        }
        $name = $this->post['name'];
        if (empty($this->Table->getFields()[$name])) {
            throw new errorException($this->translate('Field [[%s]] is not found.', $name));
        }
        $title = $this->translate('Changing the name of a field');
        $code = <<<CODE
=: linkToDataTable(table: 'ttm__change_field_name'; title: '$title'; width: 800; height: "80vh"; params:\$#row; refresh: 'strong';)
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
            throw new errorException($this->translate('Fill in title'));
        }
        if (empty($this->post['fields'])) {
            throw new errorException($this->translate('Select fields'));
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
        $data['isCreatorView'] = $this->isCreatorView();
        return $data;
    }

    public function getIdByFieldValue()
    {
        $data = [];
        $post = json_decode($this->post['data'], true);

        if ($this->Table->getTableRow()['id'] === 2) {
            $tableRow = $this->Totum->getTableRow($post['table_name']);
            if ($tableRow['type'] === 'calcs') {
                if (!empty($post['__cycle_id'])) {
                    $Cycle = $this->Totum->getCycle($post['__cycle_id'], $tableRow['tree_node_id']);
                    $Table = $Cycle->getTable($tableRow);
                    $data[] = ['field' => 'version', 'operator' => '=', 'value' => $Table->getTableRow()['__version']];
                } else {

                    $version = CalcsTablesVersions::init($this->Totum->getConfig())->getDefaultVersion($tableRow['table_name'],
                        false);
                    $data[] = ['field' => 'version', 'operator' => '=', 'value' => $version];

                }
            }
            unset($post['__cycle_id']);
        }

        foreach ($post as $k => $v) {
            $data[] = ['field' => $k, 'operator' => '==', 'value' => $v];
        }
        if (empty($data)) {
            throw new errorException($this->translate('Client side error'));
        }


        return ['value' => $this->Table->getByParams(['field' => 'id', 'where' => $data])];
    }

    public function formsLinks()
    {
        switch ($this->post['type'] ?? null) {
            case 'quick':

                $CA = new CalculateAction('=: linkToDataTable(table: "ttm__add_quick_form"; target: "iframe"; width: "750px"; title: $#title; params: $#data)');
                $CA->execAction('CODE',
                    [],
                    [],
                    [],
                    $this->Table->getTbl(),
                    $this->Table,
                    'exec',
                    ['title' => $this->translate('Add form'), 'data' => ['h_table_name' => $this->Table->getTableRow()['name']]]
                );


                break;
            case 'forms':
                $CA = new CalculateAction('=: linkToTable(table: "ttm__forms"; target: "iframe"; width: "70vw"; title: $#title; filter: "fl_table_prefilter_in_forms"="' . $this->Table->getTableRow()['name'] . '")');
                $CA->execAction('CODE',
                    [],
                    [],
                    [],
                    $this->Table->getTbl(),
                    $this->Table,
                    'exec',
                    ['title' => $this->translate('%s table forms', $this->Table->getTableRow()['title'])]);

                break;
            default:
                throw new errorException('Type Forms Error');
        }
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
