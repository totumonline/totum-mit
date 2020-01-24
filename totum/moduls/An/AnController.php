<?php


namespace totum\moduls\An;


use totum\common\Auth;
use totum\common\CalculateAction;
use totum\common\Controller;
use totum\common\Crypt;
use totum\common\errorException;
use totum\common\interfaceController;
use totum\common\Model;
use totum\common\Sql;
use totum\models\Table;
use totum\tableTypes\tableTypes;

class AnController extends interfaceController
{
    const __isAuthNeeded = false;
    public static $pageTemplate = 'page_template_simple.php';
    protected $Table,
        $onlyRead;

    function __construct($modulName, $inModuleUri)
    {
        parent::__construct($modulName, $inModuleUri);
        Auth::loadAuthUserByLogin("anonym", false);
    }

    function doIt($action)
    {
        if (!$this->isAjax) $action = 'Main';
        else $action = 'Actions';


        try {
            $this->checkTableByUri();
            parent::doIt($action);
        } catch (errorException $e) {
            if (empty($_POST['ajax'])) {
                static::$contentTemplate = 'templates/__error.php';
                $this->errorAnswer($e->getMessage() . "<br/>" . $e->getPathMess());
            } else {
                echo json_encode(['error' => $e->getMessage()]);
            }
        }
    }

