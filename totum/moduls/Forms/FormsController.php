<?php


namespace totum\moduls\Forms;


use Psr\Http\Message\ServerRequestInterface;
use totum\common\Auth;
use totum\common\calculates\CalculateAction;
use totum\common\calculates\CalculcateFormat;
use totum\common\Crypt;
use totum\common\errorException;
use totum\common\Field;
use totum\common\controllers\interfaceController;
use totum\common\Model;
use totum\fieldTypes\Select;
use totum\models\Table;
use totum\tableTypes\aTable;
use totum\tableTypes\tmpTable;

class FormsController extends interfaceController
{
    const __isAuthNeeded = false;
    private static $path;

    /**
     * @var aTable
     */
    protected $Table;
    protected $onlyRead;
    private $css;
    /**
     * @var array
     */
    private $FormsTableData;
    private $_INPUT;
    private $clientFields;
    /**
     * @var array
     */
    private $sections;
    /**
     * @var CalculcateFormat
     */
    private $CalcTableFormat;
    /**
     * @var CalculcateFormat
     */
    private $CalcRowFormat;
    private $CalcFieldFormat;
    /**
     * @var Calculate
     */
    private $CalcSectionStatuses;

    public function __construct($modulName, $inModuleUri, \totum\config\Conf $Config)
    {
        // Allow from any origin
        if (isset($_SERVER['HTTP_ORIGIN'])) {
            // should do a check here to match $_SERVER['HTTP_ORIGIN'] to a
            // whitelist of safe domains
            header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Max-Age: 86400');    // cache for 1 day
        }
// Access-Control headers are received during OPTIONS requests
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {

            if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
                header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");

            if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
                header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
            die;

        }

        $this->_INPUT = json_decode(file_get_contents('php://input'), true);
        $_REQUEST['ajax'] = true;

        parent::__construct($modulName, $inModuleUri, $Config);
        static::$pageTemplate = __DIR__ . '/__template.php';
    }

