<?php


namespace totum\common\configs;

use Exception;
use totum\common\errorException;
use totum\common\Lang\RU;
use totum\common\Model;
use totum\models\CalcsTableCycleVersion;
use totum\models\CalcsTablesVersions;
use totum\models\NonProjectCalcs;
use totum\models\Table;
use totum\models\TablesCalcsConnects;
use totum\models\TablesFields;
use totum\models\TmpTables;
use totum\models\Tree;
use totum\models\TreeV;
use totum\models\UserV;

trait TablesModelsTrait
{
    protected static $modelsConnector = [
        'users' => \totum\models\User::class
        , 'users__v' => UserV::class
        , 'tree' => Tree::class
        , 'tree__v' => TreeV::class
        , 'tables_fields' => TablesFields::class
        , 'tables' => Table::class
        , 'calcstable_cycle_version' => CalcsTableCycleVersion::class
        , 'tables_nonproject_calcs' => NonProjectCalcs::class
        , 'tables_calcs_connects' => TablesCalcsConnects::class
        , 'calcstable_versions' => CalcsTablesVersions::class
        , '_tmp_tables' => TmpTables::class
    ];

    private $tableRowsById = [];
    private $tableRowsByName = [];

    /* Инициализированные модели */
    protected array $models = [];

    public function getModel($table, $idField = null, $isService = null): Model
    {

        $keyStr = $table . ($isService ? '!!!' : '');

        if (key_exists($keyStr, $this->models)) {
            return $this->models[$keyStr];
        }
        $className = $this->getModelClassName($table);

        /** @var Model $model */
        $model = new $className(
            $this->getSql(),
            $table,
            $this->getLangObj(),
            $idField,
            $isService
        );
        return $this->models[$keyStr] = $model;
    }

    public static function getTableNameByModel(string $className): string
    {
        $tableName = array_flip(static::$modelsConnector)[$className] ?? null;

        if (!$tableName) {
            if ($className === Model::class) {
                debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
                throw new Exception('Design error: Incorrect model call from php code.');
            }
            throw new Exception('Model ' . $className . '  is not connected to the connector.');
        }
        return $tableName;
    }

    /* Сюда можно будет поставить общую систему кешей */
    /**
     * @param int|string $table
     * @return array|null
     */
    public function getTableRow($table, $force = false): ?array
    {
        if(empty($table)){
            throw new errorException($this->translate('Fill in the parameter [[%s]].', 'name of table'));
        }
        elseif (is_int($table) || ctype_digit($table)) {
            if (!$force && key_exists($table, $this->tableRowsById)) {
                return $this->tableRowsById[$table];
            }

            $where = ['id' => $table];
        } else {
            if (!$force && key_exists($table, $this->tableRowsByName)) {
                return $this->tableRowsByName[$table];
            }
            $where = ['name' => $table];
        }
        $Model = $this->getModel('tables');
        $stmt=$Model->executePrepared(true, $where);

        if ($row = $stmt->fetch()) {
            foreach ($row as $k => &$v) {
                if (!in_array($k, Model::serviceFields)) {
                    if (is_array($v)) {
                        debug_print_backtrace();
                    }
                    if (!array_key_exists('v', json_decode($v, true))) {
                        var_dump($row, $k);
                        die;
                    }
                    $v = json_decode($v, true)['v'];
                }
            }
            unset($v);

            $this->tableRowsByName[$row['name']] = $row;
            $this->tableRowsById[$row['id']] = $row;
        }else{
            throw new errorException($this->translate('Table [[%s]] is not found.', $table));
        }
        return $row;
    }

    public function getNamedModel($className, $isService): Model
    {
        return $this->getModel(static::getTableNameByModel($className), null, $isService);
    }

    protected function getModelClassName($table)
    {
        if (empty(static::$modelsConnector[$table])) {
            $className = Model::class;
        } else {
            $className = static::$modelsConnector[$table];
        }
        return $className;
    }

    public function clearRowsCache()
    {
        $this->tableRowsById=[];
        $this->tableRowsByName=[];
    }

}
