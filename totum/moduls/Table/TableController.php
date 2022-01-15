<?php

namespace totum\moduls\Table;

use Psr\Http\Message\ServerRequestInterface;
use totum\common\controllers\interfaceController;
use totum\common\controllers\WithAuthTrait;
use totum\common\criticalErrorException;
use totum\common\Crypt;
use totum\common\Cycle;
use totum\common\errorException;
use totum\common\Auth;
use totum\common\Field;
use totum\common\Lang\RU;
use totum\common\logs\CalculateLog;
use totum\common\WithPathMessTrait;
use totum\common\sql\SqlException;
use totum\common\tableSaveOrDeadLockException;
use totum\common\Totum;
use totum\config\Conf;
use totum\models\Table;
use totum\models\Tree;
use totum\models\UserV;
use totum\tableTypes\aTable;

class TableController extends interfaceController
{
    use WithAuthTrait;

    /**
     * @var aTable
     */
    protected $Table;
    protected $onlyRead;
    protected $branchId;
    /**
     * @var Cycle
     */
    protected $Cycle;
    private $logTypes;
    /**
     * @var mixed
     */
    private $anchorId;
    /**
     * @var string|string[]
     */
    protected $tableUri;
    /**
     * @var CalculateLog
     */
    protected $CalculateLog;
    protected $totumTries = 0;
    protected bool $isMainAction = false;
    /**
     * @var mixed|null
     */
    protected ?string $tabButton;

    public function __construct(Conf $Config, $totumPrefix = '')
    {
        $this->Config = $Config;
        parent::__construct($Config, $totumPrefix);
        static::$contentTemplate = $this->folder . '/__Table.php';
    }

    public function actionMain(ServerRequestInterface $request)
    {
        $tmpTables = $this->Totum->getTable('roles')->getByParams(
            [
                'field' => 'tmp_for_favorites', 'where' => [
                ['field' => 'id', 'operator' => '=', 'value' => $this->Totum->getUser()->getRoles()],
                ['field' => 'tmp_for_favorites', 'operator' => '!=', 'value' => []]
            ]
            ],
            'list'
        );
        $tmp = [];
        foreach ($tmpTables as $tList) {
            array_push($tmp, ...$tList);
        }

        if ($tmp) {
            $tmp_tables = $this->Totum->getTable('tables')->getByParams(
                [
                    'field' => ['id', 'title'],
                    'where' => [
                        ['field' => 'id', 'operator' => '=', 'value' => $tmp]
                    ]
                ],
                'rows'
            );

            $this->__addAnswerVar(
                'tmp_tables',
                $tmp_tables
            );
        }

        $this->__addAnswerVar(
            'html',
            preg_replace('#<script(.*?)>(.*?)</script>#is', '', $this->Config->getSettings('main_page'))
        );
    }

    public function actionAjaxActions(ServerRequestInterface $request)
    {
        $this->checkTableByUri($request);

        $tableChanged = false;
        if ((($request->getParsedBody()['tableData']['updated']['code'] ?? null)) &&
            ($oldUpdated = json_decode($this->Table->getUpdated(),
                true))['code'] != $request->getParsedBody()['tableData']['updated']['code']) {
            $tableChanged = $oldUpdated;
        }

        $Actions = null;

        try {
            if (!($method = $request->getParsedBody()['method'] ?? '')) {
                throw new errorException($this->translate('Method not specified'));
            }
            $Actions = $this->getTableActions($request, $method);

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
            if ($tableChanged && $method !== 'refresh') {
                $tableChanged['username'] = $this->Totum->getNamedModel(UserV::class)->getFio($tableChanged['user'],
                    true);
                $result['tableChanged'] = $tableChanged;
            }
            $this->Totum->transactionCommit();
        } catch (errorException|criticalErrorException $exception) {
            $result = ['error' => $exception->getMessage() . ($this->User->isCreator() && is_callable([$exception, 'getPathMess']) ? '<br/>' . $exception->getPathMess() : '')];
        }

        if ($this->User->isCreator() && $Actions?->withLog && $this->CalculateLog && ($types = $this->Totum->getCalculateLog()->getTypes())) {
            $this->CalculateLog->addParam('result', 'done');

            if (in_array('flds', $types)) {
                $result['FieldLOGS'] = $this->CalculateLog->getFieldLogs();
            } else {
                $result['LOGS'] = $this->CalculateLog->getLogsByElements($this->Table->getTableRow()['id']);
                //$result['TREELOGS'] = $this->CalculateLog->getLodTree();
                $result['FullLOGS'] = [$this->CalculateLog->getLogsForJsTree($this->Totum->getLangObj())];
            }
        }


        return $result;
    }