    function doIt(ServerRequestInterface $request, bool $output)
    {
        $action = 'Actions';
        try {
            $this->FormsTableData = $this->checkTableByStr($this->inModuleUri);

            Auth::loadAuthUser($this->FormsTableData['call_user'], false);
        } catch (errorException $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    function actionAjaxActions()
    {

        $method = $this->_INPUT['method'] ?? null;
        $_POST = $this->_INPUT;

        $this->loadTable($this->FormsTableData);

        if (!$this->Table) {
            return $this->answerVars['error'] ?? 'Таблица не найдена';
        }


        try {
            if ($this->onlyRead && !in_array($method,
                    ['refresh', 'printTable', 'click', 'getValue', 'loadPreviewHtml', 'edit', 'checkTableIsChanged', 'getTableData', 'getEditSelect']))
                return 'Ваш доступ к этой таблице - только на чтение. Обратитесь к администратору для внесения изменений';


            Sql::transactionStart();

            $this->Table->setFilters($_POST['filters'] ?? '');

            switch ($method) {

                case 'getTableData':
                    $result = $this->getTableData();

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

                    $result = $this->modify(
                        $_POST['tableData'] ?? [],
                        ['modify' => $data ?? []]
                    );
                    break;
                case 'click':
                    $result = $this->modify(
                        $_POST['tableData'] ?? [],
                        ['click' => $_POST['data'] ?? []]
                    );
                    break;
                case 'add':
                    $result = $this->modify(
                        $_POST['tableData'] ?? [],
                        ['add' => $_POST['data'] ?? []]
                    );
                    break;

                case 'saveOrder':
                    if (!empty($_POST['ids']) && ($orderedIds = json_decode($_POST['orderedIds'],
                            true))) {
                        $result = $this->modify(
                            $_POST['tableData'] ?? [],
                            ['reorder' => $orderedIds ?? []]
                        );
                    } else throw new errorException('Таблица пуста');
                    break;
                case 'getValue':
                    $result = $this->Table->getValue($_POST['data'] ?? []);
                    break;
                case 'checkInsertRow':
                    $result = ['row' => $this->Table->checkInsertRow($_POST['data'] ?? [], '')];
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
                case 'getEditSelect':
                    $result = $this->getEditSelect(['field' => $_POST['data']['field'], 'item' => $_POST['data']['item']],
                        $_POST['data']['q'] ?? '',
                        $_POST['data']['parentId'] ?? null,
                        $_POST['data']['viewtype'] ?? null);
                    break;
                case 'loadPreviewHtml':
                    $result = $this->getPreviewHtml($_POST['data'] ?? []);
                    break;
                case 'delete':
                    $result = $this->modify(
                        $_POST['tableData'] ?? [],
                        ['remove' => $_POST['data']]
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
                            $result = $this->modify(
                                $_POST['tableData'] ?? [],
                                ['channel' => 'inner', 'duplicate' => ['ids' => $ids, 'replaces' => $_POST['data']], 'addAfter' => ($_POST['insertAfter'] ?? null)]);
                        }
                        $result = $this->Table->getTableDataForRefresh();


                    }
                    break;
                case 'refresh_rows':
                    $ids = !empty($_POST['refreash_ids']) ? json_decode($_POST['refreash_ids'], true) : [];
                    $result = $this->modify(
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
            return ['error' => $exception->getMessage()];
        }
    }

    function getEditSelect($data, $q, $parentId, $viewtype = null)
    {

        $type = $viewtype;

        $fields = $this->Table->getFields();

        if (!($field = $fields[$data['field']] ?? null))
            throw new errorException('Не найдено поле [[' . $data['field'] . ']]. Возможно изменилась структура таблицы. Перегрузите страницу');
        if (!in_array($field['type'],
            ['select', 'tree'])) throw new errorException('Ошибка - поле не типа select/tree');

        $this->Table->loadDataRow();


        $row = $data['item'];
        foreach ($row as $k => &$v) {
            if ($k !== 'id') {
                $v = ['v' => $v];
            }
        }

        $row = $row + $this->Table->getTbl()['params'];


        /** @var Select $Field */
        $Field = Field::init($field, $this->Table);

        if ($type) {
            $list = [];
            $indexed = [];
            foreach ($Field->calculateSelectListWithPreviews($row[$field['name']],
                $row,
                $this->Table->getTbl()) as $val => $data) {
                if (!empty($data['2'])) {
                    $data['2'] = $data['2']();
                }

                $val = strval($val);

                $list[] = $val;
                $indexed[$val] = $data;
                switch ($type) {
                    case 'checkboxpicture':
                        foreach ($indexed[$val][array_key_last($indexed[$val])] as $kPreview => $vls) {
                            switch ($vls[2]) {
                                case 'file':
                                    $pVal = $this->_getHttpFilePath() . $vls[1][0]['file'];
                                    break;
                                default:
                                    $pVal = $vls[1];
                            }
                            $indexed[$val][array_key_last($indexed[$val])][$kPreview] = $pVal;
                        }

                        break;
                    default:
                        foreach ($indexed[$val][array_key_last($indexed[$val])] as $name => &$vls) {
                            switch ($vls[2]) {
                                case 'file':
                                    foreach ($vls[1] as &$f) {
                                        $f = $this->_getHttpFilePath() . $f['file'];
                                    }
                                    unset($f);
                                    break;
                            }
                            array_unshift($vls, $name);
                        }
                        $indexed[$val][array_key_last($indexed[$val])] = array_values($indexed[$val][array_key_last($indexed[$val])]);
                        unset($vls);
                }
            }


            return ['indexed' => $indexed, 'list' => $list, 'sliced' => false];
        }
        $list = $Field->calculateSelectList($row[$field['name']], $row, $this->Table->getTbl());

        return $Field->cropSelectListForWeb($list, $row[$field['name']]['v'], $q, $parentId);
    }

    static private function _getHttpFilePath()
    {
        return static::$path ?? (static::$path = (
                (!empty($_SERVER['HTTPS']) && 'off' !== strtolower($_SERVER['HTTPS']) ? 'https://' : 'http://') . \totum\config\Conf::getFullHostName() . '/fls/'
            ));

    }

    function actionMain()
    {
        $this->__addAnswerVar('css', $this->FormsTableData['css']);
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
                if ($field['category'] === $category) {
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
            if ($field['category'] === 'column') {
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
    function checkTableByStr($form)
    {
        if ($form) {
            Auth::ServiceUserStart();
            $tableData = tableTypes::getTableByName('ttm__forms')->getByParams(
                ['where' => [
                    ['field' => 'path_code', 'operator' => '=', 'value' => $form],
                    ['field' => 'on_off', 'operator' => '=', 'value' => true]],
                    'field' => ['table_name', 'call_user', 'css', 'format_static', 'fields_else_params', 'section_statuses_code']],
                'row');
            if (!$tableData) {
                $this->__addAnswerVar('error', 'Доступ к таблице запрещен');
            } else {
                return $tableData;
            }
        } else {
            throw new errorException('Неверный путь к таблице');
        }
    }

    private function loadTable($tableData)
    {
        $tableRow = Table::getTableRowByName($tableData['table_name']);
        if (!key_exists($tableRow['id'], Auth::$aUser->getTables())) {
            throw new errorException('Ошибка настройки формы - пользователю запрещен доступ к таблице');
        }

        $extradata = null;

        $extradata = $_POST['sess_hash'] ?? $_GET['sess_hash'] ?? null;
        if ($tableRow['type'] === 'tmp' && $extradata) {
            if (!tmpTable::checkTableExists($tableRow['name'], $extradata)) {
                $extradata = null;
            }
        }

        $this->Table = tableTypes::getTable($tableRow, $extradata);
        $this->Table->setNowTable();

        $this->onlyRead = (Auth::$aUser->getTables()[$this->Table->getTableRow()['id']] ?? null) !== 1;

        if (!$extradata) {
            $add_tbl_data = [];
            $add_tbl_data["params"] = [];
            if (key_exists('h_get', $this->Table->getFields())) {
                $add_tbl_data["params"]['h_get'] = $_POST['get'] ?? [];
            }
            if (key_exists('h_post', $this->Table->getFields())) {
                $add_tbl_data["params"]['h_post'] = $_POST['post'] ?? [];
            }
            if (key_exists('h_input', $this->Table->getFields())) {
                $add_tbl_data["params"]['h_input'] = $_POST['input'] ?? '';
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
            if ($add_tbl_data && $this->Table->getTableRow()['type'] === 'tmp') {
                $this->Table->addData($add_tbl_data);
            }
        }


        $visibleFields = [];
        foreach ($this->Table->getFields() as $field) {
            if ($field['category'] === 'column') continue;

            if (!Auth::isCreator() && !empty($field['webRoles']) && $field['category'] !== 'filter') {
                if (count(array_intersect($field['webRoles'], Auth::$aUser->getRoles())) == 0) {
                    $field['showInWeb'] = false;
                }
            }

            if (!empty($field['addRoles'])) {
                if (count(array_intersect($field['addRoles'], Auth::$aUser->getRoles())) == 0) {
                    $field['insertable'] = false;
                }
            }
            if (!empty($field['editRoles'])) {
                if (count(array_intersect($field['editRoles'], Auth::$aUser->getRoles())) == 0) {
                    $field['editable'] = false;
                }
            }

            if ($field['showInWeb'] ?? false) {
                $visibleFields[$field['name']] = $field;
            }
        }

        $clientFields = $this->fieldsForClient($visibleFields);

        foreach ($clientFields as &$field) {
            $field = array_replace($field, $this->FormsTableData['fields_else_params'][$field['name']] ?? []);

            if ($field['showInWebOtherPlacement'] ?? null) {
                $field['category'] = $field['showInWebOtherPlacement'];
                unset($field['showInWebOtherPlacement']);
            }
            if ($field['showInWebOtherOrd'] ?? null) {
                $field['ord'] = $field['showInWebOtherOrd'];
                unset($field['showInWebOtherOrd']);
            }
        }
        unset($field);
        $fields = array_column($clientFields, 'ord');
        array_multisort($fields, SORT_ASC, SORT_NUMERIC, $clientFields);
        $this->clientFields = $clientFields;


        $sections = [];
        foreach ($this->clientFields as $field) {
            switch ($field["category"]) {
                case "footer":
                    /*if ($field['column']) {
                        $field["category"] = "rows_footer";
                    }*/
                case 'param':

                    if ($field["tableBreakBefore"] && $field["sectionTitle"] ?? false) {
                        $name = null;
                        if (preg_match('/\*\*.*?name\s*:\s*([a-z_\-0-9]+)/i', $field['sectionTitle'], $matches)) {
                            $name = $matches[1];
                        }
                        $sections[$field["category"]][] = ['name' => $name, 'title' => $field['sectionTitle'], 'fields' => []];
                    } elseif (empty($sections[$field["category"]])) {
                        $sections[$field["category"]] [] = ['name' => null, 'title' => "", 'fields' => []];
                    }

                    $sections[$field["category"]][count($sections[$field["category"]]) - 1]['fields'][] = $field['name'];
                    break;

                default:

                    /*if (empty($sections['rows'])) {
                        $sections['rows'] = ['name' => "", 'title' => "", 'fields' => []];
                    }
                    $sections['rows']['fields'][] = $field['name'];*/
            }
        }
        $this->sections = $sections;

        $this->CalcTableFormat = new CalculcateFormat($this->Table->getTableRow()['table_format']);
        $this->CalcRowFormat = new CalculcateFormat($this->Table->getTableRow()['row_format']);

        if ($this->FormsTableData['section_statuses_code'] && !preg_match('/^\s*=\s*:\s*$/',
                $this->FormsTableData['section_statuses_code'])) {
            $this->CalcSectionStatuses = new Calculate($this->FormsTableData['section_statuses_code']);

        }

    }

    private function modify(array $tableData, array $data)
    {
        $modify = $data['modify'] ?? [];
        $remove = $data['remove'] ?? [];
        $add = $data['add'] ?? null;
        $duplicate = $data['duplicate'] ?? [];
        $reorder = $data['reorder'] ?? [];

        $tableRow = $this->Table->getTableRow();

        if ($add && !Table::isUserCanAction('insert',
                $tableRow)) throw new errorException('Добавление в эту таблицу вам запрещено');
        if ($remove && !Table::isUserCanAction('delete',
                $tableRow)) throw new errorException('Удаление из этой таблицы вам запрещено');
        if ($duplicate && !Table::isUserCanAction('duplicate',
                $tableRow)) throw new errorException('Дублирование в этой таблице вам запрещено');
        if ($reorder && !Table::isUserCanAction('reorder',
                $tableRow)) throw new errorException('Сортировка в этой таблице вам запрещена');

        $click = $data['click'] ?? [];
        $refresh = $data['refresh'] ?? [];


        //checkTableUpdated($tableData);

        $inVars = [];
        $inVars['modify'] = [];
        $inVars['channel'] = $data['channel'] ?? 'web';
        if (!empty($modify['setValuesToDefaults'])) {
            unset($modify['setValuesToDefaults']);
            $inVars['setValuesToDefaults'] = $modify;
        } else {
            $inVars['modify'] = $modify;
        }


        $inVars['calculate'] = aTable::CalcInterval['changed'];

        if ($refresh) {
            $inVars['modify'] = $inVars['modify'] + array_flip($refresh);
        }
        $fieldFormatEditable = $this->getTableFormats(true,
            array_intersect_key($this->Table->getTbl()['rows'], $inVars['modify']));

        if (empty($fieldFormatEditable['t']['blockadd']))
            $inVars['add'] = !is_null($add) ? [$add] : [];
        if (empty($fieldFormatEditable['t']['blockdelete']))
            $inVars['remove'] = $remove;
        if (empty($fieldFormatEditable['t']['blockduplicate'])) {
            $inVars['duplicate'] = $duplicate;
        }
        if (empty($fieldFormatEditable['t']['blockorder']))
            $inVars['reorder'] = $reorder;

        if (!empty($data['addAfter']) && in_array($data['addAfter'],
                $duplicate['ids']) && !(empty($inVars['duplicate']))) {
            $inVars['addAfter'] = $data['addAfter'];
        }

        foreach ($inVars['modify'] as $itemId => &$editData) {//Для  saveRow
            if ($itemId == 'params') {
                foreach ($editData as $k => $v) {
                    if (!key_exists($k, $fieldFormatEditable)) {
                        unset($inVars['modify']['params'][$k]);
                    }
                }
                continue;
            }
            if (!is_array($editData)) {//Для  refresh
                $editData = [];
                continue;
            }
            if (!key_exists($itemId, $fieldFormatEditable)) {
                unset($inVars['modify'][$itemId]);
            }

            foreach ($editData as $k => &$v) {
                if (!key_exists($k, $fieldFormatEditable[$itemId])) {
                    unset($editData[$k]);
                    continue;
                }
                if (is_array($v) && array_key_exists('v', $v)) {
                    if (array_key_exists('h', $v)) {
                        if ($v['h'] == false) {
                            $inVars['setValuesToDefaults'][$itemId][$k] = true;
                            unset($editData[$k]);
                            continue;
                        }
                    }
                    $v = $v['v'];
                }
            }
        }
        unset($editData);
        $return = ['chdata' => []];
        if ($click) {

            $this->Table->reCalculateFilters('web');

            $clickItem = array_key_first($click);
            $clickFieldName = array_key_first($click[$clickItem]);
            if ($clickItem === 'params') {
                if (!key_exists($clickFieldName,
                        $fieldFormatEditable) && !($this->clientFields[$clickFieldName]['pressableOnOnlyRead'] ?? false))
                    throw new errorException('Таблица была изменена. Обновите таблицу для проведения изменений');
                $row = $this->Table->getTbl()['params'];
            } else {
                if (!key_exists($clickFieldName, $fieldFormatEditable[$clickItem] ?? []))
                    throw new errorException('Таблица была изменена. Обновите таблицу для проведения изменений');

                $row = $this->tbl['rows'][$clickItem] ?? null;
                if (!$row || !empty($row['is_del'])) throw new errorException('Таблица была изменена. Обновите таблицу для проведения изменений');
            }
            try {


                Field::init($this->Table->getFields()[$clickFieldName], $this->Table)->action($row,
                    $row,
                    $this->Table->getTbl(),
                    $this->Table->getTbl(),
                    ['ids' => $click['checked_ids'] ?? []]);

            } catch (\ErrorException $e) {
                throw $e;
            }

            $return['ok'] = 1;
        } else {
            $this->Table->reCalculateFromOvers($inVars);
        }


        $return['chdata']['rows'] = [];

        if ($this->Table->getChangeIds()['added']) {
            $return['chdata']['rows'] = array_intersect_key($this->Table->getTbl()['rows'],
                $this->Table->getChangeIds()['added']);
        }

        if ($this->Table->getChangeIds()['deleted']) {
            $return['chdata']['deleted'] = array_keys($this->Table->getChangeIds()['deleted']);
        }
        $modify = $inVars['modify'];
        unset($modify['params']);


        $fieldFormatS = $this->getTableFormats(false, $this->Table->getTbl()['rows']);

        if (($modify += $this->Table->getChangeIds()['changed']) && $fieldFormatS['r']) {
            foreach ($modify as $id => $changes) {
                if (empty($this->Table->getTbl()['rows'][$id]) || !empty($fieldFormatS['r'][$id]['hidden'])) continue;
                $return['chdata']['rows'][$id] = $this->Table->getTbl()['rows'][$id];
                $return['chdata']['rows'][$id]['id'] = $id;
            }
        }


        $return['chdata']['params'] = array_intersect_key($this->Table->getTbl()['params'], $fieldFormatS['p']);
        $return['chdata'] = $this->getValuesForClient($return['chdata'], $fieldFormatS);
        $return['chdata']['f'] = $fieldFormatS;

        $return['updated'] = $this->Table->getSavedUpdated();

        return $return;
    }

    private function getTableFormats(bool $onlyEditable, $rows)
    {
        $tableFormats = $this->CalcTableFormat->getFormat('TABLE', [], $this->Table->getTbl(), $this->Table);
        $tableJsonFromRow = $this->FormsTableData['format_static'];

        if ($this->CalcSectionStatuses) {
            $sectionFormats = $this->CalcSectionStatuses->exec([],
                null,
                [],
                $this->Table->getTbl()['params'],
                [],
                $this->Table->getTbl(),
                $this->Table);
            if ($sectionFormats && is_array($sectionFormats)) {
                foreach ($sectionFormats as $k => $status) {
                    $tableFormats['sections'][$k]['status'] = $status;
                }
            }
        }


        if (!empty($tableFormats['rowstitle'])) {

            if (preg_match('/^([a-z0-9_]{1,})\s*\:\s*(.*)/', $tableFormats['rowstitle'], $matches)) {
                $tableFormats['rowsTitle'] = $matches[2];
                $tableFormats['rowsName'] = $matches[1];
            } else {
                $tableFormats['rowsName'] = "";
                $tableFormats['rowsTitle'] = $tableFormats['rowstitle'];
            }

        }
        unset($tableFormats['rowstitle']);

        /*2-edit; 1- view; 0 - hidden*/
        $getSectionEditType = function ($sectionName) use ($tableFormats) {
            if (!$sectionName || !key_exists($sectionName,
                    $tableFormats['sections'])) return 2;
            switch ($tableFormats['sections'][$sectionName]['status'] ?? null) {
                case 'edit':
                    return 2;
                case 'view':
                    return 1;
                default:
                    return 0;
            }

        };


        if ($onlyEditable) {
            $result = [];
            if (!$this->onlyRead) {
                foreach ($this->sections as $category => $sec) {
                    switch ($category) {
                        case 'rows':
                            $section = $sec;
                            if ($st = $getSectionEditType($section['name'])) {

                                /*Если секция редактируемая*/
                                if ($st == 2)

                                    foreach ($rows as $row) {
                                        $rowFormat = $this->CalcRowFormat->getFormat('ROW',
                                            $row,
                                            $this->Table->getTbl(),
                                            $this->Table);
                                        if (empty($rowFormat['block'])) {
                                            foreach ($section['fields'] as $fieldName) {

                                                if (!key_exists($fieldName, $this->clientFields)) continue;

                                                $FieldFormat = $this->CalcFieldFormat[$fieldName]
                                                    ?? ($this->CalcFieldFormat[$fieldName]
                                                        = new CalculcateFormat($this->Table->getFields()[$fieldName]['format']));
                                                $format = $FieldFormat->getFormat($fieldName,
                                                    $row,
                                                    $this->Table->getTbl(),
                                                    $this->Table);

                                                if (empty($format['block']) && empty($format['hidden'])) {
                                                    $result[$row['id']][$fieldName] = true;
                                                }
                                            }
                                        }
                                    }
                            }
                            break;
                        default:
                            foreach ($sec as $section) {

                                if ($st = $getSectionEditType($section['name'])) {
                                    /*Если секция редактируемая*/
                                    if ($st == 2)
                                        foreach ($section['fields'] as $fieldName) {
                                            if (!key_exists($fieldName, $this->clientFields)) continue;

                                            $FieldFormat = $this->CalcFieldFormat[$fieldName]
                                                ?? ($this->CalcFieldFormat[$fieldName]
                                                    = new CalculcateFormat($this->Table->getFields()[$fieldName]['format']));
                                            $format = $FieldFormat->getFormat($fieldName,
                                                $this->Table->getTbl()['params'],
                                                $this->Table->getTbl(),
                                                $this->Table);

                                            if (empty($format['block']) && empty($format['hidden'])) {
                                                $result[$fieldName] = true;
                                            }
                                        }
                                }
                            }
                            break;
                    }
                }
            }


            $result = ['t' => $tableFormats] + $result;
        } else {


            $result = ['t' => $tableFormats, 'r' => [], 'p' => []];

            foreach ($this->sections as $category => $sec) {

                switch ($category) {
                    /*case 'rows':
                        $section = $sec;
                        $sectionStatus = $getSectionEditType($section['name']);
                        foreach ($rows as $row) {
                            $rowFormat = $this->CalcRowFormat->getFormat('ROW',
                                $row,
                                $this->Table->getTbl(),
                                $this->Table);

                            $result['r'][$row['id']]['f'] = $rowFormat;

                            foreach ($section['fields'] as $fieldName) {

                                if (!key_exists($fieldName, $this->clientFields)) continue;
                                if (!$sectionStatus) {
                                    $result['r'][$row['id']][$fieldName] = [];
                                } else {

                                    $FieldFormat = $this->CalcFieldFormat[$fieldName]
                                        ?? ($this->CalcFieldFormat[$fieldName]
                                            = new CalculcateFormat($this->Table->getFields()[$fieldName]['format']));
                                    $format = $FieldFormat->getFormat($fieldName,
                                        $row,
                                        $this->Table->getTbl(),
                                        $this->Table);

                                    if (empty($format['hidden'])) {
                                        $result['r'][$row['id']][$fieldName] = $format;
                                    } else {
                                        $result['r'][$row['id']][$fieldName] = ['hidden' => true];
                                    }
                                }
                            }
                        }
                        break;*/
                    default:
                        foreach ($sec as $section) {
                            $sectionStatus = $getSectionEditType($section['name']);
                            foreach ($section['fields'] as $fieldName) {

                                if (!key_exists($fieldName, $this->clientFields)) continue;

                                if ($sectionStatus) {

                                    $FieldFormat = $this->CalcFieldFormat[$fieldName]
                                        ?? ($this->CalcFieldFormat[$fieldName]
                                            = new CalculcateFormat($this->Table->getFields()[$fieldName]['format']));
                                    $format = $FieldFormat->getFormat($fieldName,
                                        $this->Table->getTbl()['params'],
                                        $this->Table->getTbl(),
                                        $this->Table);
                                    if (empty($format['hidden'])) {
                                        $result['p'][$fieldName] = $format;
                                    } else {
                                        $result['p'][$fieldName] = ['hidden' => true];
                                    }
                                } else {
                                    $result['p'][$fieldName] = [];
                                }
                            }
                        }
                        break;
                }
            }
            $result['t']['s'] = $result['t']['sections'] ?? [];
            unset($result['t']['sections']);

        }
        return array_replace_recursive($tableJsonFromRow, $result);
    }

    private
    function getValuesForClient($data, &$formats, $isFirstLoad = false)
    {
        /*foreach (($data['rows'] ?? []) as $i => $row) {

            $newRow = ['id' => ($row['id'] ?? null)];
            if (!empty($row['InsDel'])) {
                $newRow['InsDel'] = true;
            }
            foreach ($row as $fName => $value) {
                if (key_exists($fName, $this->Table->getFields())) {
                    Field::init($this->Table->getFields()[$fName], $this->Table)->addViewValues('edit',
                        $value,
                        $row,
                        $this->Table->getTbl());
                    $newRow[$fName] = $value;
                }
            }
            $data['rows'][$i] = $newRow;
        }*/
        if (!empty($data['params'])) {
            foreach ($data['params'] as $fName => &$value) {
                $field = $this->Table->getFields()[$fName];

                switch ($field['type']) {
                    case 'file':
                        Field::init($field, $this->Table)->addViewValues('edit',
                            $value,
                            $this->Table->getTbl()['params'],
                            $this->Table->getTbl());

                        $value['v_'] = [];
                        foreach ($value['v'] as $val) {
                            $value['v_'][] = static::_getHttpFilePath() . $val['file'];
                        }
                        unset($val);
                        break;
                    case 'select':
                        switch (strval($formats['p'][$fName]['viewtype'])) {
                            case 'viewimage':
                                $Field = Field::init($this->Table->getFields()[$fName], $this->Table);
                                $fileData = $Field->getPreviewHtml($data['params'][$fName]['v'],
                                    $this->Table->getTbl()['params'],
                                    $this->Table->getTbl(),
                                    true);
                                $data['params'][$fName]['v_'] = static::_getHttpFilePath() . ($fileData[$formats['p'][$fName]['viewdata']['picture_name'] ?? ''][1][0]['file'] ?? '');
                                break 2;
                            case "":
                                Field::init($field, $this->Table)->addViewValues('edit',
                                    $value,
                                    $this->Table->getTbl()['params'],
                                    $this->Table->getTbl());
                                break;
                            default:
                                if ($isFirstLoad || $field['codeSelectIndividual']) {
                                    $formats['p'][$fName]['selects'] = $this->getEditSelect(['field' => $fName, 'item' => array_map(function ($val) {
                                        return $val['v'];
                                    },
                                        $this->Table->getTbl()['params'])],
                                        '',
                                        null,
                                        $formats['p'][$fName]['viewtype']);

                                }
                        }

                    default:
                        Field::init($field, $this->Table)->addViewValues('edit',
                            $value,
                            $this->Table->getTbl()['params'],
                            $this->Table->getTbl());
                }
            }
            unset($value);
        }

        return $data;
    }

    private
    function getTableData()
    {


        try {

            $inVars = ['calculate' => aTable::CalcInterval['changed']
                , 'channel' => 'web'
                , 'isTableAdding' => ($this->Table->getTableRow()['type'] === 'tmp' && $this->Table->isTableAdding())
            ];
            Sql::transactionStart();
            $tbl = $this->Table->getTbl();
            $this->Table->reCalculateFromOvers($inVars);
            $tbl = $this->Table->getTbl();
            Sql::transactionCommit();
        } catch (errorException $e) {
            Sql::transactionRollBack();
            $error = $e->getMessage() . ' <br/> ' . $e->getPathMess();
            $this->Table->reCalculateFilters('web', false, true);
        }

        $data = [];

        $formats = $this->getTableFormats(false, $tbl['rows']);
        $data['params'] = array_intersect_key($tbl['params'], $formats['p']);
        $data['rows'] = [];
        foreach ($tbl['rows'] as $row) {
            if (!empty($row['is_del'])) continue;
            if (key_exists($row['id'], $formats['r'])) {
                $newRow = ['id' => $row['id']];
                foreach ($row as $k => $v) {
                    if (key_exists($k, $formats['r'][$row['id']])) {
                        $newRow[$k] = $v;
                    }
                }
                $data['rows'][] = $newRow;
            }
        }
        $data = $this->getValuesForClient($data, $formats, true);

        $result = [
            'tableRow' => $this->tableRowForClient($this->Table->getTableRow())
            , 'f' => $formats
            , 'c' => $this->getTableControls()
            , 'fields' => $this->clientFields
            , 'sections' => $this->sections
            , 'error' => $error ?? null
            /*, 'data' => $data['rows']*/
            , 'data_params' => $data['params']
            , 'updated' => $this->Table->getSavedUpdated()

        ];

        return $result;
    }

    private function getTableControls()
    {
        $result = [];
        $result['deleting'] = !$this->onlyRead && Table::isUserCanAction('delete',
                $this->Table->getTableRow());
        $result['adding'] = !$this->onlyRead && Table::isUserCanAction('insert',
                $this->Table->getTableRow());
        $result['duplicating'] = !$this->onlyRead && Table::isUserCanAction('duplicate',
                $this->Table->getTableRow());
        $result['sorting'] = !$this->onlyRead && Table::isUserCanAction('reorder',
                $this->Table->getTableRow());
        $result['editing'] = !$this->onlyRead;
        return $result;
    }
}