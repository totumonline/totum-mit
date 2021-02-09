<?php


namespace totum\moduls\Forms;

use Psr\Http\Message\ServerRequestInterface;
use totum\common\Auth;
use totum\common\calculates\Calculate;
use totum\common\calculates\CalculcateFormat;
use totum\common\criticalErrorException;
use totum\common\Crypt;
use totum\common\errorException;
use totum\common\Field;
use totum\common\controllers\interfaceController;
use totum\common\tableSaveException;
use totum\common\Totum;
use totum\config\Conf;
use totum\config\totum\moduls\Forms\ReadTableActionsForms;
use totum\config\totum\moduls\Forms\WriteTableActionsForms;
use totum\fieldTypes\Select;
use totum\moduls\Table\Actions;
use totum\tableTypes\aTable;
use totum\tableTypes\tmpTable;

class FormsController extends interfaceController
{
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
    /**
     * @var array|object|null
     */
    private $INPUT;
    private $totumTries = 0;

    public function __construct(Conf $Config, $totumPrefix = '')
    {
        $this->applyAllOrigins();
        parent::__construct($Config, $totumPrefix);
        static::$pageTemplate = __DIR__ . '/__template.php';
    }

    public function doIt(ServerRequestInterface $request, bool $output)
    {
        $requestUri = preg_replace('/\?.*/', '', $request->getUri()->getPath());
        $requestTable = substr($requestUri, strlen($this->modulePath));


        if ($request->getMethod() === 'GET') {
            $action = "Main";
            $this->__addAnswerVar('path', $requestTable);
        } else {
            $this->isAjax = true;

            try {
                $this->FormsTableData = $this->checkTableByStr($requestTable);
                $User = Auth::loadAuthUser($this->Config, $this->FormsTableData['call_user'], false);

                if (!$User) {
                    throw new errorException('Ошибка авторизации пользователя форм');
                }

                try {
                    $this->Totum = new Totum($this->Config, $User);
                    $this->answerVars = $this->actions($request);
                } catch (tableSaveException $exception) {
                    if (++$this->totumTries < 5) {
                        $this->Config = $this->Config->getClearConf();
                        $this->Totum = new Totum($this->Config, $User);
                        $this->answerVars = $this->actions($request);
                    } else {
                        throw new \Exception('Ошибка одновременного доступа к таблице');
                    }
                }
            } catch (\Exception $e) {
                if (!$this->isAjax) {
                    static::$contentTemplate = $this->Config->getTemplatesDir() . '/__error.php';
                }
                $message = $e->getMessage();
                $this->__addAnswerVar('error', $message);
            }
            $action = "json";
        }
        if ($output) {
            $this->output($action);
        }
    }

    protected function actions(ServerRequestInterface $request)
    {
        $this->loadTable($this->FormsTableData, $request);

        $parsedRequest = json_decode((string)$request->getBody(), true);
        try {
            if (!($method = $parsedRequest['method'] ?? '')) {
                throw new errorException('Ошибка. Не указан метод');
            }
            $Actions = $this->getTableActions($request, $method);

            if (is_callable([$Actions, 'addFormsTableData'])) {
                $Actions->addFormsTableData($this->FormsTableData);
            }

            if (!in_array($method, ['checkForNotifications', 'checkTableIsChanged'])) {
                $this->Totum->transactionStart();
            }

            /** @var string $method */
            $result = $Actions->$method();

            if ($links = $this->Totum->getInterfaceLinks()) {
                $result['links'] = $links;
            }
            if ($panels = $this->Totum->getPanelLinks()) {
                $result['panels'] = $panels;
            }
            if ($links = $this->Totum->getInterfaceDatas()) {
                $result['interfaceDatas'] = $links;
            }

            $this->Totum->transactionCommit();
        } catch (errorException $exception) {
            $result = ['error' => $exception->getMessage() . ($this->Totum->getUser()->isCreator() && is_callable([$exception, 'getPathMess']) ? "<br/>" . $exception->getPathMess() : '')];
        } catch (criticalErrorException $exception) {
            $result = ['error' => $exception->getMessage() . ($this->Totum->getUser()->isCreator() && is_callable([$exception, 'getPathMess']) ? "<br/>" . $exception->getPathMess() : '')];
        }

        return $result;
    }