    protected function setTreeData()
    {
        $this->__addAnswerVar('Branch', $this->branchId);
        $this->__addAnswerVar('ModulePath', $this->modulePath);


        $tree = [];
        $branchIds = [];

        if ($this->User->isCreator()) {
            $branchesArray = Tree::init($this->Config)->getBranchesForCreator($this->branchId);
        } else {
            $branchesArray = Tree::init($this->Config)->getBranchesByTables(
                $this->branchId,
                array_keys($this->User->getTreeTables()),
                $this->User->getRoles()
            );
        }
        $anchors = [];
        foreach ($branchesArray as $t) {
            /*For top branches get only same branch. Top branches are got not here*/
            if (!$t['parent_id']) {
                if ($t['id'] === (int)$this->branchId) {
                    $this->__addAnswerVar('title', $t['title'], true);
                    $this->__addAnswerVar('BranchTitle', $t['title'], true);
                }
                if ($t['top'] !== (int)$this->branchId) {
                    continue;
                }
            }

            if ($t['type'] === 'anchor') {
                $anchors[$t['parent_id']][$t['default_table']][] = count($tree);
            }

            $tree[] =
                ($t['type'] === 'link' ? ['link' => $t['link']] : []) + [
                    'id' => 'tree' . $t['id']
                    , 'text' => $t['title']
                    , 'type' => $t['type'] ? ($t['type'] === 'anchor' ? "link" : $t['type']) : 'folder'
                    , 'link' => $t['type'] == 'anchor' ? ($this->modulePath . $t['id'] . '/') : null
                    , 'parent' => ($parent = (!$t['parent_id'] ? '#' : 'tree' . $t['parent_id']))
                    , 'ord' => $t['ord']
                    , 'state' => [
                        'selected' => $t['type'] === 'anchor' ? ((int)$this->anchorId === (int)$t['id']) : false
                    ]
                ]
                + (
                $t['icon'] ? ['icon' => 'fa fa-' . $t['icon']] : []
                );
            if ($t['type'] !== "link") {
                $branchIds[] = $t['id'];
            }
        }
        if ($branchIds) {
            foreach (Table::init($this->Config)->getAll(
                ['tree_node_id' => ($branchIds), 'id' => array_keys($this->User->getTreeTables())],
                'id, title, type, tree_node_id, sort, icon, name',
                '(sort->>\'v\')::numeric'
            ) as $t) {
                $tree[] = [
                    'id' => 'table' . $t['id']
                    , 'href' => $t['id']
                    , 'text' => $t['title']
                    , 'type' => 'table_' . $t['type']
                    , 'name' => $t['name']
                    , 'icon' => ($t['icon'] ?? null)
                    , 'parent' => 'tree' . $t['tree_node_id']
                    , 'ord' => (int)$t['sort']
                    , 'state' => [
                        'selected' => !$this->anchorId && $this->Table && $this->Table->getTableRow()['id'] === $t['id']
                    ]
                ];

                if (!empty($anchors[$t['tree_node_id']][$t['id']])) {
                    foreach ($anchors[$t['tree_node_id']][$t['id']] as $index) {
                        $tree[$index]['parent'] = 'table' . $t['id'];
                    }
                }
            }
        };
        /*sort through folders and tables*/
        {
            $ords = array_column($tree, 'ord');
            array_multisort($ords, $tree);
        }

        if ($this->Cycle) {
            $cyclesTableId = $this->Cycle->getCyclesTableId();
            $idHref = 'Cycle' . $this->Cycle->getId();
            $CyclesTable = $this->Cycle->getCyclesTable();

            $isOneTable = false;
            if ($this->User->isOneCycleTable($CyclesTable->getTableRow()) && $CyclesTable->getUserCyclesCount() === 1) {
                $idHref = 'table' . $cyclesTableId;
                $isOneTable = true;
            } else {
                $cycleRow = [
                    'id' => $idHref
                    , 'href' => '#'
                    , 'text' => $this->Cycle->getRowName()
                    , 'type' => 'cycle_name'
                    , 'parent' => ($this->anchorId ? 'tree' . $this->anchorId : 'table' . $cyclesTableId)
                    , 'state' => [
                        'selected' => false
                    ]
                ];
                $tree[] = &$cycleRow;
            }

            foreach ($this->Cycle->getViewListTables() as $i => $tName) {
                if ($tableRow = $this->Totum->getTableRow($tName)) {
                    $tId = $tableRow['id'];
                    if (array_key_exists($tId, $this->User->getTreeTables())) {
                        $tbl = [
                            'id' => 'table' . $tId
                            , 'href' => $cyclesTableId . '/' . $this->Cycle->getId() . '/' . $tId
                            , 'text' => $tableRow['title']
                            , 'icon' => ($tableRow['icon'] ?? null)
                            , 'type' => 'table_calcs'
                            , 'parent' => $idHref
                            , 'isCycleTable' => true
                            , 'isOneUserCycle' => $isOneTable
                            , 'state' => [
                                'selected' => ($this->Table?->getTableRow()['id'] ?? 0) === $tId
                            ]
                        ];
                        if ($this->User->isCreator()) {
                            $tbl['name'] = $tableRow['name'];
                        }
                        $tree[] = $tbl;
                        if ($this->anchorId) {
                            unset($tree[count($tree) - 1]['href']);
                            $tree[count($tree) - 1]['link'] = $this->modulePath . $this->anchorId . '/' . $this->Cycle->getId() . '/' . $tId;
                        }
                        if ($i === 0 && !empty($cycleRow)) {
                            if ($this->anchorId) {
                                $cycleRow['link'] = $this->modulePath . $this->anchorId . '/' . $this->Cycle->getId() . '/' . $tId;
                            } else {
                                $cycleRow['href'] = $cyclesTableId . '/' . $this->Cycle->getId() . '/' . $tId;
                            }
                        }
                    }
                }
            }
            /*Подключенные вкладки-кнопки*/


            foreach ($CyclesTable->getFields() as $field) {
                if ($field['category'] === 'column' && str_starts_with($field['name'], 'tab_') &&
                    $field['type'] === 'button' && $CyclesTable->isField('visible', 'web', $field) &&
                    ($CyclesTable->isUserCanAction('edit') || ($field['pressableOnOnlyRead'] ?? false))) {

                    $tree[] = [
                        'id' => $cyclesTableId . '/' . $this->Cycle->getId() . '/' . $field['name']
                        , 'text' => $field['title']
                        , 'icon' => 'fa fa-hand-pointer-o'
                        , 'type' => 'tab_button'
                        , 'isCycleTable' => true
                        , 'parent' => $idHref
                        , 'state' => [
                            'selected' => $this->tabButton === $field['name']
                        ]
                    ];

                }

            }

        }


        foreach ($tree as $i => $_t) {
            if ($_t['id'] === 'tree' . $this->branchId) {
                unset($tree[$i]);
            } elseif ($_t['parent'] === 'tree' . $this->branchId) {
                $tree[$i]['parent'] = '#';
            }
        }
        $tree = array_values($tree);

        if (empty($tree) && $this->isMainAction) {
            foreach (Table::init($this->Config)->getAll(
                ['id' => $this->User->getFavoriteTables()],
                'id, top, title, type, icon, name',
                'sort'
            ) as $t) {
                $tbl = [
                    'id' => 'table' . $t['id']
                    , 'href' => $this->modulePath . $t['top'] . '/' . $t['id']
                    , 'text' => $t['title']
                    , 'type' => 'table_' . $t['type']
                    , 'name' => $t['name']
                    , 'icon' => ($t['icon'] ?? null)
                    , 'parent' => '#'
                ];
                if ($this->User->isCreator()) {
                    $tbl['name'] = $t['name'];
                }
                $tree[] = $tbl;
            }
        }

        $this->__addAnswerVar('treeData', $tree);
    }

