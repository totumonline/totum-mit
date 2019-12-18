<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 26.04.17
 * Time: 16:32
 */

namespace totum\moduls\Json;

/*

{
  "auth": {
    "login": "json",
    "password": "1111"
  },
  "import": {
    "rows-set-where":[
        {"where": {"field": "id", "value" : "test", "operator": "=" }, "set": {"test": true, "__pins":["rekvizity"]}}
    ],
    "header":{
      "__pins":["rekvizity"],
      "__clears":["rekvizity"]
    },
      "rows":{
           "modify": [
        {"rowId": {"rowField": "new value", "__pins":["rekvizity"]}},
        {"rowId2": {"rowField": "new value"}}

        ],
        "add":[],
        "remove": []
  },
  "recalculate":[
      {"field":"id", "operator": "=", "value": [1,2,3]}
    ],
  "export": {
    "fields": [
      "id","rekvizity"
     ],
    "filters": {
      "id": [
        1,
        2,
        3
      ]
    }
  }
}

 */

use totum\common\Auth;
use totum\common\Calculate;
use totum\common\CalculateAction;
use totum\common\Controller;
use totum\common\Cycle;
use totum\common\errorException;
use totum\common\Field;
use totum\common\Sql;
use totum\common\tableSaveException;
use totum\models\Table;
use totum\models\User;
use totum\tableTypes\aTable;
use totum\tableTypes\JsonTables;
use totum\tableTypes\tableTypes;

use SimpleXMLElement;

class JsonController extends Controller
{
    private static $errors = [
        1 => 'Json не получен или неверно оформлен',
        2 => 'Секция auth не найдена',
        3 => 'Атрибут login секции auth не найден',
        4 => 'Атрибут password секции auth не найден',
        5 => 'Пользователь с такими данными не найден. Возможно, ему не включен доступ к xml/json-интерфейсу',
        6 => 'Путь к таблице не верный',
        7 => 'Доступ к таблице запрещен',
        8 => 'Доступ к таблице на запись запрещен',
        9 => 'Секция recalculate должна содержать ограничения в формате [["field":FIELDNAME,"operator":OPERATOR,"value":VALUE]]',
        10 => 'Поле запрещено для редактирования через api или не существует в указанной категории',
        11 => 'Поле должно содержать/не содержать множественный селект',
        12 => 'В секции export укажете "fields":[] - перечисление полей для вывода в экспорт',
        13 => 'Неверно оформлено where в секции rows-set-where',
        14 => 'Без указания таблицы в пути работает только секция remotes',
        15 => 'Remote {var} не существует или не доступен для вас',
        16 => 'Не задан  name для remote',
    ];
    private static $translates = ['header' => 'param', 'footer' => 'footer', 'rows' => 'column'];


    protected $arrayIn, $arrayOut = [];
    protected $inModuleUri;
    /**
     * @var aTable
     */
    protected $Table;
    /**
     * @var Auth
     */
    protected $aUser;
    private $tableUpdatedOnLoad;
    private $addedIds = [];


    function __construct($modulName, $inModuleUri)
    {
        parent::__construct();
        $this->inModuleUri = $inModuleUri;
    }

    protected function __addLogVar(aTable $table, $path, $type = 'c', $log)
    {

        $logTypes = $this->arrayIn['withLogs'] ?? [];

        if (in_array($type, $logTypes)) {
            $this->__addInGlobalLog($table, $path, $type, $log);
        }
    }

    protected function __addInGlobalLog(aTable $table, $path, $type = 'c', $log)
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

    function doIt($action)
    {

        $jsonString = file_get_contents('php://input');
        try {
            sql::transactionStart();
            $this->arrayIn = json_decode($jsonString, true) ?? json_decode($_POST['data'] ?? "", true);
            if (!is_array($this->arrayIn)) $this->throwError(1);
            if (!empty($this->arrayIn['withLogs'])) {
                Calculate::$logsOn = true;
            }
            $this->authUser();
            $this->checkTable(
                array_key_exists('import', $this->arrayIn)
                || array_key_exists('recalculate', $this->arrayIn)
            );

            foreach (['import', 'recalculate', 'remotes', 'export'] as $action) {
                if (array_key_exists($action, $this->arrayIn)) {
                    if (!$this->Table && $action != 'remotes')
                        $this->throwError(14);
                    $this->{'json' . $action}();
                }
            }

            foreach (Controller::getLinks() ?? [] as $link) {

                $data = http_build_query($link['postData']);

                $context = stream_context_create(
                    array(
                        'http' => array(
                            'header' => "Content-type: application/x-www-form-urlencoded\r\nUser-Agent: TOTUM\r\nConnection: Close\r\n\r\n",
                            'method' => 'POST',
                            'content' => $data
                        )
                    )
                );
                file_get_contents($link['uri'], false, $context);
            }
            sql::transactionCommit();

        } catch (errorException $e) {
            $error = $e->getCode() ? $e->getCode() : -1;
            $errorDescription = $e->getMessage();
            sql::transactionRollBack();
        }

        $this->sendJson($error ?? 0, $errorDescription ?? '');

    }