    function actionAjaxActions()
    {
        if (!$this->Table) {
            return $this->answerVars['error'] ?? 'Таблица не найдена';
        }

        $method = $_POST['method'] ?? null;
        try {
            if ($this->onlyRead && !in_array($method,
                    ['refresh', 'csvExport', 'printTable', 'click', 'getValue', 'loadPreviewHtml', 'edit', 'checkTableIsChanged', 'getTableData', 'getEditSelect'])) return 'Ваш доступ к этой таблице - только на чтение. Обратитесь к администратору для внесения изменений';

            if (!empty($_POST['data']) && is_string($_POST['data'])) $_POST['data'] = json_decode($_POST['data'], true);

            //   if ($method != 'checkTableIsChanged') {}

            Sql::transactionStart();

            $this->Table->setFilters($_POST['filters'] ?? '');

            switch ($method) {

                case 'getTableData':
                    $table = $this->Table->getTableDataForInterface(true);


                    $result = [
                        'type' => $table['type']
                        , 'control' => ['adding' => (!($table['__blocked'] ?? null) && $table['adding'])
                            , 'deleting' => (!($table['__blocked'] ?? null) && $table['deleting'])
                            , 'duplicating' => (!($table['__blocked'] ?? null) && $table['duplicating'])
                            , 'editing' => (!($table['__blocked'] ?? null) && !$table['readOnly'])
                        ]
                        , 'tableRow' => ($this->Table->getTableRow()['type'] == 'calcs' ?
                                ['fields_sets' => $this->changeFieldsSets()] : [])
                            + $this->Table->getTableRow() +
                            (is_a($this->Table,
                                \totum\tableTypes\calcsTable::class) ? ['cycle_id' => $this->Table->getCycle()->getId()] : [])
                        , 'f' => $table['f']
                        , 'withCsvButtons' => $table['withCsvButtons']
                        , 'withCsvEditButtons' => $table['withCsvEditButtons']

                        , 'isCreatorView' => Auth::isCreator()
//                        , 'notCorrectOrder' => ($table['notCorrectOrder'] ?? false)
                        , 'fields' => $this->getFieldsForClient($table['fields'] ?? [])
                        , 'data' => []
                        , 'data_params' => []
                        , 'checkIsUpdated' => 0
                        , 'updated' => $table['updated']
                    ];

                    break;
                case 'checkUnic':
                    $result = $this->Table->checkUnic($_POST['fieldName'] ?? '', $_POST['fieldVal'] ?? '');
                    break;

                case 'edit':
                    $data = [];
                    if ($this->onlyRead) {

                        $filterFields = $this->Table->getSortedFields()['filter'] ?? [];
                        foreach ($_POST['data']["params"] as $fName => $fData) {
                            if (array_key_exists($fName, $filterFields)) {
                                $data[$fName] = $fData;
                            }
                        }
                        foreach ($_POST['data']["setValuesToDefaults"] as $fName) {
                            if (array_key_exists($fName, $filterFields)) {
                                $data["setValuesToDefaults"][] = $fName;
                            }
                        }
                        if (empty($data)) {
                            return 'Ваш доступ к этой таблице - только на чтение. Обратитесь к администратору для внесения изменений';
                        }

                    } else {
                        $data = $_POST['data'];
                    }

                    $result = $this->Table->modify(
                        $_POST['tableData'] ?? [],
                        ['modify' => $_POST['data'] ?? []]
                    );
                    break;
                case 'click':
                    $result = $this->Table->modify(
                        $_POST['tableData'] ?? [],
                        ['click' => $_POST['data'] ?? []]
                    );
                    break;
                case 'add':

                    if (Auth::$aUser->isOneCycleTable($this->Table->getTableRow())) return 'Добавление запрещено';

                    $result = $this->Table->modify(
                        $_POST['tableData'] ?? [],
                        ['add' => $_POST['data'] ?? []]
                    );
                    break;

                case 'saveOrder':
                    if (!empty($_POST['ids']) && ($orderedIds = json_decode($_POST['orderedIds'],
                            true))) {
                        $result = $this->Table->modify(
                            $_POST['tableData'] ?? [],
                            ['reorder' => $orderedIds ?? []]
                        );
                    } else throw new errorException('Таблица пуста');
                    break;
                case 'getValue':
                    $result = $this->Table->getValue($_POST['data'] ?? []);
                    break;
                case 'checkInsertRow':
                    $result = $this->Table->checkInsertRowForClient($_POST['data'] ?? [],
                        $_POST['tableData'] ?? [],
                        $_POST['savedFieldName'] ?? null);
                    break;
                case 'checkEditRow':
                    $result = $this->Table->checkEditRow($_POST['data'] ?? [], $_POST['tableData'] ?? []);
                    break;
                case 'refresh':

                    $result = $this->Table->getTableDataForRefresh();

                    break;
                case 'selectSourceTableAction':
                    $result = $this->Table->selectSourceTableAction($_POST['field_name'],
                        $_POST['data'] ?? []
                    );
                    break;
                case 'printTable':
                    $this->printTable();


                    break;

                case 'saveEditRow':
                    $result = $this->Table->modify($_POST['tableData'] ?? [],
                        ['modify' => [$_POST['data']['id'] => $_POST['data'] ?? []]]);
                    break;
                case 'getEditSelect':
                    $result = $this->Table->getEditSelect($_POST['data'] ?? [],
                        $_POST['q'] ?? '',
                        $_POST['parentId'] ?? null);
                    break;
                case 'loadPreviewHtml':
                    $result = $this->getPreviewHtml($_POST['data'] ?? []);
                    break;
                case 'delete':
                    $ids = !empty($_POST['delete_ids']) ? json_decode($_POST['delete_ids'], true) : [];
                    $result = $this->Table->modify(
                        $_POST['tableData'] ?? [],
                        ['remove' => $ids]
                    );
                    break;
                case 'duplicate':
                    $ids = !empty($_POST['duplicate_ids']) ? json_decode($_POST['duplicate_ids'], true) : [];
                    if ($ids) {
                        $Calc = new CalculateAction($this->Table->getTableRow()['on_duplicate']);

                        if (!empty($this->Table->getTableRow()['on_duplicate'])) {
                            try {
                                Sql::transactionStart();
                                $Calc->execAction('__ON_ROW_DUPLICATE',
                                    [],
                                    [],
                                    $this->Table->getTbl(),
                                    $this->Table->getTbl(),
                                    $this->Table,
                                    ['ids' => $ids]);
                                Sql::transactionCommit();
                                Controller::addLogVar($this->Table, ['__ON_ROW_DUPLICATE'], 'a', $Calc->getLogVar());

                            } catch (errorException $e) {
                                if (Auth::isCreator()) {
                                    $e->addPath('Таблица [[' . $this->Table->getTableRow()['name'] . ']]; КОД ПРИ ДУБЛИРОВАНИИ');
                                } else {
                                    $e->addPath('Таблица [[' . $this->Table->getTableRow()['title'] . ']]; КОД ПРИ ДУБЛИРОВАНИИ');
                                }
                                Controller::addLogVar($this->Table, ['__ON_ROW_DUPLICATE'], 'a', $Calc->getLogVar());
                                throw $e;
                            }


                        } else {
                            $result = $this->Table->modify(
                                $_POST['tableData'] ?? [],
                                ['channel' => 'inner', 'duplicate' => ['ids' => $ids, 'replaces' => $_POST['data']], 'addAfter' => ($_POST['insertAfter'] ?? null)]);
                        }
                        $result = $this->Table->getTableDataForRefresh();


                    }
                    break;
                case 'refresh_rows':
                    $ids = !empty($_POST['refreash_ids']) ? json_decode($_POST['refreash_ids'], true) : [];
                    $result = $this->Table->modify(
                        $_POST['tableData'] ?? [],
                        ['refresh' => $ids]
                    );
                    break;
                case 'csvExport':
                    if (Table::isUserCanAction('csv', $this->Table->getTableRow())) {
                        $result = $this->Table->csvExport(
                            $_POST['tableData'] ?? [],
                            $_POST['sorted_ids'] ?? '[]',
                            json_decode($_POST['visibleFields'] ?? '[]', true)
                        );
                    } else throw new errorException('У вас нет доступа для csv-выкрузки');
                    break;
                case 'csvImport':
                    if (Table::isUserCanAction('csv_edit', $this->Table->getTableRow())) {
                        $result = $this->Table->csvImport($_POST['tableData'] ?? [],
                            $_POST['csv'] ?? '',
                            $_POST['answers'] ?? []);
                    } else throw new errorException('У вас нет доступа для csv-изменений');
                    break;
                default:
                    $result = ['error' => 'Метод [[' . $method . ']] в этом модуле не определен'];
            }


            if ($links = Controller::getLinks()) {
                $result['links'] = $links;
            }
            if ($panels = Controller::getPanels()) {
                $result['panels'] = $panels;
            }
            if ($links = Controller::getInterfaceDatas()) {
                $result['interfaceDatas'] = $links;
            }

            if (!empty($result)) $this->__setAnswerArray($result);

            Sql::transactionCommit();
        } catch (errorException $exception) {
            return ['error' => $exception->getMessage() . "<br/>" . $exception->getPathMess()];
        }

    }