    protected function outputHtmlTemplate()
    {
        try {
            if ($this->User) {
                $this->__addAnswerVar('isCreatorView', $this->User->isCreator());
                $this->__addAnswerVar('UserName', $this->User->getVar('fio'), true);
                $userManager = array_intersect(Auth::$userManageRoles, $this->User->getRoles());
                $suDo = Auth::isCanBeOnShadow($this->User);
                if ($suDo || $userManager) {
                    if ($suDo) {
                        $reUsers = Auth::getUsersForShadow($this->Config, $this->User);
                        $this->__addAnswerVar(
                            'reUsers',
                            array_combine(array_column($reUsers, 'id'), array_column($reUsers, 'fio'))
                        );
                        $this->__addAnswerVar('isCreatorNotItself', Auth::isUserNotItself());
                    } else {
                        $this->__addAnswerVar(
                            'reUsers',
                            []
                        );
                    }
                    if ($userManager) {
                        $this->__addAnswerVar('UserTables', Auth::getUserManageTables($this->Config));
                    }
                }
            }
            $this->__addAnswerVar('schema_name', $this->Config->getSettings('totum_name'), true);

            $this->__addAnswerVar('notification_period', $this->Config->getSettings('periodicity') ?? 0);
            $this->__addAnswerVar('topBranches', $this->getTopBranches(), true);
            $this->__addAnswerVar('totumFooter', $this->Config->getTotumFooter());


            $this->setTreeData();

            if (isset($this->Table)) {
                $this->__addAnswerVar('title', $this->Table->getTableRow()['title'], true);
            }
        } catch (SqlException $e) {
            $this->Config->getLogger('sql')->error($e->getMessage(), $e->getTrace());
            $this->__addAnswerVar('error', $this->translate('Database error: [[%s]]', $e->getMessage()));
        }
        parent::outputHtmlTemplate();
    }