    protected function jsonRecalculate()
    {
        $inVars = ['channel' => 'xml'];
        if (!$this->Table->isJsonTable() && !empty($this->arrayIn['recalculate'])) {
            $inVars['modify'] = [];
            $params = [];
            foreach ($this->arrayIn['recalculate'] as $where) {
                if (!is_array($where) || count(array_intersect_key($where,
                        ['field' => 1, 'operator' => '', 'value' => ''])) != 3) {
                    $this->throwError(9);
                }
                $params[] = $where;
            }
            $ids = $this->Table->loadRowsByParams($params);
            $inVars['modify'] = array_map(function () {
                return [];
            },
                array_flip($ids));
        }
        $this->Table->reCalculateFromOvers($inVars);
        $this->addedIds = array_merge($this->addedIds, $this->Table->addedIds);
    }

    protected function jsonRemotes()
    {
        $selectedRemotes = [];
        $remoteOutputs = [];
        $RemotesTable = tableTypes::getTableByName('ttm__remotes');
        foreach ($this->arrayIn['remotes'] ?? [] as $remote) {
            $name = $remote['name'] ?? null;
            if (!$name) {
                $this->throwError(16);
            }

            if (!key_exists($name, $selectedRemotes)) {
                $code = ($selectedRemotes[$name] = $RemotesTable->getByParams(['where' => [
                    ['field' => 'on_off', 'operator' => '=', 'value' => true],
                    ['field' => 'name', 'operator' => '=', 'value' => $name],
                    ['field' => 'api_user', 'operator' => '=', 'value' => Auth::$aUser->getId()],
                ], 'field' => 'code'],
                    'field'));
                if (!$code) {
                    $this->throwError(15, ["{var}" => $name]);
                }
                $selectedRemotes[$name] = new CalculateAction($code);
            }

            /** @var CalculateAction array $selectedRemotes */
            $remoteOutputs[] = $selectedRemotes[$name]->execAction('CODE',
                [],
                [],
                [],
                [],
                $RemotesTable,
                [
                    'data' => $remote['data'] ?? null
                ]);
        }
        $this->arrayOut['remotes'] = $remoteOutputs;
    }

