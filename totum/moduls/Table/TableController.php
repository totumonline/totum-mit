<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 20.10.16
 * Time: 10:17
 */

namespace totum\moduls\Table;


use totum\common\aLog;
use totum\common\Calculate;
use totum\common\CalculateAction;
use totum\common\Controller;
use totum\common\Cycle;
use totum\common\errorException;
use totum\common\Field;
use totum\common\interfaceController;
use totum\common\Auth;
use totum\common\IsTableChanged;
use totum\common\Log;
use totum\common\Model;
use totum\common\reCalcLogItem;
use totum\common\Sql;
use totum\fieldTypes\Comments;
use totum\fieldTypes\File;
use totum\fieldTypes\Select;
use totum\models\Table;
use totum\models\TableFields;
use totum\models\Tables;
use totum\models\TablesFields;
use totum\models\Tree;
use totum\models\TreeV;
use totum\models\User;
use totum\models\UserV;
use totum\tableTypes\aTable;
use totum\tableTypes\tableTypes;
use totum\tableTypes\tmpTable;
use stdClass;

class TableController extends interfaceController
{

    /**
     * @var aTable
     */
    protected $Table,
        $onlyRead,
        $branchId;
    /**
     * @var Cycle
     */
    protected $Cycle;
    private $logTypes;

    function __construct($modulName, $inModuleUri)
    {
        if (!static::checkFastMethods()) {
            parent::__construct($modulName, $inModuleUri);

            if (!empty($this->getLogTypes())) {
                Calculate::$logsOn = true;
            }
        }
    }


    static function checkFastMethods()
    {
        if (!empty($_POST['method'])) {
            if (in_array($_POST['method'], array('checkTableIsChanged', 'tmpFileUpload', 'checkForNotifications'))) {
                session_start();
                if (!empty($_SESSION['userId'])) {
                    $userId = $_SESSION['userId'];
                    session_write_close();

                    switch ($_POST['method']) {
                        case 'checkTableIsChanged':
                            Log::$off = true;

                            $table_id = $_POST['table_id'];
                            $cycle_id = $_POST['cycle_id'] ?? 0;

                            $isChanged = new IsTableChanged($table_id, $cycle_id);
                            echo json_encode($isChanged->isChanged($_POST['code']), JSON_UNESCAPED_UNICODE);

                            break;
                        case 'checkForNotifications':
                            if (!empty($_POST['periodicity']) && ($_POST['periodicity'] > 1) && Auth::loadAuthUser($userId,
                                    false)) {
                                Log::$off = true;
                                $i = 0;
                                $actived = $_POST['activeIds'] ?? [];

                                $getNotification = function () use ($actived) {
                                    if (!$actived) $actived = [0];
                                    $result = [];

                                    if ($row = Model::init('notifications')->get(['id not in (' . implode(',',
                                            Sql::quote($actived,
                                                true)) . ')', 'active_dt_from->>\'v\'<=\'' . date('Y-m-d H:i:s') . '\'', 'user_id' => Auth::$aUser->getId(), 'active' => 'true'],
                                        '*',
                                        '(prioritet->>\'v\')::int, id')) {
                                        array_walk($row,
                                            function (&$v, $k) {
                                                if (!Model::isServiceField($k)) $v = json_decode($v, true);
                                            });

                                        session_start();
                                        $kod = Model::init('notification_codes')->getField('code',
                                            ['name' => $row['code']['v']]);
                                        $calc = new CalculateAction($kod);
                                        $table = tableTypes::getTableByName('notifications');
                                        $calc->execAction('code',
                                            [],
                                            $row,
                                            [],
                                            $table->getTbl(),
                                            $table,
                                            $row['vars']['v']);
                                        session_write_close();

                                        $result['notification_id'] = $row['id'];
                                    }
                                    if ($actived) {
                                        if ($ids = Model::init('notifications')->getAllIds(['id' => $actived, 'user_id' => Auth::$aUser->getId(), 'active' => 'false'])) {
                                            $result['deactivated'] = $ids;
                                        }
                                    }
                                    return $result;
                                };
                                $count = ceil(20 / $_POST['periodicity']);

                                do {
                                    echo "\n";
                                    flush();

                                    if (connection_status() != CONNECTION_NORMAL) die;
                                    if ($result = $getNotification()) break;

                                    sleep($_POST['periodicity']);
                                    Log::sql($i);
                                } while (($i++) < $count);

                                echo json_encode($result + ['notifications' => array_map(function ($n) {
                                        $n[0] = 'notification';
                                        return $n;
                                    },
                                        static::getInterfaceDatas())]);
                            }
                            break;
                        case 'tmpFileUpload':

                            echo json_encode(File::fileUpload($userId));
                            break;


                    }
                } else {
                    echo json_encode(['error' => 'Потеряна авторизация'], JSON_UNESCAPED_UNICODE);
                }
                die;
            }

        }
    }