    /**
     * @return array
     */
    protected function getTopBranches()
    {
        if ($this->User->isCreator()) {
            $topBranches = Tree::init($this->Config)->getBranchesForCreator(null);
        } else {
            $topBranches = Tree::init($this->Config)->getBranchesByTables(
                null,
                array_keys($this->User->getTreeTables()),
                $this->User->getRoles()
            );
        }
        foreach ($topBranches as &$branch) {
            $href = $this->modulePath . $branch['id'] . '/';

            if (!empty($branch['default_table']) && $this->User->isTableInAccess($branch['default_table'])) {
                $href .= $branch['default_table'] . '/';
            }
            $branch['href'] = $href;
            if (is_a(
                    $this,
                    TableController::class
                ) && !empty($this->branchId) && $branch['id'] === (int)$this->branchId) {
                $branch['active'] = true;
            }
        }
        unset($branch);
        return $topBranches;
    }

    /**
     * Check action from path, run, output
     *
     * @param ServerRequestInterface $request
     * @param bool $output
     */
    public function doIt(ServerRequestInterface $request, bool $output)
    {
        $requestUri = preg_replace('/\?.*/', '', $request->getUri()->getPath());
        $requestTable = substr($requestUri, strlen($this->modulePath));

        $post = ($request->getParsedBody());

        $this->tabButton = $request->getQueryParams()['b'] ?? null;

        if ($post['ajax'] ?? null) {
            $this->isAjax = true;
        }
        if ($requestTable || $this->isAjax) {
            if (!$this->isAjax) {
                $action = 'Table';
            } else {
                $action = 'Actions';
            }
            $this->tableUri = $requestTable;
        } else {
            $action = 'Main';
            $this->isMainAction = true;
        }
        try {
            try {
                if ($this->isAjax) {
                    $action = 'Ajax' . $action;
                }
                $this->__run($action, $request);
            } catch (tableSaveOrDeadLockException $exception) {
                if (++$this->totumTries < 5) {
                    $this->Config = $this->Config->getClearConf();
                    $this->answerVars = [];
                    $this->doIt($request, false);
                } else {
                    throw new \Exception($this->translate('Conflicts of access to the table error'));
                }
            }
        } catch (\Exception $e) {
            if (!$this->isAjax) {
                static::$contentTemplate = $this->Config->getTemplatesDir() . '/__error.php';
            }
            $message = $e->getMessage();
            if ($this->User && $this->User->isCreator() && method_exists($e, 'getPathMess') && $e->getPathMess()) {
                $message .= '<br/>' . $e->getPathMess();
            }
            $this->__addAnswerVar('error', $message);
        }
        if ($output) {
            $this->output($action);
        }
    }

