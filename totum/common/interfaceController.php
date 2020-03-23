<?php

namespace totum\common;
/*
 * TODO Вынести лишние данные из сессии и закрывать блокировку сессии сразу после проверки UserID, подумать про AuthController
 *
 * */

use totum\fieldTypes\Select;
use totum\main\AutoloadAndErrorHandlers;
use totum\models\Table;
use totum\models\UserV;
use totum\tableTypes\_Table;
use totum\tableTypes\aTable;
use totum\tableTypes\tableTypes;

abstract class interfaceController extends Controller
{
    static $pageTemplate = 'page_template.php';
    static $contentTemplate = '';
    protected $answerVars = [];
    static $actionTemplate = '';
    protected static $withAuth = true;


    protected $isAjax = false, $folder = '', $inModuleUri;

    function __construct($modulName, $inModuleUri)
    {
        parent::__construct();

        $this->inModuleUri = $inModuleUri;
        if (static::__isAuthNeeded) {
            Auth::webInterfaceSessionStart();
        }

        $this->folder = dirname((new \ReflectionClass(get_called_class()))->getFileName());
        static::$pageTemplate = AutoloadAndErrorHandlers::getTemplatesDir() . '/' . static::$pageTemplate;

        if (!empty($_REQUEST['ajax'])) {
            $this->isAjax = true;
        } else {
            $this->__addAnswerVar('Module', $modulName);
            $this->__addAnswerVar('isCreatorView', Auth::isCreator());

        }
    }


    function getPreviewHtml($data)
    {
        $fields = $this->Table->getFields();

        if (!($field = $fields[$data['field']] ?? null))
            throw new errorException('Не найдено поле [[' . $data['field'] . ']]. Возможно изменилась структура таблицы. Перегрузите страницу');

        if (!in_array($field['type'], ['select'])) throw new errorException('Ошибка - поле не типа select');

        $this->Table->loadDataRow();
        $row = $data['item'];

        if ($field['category'] == 'column' && !isset($row['id'])) {
            $row['id'] = null;
        }
        foreach ($row as $k => &$v) {
            if ($k != 'id') {
                if ($fields[$k]['type'] === 'date' && $v && $v = Calculate::getDateObject($v)) {
                    if (!empty($fields[$k]['dateTime'])) {
                        $v = $v->format('Y-m-d H:i');
                    } else {
                        $v = $v->format('Y-m-d');
                    }
                }
                $v = ['v' => $v];
            }
        }


        /** @var Select $Field */
        $Field = Field::init($field, $this->Table);

        return ['previews' => $Field->getPreviewHtml($data['val'], $row, $this->Table->getTbl())];

    }
    protected function __runAuth($action)
    {
        if (!Auth::isAuthorized()) {

            if (empty($_POST['ajax'])) {
                $this->location('/Auth/Login/');
            } else {
                echo json_encode(['error' => 'Потеряна авторизация. Обновите страницу']);
            }
            die;
        } else {
            static::__actionRun($action);
        }
    }

    protected function errorAnswer($error)
    {
        extract($this->answerVars);
        include static::$pageTemplate;
    }

    function doIt($inAction)
    {
        $action = $inAction;
        static::$actionTemplate = $action;


        if ($this->isAjax) $action = 'Ajax' . $action;

        try {
            $this->__run($action);
        } catch (tableSaveException $e) {

            unset($this->Table);

            tableTypes::$tables = [];
            Table::$tableRowsByName = [];
            Table::$tableRowsById = [];
            aTable::$recalcLogPointer = null;
            aTable::$isActionRecalculateDone = false;

            Controller::$interfaceData = null;
            Controller::$linksData = null;
            Controller::$linksPanel = null;
            Controller::$FullLogs = null;
            Controller::$Logs = null;
            Controller::$FullLogsSize = 0;
            Controller::$FullLogsTablesIndex = null;

            Field::$fields = [];

            reCalcLogItem::$topObject = null;


            UserV::clear();

            Calculate::$calcLog = [];

            static::$FullLogs = static::$Logs = [];


            Sql::clear();
            Cycle::clearProjects();


            $this->doIt($inAction);
            return;
        } catch (\Exception $e) {
            $this->__addAnswerVar('error', $e->getMessage());
        }


        if ($this->isAjax) {
            if (empty($this->answerVars)) {
                $this->answerVars['error'] = 'Ошибка обработки запроса.';
            }

            $data = json_encode($this->answerVars, JSON_UNESCAPED_UNICODE);
            if ($this->answerVars && !$data) {
                $data['error'] = 'Ошибка обработки запроса.';
                if (Auth::isCreator()) {
                    $data['error'] = 'Ошибка вывода не utf-содержимого или слишком большого пакета данных. ' . (array_key_exists('FullLO GS',
                            $this->answerVars) ? 'Попробуйте отключить вывод логов' : '');
                    //   var_dump($this->answerVars); die;
                    //$data['error'].=;
                }
                $data = json_encode($data, JSON_UNESCAPED_UNICODE);
            }
            if (empty($data)) {
                $data = '["error":"Пустой ответ сервера"]';
            }
            echo $data;
            die;
        } else {
            if (isset($this->Table)) {
                $this->__addAnswerVar('title', $this->Table->getTableRow()['title']);
            }
            if (!static::$contentTemplate) {
                static::$contentTemplate = $this->folder . '/__' . static::$actionTemplate . '.php';
            }

            extract($this->answerVars);

            include static::$pageTemplate;
        }
    }

    function getFieldsForClient($fields)
    {
        foreach ($fields as &$field) {
            foreach (_Table::fieldCodeParams as $param) {
                if (!empty($field[$param])) $field[$param] = true;
            }
            if (!Auth::isCreator()) {
                foreach (_Table::fieldRolesParams as $param) {
                    unset($field[$param]);
                }
                unset($field['logging']);
                unset($field['showInXml']);
                unset($field['copyOnDuplicate']);
                $field['help'] = preg_replace('`\s*<admin>.*?</admin>\s*`su', '', $field['help']);
            }
        }
        unset($field);
        return $fields;
    }

    function getTableRowForClient($tableRow)
    {
        $fields = ['title', 'updated', 'type', 'id', 'sess_hash', 'description', 'fields_sets', 'panel', 'order_field', 'order_desc', 'fields_actuality', 'with_order_field', 'main_field', 'delete_timer'];
        if (Auth::isCreator()) {
            $fields = array_merge($fields,
                [
                    'name', 'sort', '__version'
                ]);

        } else {
            $tableRow['description'] = preg_replace('`\s*<admin>.*?</admin>\s*`su', '', $tableRow['description']);
        }
        $_tableRow = array_intersect_key($tableRow, array_flip($fields));
        if ($tableRow['type'] === 'cycles') {
            try {
                    $_tableRow['__firstUserTable'] =
                    tableTypes::getTableByName('tables')->getByParams(
                        ['where' => [
                            ['field'=>'type', 'operator'=>'=', 'value'=>'calcs'],
                            ['field'=>'tree_node_id', 'operator'=>'=', 'value'=>$tableRow['id']],
                            ['field'=>'id', 'operator'=>'=', 'value'=>array_keys(Auth::$aUser->getTreeTables())],
                            ],
                            'order' => [['field' => 'sort', 'ad' => 'asc']], 'field' => 'id'],
                        'field');
            } catch (errorException $e) {
                var_dump($e->getMessage());
            }
        }
        return $_tableRow;
    }

    function location($to = '/Main/')
    {
        header('location: ' . $to);
    }
}