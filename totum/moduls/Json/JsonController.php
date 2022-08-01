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

use Psr\Http\Message\ServerRequestInterface;
use totum\common\Auth;
use totum\common\calculates\CalculateAction;
use totum\common\controllers\Controller;
use totum\common\criticalErrorException;
use totum\common\errorException;
use totum\common\Field;
use totum\common\Lang\RU;
use totum\common\Totum;
use totum\common\User;
use totum\tableTypes\aTable;

class JsonController extends Controller
{

    /* Templates for translate. Don't fix it here*/
    private static $errors = [
        1 => 'Json not received or incorrectly formatted',
        2 => 'No auth section found',
        3 => 'The login attribute of the auth section was not found',
        4 => 'The password attribute of the auth section was not found',
        5 => 'The user with this data was not found. Possibly the xml/json interface is not enabled.',

        6 => 'Wrong path to the table',
        7 => 'Table access error',
        8 => 'Write access to the table is denied',
        9 => 'The recalculate section must contain restrictions in the format [["field":FIELDNAME,"operator":OPERATOR,"value":VALUE]]',
        10 => 'The field is not allowed to be edited through the api or does not exist in the specified category',
        11 => 'Multiple/Single value type error',
        12 => 'In the export section, specify "fields":[] - enumeration of fields to be exported',
        13 => 'Incorrect where in the rows-set-where section',
        14 => 'Without a table in the path, only the remotes section works',
        15 => 'Remote {var} does not exist or is not available to you',
        16 => 'The name for remote is not set',
        17 => 'Due to exceeding the number of password attempts, your IP is blocked',
    ];
    private static $translates = ['header' => 'param', 'footer' => 'footer', 'rows' => 'column'];


    protected $arrayIn;
    protected $arrayOut = [];
    protected $inModuleUri;
    /**
     * @var aTable
     */
    protected $Table;
    /**
     * @var User
     */
    protected $aUser;
    private $tableUpdatedOnLoad;
    private $addedIds = [];


