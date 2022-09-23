<?php


namespace totum\common;

use PDO;
use Symfony\Component\Console\Output\OutputInterface;
use totum\common\calculates\CalculateAction;
use totum\common\Lang\RU;
use totum\common\sql\Sql;
use totum\config\Conf;
use totum\fieldTypes\fieldParamsResult;
use totum\models\CalcsTableCycleVersion;
use totum\models\NonProjectCalcs;
use totum\models\Table;
use totum\models\TablesFields;
use totum\tableTypes\aTable;

class TotumInstall
{
    /**
     * @var Conf
     */
    protected $Config;
    /**
     * @var Sql
     */
    protected $Sql;
    protected $Totum;
    /**
     * @var User
     */
    protected $User;
    /**
     * @var OutputInterface
     */
    protected $outputConsole;
    /**
     * @var string
     */
    protected $confClassCode;
    private $installSettings;

    public function __construct($Config, $user, $outputConsole = null, $CalculateLog = null)
    {
        if (is_array($Config)) {
            $this->installSettings = $Config;
            $this->Config = $this->createConfig($Config, $this->installSettings['host'] ?? $_SERVER['HTTP_HOST']);

            $this->User = new User(['login' => $user, 'roles' => ['1'], 'id' => 1], $this->Config);
        } else {
            $this->Config = $Config;
            if (is_object($user)) {
                $this->User = $user;
            } else {
                $this->User = Auth::loadAuthUserByLogin($this->Config, $user, false);
            }
        }
        $this->Sql = $this->Config->getSql();

        $this->Totum = new Totum($this->Config, $this->User);
        $this->Totum->setCalcsTypesLog(['all']);
        $this->CalculateLog = $CalculateLog ?? $this->Totum->getCalculateLog();
        $this->outputConsole = $outputConsole;
    }

    protected function translate(string $str, mixed $vars = []): string
    {
        return $this->Config->getLangObj()->translate($str, $vars);
    }

