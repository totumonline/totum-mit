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
use totum\common\Lang\RU;
use totum\common\tableSaveOrDeadLockException;
use totum\common\Totum;
use totum\config\Conf;
use totum\config\totum\moduls\Forms\ReadTableActionsForms;
use totum\config\totum\moduls\Forms\WriteTableActionsForms;
use totum\fieldTypes\Select;
use totum\models\TmpTables;
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
     * @var false|mixed|string
     */
    protected array $extraParams = [];
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
                $this->__addAnswerVar('settings', [
                    '__browser_title' => $this->FormsTableData['format_static']['t']['f']['t'] ?? null,
                    '__background' => $this->FormsTableData['format_static']['t']['f']['b'] ?? null,
                    '__form_width' => $this->FormsTableData['format_static']['t']['f']['m'] ?? null,
                ]);

                $User = Auth::loadAuthUser($this->Config, $this->FormsTableData['call_user'], false);

                if (!$User) {
                    throw new errorException($this->translate('Forms user authorization error'));
                }

                while (true) {
                    try {
                        $this->Totum = new Totum($this->Config, $User);
                        $this->answerVars = $this->actions($request);
                        break;
                    } catch (tableSaveOrDeadLockException $exception) {
                        if (++$this->totumTries < 5) {
                            $this->Config = $this->Config->getClearConf();
                        } else {
                            throw new \Exception($this->translate('Conflicts of access to the table error'));
                        }
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
                throw new errorException($this->translate('Method not specified'));
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
                $result['showPanels'] = $panels;
            }
            if ($links = $this->Totum->getInterfaceDatas()) {
                $result['interfaceDatas'] = $links;
            }

            $this->Totum->transactionCommit();
        } catch (errorException $exception) {
            $result = ['error' => $exception->getMessage() . ($this->Totum->getUser()->isCreator() && is_callable([$exception, 'getPathMess']) ? '<br/>' . $exception->getPathMess() : '')];
        } catch (criticalErrorException $exception) {
            $result = ['error' => $exception->getMessage() . ($this->Totum->getUser()->isCreator() && is_callable([$exception, 'getPathMess']) ? '<br/>' . $exception->getPathMess() : '')];
        }
        return ['settings' => $this->answerVars['settings']] + $result;
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
                    'field' => ['path_code', 'table_name', 'type', 'call_user', 'css', 'format_static', 'fields_else_params', 'section_statuses_code', 'field_code_formats']],
                'row'
            );

            if (!$tableData) {
                throw new errorException($this->translate('Access to the form is denied.'));
            } else {
                return $tableData;
            }
        } else {
            throw new errorException($this->translate('Wrong path to the form'));
        }
    }

    private function loadTable($tableData, ServerRequestInterface $request)
    {
        $tableRow = $this->Totum->getTableRow($tableData['table_name']);
        if (!key_exists($tableRow['id'], $this->Totum->getUser()->getTables())) {
            throw new errorException($this->translate('Form configuration error - user denied access to the table'));
        }

        $post = json_decode((string)$request->getBody(), true) ?? [];
        $extradata = $tableRow['type'] === 'tmp' ? ($post['sess_hash'] ?? null) : null;

        if ($tableRow['type'] === 'tmp') {
            if ($extradata) {
                if (!tmpTable::checkTableExists($tableRow['name'], $extradata, $this->Totum)) {
                    $extradata = null;
                }
            }
            if (($post['method'] ?? null) === 'getTableData') {
                $get = $post['data']['get'];
                if (!empty($get['d']) && ($params = @Crypt::getDeCrypted($get['d'],
                        $this->Totum->getConfig()->getCryptSolt()
                    ))) {
                    $this->extraParams = json_decode($params, true);

                    if (($this->extraParams['t'] ?? false) !== $this->FormsTableData['path_code']) {
                        throw new errorException($this->translate('Incorrect link parameters'));
                    }
                }

                if (($this->FormsTableData['format_static']['t']['f']['p'] ?? false)) {
                    if (empty($this->extraParams)) {
                        throw new errorException($this->translate('The form requires link parameters to work.'));
                    }
                }
            }

        } /*elseif ($tableRow['type'] === 'simple') {
            if ($extradata) {
                try{
                    TmpTables::init($this->Totum->getConfig())->getByHash(
                        TmpTables::SERVICE_TABLES['insert_row'],
                        $this->Totum->getUser(),
                        $extradata,
                    );
                }catch (errorException){
                    $extradata = null;
                }
            }
        }*/


        $this->Table = $this->Totum->getTable($tableRow,  $extradata);
        $this->onlyRead = ($this->Totum->getUser()->getTables()[$this->Table->getTableRow()['id']] ?? null) !== 1;


        if (!$extradata && $tableRow['type'] === 'tmp') {
            $add_tbl_data = [];
            $add_tbl_data['params'] = [];

            if (key_exists('h_get', $this->Table->getFields())) {
                $add_tbl_data['params']['h_get'] = $post['data']['get'] ?? [];
            }
            if (key_exists('h_post', $this->Table->getFields())) {
                $add_tbl_data['params']['h_post'] = $post['data']['post'] ?? [];
            }
            if (key_exists('h_input', $this->Table->getFields())) {
                $add_tbl_data['params']['h_input'] = $post['data']['input'] ?? '';
            }

            if ($d = $this->extraParams) {
                if (!empty($d['d'])) {
                    $add_tbl_data['tbl'] = $d['d'];
                }
                if (!empty($d['p'])) {
                    $add_tbl_data['params'] = $d['p'] + $add_tbl_data['params'];
                }
            }
            if ($add_tbl_data) {
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
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
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
            $error = $this->translate('Table is not found.');
        } elseif ($this->FormsTableData['type'] === 'quick') {
            if (!$this->onlyRead) {
                if ($this->Table->getTableRow()['type'] !== 'simple') {
                    $error = $this->translate('This is not a simple table. Quick forms are only available for simple tables.');
                } else {
                    $Actions = new InsertTableActionsForms($request, $this->modulePath, $this->Table);
                    $error = $this->translate('Method [[%s]] in this module for quick tables is not defined.',
                        $method);
                    $Actions->checkMethodIsAvailable($method, $error);
                }
            } else {
                $error = $this->translate('The quick table is not available in read-only mode.');
            }
        } elseif (!$this->onlyRead) {
            $Actions = new WriteTableActionsForms($request, $this->modulePath, $this->Table, null);
            $error = $this->translate('Method [[%s]] in this module is not defined or has admin level access.',
                $method);
        } else {
            $Actions = new ReadTableActionsForms($request, $this->modulePath, $this->Table, null);
            $error = $this->translate('Your access to this table is read-only. Contact administrator to make changes.');
        }

        if (empty($Actions) || !is_callable([$Actions, $method])) {
            throw new errorException($error);
        }
        return $Actions;
    }
}