    function actionMain()
    {
        if (!$this->Table) return;
        $this->Table->setFilters($_GET['f'] ?? '');
        $tableData = $this->Table->getTableDataForInterface();

        $tableData['fields']=$this->getFieldsForClient($tableData['fields']);
        $this->__addAnswerVar('table', $tableData);
        $this->__addAnswerVar('error', $tableData['error'] ?? null);
        $this->__addAnswerVar('onlyRead', $tableData['onlyRead'] ?? $this->onlyRead);
    }

    private
    function printTable()
    {
        $template = ['styles' => '@import url("https://fonts.googleapis.com/css?family=Open+Sans:400,600|Roboto:400,400i,700,700i,500|Roboto+Mono:400,700&amp;subset=cyrillic");
body { font-family: \'Roboto\', sans-serif;}
table{ border-spacing: 0; border-collapse: collapse; margin-top: 20px;table-layout: fixed; width: 100%}
table tr td{ border: 1px solid gray; padding: 3px; overflow: hidden;text-overflow: ellipsis}
table tr td.title{font-weight: bold}', 'html' => '{table}'];


        if (Table::getTableRowByName('print_templates')) {
            $template = Model::init('print_templates')->get(['name' => 'main'], 'styles, html') ?? $template;
        }


        $settings = json_decode($_POST['settings'], true);
        $tableAll = ['<h1>' . $this->Table->getTableRow()['title'] . '</h1>'];

        $sosiskaMaxWidth = $settings['sosiskaMaxWidth'];
        $fields = array_intersect_key($this->Table->getFields(), $settings['fields']);

        $result = $this->Table->getTableDataForPrint($settings['ids'], array_keys($fields));

        $getTdTitle = function ($field, $withWidth = true) {
            $title = htmlspecialchars($field['title']);
            if (!empty($field['unitType'])) {
                $title .= ', ' . $field['unitType'];
            }

            return '<td'
                . ($withWidth ? ' style="width: ' . $field['width'] . 'px;"' : '')
                . ' class="title">' . $title . '</td>';
        };


        foreach (['param', 'filter'] as $category) {
            $table = [];
            $width = 0;

            foreach ($fields as $field) {
                if ($field['category'] == $category) {
                    if (!$table || $field['tableBreakBefore'] || $width > $sosiskaMaxWidth) {
                        $width = $settings['fields'][$field['name']];
                        if ($table) {
                            $tableAll[] = $table[0] . $width . $table[1] . implode('',
                                    $table['head']) . $table[2] . implode('',
                                    $table['body']) . $table[3];
                        }
                        $table = ['<table style="width: ', 'px;"><thead><tr>', 'head' => [], '</tr></thead><tbody><tr>', 'body' => [], '</tr></tbody></table>'];

                    } else {
                        $width += $settings['fields'][$field['name']];
                    }

                    $table['head'][] = $getTdTitle($field);
                    $table['body'][] = '<td class="f-' . $field['type'] . ' n-' . $field['name'] . '"><span>' . $result['params'][$field['name']]['v'] . '</span></td>';

                }
            }
            if ($table) {
                $tableAll[] = $table[0] . $width . $table[1] . implode('',
                        $table['head']) . $table[2] . implode('',
                        $table['body']) . $table[3];
            }
        }

        $table = [];
        $width = 0;
        foreach ($fields as $field) {
            if ($field['category'] == 'column') {
                if (!$table) {
                    $table = ['<table style="width: ', 'px;"><thead><tr>', 'head' => [], '</tr></thead><tbody><tr>', 'body' => [], '</tr></tbody></table>'];
                    if (array_key_exists('id', $settings['fields'])) {
                        $table['head'][] = '<td style="width: ' . $settings['fields']['id'] . 'px;" class="title">id</td>';
                        $width += $settings['fields']['id'];
                    }
                }
                $table['head'][] = $getTdTitle($field);
                $width += $settings['fields'][$field['name']];
            }
        }
        if ($table) {
            foreach ($result['rows'] as $id => $row) {

                $tr = '<tr>';
                if (array_key_exists('id', $settings['fields'])) {
                    $tr .= '<td class="f-id"><span>' . $id . '</span></td>';
                }
                foreach ($fields as $field) {
                    if ($field['category'] == 'column') {
                        $tr .= '<td class="f-' . $field['type'] . ' n-' . $field['name'] . '"><span>' . $row[$field['name']]['v'] . '</span></td>';
                    }
                }
                $tr .= '</tr>';
                $table['body'][] = $tr;
            }


            if ($columnFooters = array_filter($fields,
                function ($field) use ($fields) {
                    if ($field['category'] == 'footer' && $field['column'] && array_key_exists($field['column'],
                            $fields)) return true;
                })) {
                while ($columnFooters) {
                    $tr_names = '<tr>';
                    $tr_values = '<tr>';
                    foreach ($fields as $field) {
                        if ($field['category'] == 'column') {
                            $column = $field['name'];

                            if ($thisColumnFooters = array_filter($columnFooters,
                                function ($field) use ($column) {
                                    if ($field['column'] == $column) return true;
                                })) {
                                $name = array_keys($thisColumnFooters)[0];
                                $thisColumnFooter = $columnFooters[$name];

                                $tr_names .= $getTdTitle($thisColumnFooter, false);
                                $tr_values .= '<td class="f-' . $thisColumnFooter['type'] . ' n-' . $thisColumnFooter['name'] . '">' . $result['params'][$thisColumnFooter['name']]['v'] . '</td>';

                                unset($columnFooters[$name]);

                            } else {
                                $tr_names .= '<td></td>';
                                $tr_values .= '<td></td>';
                            }
                        }
                    }
                    $tr_names .= '</tr>';
                    $tr_values .= '</tr>';
                    $table['body'][] = $tr_names;
                    $table['body'][] = $tr_values;
                    unset($tr_names);
                    unset($tr_values);
                }
            }

            $tableAll[] = $table[0] . $width . $table[1] . implode('',
                    $table['head']) . $table[2] . implode('',
                    $table['body']) . $table[3];
        }


        $table = [];
        $width = 0;


        foreach ($fields as $field) {
            if ($field['category'] == 'footer' && empty($field['column'])) {

                if (!$table || $field['tableBreakBefore'] || $width > $sosiskaMaxWidth) {
                    if ($table) {
                        $tableAll[] = $table[0] . $width . $table[1] . implode('',
                                $table['head']) . $table[2] . implode('',
                                $table['body']) . $table[3];
                    }

                    $width = $settings['fields'][$field['name']];
                    $table = ['<table style="width: ', 'px;"><thead><tr>', 'head' => [], '</tr></thead><tbody><tr>', 'body' => [], '</tr></tbody></table>'];

                } else {
                    $width += $settings['fields'][$field['name']];
                }

                $table['head'][] = $getTdTitle($field);
                $table['body'][] = '<td class="f-' . $field['type'] . ' n-' . $field['name'] . '"><span>' . $result['params'][$field['name']]['v'] . '</span></td>';
            }
        }
        if ($table) {
            $tableAll[] = $table[0] . $width . $table[1] . implode('',
                    $table['head']) . $table[2] . implode('',
                    $table['body']) . $table[3];
        }

        $style = $template['styles'];
        $body = str_replace(
            '{table}',
            '<div class="table-' . $this->Table->getTableRow()['name'] . '">' . implode('', $tableAll) . '</div>',
            $template['html']);

        Controller::addToInterfaceDatas('print',
            [
                'styles' => $style,
                'body' => $body
            ]);
    }

    protected
    function checkTableByUri()
    {
        $uri = preg_replace('/\?.*/', '', $this->inModuleUri);

        if ($this->inModuleUri && $tableId = $uri) {
            if (!array_key_exists($tableId, Auth::$aUser->getTables())) {
                $this->__addAnswerVar('error', 'Доступ к таблице запрещен');
            } else {
                $tableRow = Table::getTableRowById($tableId);
                $extradata = null;
                if ($tableRow['type'] != 'tmp') {
                    $this->__addAnswerVar('error', 'Доступ через модуль только для временных таблиц');
                } else {

                    $this->onlyRead = Auth::$aUser->getTables()[$tableId] == 0;
                    if ($this->isAjax && empty($_POST['tableData']['sess_hash'])) {
                        $this->__addAnswerVar('error', 'Ошибка доступа к таблице');
                    } else {
                        $extradata = $_POST['tableData']['sess_hash'] ?? $_GET['sess_hash'] ?? null;
                        $this->Table = tableTypes::getTable($tableRow, $extradata);
                        $this->Table->setNowTable();
                        if (!$this->isAjax && !$extradata) {

                            $add_tbl_data = [];
                            $add_tbl_data["params"] = [];
                            if (key_exists('h_get', $this->Table->getFields())) {
                                $add_tbl_data["params"]['h_get'] = $_GET;
                            }
                            if (key_exists('h_post', $this->Table->getFields())) {
                                $add_tbl_data["params"]['h_post'] = $_POST;
                            }
                            if (key_exists('h_input', $this->Table->getFields())) {
                                $add_tbl_data["params"]['h_input'] = file_get_contents('php://input');
                            }
                            if (!empty($_GET['d']) && ($d = Crypt::getDeCrypted($_GET['d'],
                                    false)) && ($d = json_decode($d, true))) {
                                if (!empty($d['d'])) {
                                    $add_tbl_data["tbl"] = $d['d'];
                                }
                                if (!empty($d['p'])) {
                                    $add_tbl_data["params"] = $d['p'] + $add_tbl_data["params"];
                                }
                            }
                            if ($add_tbl_data) {
                                $this->Table->addData($add_tbl_data);
                            }
                        }
                    }
                }
            }
        } else {
            $this->__addAnswerVar('error', 'Неверный путь к таблице');
        }

    }
}