    public function doIt(ServerRequestInterface $request, bool $output)
    {
        $this->modulePath = $this->totumPrefix . '/Json/';
        $this->inModuleUri = substr($request->getRequestTarget(), strlen($this->modulePath) - 1);
        $jsonString = (string)$request->getBody();

        try {
            $this->arrayIn = json_decode($jsonString, true) ?? json_decode(
                    $request->getParsedBody()['data'] ?? '',
                    true
                );
            if (!is_array($this->arrayIn)) {
                $this->throwError(1);
            }

            $this->authUser();
            $this->Totum = new Totum($this->Config, $this->aUser);
            if ($this->aUser->isCreator() && ($this->arrayIn['withLogs'] ?? null)) {
                $this->Totum->setCalcsTypesLog(is_array($this->arrayIn['withLogs']) ? $this->arrayIn['withLogs'] : ['c', 'a']);
            }

            $this->checkTable(
                array_key_exists('import', $this->arrayIn)
                || array_key_exists('recalculate', $this->arrayIn)
            );
            $this->Totum->transactionStart();
            foreach (['import', 'recalculate', 'remotes', 'export'] as $action) {
                if (array_key_exists($action, $this->arrayIn)) {
                    if (!$this->Table && $action !== 'remotes') {
                        $this->throwError(14);
                    }
                    $this->{'json' . $action}();
                }
            }

            foreach ($this->Totum->getInterfaceLinks() ?? [] as $link) {
                $data = http_build_query($link['postData']);

                $context = stream_context_create(
                    [
                        'http' => [
                            'header' => "Content-type: application/x-www-form-urlencoded\r\nUser-Agent: TOTUM\r\nConnection: Close\r\n\r\n",
                            'method' => 'POST',
                            'content' => $data
                        ]
                    ]
                );
                file_get_contents($link['uri'], false, $context);
            }
            $this->Totum->transactionCommit();
        } catch (errorException $e) {
            $error = $e->getCode() ?: -1;
            $errorDescription = $e->getMessage();
            $this->Totum?->transactionRollBack();
        } catch (criticalErrorException $e) {
            $error = $e->getCode() ?: -1;
            $errorDescription = $e->getMessage();
            $this->Totum?->transactionRollback();
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
                if (!is_array($where) || count(array_intersect_key(
                        $where,
                        ['field' => 1, 'operator' => '', 'value' => '']
                    )) !== 3) {
                    $this->throwError(9);
                }
                $params['where'][] = $where;
            }
            $params['field']='id';
            /*TODO ограничение по фильтру*/
            $ids = $this->Table->getByParams($params, 'list');
            $inVars['modify'] = array_map(
                function () {
                    return [];
                },
                array_flip($ids)
            );
        }
        $this->Table->reCalculateFromOvers($inVars);
        $this->addedIds = array_merge($this->addedIds, $this->Table->addedIds);
    }

    protected function jsonRemotes()
    {
        $selectedRemotes = [];
        $remoteOutputs = [];
        $RemotesTable = $this->Totum->getTable('ttm__remotes');
        foreach ($this->arrayIn['remotes'] ?? [] as $remote) {
            $name = $remote['name'] ?? null;
            if (!$name) {
                $this->throwError(16);
            }

            if (!key_exists($name, $selectedRemotes)) {
                $code = ($selectedRemotes[$name] = $RemotesTable->getByParams(
                    ['where' => [
                        ['field' => 'on_off', 'operator' => '=', 'value' => true],
                        ['field' => 'name', 'operator' => '=', 'value' => $name],
                        ['field' => 'api_user', 'operator' => '=', 'value' => $this->aUser->getId()],
                    ], 'field' => 'code'],
                    'field'
                ));
                if (!$code) {
                    $this->throwError(15, ['{var}' => $name]);
                }
                $selectedRemotes[$name] = new CalculateAction($code);
            }

            /** @var CalculateAction array $selectedRemotes */
            $remoteOutputs[] = $selectedRemotes[$name]->execAction(
                'CODE',
                [],
                [],
                [],
                [],
                $RemotesTable,
                'exec',
                [
                    'data' => $remote['data'] ?? null
                ]
            );
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
            if (!($isAdd ? $this->Table->isField('insertable', 'xml', $field) : $this->Table->isField(
                'editable',
                'xml',
                $field
            ))) {
                throw new errorException(
                    $this->translate($isAdd ? 'Field [[%s]] is not allowed to be added via Api' : 'Field [[%s]] is not allowed to be edited via Api',
                        $field['name']),
                    10
                );
            }

            if (Field::isFieldListValues($field['type'], $field['multiple'] ?? false)) {
                if (!is_array($v)) {
                    throw new errorException(
                        $this->translate('The [[%s]] field must contain multiple select', $field['name']),
                        11
                    );
                }
            } elseif (is_array($v)) {
                throw new errorException(
                    $this->translate('The [[%s]] field must contain a string', $field['name']),
                    11
                );
            }

            $importPart[$field['name']] = $v;
        };

        $fields = $this->Table->getFields();

        $handleItem = function ($itemArray, $category, $path, &$importPart, &$defaultsPart, &$pinnedPars, $isAdd = false) use ($fields, &$addValToImportNoColumn) {
            if (array_key_exists('__clears', $itemArray)) {
                foreach ((array)$itemArray['__clears'] as $k) {
                    if (empty($fields[$k]) || $fields[$k]['category'] !== $category) {
                        throw new errorException(
                            $this->translate('The %s field in %s table does not exist', [$k, $path]),
                            10
                        );
                    } else {
                        $defaultsPart[$k] = 1;
                    }
                }

                unset($itemArray['__clears']);
            }
            if (array_key_exists('__pins', $itemArray)) {
                foreach ((array)$itemArray['__pins'] as $k) {
                    if (empty($fields[$k]) || $fields[$k]['category'] !== $category) {
                        throw new errorException(
                            $this->translate('The %s field in %s table does not exist', [$k, $path]),
                            10
                        );
                    } else {
                        $pinnedPars[$k] = 1;
                    }
                }
                unset($itemArray['__pins']);
            }

            foreach ($itemArray as $k => $v) {
                if (empty($fields[$k]) || $fields[$k]['category'] !== $category) {
                    throw new errorException(
                        $this->translate('The %s field in %s table does not exist',[$k, $path]),
                        10
                    );
                }
                $addValToImportNoColumn($v, $fields[$k], $importPart, $isAdd);
            }
        };
        if (key_exists('rows-set-where', $this->arrayIn['import'])) {
            foreach ($this->arrayIn['import']['rows-set-where'] as $set) {
                $where = [];
                foreach ($set['where'] as $_where) {
                    if (count(array_intersect_key(
                            $_where,
                            array_flip(['field', 'operator', 'value'])
                        )) !== 3) {
                        static::throwError(13);
                    }
                    $where[] = $_where;
                }
                $ids = $this->Table->getByParams(
                    [
                        'field' => 'id', 'where' => $where
                    ],
                    'list'
                );
                if ($ids) {
                    foreach ($ids as $id) {
                        $r =& $import['modify'][(int)$id];
                        $rD =& $import['setValuesToDefaults'][(int)$id];
                        $rP =& $import['setValuesToPinned'][(int)$id];
                        unset($set['set']['id']);
                        $handleItem(
                            $set['set'],
                            'column',
                            'rows',
                            $r,
                            $rD,
                            $rP
                        );
                    }
                }
            }
        }

        foreach (static::$translates as $path => $category) {
            if ($itemsArray = ($this->arrayIn['import'][$path] ?? [])) {
                if ($path === 'rows') {
                    if (!empty($itemsArray['remove'])) {
                        foreach ($itemsArray['remove'] as $id) {
                            $import['remove'][] = (int)$id;
                        }
                        unset($itemsArray['remove']);
                    }
                    foreach ($itemsArray['add'] ?? [] as $row) {
                        unset($row['id']);
                        $nullA = [];
                        $r =& $import['add'][];
                        $handleItem(
                            $row,
                            $category,
                            $path,
                            $r,
                            $nullA,
                            $nullA,
                            true
                        );
                    }
                    foreach ($itemsArray['modify'] ?? [] as $id => $row) {
                        $r =& $import['modify'][(int)$id];
                        $rD =& $import['setValuesToDefaults'][(int)$id];
                        $rP =& $import['setValuesToPinned'][(int)$id];
                        unset($row['id']);
                        $handleItem(
                            $row,
                            $category,
                            $path,
                            $r,
                            $rD,
                            $rP
                        );
                    }
                } else {
                    $handleItem(
                        $itemsArray,
                        $category,
                        $path,
                        $import['modify']['params'],
                        $import['setValuesToDefaults']['params'],
                        $import['setValuesToPinned']['params']
                    );
                }
            }
        }
        $import['channel'] = 'xml';

        if ($import['add'] && !$this->Table->isUserCanAction(
                'insert'
            )) {
            throw new errorException($this->translate('You are not allowed to add to this table'));
        }
        if ($import['remove'] && !$this->Table->isUserCanAction(
                'delete'
            )) {
            throw new errorException($this->translate('You are not allowed to delete from this table'));
        }

        $this->Table->reCalculateFromOvers($import);
        $this->addedIds = array_merge($this->addedIds, $this->Table->addedIds);
    }

    protected function jsonExport()
    {
        if (empty($this->arrayIn['export']['fields'])) {
            $this->throwError(12);
        }

        $permittedFilters = [];
        foreach ($this->arrayIn['export']['filters'] ?? [] as $fName => $val) {
            if (($field = $this->Table->getFields()[$fName] ?? []) && $field['category'] === 'filter') {
                if ($this->Table->isField('editable', 'xml', $field)) {
                    $permittedFilters[$fName] = $val;
                }
            }
        }
        $this->Table->reCalculateFilters('xml', true, $permittedFilters);

        $data = $this->Table->getSortedFilteredRows(
            'xml',
            'xml',
            array_map(
                function ($v) {
                    return (int)$v;
                },
                (array)($this->arrayIn['export']['filters']['id'] ?? [])
            )
        );

        $withTitles = false;
        $withCalcs = false;

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
        $sortedXmlFields = $this->Table->getVisibleFields('xml', true);
        $this->arrayOut['export'] = ['header' => [], 'filters' => [], 'rows' => [], 'footer' => []];


        $tr = array_flip(static::$translates);
        //header
        foreach ($sortedXmlFields['param'] ?? [] as $fName => $field) {
            if (in_array($fName, $this->arrayIn['export']['fields'])) {
                $addToXmlOut(
                    $this->arrayOut[$tr[$field['category']]],
                    $field,
                    $this->Table->getTbl()['params'][$fName]
                );
            }
        }
        //filter
        foreach ($sortedXmlFields['filter'] ?? [] as $fName => $field) {
            if (in_array($fName, $this->arrayIn['export']['fields'])) {
                $addToXmlOut(
                    $this->arrayOut[$tr[$field['category']]],
                    $field,
                    $this->Table->getTbl()['params'][$fName]
                );
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
                $addToXmlOut(
                    $this->arrayOut[$tr[$field['category']]],
                    $field,
                    $this->Table->getTbl()['params'][$fName]
                );
            }
        }
        foreach ($this->arrayOut['export'] as $k => $v) {
            if (empty($v)) {
                unset($this->arrayOut['export'][$k]);
            }
        }
    }

    protected function checkTable($isRequestForWrite)
    {
        try {
            if ($this->inModuleUri === '') {
                return;
            } elseif (preg_match('/^(\d+)\/(\d+)\/(\d+)$/', $this->inModuleUri, $match)) {
                $cyclesTableId = $match[1];
                $cycleId = $match[2];
                $cycleTableId = $match[3];
                if (!($tableRow = $this->Totum->getTableRow($cycleTableId))) {
                    throw new errorException($this->translate('Table [[%s]] is not found.', $cycleTableId));
                }

                $Cycle = $this->Totum->getCycle($cycleId, $cyclesTableId);
                $this->Table = $Cycle->getTable($tableRow);
            } elseif (preg_match('/^(\d+)$/', $this->inModuleUri, $match)) {
                $tableId = $match[1];

                if (!($tableRow = $this->Totum->getTableRow($tableId))) {
                    throw new errorException($this->translate('Table [[%s]] is not found.', $tableId));
                }

                $this->Table = $this->Totum->getTable($tableRow);
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

    protected function throwError($code, $datas = [])
    {
        throw new errorException(str_replace(array_keys($datas),
            array_values($datas),
            $this->translate(static::$errors[$code])), $code);
    }

    protected function authUser()
    {
        if (!isset($this->arrayIn['auth'])) {
            $this->throwError(2);
        }

        $Auth = $this->arrayIn['auth'];

        if (empty($Auth['login'])) {
            $this->throwError(3);
        }
        if (empty($Auth['password'])) {
            $this->throwError(4);
        }

        switch (Auth::passwordCheckingAndProtection($Auth['login'],
            $Auth['password'],
            $userRow,
            $this->Config,
            'xmljson')) {
            case Auth::$AuthStatuses['OK']:
                $this->aUser = new User($userRow, $this->Config);
                break;
            case Auth::$AuthStatuses['WRONG_PASSWORD']:
                $this->throwError(5);
                break;
            case Auth::$AuthStatuses['BLOCKED_BY_CRACKING_PROTECTION']:
                $this->throwError(17);
        }
    }

    protected function sendJson($error, $errorDescription)
    {
        if ($errorDescription) {
            $this->arrayOut['error'] = $error;
            $this->arrayOut['errorDescription'] = $errorDescription;
        } else {
            if ($this->Table) {
                $this->arrayOut['updated'] = json_decode($this->Table->getLastUpdated(), true)['dt'];
                if ($this->tableUpdatedOnLoad !== $this->Table->getLastUpdated()) {
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

        if ($this->Totum && $this->aUser->isCreator() && ($this->arrayIn['withLogs'] ?? false)) {
            $this->arrayOut['logs'] = $this->Totum->getCalculateLog()->getLodTree();
        }

        header('Content-type: text/json');
        echo json_encode($this->arrayOut, JSON_UNESCAPED_UNICODE);
    }
}
