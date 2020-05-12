<?php

namespace totum\common;

use totum\config\Conf;

class Auth
{
    /**
     * @var Auth
     */
    static $aUser;
    private static $usersFields;
    protected $rowData, $tables, $treeTables = [], $roles, $roleIds, $branches, $topBranches, $connectedUsers, $isCreator = false, $oneCycleTables = [];

    protected function __construct($rowData)
    {
        $this->rowData = $rowData;


        $this->roles = Model::init('roles')->getAll(['id' => json_decode($this->rowData['roles'])],
            'tables, tables_read, tree_off, id, one_cycle_tables');
        $tables = [];

        $this->roleIds = [];

        foreach ($this->roles as $role) {

            $this->roleIds[] = $role['id'];

            if ($role['id'] == 1) {
                $this->isCreator = true;
            }
            $noTables = json_decode($role['tree_off'], true) ?? [];

            foreach (json_decode($role['tables']) as $table) {
                $tables[$table] = 1;
                if (!in_array($table, $noTables)) $this->treeTables[$table] = 1;
            }
            foreach (json_decode($role['tables_read'], true) ?? [] as $table) {
                if (empty($tables[$table]))
                    $tables[$table] = 0;
                if (!in_array($table, $noTables)) $this->treeTables[$table] = 1;
            }


            $this->oneCycleTables = array_merge($this->oneCycleTables,
                (json_decode($role['one_cycle_tables'], true) ?? []));
        }

        $this->oneCycleTables = array_unique($this->oneCycleTables ?? []);
        $this->tables = $tables;
    }

    static function checkUserPass($string, $hash)
    {
        return $hash === md5($string);
    }

    static function getUserLogin()
    {
        return static::isAuthorized() ? static::$aUser->rowData['login'] : null;
    }

    static function getUserVar($name)
    {
        return static::isAuthorized() ? static::$aUser->rowData[$name] : null;
    }

    static function getUserId(): int
    {
        return static::isAuthorized() ? static::$aUser->rowData['id'] : null;
    }

    static function isAuthorized()
    {
        return !empty(static::$aUser);
    }

    static function getUserFields()
    {
        return static::$usersFields ?? static::$usersFields = Sql::getFieldArray('select name->>\'v\' from tables_fields where category->>\'v\'=\'column\' AND table_name->>\'v\'=\'users\'');
    }

    static function loadAuthUserByLogin($userLogin, $UpdateActive)
    {

        $where = ['login' => $userLogin, 'is_del' => false, 'on_off' => "true"];
        if ($userRow = static::getUserWhere($where, $UpdateActive)) {
            static::$aUser = new static($userRow);
        }

        return static::$aUser;
    }

    static function loadAuthUser($userId, $UpdateActive)
    {

        $where = ['id' => $userId, 'is_del' => false, 'on_off' => "true"];
        if ($userRow = static::getUserWhere($where, $UpdateActive)) {
            static::$aUser = new static($userRow);
        }

        return static::$aUser;
    }

    static function webInterfaceSessionStart()
    {

        /*

        $sessDir = './sess/'.Conf::getDb()['schema'];
         if (!is_dir($sessDir)) mkdir($sessDir, 0777, true);
         session_save_path($sessDir);*/


        //Если в рамках одного сервера переключается на разные аккаунты домен, то залипает сессия - почисти куку.
        session_start();
        if (!empty($_SESSION['userId'])) {
            static::loadAuthUser($_SESSION['userId'], true);
        }
    }

    static protected function getUserWhere($where, $UpdateActive = true)
    {
        $r = Model::init('users')->get($where, 'id, ' . implode(', ', static::getUserFields()));
        if ($UpdateActive && in_array('activ_datetime', static::getUserFields())) {
            Model::init('users')->update(['activ_datetime' => json_encode(['v' => date('Y-m-d H:i:s')],
                JSON_UNESCAPED_UNICODE)],
                ['id' => $r['id']]);
        }
        return $r;
    }

    static function CronUserStart()
    {

        if ($userRow = static::getUserWhere(['login' => 'cron'])) {
            static::$aUser = new static($userRow);
        }
    }

    static function ServiceUserStart()
    {

        if ($userRow = static::getUserWhere(['login' => 'service'])) {
            static::$aUser = new static($userRow);
        } else {
            die('Пользователь service не настроен. Обратитесь к администратору системы');
        }
    }

    static function xmlInterfaceAuth($userId)
    {
        $where = ['id' => $userId, 'is_del' => false, 'on_off' => "true"];
        if ($userRow = static::getUserWhere($where)) {
            return static::$aUser = new static($userRow);
        }
    }

    static function simpleAuth($userId)
    {
        $where = ['id' => $userId, 'is_del' => false, 'on_off' => "true"];
        if ($userRow = static::getUserWhere($where)) {
            return static::$aUser = new static($userRow);
        }
    }

    static function webInterfaceSetAuth($userId)
    {
        $_SESSION['userId'] = $userId;

    }

    static function webInterfaceRemoveAuth()
    {
        static::$aUser = null;
        session_destroy();
    }

    static function isCreator()
    {
        return static::$aUser ? static::$aUser->isCreator : false;
    }

    public static function reUserFromCreator($id)
    {
        if (empty($_SESSION['CretorId'])) {
            $_SESSION['CretorId'] = Auth::getUserId();
        }
        $_SESSION['userId'] = $id;
    }

    public static function isCreatorOnShadow()
    {
        return !empty($_SESSION['CretorId']);
    }

    public static function isCreatorNotItself()
    {
        return !empty($_SESSION['CretorId']) && $_SESSION['CretorId'] != $_SESSION['userId'];
    }

    function isTableInAccess($tableId)
    {
        return array_key_exists($tableId, $this->tables);
    }

    function getFavoriteTables()
    {
        return json_decode($this->rowData['favorite'], true);
    }

    function getRoles()
    {
        return $this->roleIds;
    }

    function getConnectedUsers()
    {
        return json_decode($this->rowData['all_connected_users'], true);
    }

    function getProjectsWhere()
    {
        if ($this->rowData['other_prs_access'] > 0) return [];
        return ['creator_id' => $this->getConnectedUsers()];
    }

    function getTables()
    {

        return $this->tables;
    }


    public function getInterface()
    {
        return $this->rowData['interface'];
    }

    public function isOneCycleTable($table)
    {
        return ($table['type'] === 'cycles' && in_array($table['id'], $this->oneCycleTables));
    }

    public function getId()
    {
        return $this->rowData['id'];
    }

    public function isOuter()
    {
        return $this->rowData['is_outer'] === "true";
    }

    /**
     * @return array
     */
    public function getTreeTables(): array
    {
        return $this->treeTables;
    }

}