    function actionAjaxActions()
    {

        $method = $_POST['method'] ?? null;

        if (!$this->Table) {
            //debug_print_backtrace();
            return $this->answerVars['error'] ?? 'Таблица не найдена';
        }


        try {
            if ($this->onlyRead && !in_array($method,
                    ['linkButtonsClick', 'setCommentsViewed', 'setTableFavorite', 'refresh', 'csvExport', 'printTable', 'click', 'getValue', 'loadPreviewHtml', 'notificationUpdate', 'edit', 'checkTableIsChanged', 'getTableData', 'getEditSelect'])) return 'Ваш доступ к этой таблице - только на чтение. Обратитесь к администратору для внесения изменений';

            if (!empty($_POST['data']) && is_string($_POST['data'])) $_POST['data'] = json_decode($_POST['data'], true);

            //   if ($method != 'checkTableIsChanged') {}

            Sql::transactionStart();

            $this->Table->setFilters($_POST['filters'] ?? '');

            switch ($method) {
                case 'linkButtonsClick':
                    $model = Model::initService('_tmp_tables');
                    $key = ['table_name' => '_linkToButtons', 'user_id' => Auth::$aUser->getId(), 'hash' => $_POST['hash'] ?? null];
                    if ($data = $model->getField( 'tbl', $key)) {
                        $data = json_decode($data, true);
                        if ($data['buttons'][$_POST['index']] ?? null) {
                            $CA = new CalculateAction($data['buttons'][$_POST['index']]['code']);
                            try {
                                $CA->execAction('CODE',
                                    [],
                                    [],
                                    $this->Table->getTbl(),
                                    $this->Table->getTbl(),
                                    $this->Table,
                                    $data['buttons'][$_POST['index']]['vars'] ?? []);
                                static::addLogVar($this->Table, ['BUTTON_CLICK'], 'a', $CA->getLogVar());
                            } catch (errorException $e) {
                                static::addLogVar($this->Table, ['BUTTON_CLICK'], 'a', $CA->getLogVar());
                                throw $e;
                            }


                        } else {
                            throw new errorException('Ошибка интерфейса - выбрана несуществующая кнопка');
                        }
                    } else {
                        throw new errorException('Предложенный выбор устарел.');
                    }


                    break;
                case 'setTableFavorite':
                    if ($_POST['status']) {
                        $_POST['status'] = json_decode($_POST['status'], true);
                        if (key_exists($this->Table->getTableRow()['id'],
                                Auth::$aUser->getTreeTables()) && in_array($this->Table->getTableRow()['id'],
                                Auth::$aUser->getFavoriteTables()) !== $_POST['status']) {
                            $Users = tableTypes::getTableByName('users');
                            if ($_POST['status']) {
                                $favorite = array_merge(Auth::$aUser->getFavoriteTables(),
                                    [$this->Table->getTableRow()['id']]);

                            } else {
                                $favorite = array_diff(Auth::$aUser->getFavoriteTables(),
                                    [$this->Table->getTableRow()['id']]);
                            }
                            $Users->reCalculateFromOvers(['modify' => [Auth::$aUser->getId() => ['favorite' => $favorite]]]);
                        }
                        $result = ['status' => $_POST['status']];
                    }
                    break;

                case 'getAllTables':
                    if (!Auth::isCreator()) throw new errorException('Функция доступна только Создателю');
                    $tables = [];
                    $fields = \totum\common\Model::init('tables_fields')->getAll(['is_del' => false],
                        'name, table_id, title, data, category');
                    $fieldsForSobaka = [];

                    foreach (\totum\common\Model::init('tables')->getAll(['is_del' => false],
                        'name, id, title') as $tRow) {
                        $tFields = [];
                        $fieldsForSobaka = [];
                        foreach ($fields as $v) {
                            if ($v['table_id'] == $tRow['id']) {
                                $tFields[$v['name']] = $v['title'];
                                if (!in_array($v['category'], ['filter', 'column']) && json_decode($v['data'],
                                        true)['type'] != 'button') {
                                    $fieldsForSobaka[] = $v['name'];
                                }
                            }
                        }
                        $tables[$tRow['name']] = ['t' => $tRow['title'], 'f' => $tFields, '@' => $fieldsForSobaka];

                    }

                    $result = ['tables' => $tables];

                    break;
                case 'refresh_cycles':
                    if (!Auth::isCreator()) throw new errorException('Функция доступна только Создателю');
                    $ids = !empty($_POST['refreash_ids']) ? json_decode($_POST['refreash_ids'], true) : [];
                    $result = $this->refreshCycles($ids);

                    break;
                case 'renameField':
                    if (!Auth::isCreator()) throw new errorException('Функция доступна только Создателю');
                    if (empty($_POST['name'])) throw new errorException('Нужно выбрать поле');
                    if (empty($this->Table->getFields()[$_POST['name']])) throw new errorException('Поле в таблице не найдено');

                    $calc = new CalculateAction("=: linkToDataTable(table: 'ttm__change_field_name'; title: 'Изменение name поля'; width: 800; height: \"80vh\"; params:\$row; refresh: 'strong';)
row: rowCreate(field: 'table_name'='{$this->Table->getTableRow()['name']}'; field: 'field_name'='{$_POST['name']}')");
                    $calc->exec(['name' => 'without_field'], [], [], [], [], [], $this->Table);

                    break;
                case 'addEyeGroupSet':
                    if (!Auth::isCreator()) throw new errorException('Функция доступна только Создателю');
                    if (empty(trim($_POST['name']))) throw new errorException('Имя сета должно быть не пустым');
                    if (empty($_POST['fields'])) throw new errorException('Сет не должен быть пустым');


                    $set = $this->changeFieldsSets(function ($set) {
                        $set[] = ['name' => trim($_POST['name']), 'fields' => $_POST['fields']];
                        return $set;
                    });

                    $result = ['sets' => $set];
                    break;
                case 'removeEyeGroupSet':
                    if (!Auth::isCreator()) throw new errorException('Функция доступна только Создателю');
                    $set = $this->changeFieldsSets(function ($set) {
                        array_splice($set, $_POST['index'], 1);
                        return $set;
                    });

                    $result = ['sets' => $set];
                    break;
                case 'leftEyeGroupSet':
                    if (!Auth::isCreator()) throw new errorException('Функция доступна только Создателю');

                    $set = $this->changeFieldsSets(function ($set) {
                        if ($_POST['index'] > 0) {
                            $setItem = array_splice($set, $_POST['index'], 1);
                            array_splice($set, $_POST['index'] - 1, 0, $setItem);
                        }
                        return $set;
                    });
                    $result = ['sets' => $set];
                    break;
                case
                'setCommentsViewed':
                    if (Auth::getUserId()) {
                        $field = $this->Table->getFields()[$_POST['field_name']] ?? null;
                        if ($field && $field['type'] === 'comments') {
                            /** @var Comments $Field */
                            $Field = Field::init($field, $this->Table);
                            $Field->setViewed($_POST['nums'], $_POST['id'] ?? null);
                        }
                    }
                    $result = ['ok' => true];
                    break;
                case 'checkTableIsChanged':
                    $result = $this->Table->checkTableIsChanged($_POST['code'] ?? '');
                    break;
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
                case 'getFieldLog':

                    $this->getFieldLog($_POST['field'], $_POST['id'] ?? null, $_POST['rowName'] ?? null);

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
                        json_decode($_POST['editedFields'] ?? '[]', true));
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
                case 'calcFieldsLog':

                    $CA = new CalculateAction('= : linkToDataTable(title:"' . $_POST['name'] . '" ; table: \'calc_fields_log\'; width: 1000; height: "80vh"; params: $row; refresh: false; header: true; footer: true)
row: rowCreate(field: "data" = $#DATA)');

                    $Vars = ['DATA' => $_POST['calc_fields_data']];
                    $CA->execAction('KOD',
                        [],
                        [],
                        [],
                        [],
                        tableTypes::getTableByName('tables'),
                        $Vars);
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

            $result['FullLOGS'] = [];
            if (static::$FullLogs || static::$Logs) {
                $result['LOGS'] = static::$Logs;
                $result['FullLOGS'] = static::$FullLogs;

            }

            if (in_array('recalcs', $this->getLogTypes())) {
                if ($logs = reCalcLogItem::getAllLog()) {
                    $result['FullLOGS'][] = $logs;

                }
            }
            if (in_array('flds', $this->getLogTypes())) {
                $result['FieldLogs'] = Calculate::$calcLog;
            }


            if (!empty($result)) $this->__setAnswerArray($result);

            Sql::transactionCommit();


        } catch (errorException $exception) {
            $return = ['error' => $exception->getMessage().(Auth::isCreator()? "<br/>" . $exception->getPathMess():'')];

            $result['FullLOGS'] = [];
            if (static::$FullLogs || static::$Logs) {
                $return['LOGS'] = static::$Logs;
                $return['FullLOGS'] = static::$FullLogs;
                $result['FieldLogs'] = Calculate::$calcLog;
            }

            if (in_array('recalcs', $this->getLogTypes()) && $logs = reCalcLogItem::getAllLog()) {
                $result['FullLOGS'][] = $logs;
            }
            return $return;
        }

    }

    function changeFieldsSets($func = null)
    {
        if ($this->Table->getTableRow()['type'] == 'calcs') {
            $tableVersions = tableTypes::getTable(Table::getTableRowByName('calcstable_versions'));
            $vIdSet = $tableVersions->getByParams(['where' => [['field' => 'table_name', 'operator' => '=', 'value' => $this->Table->getTableRow()['name']],
                ['field' => 'version', 'operator' => '=', 'value' => $this->Table->getTableRow()['__version']]], 'field' => ['id', 'fields_sets']],
                'row');
            $set = $vIdSet['fields_sets'] ?? [];

            if ($func) {
                $set = $func($set);
                $tableVersions->reCalculateFromOvers([
                    'modify' => [$vIdSet['id'] => ['fields_sets' => $set]]
                ]);
            }


        } else {
            $set = $this->Table->getTableRow()['fields_sets'];
            $tableTables = tableTypes::getTable(Table::getTableRowByName('tables'));
            if ($func) {
                $set = $func($set);
                $tableTables->reCalculateFromOvers([
                    'modify' => [$this->Table->getTableRow()['id'] => ['fields_sets' => $set]]
                ]);
            }
        }
        return $set;
    }

    function setTreeData()
    {

        $this->__addAnswerVar('Branch', $this->branchId);

        $tree = [];
        $branchIds = [];
        $topBranches = [];

        if (Auth::isCreator()) {
            $branchesArray = Tree::init()->getBranchesForCreator($this->branchId);

        } else {
            $branchesArray = Tree::init()->getBranchesByTables(
                $this->branchId,
                array_keys(Auth::$aUser->getTreeTables()),
                Auth::$aUser->getRoles());
        }
        foreach ($branchesArray as $t) {


            if (!$t['parent_id']) {
                if ($t['id'] == $this->branchId) {
                    $this->__addAnswerVar('title', $t['title']);
                }
                $topBranches[] = $t;
                if ($t['top'] != $this->branchId) continue;
            }

            $tree[] = [
                    'id' => 'tree' . $t['id']
                    , 'text' => $t['title']
                    , 'type' => $t['type'] ? $t['type'] : 'folder'
                    , 'parent' => ($parent = (!$t['parent_id'] ? '#' : 'tree' . $t['parent_id']))
                ] + ($t['icon'] ? ['icon' => 'fa fa-' . $t['icon']] : []) + ($t['type'] == 'link' ? ['link' => $t['link']] : []);
            if ($t['type'] != "link")
                $branchIds[] = $t['id'];
        }
        if ($branchIds) {

            foreach (Table::init()->getAll(['tree_node_id' => ($branchIds), 'id' => array_keys(Auth::$aUser->getTreeTables())],
                'id, title, type, tree_node_id',
                '(sort->>\'v\')::numeric') as $t) {
                $tree[] = [
                    'id' => 'table' . $t['id']
                    , 'href' => $t['id']
                    , 'text' => $t['title']
                    , 'type' => 'table_' . $t['type']
                    , 'parent' => 'tree' . $t['tree_node_id']
                    , 'state' => [
                        'selected' => ($this->Table && $this->Table->getTableRow()['id'] == $t['id'] ? true : false)
                    ]
                ];
            }
        }

        if ($this->Table && ($this->Table->getTableRow()['type'] == 'calcs') && $this->Cycle) {


            $idHref = 'Cycle' . $this->Cycle->getId();
            if (Auth::$aUser->isOneCycleTable($this->Cycle->getCyclesTable()->getTableRow()) && count($this->Cycle->getCyclesTable()->getUserCycles(Auth::getUserId())) === 1) {
                $idHref = 'table' . $this->Table->getTableRow()['tree_node_id'];

            } else {
                $cycleRow = [
                    'id' => $idHref
                    , 'href' => '#'
                    , 'text' => $this->Cycle->getRowName()
                    , 'type' => 'cycle_name'
                    , 'parent' => 'table' . $this->Table->getTableRow()['tree_node_id']
                    , 'state' => [
                        'selected' => false
                    ]
                ];
                $tree[] = &$cycleRow;
            }


            foreach ($this->Cycle->getListTables() as $i => $tId) {
                if (array_key_exists($tId, Auth::$aUser->getTreeTables())) {
                    if ($tableRow = Table::getTableRowById($tId)) {
                        $tree[] = [
                            'id' => 'table' . $tId
                            , 'href' => $this->Table->getTableRow()['tree_node_id'] . '/' . $this->Cycle->getId() . '/' . $tId
                            , 'text' => $tableRow['title']
                            , 'type' => 'table_calcs'
                            , 'parent' => $idHref
                            , 'state' => [
                                'selected' => ($this->Table && $this->Table->getTableRow()['id'] == $tId ? true : false)
                            ]
                        ];
                        if ($i === 0 && !empty($cycleRow)) {
                            $cycleRow['href'] = $this->Table->getTableRow()['tree_node_id'] . '/' . $this->Cycle->getId() . '/' . $tId;
                        }
                    }
                }
            }
        }

        $tree = array_values($tree);

        $this->__addAnswerVar('topBranches', $topBranches);
        $this->__addAnswerVar('treeData', $tree);
        if (!$this->Table) {
            $this->__addAnswerVar('html',
                tableTypes::getTableByName('tree')->getByParams(['field' => 'html', 'where' => [['field' => 'id', 'operator' => '=', 'value' => $this->branchId]]],
                    'field'));
        }
    }

    function doIt($action)
    {
        if (!$this->isAjax) $action = 'Table';
        else $action = 'Actions';


        try {
            if (Auth::isAuthorized()) {
                $this->checkTableByUri();
            }

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

    function actionTable()
    {
        $this->setTreeData();
        if (!$this->Table) return;


        /*Для таблиц циклов с одним циклом на пользователя*/
        if (Auth::$aUser->isOneCycleTable($this->Table->getTableRow())) {
            $cycles = $this->Table->getUserCycles(Auth::$aUser->getId());
            if (count($cycles) === 0) {
                $this->Table->modify(
                    null,
                    ['add' => []]);
                $cycles = $this->Table->getUserCycles(Auth::$aUser->getId());
            }
            if (count($cycles) === 1) {
                $Cycle = Cycle::init($cycles[0], $this->Table->getTableRow()['id']);
                $calcsTablesIDs = $Cycle->getTables();
                if (!empty($calcsTablesIDs)) {
                    foreach ($calcsTablesIDs as $tableId) {
                        if (Auth::$aUser->isTableInAccess($tableId)) {
                            header('location: /Table/' . $this->Table->getTableRow()['top'] . '/' . $this->Table->getTableRow()['id'] . '/' . $cycles[0] . '/' . $tableId);
                            die;
                        }
                    }
                }
            }
        }
        /*;;;;;;;;;;;;*/


        $this->Table->setFilters($_GET['f'] ?? '');

        $tableData = $this->Table->getTableDataForInterface();

        $tableData['fields']=$this->getFieldsForClient($tableData['fields']);
        $this->__addAnswerVar('table', $tableData);
        $this->__addAnswerVar('error', $tableData['error'] ?? null);
        $this->__addAnswerVar('onlyRead', $tableData['onlyRead'] ?? $this->onlyRead);

        $result['FullLOGS'] = static::$FullLogs ?? [];


        if (static::$Logs ?? null) {
            $this->__addAnswerVar('LOGS', static::$Logs);
        }
        if ($result['FullLOGS'] || in_array('recalcs', $this->getLogTypes())) {
            if (in_array('recalcs', $this->getLogTypes())) {
                $result['FullLOGS'][] = reCalcLogItem::getAllLog();
            }
            $this->__addAnswerVar('FullLOGS', $result['FullLOGS']);
        }
        if (in_array('flds', $this->getLogTypes())) {
            $this->__addAnswerVar('FieldLOGS', Calculate::$calcLog);
        }
    }

    /**
     * @return array
     */
    public
    function getLogTypes()
    {
        return $this->logTypes ?? ($this->logTypes = json_decode($_COOKIE['pcTableLogs'] ?? '[]', true));
    }

    protected
    function __addInGlobalLog(aTable $table, $path, $type = 'c', $log)
    {
        if (is_null($log)) return;

        if (!static::$FullLogsTOO_BIG && static::$FullLogsSize >= 5000000) {
            static::$FullLogsTOO_BIG = true;
            array_unshift(static::$FullLogs[0]['children'], ['text' => 'СЛИШКОМ БОЛЬШОЙ ЛОГ', 'type' => '!']);
            return;
        }
        static::$FullLogsSize += strlen(json_encode($log, JSON_UNESCAPED_UNICODE));

        $fL =& static::$FullLogs;


        $tableFolder = null;

        if ($table->getTableRow()['type'] == 'calcs') {
            $tableHashName = $table->getTableRow()['id'] . '_' . $table->getCycle()->getId();
            $tableTitle = $table->getTableRow()['title'] . ' / цикл id ' . $table->getCycle()->getId();
        } else {
            $tableHashName = $table->getTableRow()['id'];
            $tableTitle = $table->getTableRow()['title'];
        }

        if (empty(static::$FullLogsTablesIndex[$tableHashName])) {
            static::$FullLogsTablesIndex[$tableHashName] =  &$fL[];
            static::$FullLogsTablesIndex[$tableHashName]['text'] = $tableTitle;
            static::$FullLogsTablesIndex[$tableHashName] = &static::$FullLogsTablesIndex[$tableHashName]['children'];
        }

        $tableFolder = &static::$FullLogsTablesIndex[$tableHashName];
        $tableFolderPath = [$tableTitle];


        if (ctype_digit(strval($path[0]))) {
            if ($path[0] === 0) {
                $path[0] = 'Строка добавления';
            }
            array_unshift($path, 'СТРОЧНАЯ ЧАСТЬ');
        }

        $types = [
            'c' => 'Код',
            's' => 'Селект',
            'f' => 'Формат',
            'a' => 'Код действия',
        ];

        $path[] = $types[$type];
        foreach ($path as $folder) {
            $thisFolderPath = null;
            if ($tableFolder) {
                foreach ($tableFolder as $k => $innerItem) {
                    if ($innerItem['text'] === $folder) {
                        $tableFolder = &$tableFolder[$k]['children'];
                        continue 2;
                    }
                }
            }

            $tableFolder = &$tableFolder[];
            $tableFolder['text'] = $folder;
            $tableFolder = &$tableFolder['children'];

        }

        $tableFolder[] = $log;
    }

    protected
    function __addLogVar(aTable $table, $path, $type = 'c', $log)
    {

        $logTypes = $this->getLogTypes();


        if (in_array($type, $logTypes)) {
            $this->__addInGlobalLog($table, $path, $type, $log);


            if ($this->Table->getTableRow()['id'] != $table->getTableRow()['id']) return;
            if ($this->Table->getTableRow()['type'] === 'calcs') {
                if ($this->Table->getCycle()->getID() != $table->getCycle()->getId()) return;
            }
            $f =& static::$Logs;

            foreach ($path as $folder) {
                if (empty($f[$folder])) $f[$folder] = [];
                $f = &$f[$folder];
            }
            if (empty($f[$type])) $f[$type] = [];
            $f[$type][] = $log;

        }
    }

    protected
    function checkTableByUri()
    {
        if (empty($this->inModuleUri) || !preg_match('/^(\d+)\//', $this->inModuleUri, $branchMatches)) {
            $this->location();
            die;
        }
        $this->branchId = $branchMatches[1];

        $tableUri = substr($this->inModuleUri, strlen($this->branchId) + 1);
        $tableId = 0;

        $checkTreeTable = function ($tableId) {
            if (!array_key_exists($tableId, Auth::$aUser->getTables())) {
                $this->__addAnswerVar('error', 'Доступ к таблице запрещен');
                $tableId = 0;
            } else {
                $this->onlyRead = Auth::$aUser->getTables()[$tableId] == 0;
                $extradata = null;
                if ($tableRow = Table::getTableRowById($tableId)) {
                    switch ($tableRow['type']) {
                        case 'calcs':
                            $this->__addAnswerVar('error', 'Неверный путь к таблице. Воспользуйтесь деревом');
                            return;
                        case 'tmp':
                            $extradata = $_POST['tableData']['sess_hash'] ?? $_GET['sess_hash'] ?? null;
                            break;
                    }
                    $this->Table = tableTypes::getTable($tableRow, $extradata);
                    $this->Table->setNowTable();
                }
            }
        };


        if (!empty($_POST['method']) && in_array($_POST['method'], ['getValue'])) {
            if (!empty($_POST['table_id'])) {
                $checkTreeTable((int)$_POST['table_id']);
                return;
            }
        }


        if ($tableUri && preg_match('/^(\d+)\/(\d+)\/(\d+)/', $tableUri, $tableMatches)) {

            $this->Cycle = Cycle::init($tableMatches[2], $tableMatches[1]);
            if (!$this->Cycle->loadRow()) {
                throw new errorException('Цикл не найден');
            }


            $tableId = $tableMatches[3];

            if (!array_key_exists($tableId, Auth::$aUser->getTables())) {
                $this->__addAnswerVar('error', 'Доступ к таблице запрещен');
            } else {
                $this->onlyRead = Auth::$aUser->getTables()[$tableId] == 0;

                //Проверка доступа к циклу

                if (!Auth::isCreator() && !empty($this->Cycle->getCyclesTable()->getFields()['creator_id']) && in_array($this->Cycle->getCyclesTable()->getTableRow()['cycles_access_type'],
                        [1, 2, 3])) {
                    //Если не связанный пользователь
                    if (count(array_intersect($this->Cycle->getRow()['creator_id']['v'],
                            Auth::$aUser->getConnectedUsers())) === 0) {
                        if ($this->Cycle->getCyclesTable()->getTableRow()['cycles_access_type'] == 3) {
                            $this->onlyRead = true;
                        } else {
                            $this->__addAnswerVar('error', 'Доступ к циклу запрещен');
                            return;
                        }
                    }
                }

                if ($tableRow = Table::getTableRowById($tableId)) {
                    if ($tableRow['type'] != 'calcs') throw new errorException('Это не рассчетная таблица');
                    $this->Table = $this->Cycle->getTable($tableRow);
                    $this->Table->setNowTable();
                }

            }
        } elseif ($tableUri && preg_match('/^(\d+)/', $tableUri, $tableMatches)) {
            $tableId = $tableMatches[1];

            $checkTreeTable($tableId);
        }

    }

    private
    function getFieldLog($field, $id, $rowName)
    {
        $fields = $this->Table->getFields();
        if (empty($fields[$field])) throw new errorException('Поле [[' . $field . ']] в этой таблице не найдено');
        if (empty($fields[$field]['showInWeb']) || (!empty($fields[$field]['logRoles']) && !array_intersect(fields[$field]['logRoles'],
                    Auth::$aUser->getRoles()))) throw new errorException('Доступ к логам запрещен');


        $logs = aLog::getLogs($this->Table->getTableRow()['id'],
            $this->Table->getCycle() ? $this->Table->getCycle()->getId() : null,
            $id,
            $field);

        $title = 'Лог ручных изменений по полю "' . $fields[$field]['title'] . '"';
        if ($id) {
            $title .= ' id ' . $id;
            if ($rowName) {
                $title .= ' "' . $rowName . '""';
            }
        }

        if (empty($logs)) {
            Controller::addToInterfaceDatas('text',
                ['title' => $title, 'width' => '500', 'text' => 'Ручных изменений по полю не производилось']);
            return;
        }

        $tmp = tableTypes::getTable(Table::getTableRowByName('log_structure'));
        $tmp->addData(['tbl' => $logs]);
        $logs = $tmp->getTableDataForRefresh(null);

        $width = 130;
        foreach ($tmp->getFieldsFiltered('sortedVisibleFields')['column'] as $field) {
            $width += $field['width'];
        }
        $table = [
            'title' => $title,
            'table_id' => Table::getTableIdByName('log_structure'),
            'sess_hash' => $tmp->getTableRow()['sess_hash'],
            'data' => array_values($logs['chdata']['rows']),
            'data_params' => $logs['chdata']['params'],
            'width' => $width
        ];

        Controller::addToInterfaceDatas('table', $table);
    }

    private
    function refreshCycles($ids)
    {
        Sql::transactionStart();

        $tables = [];
        foreach ($ids as $id) {
            $this->Cycle = Cycle::init($id, $this->Table->getTableRow()['id']);

            if (empty($tables)) {
                $tables = $this->Cycle->getTables();
                foreach ($tables as &$t) {
                    $t = Table::getTableRowById($t);
                }
                unset($t);
            }
            foreach ($tables as $inTable) {
                $CalcsTable = $this->Cycle->getTable($inTable);
                $CalcsTable->reCalculateFromOvers();
            }

        }

        $refresh = $this->Table->getTableDataForRefresh();

        Sql::transactionCommit();
        return $refresh;
    }

    private
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
}