    public function createConfig($post, $host)
    {
        $post['db_schema'] = trim($post['db_schema']);
        $post['db_port'] = $post['db_port'] ?? 5432;
        $post['lang'] = $post['lang'] ?? 'en';
        $db = [
            'dsn' => 'pgsql:host=' . $post['db_host'] . ';port=' . $post['db_port'] . ';dbname=' . $post['db_name'],
            'host' => $post['db_host'],
            'username' => $post['db_user_login'],
            'dbname' => $post['db_name'],
            'password' => $post['db_user_password'],
            'charset' => 'UTF8',
            'pg_dump' => $post['pg_dump'],
            'psql' => $post['psql']
        ];
        $dbExport = var_export($db, true);

        if ($post['multy'] === '1') {
            $multyPhp = <<<CONF

/***** multi start ***/
    use MultiTrait;
/***** multi stop ***/

/***** no-multi start ***
    protected \$hostName='$host';
    protected \$schemaName='{$post['db_schema']}';
/***** no-multi stop ***/   
CONF;
        } else {
            $multyPhp = <<<CONF

/***** multi start ***
    use MultiTrait;
/***** multi stop ***/

/***** no-multi start ***/
    protected \$hostName='$host';
    protected \$schemaName='{$post['db_schema']}';
/***** no-multi stop ***/   
CONF;
        }


        $mail = 'use WithPhpMailerTrait;';
        $useMail = 'use totum\common\configs\WithPhpMailerTrait;';
        if (($post['mail'] ?? false) === 'smtp') {
            $mail = <<<PHP

        use WithPhpMailerSmtpTrait;
        
        protected \$SmtpData = [
                'host' => 'ttm-smtp',
                'port' => 25,
                'login' => '',
                'pass' => '',
            ];
PHP;
            $useMail = 'use totum\common\configs\WithPhpMailerSmtpTrait;';
        }


        $this->confClassCode = <<<CONF

namespace totum\config;

$useMail
use totum\common\configs\ConfParent;
use totum\common\configs\MultiTrait;

class Conf extends ConfParent{
    $multyPhp
    
    
    $mail
    
    
    
    
    
    const db=$dbExport;
    
    public static \$timeLimit = 120;
    
    const adminEmail='{$post['admin_email']}';
    
    const ANONYM_ALIAS='An';
    
    const LANG="{$post['lang']}";
    
    protected \$execSSHOn = 'inner'; //set true if you want to run ssh scripts via execSsh
    
    /***getSchemas***/
    static function getSchemas()
    {
        return ['$host'=>'{$post['db_schema']}'];
    }
    /***getSchemasEnd***/
    
    public function setSessionCookieParams()
    {
        session_set_cookie_params([
            'path' => '/',
            /*'secure' => true,*/ //-- uncomment this if your totum always on ssl
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }
}
CONF;

        eval($this->confClassCode);
        $Conf = new Conf();
        if ($post['multy'] === '1') {
            $Conf->setHostSchema($host);
        }
        return $Conf;
    }

    public function install($getFilePath)
    {
        $this->createSchema($this->installSettings, $getFilePath);
        $this->consoleLog('Save config file');
        $this->saveFileConfig();
    }

    public function getDataFromFile($path)
    {
        if (!is_file($path)) {
            throw new errorException($this->translate('Scheme file not found.'));
        }

        if (!($filedata = file_get_contents($path))) {
            throw new errorException($this->translate('Scheme file is empty'));
        }

        if (!($filedata = gzdecode($filedata))) {
            throw new errorException($this->translate('Wrong format scheme file.'));
        }
        if (!($schema = json_decode($filedata, true))) {
            throw new errorException($this->translate('Wrong format scheme file.'));
        }
        return $schema;
    }

    public function getTranslatesFromFile($path)
    {
        if (!is_file($path)) {
            throw new errorException($this->translate('Translates file not found.'));
        }

        if (!($filedata = file_get_contents($path))) {
            throw new errorException($this->translate('Translates file is empty'));
        }

        if (!($schema = json_decode($filedata, true))) {
            throw new errorException($this->translate('Wrong format file.'));
        }
        return $schema;
    }

    public function schemaTranslate($data, $pathToLang, $pathToBackLang = null)
    {
        $translates = [];
        if ($pathToLang) {
            $translates = $this->getTranslatesFromFile($pathToLang);
            if ($pathToBackLang) {
                $translates = $translates + $this->getTranslatesFromFile($pathToBackLang);
            }
        }

        if ($translates) {
            $translate = function ($v) use (&$translate, $translates) {
                if (is_array($v)) {
                    $vt = [];
                    foreach ($v as $k => $_v) {
                        $vt[$translate($k)] = $translate($_v);
                    }
                    return $vt;
                } elseif (is_string($v)) {
                    return preg_replace_callback("~\{\{[/a-zA-Z0-9,?'!_\-]+\}\}~",
                        function ($template) use ($translates) {
                            return $translates[$template[0]] ?? $template[0];
                        },
                        $v);
                } else return $v;
            };
            $data = $translate($data);
        }
        return $data;
    }

    public function systemTableFieldsApply($fields, $tableId)
    {
        $selectFields = $this->Totum->getNamedModel(TablesFields::class)->getAll(
            ['table_id' => $tableId],
            'id, name'
        );
        $selectFields = array_combine(array_column($selectFields, 'name'), $selectFields);
        $fieldsAdd = [];
        $fieldsModify = [];
        foreach ($fields as $field) {
            if (empty($field['name'])) {
                throw new errorException($this->translate('Parametr [[%s]] is required.', 'name of field'));
            }
            if (key_exists($field['name'], $selectFields)) {
                $fieldsModify[$selectFields[$field['name']]['id']] = array_intersect_key(
                    $field,
                    array_flip(['data_src', 'title', 'ord', 'category'])
                );
            } else {
                $field['table_id'] = $tableId;
                $fieldsAdd[] = $field;
            }
        }

        $this->Totum->getTable('tables_fields')->reCalculateFromOvers(['add' => $fieldsAdd, 'modify' => $fieldsModify]);
        $this->Totum->clearTables();
    }

    /**
     * @return Totum
     */
    public function getTotum(): Totum
    {
        return $this->Totum;
    }

    public function updateSysTablesAfterInstall($funcTree, $funcCats)
    {
        $rows = $this->Totum->getModel('tables')->getAllIndexedById(
            ['id' => [1, 2, 3, 4, 5, 6]],
            'id, category, tree_node_id'
        );
        foreach ($rows as &$row) {
            $row['tree_node_id'] = $funcTree($row['tree_node_id']);
            $row['category'] = $funcCats($row['category']);
            unset($row['top']);
        }
        $this->Totum->getTable('tables')->reCalculateFromOvers(['modify' => $rows]);
    }

    public function reCalculateTree()
    {
        $ids = $this->Totum->getModel('tree')->getColumn('id');
        $this->Totum->getTable('tree')->reCalculateFromOvers(['modify' => array_combine(
            $ids,
            array_fill(0, count($ids), [])
        )]);
    }

    /**
     * @param array $post
     * @throws errorException
     */
    public function insertUsersAndAuthAdmin(array $post)
    {
        $this->Totum->getTable('users')->reCalculateFromOvers(['add' => [
            ['login' => $post['user_login'], 'pass' => $post['user_pass'], 'fio' => $this->translate('Administrator'), 'roles' => ['1']],
            ['login' => 'cron', 'pass' => '', 'fio' => 'Cron', 'roles' => []],
            ['login' => 'service', 'pass' => '', 'fio' => 'service', 'roles' => ['1']],
            ['login' => 'anonym', 'pass' => '---', 'fio' => 'anonym', 'roles' => [], 'on_off' => false],
        ]]);
        $AdminId = $this->Totum->getTable('users')->getByParams(
            ['where' => [
                ['field' => 'login', 'operator' => '=', 'value' => $post['user_login']]
            ], 'field' => 'id'],
            'field'
        );
        if (!$this->outputConsole) {
            Auth::webInterfaceSessionStart($this->Config);
            Auth::webInterfaceSetAuth($AdminId);
        }
    }

    public function createSchema($post, $getFilePath)
    {
        $Sql = $this->Sql;
        $Sql->transactionStart();

        $this->consoleLog('Check/create schema');
        if ($this->Config->getSchema() === 'public') {
            throw new errorException($this->translate('You can\'t install totum in schema "public"'));
        }

        $this->checkSchemaExists($post['schema_exists']);

        $this->consoleLog('Upload start sql');
        $this->applySql($getFilePath('start.sql'));

        $data = $this->getDataFromFile($getFilePath('start.json.gz.ttm'));
        $lang = strtolower($this->Totum->getConfig()->getLang());

        $data = $this->schemaTranslate($data,
            $getFilePath($lang . '.json'),
            $lang !== 'en' ? $getFilePath('en.json') : null);

        $this->consoleLog('Install base tables');
        $baseTablesIds = $this->installBaseTables($data);


        foreach ($data['roles'] as $k => &$role) {
            if ($role['id'] === 1) {
                $role['favorite'] = [];
                $this->consoleLog('Add role Creator');

                $this->getTotum()->getTable('roles')->reCalculateFromOvers(['add' => [$role]]);
                unset($data['roles'][$k]);
                break;
            }
        }
        $data = $this->applyMatches($data, []);

        unset($data['tables_settings']['settings']);
        unset($data['fields_settings']);

        $this->consoleLog('Install other tables from schema file');

        list($schemaRows, $funcRoles, $funcTree, $funcCats) = $this->updateSchema(
            $data,
            false
        );


        $this->consoleLog('Create views');
        $this->applySql($getFilePath('start_views.sql'));

        $this->consoleLog('Update base tables tree and category');

        $this->updateSysTablesAfterInstall($funcTree, $funcCats);

        $this->consoleLog('Create default users');
        $this->insertUsersAndAuthAdmin($post);

        $this->consoleLog('Load data to tables and exec codes from schema');
        $this->updateDataExecCodes(
            $schemaRows,
            $funcCats('all'),
            $funcRoles('all'),
            $funcTree('all'),
            'totum_' . $this->Totum->getConfig()->getLang(),
            true
        );


        /*
         * ord для дерева!
         *
       */

        $this->consoleLog('Commit database transaction');
        $Sql->transactionCommit();
    }

    /**
     * TODO TEST IT
     *
     * @param $code
     * @param $funcRoles
     * @return string|string[]|null
     */
    protected function funcReplaceRolesInCode($code, $funcRoles)
    {
        return preg_replace_callback(
            '/((?i:userInRoles))\(([^)]+)\)/',
            function ($matches) use ($funcRoles) {
                return $matches[1] . '(' . preg_replace_callback(
                        '/role:\s*(\d+)/',
                        function ($matches) use ($funcRoles) {
                            $roles = '';
                            foreach ($matches[1] as $role) {
                                if ($roles !== '') {
                                    $roles .= '; ';
                                }
                                $roles .= 'role: ' . $funcRoles($role);
                            }
                            return $roles;
                        },
                        $matches[2]
                    ) . ')';
            },
            $code
        );
    }

    /**
     * @param $vRoles
     * @param $funcRoles
     * @return array
     */
    public function funcGetReplacesRolesList($vRoles, $funcRoles)
    {
        $newVal = [];
        foreach ($vRoles as $i => $role) {
            $newVal[] = $funcRoles($role);
        }
        return array_unique($newVal);
    }

    protected function calcTableSettings(&$schemaRow, &$tablesChanges, $funcRoles, $getTreeId, $funcCategories)
    {


        $schemaRow['name'] = $schemaRow['name'] ?? $schemaRow['table'];
        if (empty($schemaRow['name'])) {
            throw new errorException($this->translate('Parametr [[%s]] is required.', 'name of table'));
        }

        if (empty($schemaRow['type'])) {
            throw new errorException($this->translate('Parametr [[%s]] is required.', 'type of table'));
        }
        if ($schemaRow['type'] === 'calcs' && empty($schemaRow['version'])) {
            throw new errorException($this->translate('Parametr [[%s]] is required.', 'version of calcs table'));
        }

        unset($schemaRow['settings']['top']);

        $schemaRow['tableReNamed'] = false;

        if (empty($schemaRow['settings'])) {
            return;
        }

        foreach ($schemaRow['settings'] as $setting => &$val) {
            if (in_array($setting, Totum::TABLE_ROLES_PARAMS)) {
                $val = $this->funcGetReplacesRolesList($val, $funcRoles);
            } elseif (in_array($setting, Totum::TABLE_CODE_PARAMS)) {
                $val = $this->funcReplaceRolesInCode($val, $funcRoles);
            }
        }
        unset($val);

        try {
            $selectTableRow = $this->Totum->getTableRow($schemaRow['name']);
        } catch (errorException) {
            $selectTableRow = null;
        }

        try {
            if ($selectTableRow) /* Изменение */ {
                $this->consoleLog('Update settings table "' . $schemaRow['name'] . '"', 3);

                $Log = $this->calcLog(['name' => "UPDATE SETTINGS TABLE {$schemaRow['name']}"]);

                $tableId = $selectTableRow['id'];

                if ($selectTableRow['type'] !== $schemaRow['type']) {
                    throw new errorException($this->translate('The type of the loaded table [[%s]] does not match.',
                        $selectTableRow['name']));
                }
                unset($schemaRow['settings']['tree_node_id']);
                unset($schemaRow['settings']['sort']);
                unset($schemaRow['settings']['category']);
                unset($schemaRow['license']);
                /*Чтобы роли не сбрасывались*/
                foreach (Totum::TABLE_ROLES_PARAMS as $roleParam) {
                    $schemaRow['settings'][$roleParam] = array_values(array_unique(array_merge(
                        $selectTableRow[$roleParam] ?? [],
                        $schemaRow['settings'][$roleParam] ?? []
                    )));
                }

                $tablesChanges['modify'][$tableId] = $schemaRow['settings'];
                $schemaRow['isTableCreated'] = false;
            } else /* Добавление */ {
                $schemaRow['isTableCreated'] = true;
                $this->consoleLog('Add table "' . $schemaRow['name'] . '"', 3);
                $Log = $this->calcLog(['name' => "ADD TABLE {$schemaRow['name']}"]);

                if ($schemaRow['type'] === 'calcs') {
                    if (empty($schemaRow['cycles_table'])) {
                        throw new errorException($this->translate('The cycles table for the adding calculation table [[%s]] is not set.',
                            $schemaRow['name']));
                    }
                    $treeNodeId = $this->Totum->getNamedModel(Table::class)->getField(
                        'id',
                        ['name' => $schemaRow['cycles_table'], 'type' => 'cycles']
                    );

                    if (empty($treeNodeId)) {
                        throw new errorException($this->translate('Table [[%s]] is not found.',
                            $schemaRow['cycles_table']));
                    }
                } else {
                    $treeNodeId = $getTreeId($schemaRow['settings']['tree_node_id']);
                }
                $tablesChanges['add'][] = array_merge(
                    $schemaRow['settings'],
                    ['tree_node_id' => $treeNodeId, 'category' => $funcCategories(
                        $schemaRow['settings']['category']
                    ), 'name' => $schemaRow['name'], 'type' => $schemaRow['type']]
                );
                $tableId = null;
            }
            $this->calcLog($Log, 'result', 'done');
        } catch (\Exception $exception) {
            $this->calcLog($Log, 'error', $exception->getMessage());
            throw $exception;
        }
        $schemaRow['tableId'] = $tableId;
    }

    public function applyMatches($schemaData, $matches)
    {
        if ($schemaData['match_existings'] ?? false) {
            foreach (['tree' => 'tree', 'categories' => 'table_categories', 'roles' => 'roles'] as $type => $table) {
                $existings[$type] = $this->Totum->getModel($table)->getAllIds();
                $existings[$type] = array_combine($existings[$type], $existings[$type]);
            }
        }

        foreach (['tree', 'categories', 'roles'] as $type) {
            foreach ($schemaData[$type] as &$row) {
                if (!key_exists('out_id', $row)) {
                    $row['out_id'] = $existings[$type][$row['id']] ?? $matches[$type][$row['id']] ?? '';
                }
            }
            unset($row);
        }
        return $schemaData;
    }

    /**
     * @param array $schemaData
     * @param bool $withDataAndCodes
     * @return mixed
     * @throws errorException
     */
    public function updateSchema(array $schemaData, $withDataAndCodes = false, $matchesName = '')
    {
        $funcCategories = $this->getFuncCategories($schemaData['categories']);

        $funcRoles = $this->getFuncRoles($schemaData['roles']);
        $getTreeId = $this->getFuncTree($schemaData['tree'], $funcRoles);

        if (!empty($schemaFieldSettings = $schemaData['fields_settings'] ?? [])) {
            $this->consoleLog('Set fields of table tables_fields', 2);
            $this->systemTableFieldsApply($schemaFieldSettings, 2);
        }
        if (!empty($schemaTableSettings = $schemaData['tables_settings']['settings'] ?? [])) {
            $this->consoleLog('Set fields of table tables', 2);
            $this->systemTableFieldsApply($schemaTableSettings, 1);
        }

        if (!empty($schemaData['tables_settings']['sys_data'])) {
            $this->consoleLog('Set settings in table tables for "tables" and "tables_fields"');
            $this->updateSysTablesRows($schemaData['tables_settings']['sys_data'], 2);
        }
        $schemaRows = $schemaData['tables'];

        /*Настройки таблиц*/
        $calcTableFields = function (&$fieldsAdd, &$fieldsModify, $schemaRow) use ($funcRoles) {
            if ($schemaRow['fields']) {
                $tableId = $schemaRow['tableId'];
                $selectFields = $this->Totum->getNamedModel(TablesFields::class)->executePrepared(
                    true,
                    ['table_id' => $tableId, 'version' => $schemaRow['version']],
                    'id, name'
                )->fetchAll();
                $selectFields = array_combine(array_column($selectFields, 'name'), $selectFields);

                /*TODO Проверить и удалить если не используется*/
                if ($schemaRow['type'] === 'calcs') {
                    if ($vers = $this->Totum->getModel('calcstable_versions')->executePrepared(
                        true,
                        ['table_name' => $schemaRow['name'], 'version' => $schemaRow['version']],
                        '*',
                        null,
                        '0,1'
                    )->fetch()) {
                        if (!empty($schemaRow['is_default']) && empty($vers['is_default'])) {
                            $versions['modify'][$vers['id']]['is_default'] = true;
                        }
                    } else {
                        $versions['add'][] = ['table_name' => $schemaRow['name'], 'version' => $schemaRow['version'], 'is_default' => $schemaRow['is_default']];
                    }
                }

                foreach ($schemaRow['fields'] as $field) {
                    if (empty($field['name'])) {
                        throw new errorException($this->translate('Parametr [[%s]] is required.', 'name of field'));
                    }

                    if (key_exists($field['name'], $selectFields)) {
                        $fieldsModify[$selectFields[$field['name']]['id']] = array_intersect_key(
                            $field,
                            array_flip(['data_src', 'title', 'ord', 'category'])
                        );
                    } else {
                        $field['table_id'] = $tableId;
                        $field['version'] = $schemaRow['version'];
                        $fieldsAdd[] = $field;
                    }
                }
            }
        };

        $this->caclsFilteredTables(
            function ($schemaRow) {
                return $schemaRow['type'] !== 'calcs';
            },
            $calcTableFields,
            $schemaRows,
            $funcRoles,
            $getTreeId,
            $funcCategories
        );

        $this->caclsFilteredTables(
            function ($schemaRow) {
                return $schemaRow['type'] === 'calcs';
            },
            $calcTableFields,
            $schemaRows,
            $funcRoles,
            $getTreeId,
            $funcCategories
        );

        $this->Totum->getModel('roles')->saveVars(
            1,
            ["tables=jsonb_set(tables, '{v}', to_jsonb(array(select id::text from tables order by name->>'v')))"]
        );

        $this->consoleLog('update roles favorites', 2);
        $this->updateRolesFavorites($schemaData['roles'], $funcRoles);

        $this->consoleLog('Add tree links and anchors', 2);
        $getTreeId('link and anchors');


        if ($withDataAndCodes) {
            $this->consoleLog('Load data to tables and exec codes from schema', 2);
            $this->updateDataExecCodes(
                $schemaRows,
                $funcCategories('all'),
                $funcRoles('all'),
                $getTreeId('all'),
                $matchesName
            );
        }
        $this->consoleLog('Set default tables and sort for new tree branches', 2);
        $getTreeId('set default tables and sort');

        return [$schemaRows, $funcRoles, $getTreeId, $funcCategories];
    }

    protected function updateRolesFavorites($roles, \Closure $funcRoles)
    {
        $rolesUpdate = [];
        foreach ($roles as $row) {
            if ($row['favorite']) {
                if ($newId = $funcRoles($row['id'])) {
                    $tableIds = $this->Totum->getModel('tables')->executePrepared(
                        true,
                        ['name' => $row['favorite']],
                        'id'
                    )->fetchAll(PDO::FETCH_COLUMN);
                    $rolesUpdate[$newId] = $tableIds;
                }
            }
        }
        if ($rolesUpdate) {
            $TableRolesModel = $this->Totum->getModel('roles');
            $rolesOldFavs = $TableRolesModel->getFieldIndexedById('favorite', ['id' => array_keys($rolesUpdate)]);
            $modify = [];
            foreach ($rolesUpdate as $id => $tl) {
                $modify[$id] = ['favorite' => array_unique(array_merge(json_decode($rolesOldFavs[$id], true), $tl))];
            }
            $this->Totum->getTable('roles')->reCalculateFromOvers(['modify' => $modify]);
        }
    }

    protected function calcLog($Log, $key = null, $result = null)
    {
        if (is_array($Log)) {
            $this->CalculateLog = $this->CalculateLog->getChildInstance($Log);
        } elseif ($key) {
            $Log->addParam($key, $result);
            $this->CalculateLog = $Log->getParent();
        } elseif (is_object($Log)) {
            $this->CalculateLog = $Log;
        }
        return $this->CalculateLog;
    }

    protected function caclsFilteredTables($fulterFunc, $calcTableFields, &$schemaRows, $funcRoles, $getTreeId, $funcCategories)
    {
        $tablesChanges = [];

        foreach ($schemaRows as &$schemaRow) {
            if ($fulterFunc($schemaRow)) {
                $this->calcTableSettings(
                    $schemaRow,
                    $tablesChanges,
                    $funcRoles,
                    $getTreeId,
                    $funcCategories
                );
            }
        }
        unset($schemaRow);

        if (!empty($tablesChanges)) {
            $this->Totum->getTable('tables')->reCalculateFromOvers($tablesChanges);
            $this->Totum->clearTables();
        }

        $TableModel = $this->Totum->getNamedModel(Table::class);
        foreach ($schemaRows as &$schemaRow) {
            if ($fulterFunc($schemaRow) && empty($schemaRow['tableId'])) {
                $schemaRow['tableId'] = $TableModel->executePrepared(
                    true,
                    ['name' => $schemaRow['name']],
                    'id',
                    null,
                    '0,1'
                )->fetchColumn(0);
            }
        }
        unset($schemaRow);


        /*Поля таблиц*/
        $fieldsAdd = [];
        $fieldsModify = [];
        $n = 0;
        foreach ($schemaRows as $schemaRow) {
            if ($fulterFunc($schemaRow)) {
                $calcTableFields($fieldsAdd, $fieldsModify, $schemaRow);
                $n++;
            }
        }
        $this->consoleLog('Add and modify fields for ' . $n . ' tables ', 2);
        $this->Totum->getTable('tables_fields')->reCalculateFromOvers(['add' => $fieldsAdd, 'modify' => $fieldsModify]);
        $this->Totum->clearTables();
        $this->Totum->getConfig()->clearRowsCache();
    }

    public function checkSchemaExists($schema_exists_conf)
    {
        if (!preg_match('/^[a-z_0-9\-]+$/', $this->Config->getSchema())) {
            throw new errorException($this->translate('The format of the schema name is incorrect. Small English letters, numbers and - _'));
        }

        $prepare = $this->Sql->getPrepared('SELECT 1 FROM information_schema.schemata WHERE schema_name = ?');
        $prepare->execute([$this->Config->getSchema()]);
        $schemaExists = $prepare->fetchColumn();

        if (!$schemaExists) {
            $this->Sql->exec('CREATE SCHEMA IF NOT EXISTS "' . $this->Config->getSchema() . '"');
        } elseif ($schema_exists_conf !== true) {
            throw new errorException($this->translate('A scheme exists - choose another one to install.'));
        }
    }

    public function applySql(string $filePath)
    {
        $this->Sql->exec(file_get_contents($filePath), null, true);
    }

    public function installBaseTables($data)
    {
        $insertSysField = function ($table_id, $table_name, $setting) use (&$prepared1) {
            ksort($setting);
            $setting['table_id'] = strval($table_id);
            $setting['table_name'] = $table_name;
            $setting['data'] = fieldParamsResult::getDataFromDataSrc($setting['data_src'], $table_name);
            if (!$prepared1) {
                $fields = implode(',', array_keys($setting));
                $questions = implode(',', array_fill(0, count($setting), '?'));
                $prepared1 = $this->Sql->getPrepared('insert into tables_fields (' . $fields . ') values(' . $questions . ')');
            }
            foreach ($setting as $k => $val) {
                $setting[$k] = json_encode(['v' => $val], JSON_UNESCAPED_UNICODE);
            }
            $prepared1->execute(array_values($setting));
        };

        /*Заполняем поля таблицы Состав таблиц */
        foreach ($data['fields_settings'] as $setting) {
            $insertSysField(2, 'tables_fields', $setting);
        }
        $data['fields_settings'] = [];

        /*Заполняем поля таблицы Список таблиц */
        foreach ($data['tables_settings']['settings'] as $setting) {
            if (!in_array($setting['name'], ['type', 'name']) && $setting['category'] === 'column') {
                $this->Sql->exec('ALTER TABLE "tables" ADD COLUMN "' . $setting['name'] . '" JSONB NOT NULL DEFAULT \'{"v":null}\' ');
            }
            $insertSysField(1, 'tables', $setting);
        }


        $fieldsIds = $this->Totum->getModel('tables_fields')->executePrepared(
            true,
            [],
            'id'
        )->fetchAll(PDO::FETCH_COLUMN, 0);

        $this->Totum->getTable('tables_fields')->reCalculateFromOvers(['modify' => array_combine(
            array_flip($fieldsIds),
            array_fill(0, count($fieldsIds), [])
        )]);

        $do2 = 0;
        $table = $this->Totum->getTable('tables');
        $tablesIds = ['roles' => 3, 'tree' => 4, 'table_categories' => 5, 'settings' => 6];
        foreach ($data['tables'] as $_t) {
            if ($tablesIds[$_t['table']] ?? false) {
                unset($_t['settings']['top']);
                $table->reCalculateFromOvers(['modify' => [$tablesIds[$_t['table']] => $_t['settings']]]);
                foreach ($_t['fields'] as $setting) {
                    if ($setting['category'] === 'column') {
                        if (!$this->Sql->exec('SELECT column_name FROM information_schema.columns WHERE table_schema=\'' . $this->Config->getSchema() . '\' and table_name=\'' . $_t['table'] . '\' and column_name=\'' . $setting['name'] . '\'')) {
                            $this->Sql->exec('ALTER TABLE "' . $_t['table'] . '" ADD COLUMN "' . $setting['name'] . '" JSONB NOT NULL DEFAULT \'{"v":null}\' ');
                        }
                    }
                    $insertSysField($tablesIds[$_t['table']], $_t['table'], $setting);
                }
                if (++$do2 >= count($tablesIds)) {
                    break;
                }
            }
        }
        $this->refresh();
        return $tablesIds;
    }

    protected function refresh()
    {
        $this->Totum = new Totum($this->Config, $this->User);
        $this->Totum->setCalcsTypesLog(['all']);
    }

    /**
     * @param $sysData
     */
    protected function updateSysTablesRows($sysData)
    {
        $TableModel = $this->Totum->getNamedModel(Table::class);
        foreach ($sysData['rows'] as $row) {
            $selectedRow = $this->Totum->getTableRow($row['name']['v'], true);
            unset($row['name']);
            foreach (['tree_node_id', 'sort', 'category'] as $param) {
                if (key_exists($param, $selectedRow) && $selectedRow[$param]) {
                    unset($row[$param]);
                }
            }
            foreach ($row as &$item) {
                $item = json_encode($item, JSON_UNESCAPED_UNICODE);
            }
            $TableModel->updatePrepared(true, $row, ['id' => $selectedRow['id']]);
        }
    }

    /**
     * TODO it
     *
     *
     * @param $schemaRows
     * @param array $categoriesMatches
     * @param array $rolesMatches
     * @param array $treeMatches
     */
    public function updateDataExecCodes($schemaRows, array $categoriesMatches, array $rolesMatches, array $treeMatches, $matchName, $isInstall = false)
    {
        $TablesTable = $this->Totum->getTable('tables');
        $TablesTable->addCalculateLogInstance($this->CalculateLog);

        $TablesModel = $this->Totum->getNamedModel(Table::class);

        /*Данные и Коды*/
        foreach ($schemaRows as $schemaRow) {
            $insertedIds = [];
            $changedIds = [];


            if ($schemaRow['data']) {
                $Log = $TablesTable->calcLog(['name' => "ADD DATA TO TABLE {$schemaRow['name']}"]);
                $tableId = $schemaRow['tableId'];
                switch ($schemaRow['type']) {
                    case 'globcalcs':
                        $this->Totum->getNamedModel(NonProjectCalcs::class)->update(
                            ['tbl' => json_encode(
                                $schemaRow['data'],
                                JSON_UNESCAPED_UNICODE
                            ), 'updated' => $updated = aTable::formUpdatedJson($this->Totum->getUser())],
                            ['tbl_name' => $schemaRow['name']]
                        );
                        break;
                    case 'simple':
                    case 'cycles':
                        if (!empty($schemaRow['data']['params'])) {
                            $header = json_decode(
                                $TablesModel->getField('header', ['id' => $tableId]),
                                true
                            );
                            foreach ($schemaRow['data']['params'] as $param => $val) {
                                $header[$param] = $val;
                            }
                            $TablesModel->updatePrepared(
                                true,
                                ['header' => json_encode($header, JSON_UNESCAPED_UNICODE)],
                                ['id' => $tableId]
                            );
                        }

                        if (!empty($schemaRow['data']['rows'])) {
                            $_tableModel = $this->Totum->getModel($schemaRow['name']);


                            $getRowId = function ($row) use ($_tableModel, $schemaRow) {
                                if (!empty($schemaRow['key_fields']) || (key_exists(
                                            'id',
                                            $row
                                        ) && $schemaRow['key_fields'] = ['id'])) {
                                    $keys = [];
                                    foreach ($schemaRow['key_fields'] as $key) {
                                        $keys[$key] = (is_array($row[$key] ?? []) && key_exists(
                                                'v',
                                                $row[$key] ?? []
                                            )) ? $row[$key]['v'] : $row[$key];
                                        if (is_array($keys[$key])) {
                                            $keys[$key] = json_encode(
                                                $keys[$key],
                                                JSON_UNESCAPED_UNICODE
                                            );
                                        } elseif ($key !== 'id' && (is_int($keys[$key]) || is_float($keys[$key]))) {
                                            $keys[$key] = strval($keys[$key]);
                                        }
                                    }
                                    $selectedRowId = $_tableModel->getField('id', $keys);
                                }
                                return $selectedRowId ?? null;
                            };

                            $before = null;
                            $orderedIds = [];
                            foreach ($schemaRow['data']['rows'] as $row) {
                                $selectedRowId = $getRowId($row);

                                if ($cycleTables = $row['_tables'] ?? []) {
                                    unset($row['_tables']);
                                }
                                foreach ($row as $k => &$v) {
                                    if (is_array($v)) {
                                        $v = json_encode($v, JSON_UNESCAPED_UNICODE);
                                    }
                                }
                                unset($v);

                                $rowId = null;
                                /*Изменение*/
                                if ($selectedRowId) {
                                    if ($schemaRow['change'] !== 'add') {
                                        if ($_tableModel->saveVars($selectedRowId, $row)) {
                                            $changedIds[] = $selectedRowId;
                                        }
                                        $rowId = $selectedRowId;
                                    }
                                    $before = $selectedRowId;
                                    $orderedIds[] = $selectedRowId;
                                } /*Добавление*/
                                elseif ($schemaRow['change'] !== 'edit') {
                                    $rowId = $_tableModel->insertPrepared($row);
                                    if (key_exists('id', $row)) {
                                        $rowId = $row['id'];
                                        $lastQ = $this->Totum->getModel(
                                            $schemaRow['name'] . '_id_seq',
                                            true
                                        )->getField('last_value', []);
                                        if ($lastQ <= $rowId) {
                                            $this->Totum->getConfig()->getSql()->exec(
                                                "select setval('{$schemaRow['name']}_id_seq', $rowId, true)",
                                                [],
                                                true
                                            );
                                        }
                                    }

                                    if (($this->Totum->getTableRow($tableId)['with_order_field'] ?? false) && ($schemaRow['n_type'] ?? false) === '1') {
                                        if (!$before) {
                                            foreach ($schemaRow['data']['rows'] as $_i => $_row) {
                                                if ($_i && $after = $getRowId($_row)) {
                                                    $afterN = $_tableModel->getField('n', ['id' => $after]);
                                                    $before = $_tableModel->getField('id', ['<n' => $afterN], 'n desc');
                                                    break;
                                                }
                                            }
                                        }

                                        if (!$before) $before = '0';

                                        $this->Totum->getTable($tableId)->reCalculateFromOvers([
                                            'reorder' => [$rowId],
                                            'addAfter' => $before
                                        ]);
                                    }
                                    $orderedIds[] = $insertedIds[] = $rowId;
                                    $before = $rowId;
                                }

                                if ($rowId && $schemaRow['type'] === 'cycles' && !empty($cycleTables)) {
                                    $cyclesTablesNames = $TablesModel->getColumn(
                                        'name',
                                        ['tree_node_id' => $tableId, 'type' => 'calcs']
                                    );


                                    array_map(
                                        function ($tName, $table) use ($cyclesTablesNames, $tableId, $rowId) {
                                            if (in_array($tName, $cyclesTablesNames)) {
                                                $tbl = json_encode($table['tbl'], JSON_UNESCAPED_UNICODE);
                                                $model = $this->Totum->getModel($tName);
                                                if (!$model->update(
                                                    ['tbl' => $tbl, 'updated' => aTable::formUpdatedJson($this->Totum->getUser())],
                                                    ['cycle_id' => $rowId]
                                                )) {
                                                    $model->insertPrepared(['tbl' => $tbl, 'updated' => aTable::formUpdatedJson($this->Totum->getUser()), 'cycle_id' => $rowId]);
                                                    $this->Totum->getNamedModel(CalcsTableCycleVersion::class)->insertPrepared(['table_name' => json_encode(
                                                        ['v' => $tName],
                                                        JSON_UNESCAPED_UNICODE
                                                    ), 'cycles_table' => json_encode(
                                                        ['v' => $tableId],
                                                        JSON_UNESCAPED_UNICODE
                                                    ), 'sort' => json_encode(
                                                        ['v' => 10],
                                                        JSON_UNESCAPED_UNICODE
                                                    ), 'cycle' => json_encode(
                                                        ['v' => $rowId],
                                                        JSON_UNESCAPED_UNICODE
                                                    ), 'version' => json_encode(
                                                        ['v' => $table['version']],
                                                        JSON_UNESCAPED_UNICODE
                                                    )]);
                                                } else {
                                                    $this->Totum->getNamedModel(CalcsTableCycleVersion::class)->update(
                                                        ['sort' => json_encode(
                                                            ['v' => 10],
                                                            JSON_UNESCAPED_UNICODE
                                                        ), 'version' => json_encode(
                                                            ['v' => $table['version']],
                                                            JSON_UNESCAPED_UNICODE
                                                        )],
                                                        ['cycle' => json_encode(
                                                            ['v' => $rowId],
                                                            JSON_UNESCAPED_UNICODE
                                                        ),
                                                            'table_name' => json_encode(
                                                                ['v' => $tName],
                                                                JSON_UNESCAPED_UNICODE
                                                            ), 'cycles_table' => json_encode(
                                                            ['v' => $tableId],
                                                            JSON_UNESCAPED_UNICODE
                                                        ),]
                                                    );
                                                }
                                            }
                                        },
                                        array_keys($cycleTables),
                                        $cycleTables
                                    );
                                }
                            }
                            if (($this->Totum->getTableRow($tableId)['with_order_field'] ?? false) && ($schemaRow['n_type'] ?? false) === '2') {
                                if ($orderedIds) {
                                    $this->Totum->getTable($tableId)->reCalculateFromOvers([
                                        'reorder' => $orderedIds,
                                    ]);
                                }
                            }
                        }
                        $TablesModel->saveVars(
                            $tableId,
                            ['updated' => $updated = aTable::formUpdatedJson($this->Totum->getUser())]
                        );
                }
                $TablesTable->calcLog($Log, 'result', 'done');
            }


            if ($schemaRow['code'] ?? null) {
                $this->consoleLog(
                    'exec code: ' . substr(
                        $schemaRow['code'],
                        0,
                        strpos($schemaRow['code'], "\n")
                    ) . '...',
                    3
                );
                $Log = $TablesTable->calcLog(['name' => 'CODE FROM SCHEMA', 'code' => $schemaRow['code']]);
                $action = new CalculateAction($schemaRow['code']);
                $r = $action->execAction(
                    'InstallCode',
                    [],
                    $schemaRow,
                    [],
                    [],
                    $TablesTable,
                    'exec',
                    ['insertedIds' => $insertedIds, 'changedIds' => $changedIds, 'categories' => $categoriesMatches, 'roles' => $rolesMatches, 'tree' => $treeMatches, 'type' => $isInstall ? 'install' : 'update', 'is_table_created' => $schemaRow['isTableCreated']]
                );
                $TablesTable->calcLog($Log, 'result', $r);
            }
        }


        $this->consoleLog('update @ttm__updates.h_matches/' . $matchName, 2);
        $ttmUpdates = $this->Totum->getTable('ttm__updates');
        if (!key_exists('h_matches', $ttmUpdates->getFields())) {
            $matchesField = ['category' => 'param', 'table_id' => $ttmUpdates->getTableRow()['id'], 'name' => 'h_matches', 'data_src' => ['type' => ['isOn' => true, 'Val' => 'listRow']]];
            $this->Totum->getTable('tables_fields')->reCalculateFromOvers(['add' => [$matchesField]]);
        }
        $matches = $ttmUpdates->getTbl()['params']['h_matches']['v'] ?? [];
        $matches[$matchName] = [
            'tree' => $treeMatches,
            'categories' => $categoriesMatches,
            'roles' => $rolesMatches
        ];
        $ttmUpdates->reCalculateFromOvers(['modify' => ['params' => ['h_matches' => $matches]]]);
    }

    protected function getFuncCategories(array $categoriesIn)
    {
        $categoriesMatches = [];
        foreach ($categoriesIn as $row) {
            if (!empty($row['out_id'])) {
                $categoriesMatches[$row['id']] = $row['out_id'];
            }
        }

        return function ($cat) use ($categoriesIn, &$categoriesMatches, &$Categories) {
            if (key_exists(
                $cat,
                $categoriesMatches
            )) {
                return $categoriesMatches[$cat];
            } elseif ($cat === 'all') {
                return $categoriesMatches;
            }
            foreach ($categoriesIn as $k => $row) {
                if ((string)$row['id'] === (string)$cat) {
                    $id = $row['id'];
                    $row['out_id'] = $row['out_id'] ?? null;

                    unset($row['id']);
                    $Categories = $Categories ?? $this->Totum->getTable('table_categories');

                    $Categories->reCalculateFromOvers(['add' => [$row]]);
                    $outId = array_key_last($Categories->getChangeIds()['added']);
                    $categoriesMatches[$id] = $outId;
                    return $categoriesMatches[$id];
                }
            }

            throw new errorException($this->translate('Category [[%s]] not found for replacement.', $cat));
        };
    }

    protected function getFuncRoles(array $rolesIn)
    {
        $rolesMatch = [1 => 1];
        foreach ($rolesIn as $row) {
            if (!empty($row['out_id'])) {
                $rolesMatch[$row['id']] = $row['out_id'];
            }
        }

        return function ($inId) use ($rolesIn, &$rolesMatch) {
            if (key_exists($inId, $rolesMatch)) {
                return $rolesMatch[$inId];
            } elseif ($inId === 'all') {
                return $rolesMatch;
            }

            foreach ($rolesIn as $k => $row) {
                if ((string)$row['id'] === (string)$inId) {
                    $id = $row['id'];
                    unset($row['id']);
                    unset($row['favorite']);

                    $Roles = $this->Totum->getTable('roles');
                    $Roles->reCalculateFromOvers(['add' => [$row]]);
                    $outId = array_key_last($Roles->getChangeIds()['added']);
                    $rolesMatch[$id] = $outId;
                    return $rolesMatch[$id];
                }
            }

            throw new errorException($this->translate('Role [[%s]] not found for replacement.', $inId));
        };
    }

    protected function getFuncTree(array $treeIn, \Closure $funcRoles)
    {
        $defaultTables = [];
        $addedBranches = [];
        $treeMatches = [];

        foreach ($treeIn as $row) {
            if (!empty($row['out_id'])) {
                $treeMatches[$row['id']] = $row['out_id'];
            }
        }

        return $getTreeId = function ($tree_node_id) use ($funcRoles, &$addedBranches, $treeIn, &$treeMatches, &$getTreeId, &$Tree, &$defaultTables) {
            if (key_exists($tree_node_id, $treeMatches)) {
                return $treeMatches[$tree_node_id];
            } elseif ($tree_node_id === 'all') {
                return $treeMatches;
            } elseif ($tree_node_id === 'link and anchors') {
                foreach ($treeIn as $row) {
                    if ($row['type']) {
                        $getTreeId($row['id']);
                    }
                }
                return;
            } elseif ($tree_node_id === 'set default tables and sort') {
                if ($defaultTables || $addedBranches) {
                    $modify = [];
                    if ($defaultTables) {
                        $defTables = $this->Totum->getModel('tables')->getFieldIndexedByField(
                            ['name' => array_values($defaultTables)],
                            'name',
                            'id'
                        );
                        foreach ($defaultTables as $treeId => &$name) {
                            $modify[$treeId] = ['default_table' => $defTables[$name] ?? null];
                        }
                        unset($name);
                    }
                    if ($addedBranches) {
                        $lastOrdParent = [];
                        foreach ($treeIn as $row) {
                            if (key_exists($row['id'], $addedBranches)) {
                                if (!key_exists($row['parent_id'], $lastOrdParent)) {
                                    $lastOrdParent[$row['parent_id']] = $this->Totum->getModel('tree')->getField(
                                            'ord',
                                            ['parent_id' => $row['parent_id'], '!id' => array_values($addedBranches)],
                                            'ord desc'
                                        ) ?? 0;
                                }
                                $lastOrdParent[$row['parent_id']] += 10;
                                $modify[$addedBranches[$row['id']]]['ord'] = $lastOrdParent[$row['parent_id']];
                            }
                        }
                    }
                    /** @var aTable $Tree */
                    $Tree->reCalculateFromOvers(['modify' => $modify]);
                }
                return;
            }
            foreach ($treeIn as $k => $row) {
                if ((string)$row['id'] === (string)$tree_node_id) {
                    $id = $row['id'];
                    unset($row['id']);
                    if ($row['parent_id']) {
                        $row['parent_id'] = $getTreeId($row['parent_id']);
                    }
                    if (!empty($row['default_table'])) {
                        $defaultTable = $row['default_table'];
                        $row['default_table'] = null;
                    }
                    if (!empty($row['roles'])) {
                        foreach ($row['roles'] as &$role) {
                            $role = $funcRoles($role);
                        }
                        unset($role);
                    }

                    $Tree = $this->Totum->getTable('tree');
                    $Tree->reCalculateFromOvers(['add' => [$row]]);
                    $outId = array_key_last($Tree->getChangeIds()['added']);
                    if (isset($defaultTable)) {
                        $defaultTables[$outId] = $defaultTable;
                    }
                    $addedBranches[$id] = $outId;
                    $treeMatches[$id] = $outId;
                    return $treeMatches[$id];
                }
            }
            throw new errorException($this->translate('Branch [[%s]] not found for replacement.', $tree_node_id));
        };
    }

    private function saveFileConfig()
    {
        $Log = $this->CalculateLog->getChildInstance(['name' => 'Save Config file']);
        if (!file_put_contents(
            dirname(__FILE__) . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'Conf.php',
            '<?php' . "\n" . $this->confClassCode
        )) {
            throw new errorException($this->translate('Error saving file %s', 'Conf.php'));
        } else {
            $Log->addParam('result', 'done');
        }
    }

    private function consoleLog(string $string, $level = 0)
    {
        $this->outputConsole?->write(str_repeat(' ', $level) . $string, true);
    }
}
