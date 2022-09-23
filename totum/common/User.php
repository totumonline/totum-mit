<?php


namespace totum\common;

use Exception;
use totum\common\Lang\RU;
use totum\config\Conf;
use totum\models\Table;

class User
{
    private $allData;
    /**
     * @var array | null
     */
    private $roles;
    /**
     * @var bool
     */
    private $isCreatorValue;
    /**
     * @var Conf
     */
    private $Config;
    /**
     * @var array
     */
    private $tables;

    /**
     * @var mixed
     */

    public function __get($name)
    {
        if ($name === 'allData') {
            return $this->allData;
        }
        if (key_exists($name, $this->allData)) {
            return $this->allData[$name];
        }

        throw new Exception($this->Config->getLangObj()->translate('A nonexistent [[%s]] property was requested.',
            $name));
    }

    public function __construct($allData, Conf $Config)
    {
        $this->allData = $allData;
        $this->roles = $allData['roles'];

        /*EXTRA ROLES*/
        if (($allData['user_manager'] ?? false) && !in_array(1, $this->roles)) {
            $this->roles[] = -1;
        }
        if (($allData['sudo'] ?? false) && !in_array(1, $this->roles)) {
            $this->roles[] = -2;
        }

        $this->Config = $Config;
    }

    public function isCreator()
    {
        return $this->isCreatorValue ?? $this->isCreatorValue = in_array('1', $this->roles);
    }

    public function getRoles()
    {
        return $this->roles;
    }

    /**
     * @return array
     */
    public function getTreeTables(): array
    {
        return $this->tables['tree'] ?? $this->loadRolesTables()['tree'];
    }

    public function getTables()
    {
        return $this->tables['all'] ?? $this->loadRolesTables()['all'];
    }

    public function getVar(string $string)
    {
        return $this->allData[$string];
    }

    private function loadRolesTables()
    {
        $roles = $this->Config->getModel('roles')->executePrepared(
            true,
            ['id' => $this->getRoles()],
            'tables, tables_read, tree_off, id, one_cycle_tables'
        )->fetchAll();
        $tables = [];
        $roleIds = [];
        $oneCycleTables = [];

        $NoTreeTables = [];

        foreach ($roles as $role) {
            $noTables = json_decode($role['tree_off'], true) ?? [];

            foreach (json_decode($role['tables']) as $table) {
                $tables[$table] = 1;
                if (in_array($table, $noTables)) {
                    $NoTreeTables[$table] = 1;
                }
            }
            foreach (json_decode($role['tables_read'], true) ?? [] as $table) {
                if (empty($tables[$table])) {
                    $tables[$table] = 0;
                }
                if (in_array($table, $noTables)) {
                    $NoTreeTables[$table] = 1;
                }
            }
            $oneCycleTables = array_merge(
                $oneCycleTables,
                (json_decode($role['one_cycle_tables'], true) ?? [])
            );
        }
        $treeTables = array_diff_key($tables, $NoTreeTables);
        $treeTables = array_combine(array_keys($treeTables), array_fill(0, count($treeTables), 1));

        /*EXTRA ROLES*/
        if (in_array(-1, $this->roles)) {

            $tablesManage = Auth::$userManageTables;
            if (in_array(-2, $this->roles)) {
                $tablesManage[] = 'ttm__user_log';
            }

            $user_manager_tables = Table::init($this->Config)->getColumn(
                'id',
                ['name' => $tablesManage]
            );
            foreach ($user_manager_tables as $id) {
                $tables[$id] = 1;
            }
        }
        return $this->tables = ['oneCycle' => array_unique($oneCycleTables ?? []), 'all' => $tables, 'tree' => $treeTables];
    }

    public function isTableInAccess($tableId)
    {
        return key_exists($tableId, $this->getTables());
    }

    public function getFavoriteTables()
    {
        return $this->allData['favorite'];
    }

    public function getConnectedUsers()
    {
        return $this->allData['all_connected_users'];
    }

    public function getProjectsWhere()
    {
        if ($this->allData['other_prs_access'] > 0) {
            return [];
        }
        return ['creator_id' => $this->getConnectedUsers()];
    }

    public function getInterface()
    {
        return $this->allData['interface'];
    }

    public function isOneCycleTable($table)
    {
        return ($table['type'] === 'cycles'
            && in_array(
                $table['id'],
                $this->tables['oneCycle'] ?? $this->loadRolesTables()['oneCycle']
            ));
    }

    public function getId()
    {
        return $this->allData['id'];
    }

    public function getUserStartPath(): ?string
    {
        if ($tables = $this->Config->getModel('roles')->getField('redirect_table_in_roles',
            ['id' => $this->getRoles(), '!redirect_table_in_roles' => ''],
            null,
            null)) {
            $row = $this->Config->getModel('tables')->get(['id' => $tables], 'id, top', 'sort');
            return '/Table/' . $row['top'] . '/' . $row['id'];
        }
        return null;
    }
}