    public function __actionRun($action, ServerRequestInterface $request)
    {
        $this->Totum = new Totum($this->Config, $this->User);
        $this->Totum->setCalcsTypesLog(json_decode($request->getCookieParams()['pcTableLogs'] ?? '[]', true));

        parent::__actionRun($action, $request);
    }


    public function actionTable(ServerRequestInterface $request)
    {
        $this->checkTableByUri($request);

        if (!$this->Table) {
            return;
        }
        try {
            /*Для таблиц циклов с одним циклом на пользователя*/
            if ($this->Table->getUser()->isOneCycleTable($this->Table->getTableRow())) {
                $cyclesCount = $this->Table->getUserCyclesCount();
                if ($cyclesCount === 0) {
                    $this->Table->reCalculateFromOvers(['add' => []]);
                    $cyclesCount = 1;
                }
                if ($cyclesCount === 1) {
                    $Cycle = $this->Totum->getCycle(
                        $this->Table->getUserCycles($this->Table->getUser()->getId())[0],
                        $this->Table->getTableRow()['id']
                    );
                    $calcsTablesIDs = $Cycle->getTableIds();
                    if (!empty($calcsTablesIDs)) {
                        foreach ($calcsTablesIDs as $tableId) {
                            if ($this->Table->getUser()->isTableInAccess($tableId)) {
                                $this->location($this->modulePath . $this->Table->getTableRow()['top'] . '/' . $this->Table->getTableRow()['id'] . '/' . $Cycle->getId() . '/' . $tableId);
                                die;
                            }
                        }
                    }
                }
            }
        } catch (criticalErrorException $e) {
            $error = $this->translate('Error: %s', $e->getMessage());
            if ($this->User && $this->User->isCreator() && $e->getPathMess()) {
                $error .= '<br/>' . $e->getPathMess();
            }
        }


        /*Основная часть отдачи таблицы*/
        if (empty($error)) {
            try {
                $Actions = $this->getTableActions($request, 'getFullTableData');
                $result = $Actions->getFullTableData(true);
            } catch (criticalErrorException $exception) {
                $this->clearTotum($request);
                $Actions = $this->getTableActions($request, 'getFullTableData');
                $error = $exception->getMessage();
                if ($this->User && $this->User->isCreator() && $exception->getPathMess()) {
                    $error .= '<br/>' . $exception->getPathMess();
                }
                $result = $Actions->getFullTableData(false);
            }
        }

        $result['isCreatorView'] = $this->User->isCreator();
        $result['checkIsUpdated'] = (in_array(
            $this->Table->getTableRow()['actual'],
            ['none', 'disable']
        )) ? 0 : 1;

        $result['isMain'] = true;
        if (!empty($a = ($request->getQueryParams()['a'] ?? null))) {
            $result['addVars'] = $a;
        }

        if ($this->User->isCreator()) {
            $result['TableFields'] = ['branchId' => 1, 'id' => 'tables_fields'];
            $result['Tables'] = ['branchId' => 1, 'id' => 'tables'];

            $result['hidden_fields'] = array_diff_key($this->Table->getFields(), $this->Table->getVisibleFields('web'));

            if ($result['tableRow']['type'] === 'calcs') {
                $result['TablesCyclesVersions'] = ['branchId' => 1, 'id' => 'calcstable_cycle_version'];
                $result['TablesVersions'] = ['branchId' => 1, 'id' => 'calcstable_versions'];
                $result['calcstable_cycle_version_filters'] = Crypt::getCrypted(json_encode(
                    [
                        'fl_table' => $this->Table->getTableRow()['tree_node_id'],
                    ],
                    JSON_UNESCAPED_UNICODE
                ));
                $result['calcstable_versions_filters'] = Crypt::getCrypted(json_encode(
                    [
                        'fl_table' => $this->Table->getTableRow()['tree_node_id'],
                        'fl_name' => $this->Table->getTableRow()['name'],
                        'fl_cycle' => $this->Table->getCycle()->getId()
                    ],
                    JSON_UNESCAPED_UNICODE
                ));
            }
            ini_set('xdebug.var_display_max_depth', '20');

            if (($types = $this->Totum->getCalculateLog()->getTypes())) {
                if (in_array('flds', $types)) {
                    $result['FieldLOGS'] = [['data' => $this->CalculateLog->getFieldLogs(), 'name' => $this->translate('Calculating the table')]];
                } else {
                    $result['LOGS'] = $this->CalculateLog->getLogsByElements($this->Table->getTableRow()['id']);
                    $result['FullLOGS'] = [$this->CalculateLog->getLogsForjsTree($this->Totum->getLangObj())];
                    // $result['treeLogs'] = $this->CalculateLog->getLodTree();
                }
            }
        }

        if ($links = $this->Totum->getInterfaceLinks()) {
            $result['links'] = $links;
        }
        if ($panels = $this->Totum->getPanelLinks()) {
            $result['panels'] = $panels;
        }
        if ($links = $this->Totum->getInterfaceDatas()) {
            $result['interfaceDatas'] = $links;
        }

        $this->__addAnswerVar('error', $error ?? $result['error'] ?? null);
        $this->__addAnswerVar('tableConfig', $result);
    }