    public function getEditSelect($data, $q, $parentId, $viewtype = null)
    {
        $type = $viewtype;

        $fields = $this->Table->getFields();

        if (!($field = $fields[$data['field']] ?? null)) {
            throw new errorException('Не найдено поле [[' . $data['field'] . ']]. Возможно изменилась структура таблицы. Перегрузите страницу');
        }
        if (!in_array(
            $field['type'],
            ['select', 'tree']
        )) {
            throw new errorException('Ошибка - поле не типа select/tree');
        }

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
            foreach ($Field->calculateSelectListWithPreviews(
                $row[$field['name']],
                $row,
                $this->Table->getTbl()
            ) as $val => $data) {
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

    private static function _getHttpFilePath()
    {
        return static::$path ?? (static::$path = (
                (!empty($_SERVER['HTTPS']) && 'off' !== strtolower($_SERVER['HTTPS']) ? 'https://' : 'http://') . \totum\config\Conf::getFullHostName() . '/fls/'
            ));
    }

    public function actionMain()
    {
        $this->__addAnswerVar('css', $this->FormsTableData['css']);
    }

    protected function checkTableByStr($form)
    {
        if ($form) {
            $Totum = new Totum($this->Config, Auth::ServiceUserStart($this->Config));
            $tableData = $Totum->getTable('ttm__forms')->getByParams(
                ['where' => [
                    ['field' => 'path_code', 'operator' => '=', 'value' => $form],
                    ['field' => 'on_off', 'operator' => '=', 'value' => true]],
                    'field' => ['table_name', 'call_user', 'css', 'format_static', 'fields_else_params', 'section_statuses_code']],
                'row'
            );

            if (!$tableData) {
                throw new errorException('Доступ к таблице запрещен');
            } else {
                return $tableData;
            }
        } else {
            throw new errorException('Неверный путь к таблице');
        }
    }

    protected function stch()
    {
        $this->CalcTableFormat = new CalculcateFormat($this->Table->getTableRow()['table_format']);
        $this->CalcRowFormat = new CalculcateFormat($this->Table->getTableRow()['row_format']);

        if ($this->FormsTableData['section_statuses_code'] && !preg_match(
                '/^\s*=\s*:\s*$/',
                $this->FormsTableData['section_statuses_code']
            )) {
            $this->CalcSectionStatuses = new Calculate($this->FormsTableData['section_statuses_code']);
        }
    }

    private function loadTable($tableData, ServerRequestInterface $request)
    {
        $tableRow = $this->Totum->getTableRow($tableData['table_name']);
        if (!key_exists($tableRow['id'], $this->Totum->getUser()->getTables())) {
            throw new errorException('Ошибка настройки формы - пользователю запрещен доступ к таблице');
        }

        $extradata = null;
        $post = json_decode((string)$request->getBody(), true) ?? [];
        $extradata = $post['sess_hash'] ?? null;
        if ($tableRow['type'] === 'tmp' && $extradata) {
            if (!tmpTable::checkTableExists($tableRow['name'], $extradata, $this->Totum)) {
                $extradata = null;
            }
        }

        $this->Table = $this->Totum->getTable($tableRow, $extradata);

        $this->onlyRead = ($this->Totum->getUser()->getTables()[$this->Table->getTableRow()['id']] ?? null) !== 1;

        if (!$extradata) {
            $add_tbl_data = [];
            $add_tbl_data["params"] = [];
            if (key_exists('h_get', $this->Table->getFields())) {
                $add_tbl_data["params"]['h_get'] = $post['data']['get'] ?? [];
            }
            if (key_exists('h_post', $this->Table->getFields())) {
                $add_tbl_data["params"]['h_post'] = $post['data']['post'] ?? [];
            }
            if (key_exists('h_input', $this->Table->getFields())) {
                $add_tbl_data["params"]['h_input'] = $post['data']['input'] ?? '';
            }
            if (!empty($_GET['d']) && ($d = Crypt::getDeCrypted(
                    $_GET['d'],
                    false
                )) && ($d = json_decode($d, true))) {
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
    }

    private function applyAllOrigins()
    {
        if (isset($_SERVER['HTTP_ORIGIN'])) {
            // should do a check here to match $_SERVER['HTTP_ORIGIN'] to a
            // whitelist of safe domains
            header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Max-Age: 86400');    // cache for 1 day
        }
        if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
            if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD'])) {
                header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
            }

            if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'])) {
                header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
            }
            die;
        }
    }

    /**
     * @param ServerRequestInterface $request
     * @param string $method
     * @throws errorException
     */
    protected function getTableActions(ServerRequestInterface $request, string $method)
    {
        if (!$this->Table) {
            $Actions = new Actions($request, $this->modulePath, null, $this->Totum);
            $error = 'Таблица не найдена';
        } elseif (!$this->onlyRead) {
            $Actions = new WriteTableActionsForms($request, $this->modulePath, $this->Table, null);
            $error = 'Метод [[' . $method . ']] в этом модуле не определен или имеет админский уровень доступа';
        } else {
            $Actions = new ReadTableActionsForms($request, $this->modulePath, $this->Table, null);
            $error = 'Ваш доступ к этой таблице - только на чтение. Обратитесь к администратору для внесения изменений';
        }

        if (!is_callable([$Actions, $method])) {
            throw new errorException($error);
        }
        return $Actions;
    }
}