    protected function jsonImport()
    {
        $import = ['channel' => 'xml'];


        $import['modify'] = [];
        $import['setValuesToDefaults'] = [];
        $import['setValuesToPinned'] = [];
        $import['add'] = [];
        $import['remove'] = [];

        $addValToImportNoColumn = function ($v, $field, &$importPart, $isAdd = false) {

            if (empty($field['showInXml']) || (!$isAdd && empty($field['apiEditable'])) || ($isAdd && empty($field['apiInsertable'])))
                throw new errorException('Поле [[' . $field['name'] . ']] запрещено для ' . ($isAdd ? 'добавления' : 'редактирования') . ' через Api',
                    10);

            if (Field::isFieldListValues($field['type'], $field['multiple'] ?? false)) {
                if (!is_array($v)) throw new errorException('Поле [[' . $field['name'] . ']] должно содержать множественный селект',
                    11);
            } else {
                if (is_array($v)) throw new errorException('Поле [[' . $field['name'] . ']]  должно содержать строку',
                    11);
            }

            $importPart[$field['name']] = $v;
        };

        $fields = $this->Table->getFields();

        $handleItem = function ($itemArray, $category, $path, &$importPart, &$defaultsPart, &$pinnedPars, $isAdd = false) use ($fields, &$addValToImportNoColumn) {
            if (array_key_exists('__clears', $itemArray)) {
                foreach ((array)$itemArray['__clears'] as $k) {
                    if (empty($fields[$k]) || $fields[$k]['category'] !== $category) throw new errorException('Поля [[' . $k . ']] в ' . $path . ' таблицы не существует',
                        10);
                    else $defaultsPart[$k] = 1;
                }

                unset($itemArray['__clears']);
            }
            if (array_key_exists('__pins', $itemArray)) {
                foreach ((array)$itemArray['__pins'] as $k) {
                    if (empty($fields[$k]) || $fields[$k]['category'] !== $category) throw new errorException('Поля [[' . $k . ']] в ' . $path . ' таблицы не существует',
                        10);
                    else $pinnedPars[$k] = 1;
                }
                unset($itemArray['__pins']);
            }

            foreach ($itemArray as $k => $v) {
                if (empty($fields[$k]) || $fields[$k]['category'] !== $category) throw new errorException('Поля [[' . $k . ']] в ' . $path . ' таблицы не существует',
                    10);
                $addValToImportNoColumn($v, $fields[$k], $importPart, $isAdd);
            };
        };


        if (array_key_exists('rows-set-where', $this->arrayIn['import'])) {
            foreach ($this->arrayIn['import']['rows-set-where'] as $set) {
                $where = [];
                foreach ($set['where'] as $_where) {
                    if (count(array_intersect_key($_where,
                            array_flip(['field', 'operator', 'value']))) != 3) static::throwError(13);
                    $where[] = $_where;
                }
                $ids = $this->Table->getByParams([
                    'field' => 'id', 'where' => $where
                ],
                    'list');
                if ($ids) {
                    foreach ($ids as $id) {
                        $r =& $import['modify'][(int)$id];
                        $rD =& $import['setValuesToDefaults'][(int)$id];
                        $rP =& $import['setValuesToPinned'][(int)$id];
                        unset($set['set']['id']);
                        $handleItem($set['set'],
                            'column',
                            'rows',
                            $r,
                            $rD,
                            $rP);
                    }
                }
            }
        }

        foreach (static::$translates as $path => $category) {
            if ($itemsArray = ($this->arrayIn['import'][$path] ?? [])) {
                if ($path == 'rows') {
                    if (!empty($itemsArray['remove'])) {
                        foreach ($itemsArray['remove'] as $id) $import['remove'][] = (int)$id;
                        unset($itemsArray['remove']);
                    }
                    foreach ($itemsArray['add'] ?? [] as $row) {
                        unset($row['id']);
                        $nullA = [];
                        $r =& $import['add'][];
                        $handleItem($row,
                            $category,
                            $path,
                            $r,
                            $nullA,
                            $nullA,
                            true);
                    }
                    foreach ($itemsArray['modify'] ?? [] as $id => $row) {
                        $r =& $import['modify'][(int)$id];
                        $rD =& $import['setValuesToDefaults'][(int)$id];
                        $rP =& $import['setValuesToPinned'][(int)$id];
                        unset($row['id']);
                        $handleItem($row,
                            $category,
                            $path,
                            $r,
                            $rD,
                            $rP);
                    }
                } else {
                    $handleItem($itemsArray,
                        $category,
                        $path,
                        $import['modify']['params'],
                        $import['setValuesToDefaults']['params'],
                        $import['setValuesToPinned']['params']);
                }

            }
        }
        $import['channel'] = 'xml';

        if ($import['add'] && !Table::isUserCanAction('insert',
                $this->Table->getTableRow())) throw new errorException('Добавление в эту таблицу вам запрещено');
        if ($import['remove'] && !Table::isUserCanAction('delete',
                $this->Table->getTableRow())) throw new errorException('Удаление из этой таблицы вам запрещено');

        $this->Table->reCalculateFromOvers($import);
        $this->addedIds = array_merge($this->addedIds, $this->Table->addedIds);

    }