    protected function checkTableByUri(ServerRequestInterface $request)
    {
        if (!preg_match('/^(\d+)\//', $this->tableUri, $branchMatches)) {
            return;
        }
        $this->branchId = $branchMatches[1];

        $tableUri = substr($this->tableUri, strlen($this->branchId) + 1);
        $tableId = 0;

        $checkTreeTable = function ($tableId) use ($request) {
            if (!array_key_exists($tableId, $this->User->getTables())) {
                throw new errorException($this->translate('Access to the table is denied.'));
            } else {
                $this->onlyRead = $this->User->getTables()[$tableId] === 0;
                $extradata = null;
                if ($tableRow = $this->Config->getTableRow($tableId)) {
                    switch ($tableRow['type']) {
                        case 'calcs':
                            $this->__addAnswerVar('error', $this->translate('Wrong path to the table'));
                            return;
                        case 'tmp':
                            $extradata = $request->getParsedBody()['tableData']['sess_hash'] ?? $request->getQueryParams()['sess_hash'] ?? null;
                            break;
                    }
                    $this->Table = $this->Totum->getTable($tableRow, $extradata);
                }
            }
        };


        if (!empty($request->getParsedBody()['method']) && in_array(
                $request->getParsedBody()['method'],
                ['getValue']
            )) {
            if (!empty($request->getParsedBody()['table_id'])) {
                $checkTreeTable((int)$request->getParsedBody()['table_id']);
                return;
            }
        }


        if ($tableUri && (preg_match(
                    '/^(\d+)\/(\d+)\/(\d+|[a-z][a-z_0-9]+)/',
                    $tableUri,
                    $tableMatches
                ) || preg_match('/^(\d+)\/(\d+)/', $tableUri, $tableMatches))) {
            if (empty($tableMatches[3])) {
                $tableRow = $this->Config->getTableRow($tableMatches[2]);
                if ($tableRow['type'] !== 'calcs') {
                    throw new errorException($this->translate('Wrong path to the table'));
                }
                $tableId = $tableMatches[2];
                $tableMatches[2] = $tableMatches[1];
                $tableMatches[1] = $tableRow['tree_node_id'];
                $branchData = $this->Totum->getTable('tree')->getByParams(
                    ['field' => ['html', 'type', 'default_table', 'filters', 'top'], 'where' => [['field' => 'id', 'operator' => '=', 'value' => $this->branchId]]],
                    'row'
                );
                switch ($branchData['type']) {
                    case null:
                        $this->__addAnswerVar(
                            'html',
                            preg_replace('#<script(.*?)>(.*?)</script>#is', '', $branchData['html'])
                        );
                        break;
                    case 'anchor':
                        $this->anchorId = $this->branchId;
                        $this->branchId = $branchData['top'];
                        break;
                }
            } else {

                $tableId = $tableMatches[3];
                if (!is_numeric($tableId) && ($calcsTableRow = $this->Totum->getTableRow($tableId))) {
                    $tableId = $calcsTableRow['id'];
                }
                if (!is_numeric($tableMatches[1])) {
                    $tableMatches[1] = $this->Totum->getTableRow($tableMatches[1])['id'];
                } elseif ($tableMatches[1] === '0') {
                    $tableMatches[1] = ($calcsTableRow ?? $this->Totum->getTableRow($tableId))['tree_node_id'];
                }

            }

            $this->Cycle = $this->Totum->getCycle($tableMatches[2], $tableMatches[1]);
            if (!$this->Cycle->loadRow()) {
                throw new errorException($this->translate('Cycle [[%s]] is not found.', ''));
            }

            if (!array_key_exists($tableId, $this->User->getTables())) {
                throw new errorException($this->translate('Access to the table is denied.'));
            } else {
                $this->onlyRead = $this->User->getTables()[$tableId] === 0;

                //Проверка доступа к циклу

                if (!$this->User->isCreator() && !empty($this->Cycle->getCyclesTable()->getFields()['creator_id']) && in_array(
                        $this->Cycle->getCyclesTable()->getTableRow()['cycles_access_type'],
                        [1, 2, 3]
                    )) {
                    //Если не связанный пользователь
                    if (count(array_intersect(
                            $this->Cycle->getRow()['creator_id']['v'],
                            $this->User->getConnectedUsers()
                        )) === 0) {
                        if ($this->Cycle->getCyclesTable()->getTableRow()['cycles_access_type'] === '3') {
                            $this->onlyRead = true;
                        } else {
                            throw new errorException($this->translate('Access to the cycle is denied.'));
                        }
                    }
                }

                if ($tableRow = $this->Config->getTableRow($tableId)) {
                    if ($tableRow['type'] === 'calcs') {
                        $this->Table = $this->Cycle->getTable($tableRow);
                    } elseif ($this->tabButton) {
                        $this->Table = $this->Totum->getTable($tableRow);
                        $this->Table->setAnchorFilters(json_decode(Crypt::getDeCrypted(strval($request->getQueryParams()['f'] ?? '[]'), true),
                            true));

                    } else {
                        throw new errorException($this->translate('Wrong path to the table'));
                    }

                }
            }
        } elseif ($tableUri && preg_match('/^([a-z0-9_]+)/', $tableUri, $tableMatches)) {
            if (!ctype_digit($tableMatches[1])) {
                $tableRow = $this->Config->getTableRow($tableMatches[1]);
                if ($tableRow) {
                    $tableId = $tableRow['id'];
                }
            } else {
                $tableId = $tableMatches[1];
            }
            $checkTreeTable($tableId);
        } else {
            if ($branchData = $this->Totum->getModel('tree')->executePrepared(
                false,
                ['id' => $this->branchId],
                'html,type,default_table,filters,top'
            )->fetch()) {

                switch ($branchData['type']) {
                    case null:
                        $this->__addAnswerVar(
                            'html',
                            preg_replace('#<script(.*?)>(.*?)</script>#is', '', $branchData['html'])
                        );
                        break;
                    case 'anchor':
                        $this->anchorId = $this->branchId;
                        $this->branchId = $branchData['top'];
                        $this->Table = $this->Totum->getTable($branchData['default_table']);
                        $this->Table->setAnchorFilters(json_decode($branchData['filters'] ?? '[]', true));
                        break;
                }
            }
        }

        if ($this->Table) {
            $this->CalculateLog = $this->Table->getCalculateLog();
            Conf::$CalcLogs = $this->CalculateLog;
        }
    }

    /**
     * @param ServerRequestInterface $request
     * @param string $method
     * @return AdminTableActions|ReadTableActions|WriteTableActions
     * @throws errorException
     */
    protected function getTableActions(ServerRequestInterface $request, string $method)
    {
        if (!$this->Table) {
            $Actions = new Actions($request, $this->modulePath, null, $this->Totum);
            $error = $this->translate('Table is not found.');
        } elseif ($this->User->isCreator()) {
            $Actions = new AdminTableActions($request, $this->modulePath, $this->Table, null);
            $error = $this->translate('Method [[%s]] in this module is not defined.', $method);
        } elseif (!$this->onlyRead) {
            $Actions = new WriteTableActions($request, $this->modulePath, $this->Table, null);
            $error = $this->translate('Method [[%s]] in this module is not defined or has admin level access.',
                $method);
        } else {
            $Actions = new ReadTableActions($request, $this->modulePath, $this->Table, null);
            $error = $this->translate('Your access to this table is read-only. Contact administrator to make changes.');
        }

        if (!is_callable([$Actions, $method])) {
            throw new errorException($error);
        }
        return $Actions;
    }

    protected function clearTotum($request): void
    {
        $this->Config = $this->Config->getClearConf();
        $this->Totum = new Totum($this->Config, $this->User);
        $this->checkTableByUri($request);
    }
}