    protected
    function jsonExport()
    {
        if (empty($this->arrayIn['export']['fields'])) {
            $this->throwError(12);
        }
        $this->Table->setFilters($this->arrayIn['export']['filters'] ?? [], false);
        $this->Table->reCalculateFilters('xml', true, true);

        $data = $this->Table->getFilteredData('xml');
        $withTitles = false;
        $withCalcs = false;

        $data = $this->Table->getValuesAndFormatsForClient($data, 'xml');

        $addToXmlOut = function (&$addTo, $field, $valArray) use ($withTitles, $withCalcs) {
            $v = $valArray['v'] ?? null;
            if ($withTitles || $withCalcs) {
                $v = ['v' => $valArray];
                if ($withCalcs) {
                    if (!empty($valArray['h'])) {
                        $v['h'] = true;
                    }
                    if (!empty($valArray['c'])) {
                        $v['c'] = $valArray['c'];
                    }
                    if (!empty($valArray['e'])) {
                        $v['e'] = $valArray['e'];
                    }
                }

            }
            $addTo[$field['name']] = $v;
        };

        //header
        $sortedXmlFields = $this->Table->getSortedXmlFields();
        $this->arrayOut['export'] = ['header' => [], 'filters' => [], 'rows' => [], 'footer' => []];


        $tr = array_flip(static::$translates);
        //header
        foreach ($sortedXmlFields['param'] ?? [] as $fName => $field) {
            if (in_array($fName, $this->arrayIn['export']['fields'])) {
                $addToXmlOut($this->arrayOut[$tr[$field['category']]], $field, $data['params'][$fName]);
            }
        }
        //filter
        foreach ($sortedXmlFields['filter'] ?? [] as $fName => $field) {
            if (in_array($fName, $this->arrayIn['export']['fields'])) {
                $addToXmlOut($this->arrayOut[$tr[$field['category']]], $field, $data['params'][$fName]);
            }
        }
        //rows
        foreach ($data['rows'] as $row) {
            $rowOut = [];
            if (in_array('id', $this->arrayIn['export']['fields'])) {
                $rowOut['id'] = $row['id'];
            }
            foreach ($sortedXmlFields['column'] ?? [] as $fName => $field) {
                if (in_array($fName, $this->arrayIn['export']['fields'])) {
                    $addToXmlOut($rowOut, $field, $row[$fName]);
                }
            }
            if ($rowOut) {
                $this->arrayOut['export']['rows'][] = $rowOut;
            }
        }
        //footer
        foreach ($sortedXmlFields['footer'] ?? [] as $fName => $field) {
            if (in_array($fName, $this->arrayIn['export']['fields'])) {
                $addToXmlOut($this->arrayOut[$tr[$field['category']]], $field, $data['params'][$fName]);
            }
        }
        foreach ($this->arrayOut['export'] as $k => $v) {
            if (empty($v)) {
                unset($this->arrayOut['export'][$k]);
            }
        }
    }

    protected
    function checkTable($isRequestForWrite)
    {
        try {
            if ($this->inModuleUri == '')
                return;
            else if (preg_match('/^(\d+)\/(\d+)\/(\d+)$/', $this->inModuleUri, $match)) {
                $cyclesTableId = $match[1];
                $cycleId = $match[2];
                $cycleTableId = $match[3];
                if (!($tableRow = Table::getTableRowById($cycleTableId))) {
                    throw new errorException('');
                }

                $Cycle = Cycle::init($cycleId, $cyclesTableId);
                $this->Table = $Cycle->getTable($tableRow);

            } elseif (preg_match('/^(\d+)$/', $this->inModuleUri, $match)) {
                $tableId = $match[1];

                if (!($tableRow = Table::getTableRowById($tableId))) {
                    throw new errorException('');
                }

                $this->Table = tableTypes::getTable($tableRow);
            } else {
                throw new errorException('');
            }

            $this->tableUpdatedOnLoad = $this->Table->getSavedUpdated();

        } catch (errorException $errorException) {
            $this->throwError(6);
        }

        $userTables = $this->aUser->getTables();
        if (!isset($userTables[$tableRow['id']])) {
            $this->throwError(7);
        }
        if ($isRequestForWrite && empty($userTables[$tableRow['id']])) {
            $this->throwError(8);
        }


    }

    protected
    function throwError($code, $datas = [])
    {
        throw new errorException(str_replace(array_keys($datas), array_values($datas), static::$errors[$code]), $code);
    }

    protected
    function authUser()
    {

        if (!isset($this->arrayIn['auth'])) $this->throwError(2);

        $Auth = $this->arrayIn['auth'];

        if (!isset($Auth['login'])) $this->throwError(3);
        if (!isset($Auth['password'])) $this->throwError(4);

        if (!($userId = User::init()->getField('id',
            ['login' => $Auth['login'], 'pass' => md5($Auth['password']), 'interface' => 'xmljson', 'is_del' => false]))
        ) {
            $this->throwError(5);
        }

        $this->aUser = Auth::xmlInterfaceAuth($userId);
    }

    protected
    function sendJson($error, $errorDescription)
    {

        if ($errorDescription) {
            $this->arrayOut['error'] = $error;
            $this->arrayOut['errorDescription'] = $errorDescription;
        } else {
            if ($this->Table) {
                $this->arrayOut['updated'] = json_decode($this->Table->getLastUpdated(), true)['dt'];
                if ($this->tableUpdatedOnLoad != $this->Table->getLastUpdated()) {
                    $this->arrayOut['changed'] = true;
                    if (!empty($this->addedIds)) {
                        $this->arrayOut['added_ids'] = array_unique($this->addedIds);
                    }
                    if (!empty($this->deletedIds)) {
                        $this->arrayOut['removed_ids'] = array_unique($this->deletedIds);
                    }
                }
            }
        }

        if (static::$FullLogs) {
            $this->arrayOut['logs'] = static::$FullLogs;
        }

        header('Content-type: text/json');
        echo json_encode($this->arrayOut, JSON_UNESCAPED_UNICODE);
    }
}