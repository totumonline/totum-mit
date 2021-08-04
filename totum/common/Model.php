<?php

namespace totum\common;

use PDO;
use PDOStatement;
use totum\common\sql\Sql;
use totum\common\sql\SqlException;
use totum\config\Conf;

class Model
{
    public const serviceFields = ['id', 'n', 'is_del', 'updated', 'cycle_id', 'header', 'tbl', 'tbl_name', 'n', '__all__', '__eye_groups'];
    protected const RESERVED_WORDS = ['all', 'analyse', 'analyze', 'and', 'any', 'array', 'as', 'asc', 'asymmetric', 'both', 'case', 'cast', 'check', 'collate', 'column', 'constraint', 'create', 'current_catalog', 'current_date', 'current_role', 'current_time', 'current_timestamp', 'current_user', 'default', 'deferrable', 'desc', 'distinct', 'do', 'else', 'end', 'except', 'false', 'fetch', 'for', 'foreign', 'from', 'grant', 'group', 'having', 'in', 'initially', 'intersect', 'into', 'lateral', 'leading', 'limit', 'localtime', 'localtimestamp', 'not', 'null', 'offset', 'on', 'only', 'or', 'order', 'placing', 'primary', 'references', 'returning', 'select', 'session_user', 'some', 'symmetric', /*'table',*/
        'then', 'to', 'trailing', 'true', 'union', 'unique', 'user', 'using', 'variadic', 'when', 'where', 'window', 'with'];


    protected $table;
    protected $idFieldName = 'id';
    protected $isServiceTable = false;
    /**
     * @var Sql
     */
    protected $Sql;
    /**
     * @var mixed|string
     */
    protected $preparedCache = [];

    public static function isServiceField($fName)
    {
        return in_array($fName, static::serviceFields);
    }

    public function __construct(Sql $Sql, $table, $idField = null, $isService = null)
    {
        $this->table = $table;
        $this->Sql = $Sql;

        if ($idField) {
            $this->idFieldName = $idField;
        }
        if (!$this->isServiceTable) {
            $this->isServiceTable = $isService === true;
        }
    }

    /*TODO delete method if will not use */
    public static function getClearValuesWithExtract($row)
    {
        if ($row) {
            array_walk(
                $row,
                function (&$v, $k) {
                    if (!Model::isServiceField($k)) {
                        $v = json_decode($v, true)['v'];
                    }
                }
            );
        }
        return $row;
    }

    public function createTable(array $fields)
    {
        $fields = '(' . implode(',', $fields) . ')';
        $this->Sql->exec('CREATE TABLE ' . $this->table . $fields);
    }

    public function createIndex($columnName, $uniq = false)
    {
        $q1 = $uniq ? 'UNIQUE' : '';
        $this->Sql->exec("CREATE {$q1} INDEX {$this->table}___ind___{$columnName} ON {$this->table} ({$columnName})");
    }

    public function createIndexOnJsonbField($columnName)
    {
        $this->Sql->exec("CREATE INDEX IF NOT EXISTS {$this->table}___ind___{$columnName} ON {$this->table} (({$columnName} ->> 'v'))");
        $this->Sql->exec('ANALYZE ' . $this->table);
    }

    public function removeIndex($columnName)
    {
        $this->Sql->exec('DROP INDEX IF EXISTS ' . $this->table . '___ind___' . $columnName);
        $this->Sql->exec('ANALYZE ' . $this->table);
    }

    public function dropColumn($name)
    {
        $this->preparedCache = [];

        $this->Sql->exec('ALTER TABLE ' . $this->table . ' DROP COLUMN IF EXISTS "' . $name . '" ', [], true);
        $this->Sql->exec('ANALYZE ' . $this->table);
    }

    public function dropTable()
    {
        $this->Sql->exec('DROP TABLE if exists ' . $this->table . ' CASCADE');
    }

    public function addOrderField()
    {
        $this->Sql->exec('ALTER TABLE ' . $this->table . ' ADD COLUMN "n" numeric ');
        $this->Sql->exec('Update ' . $this->table . ' set "n"=id ');
        $this->createIndex('n', true);
        $this->Sql->exec('ANALYZE ' . $this->table);
    }

    public function exec(string $string)
    {
        return $this->Sql->exec($string);
    }

    protected function quoteWhereField($field, $FieldType = 'S')
    {
        $field = preg_replace('/[^a-z0-9_]/', '', $field);
        if (!$this->isServiceTable && !in_array($field, static::serviceFields)) {
            $field = '(' . $field . '->>\'v\')';
            if ($FieldType === 'N') {
                $field .= '::NUMERIC';
            }
        }
        return $field;
    }

    public function addColumn($fieldName)
    {
        $this->preparedCache = [];
        $this->Sql->exec('ALTER TABLE ' . $this->table . ' ADD COLUMN ' . $fieldName . ' JSONB NOT NULL DEFAULT \'{"v":null}\' ', [], true);
    }

    public static function init(Conf $Config, $isService = false)
    {
        return $Config->getNamedModel(get_called_class(), $isService);
    }

    public function getTableName()
    {
        return $this->table;
    }

    public function getAll($where = [], $fields = '*', $order_by = null, $limit = null, $group_by = null)
    {
        return $this->query($where, $fields, $order_by, $limit, $group_by);
    }

    public function childrenIdsRecursive($id, $parentField, $bfield)
    {
        return $this->executePreparedSimple(
            true,
            'WITH RECURSIVE cte_name (id) AS ( select
                                                    ' . ($bfield === 'id' ? '(id):: text' : $bfield . '->>\'v\'') . ' as id
                                                  from ' . $this->table . '
                                                  where is_del = false AND (' . $parentField . ' ->> \'v\') :: text=?
                                                  UNION select
                                                          ' . ($bfield === 'id' ? '(tp.id) :: text' : 'tp.' . $bfield . '->>\'v\'') . ' as id
                                                        from ' . $this->table . ' tp
                                                          JOIN cte_name c ON (tp.' . $parentField . ' ->> \'v\') :: text = c.id AND
                                                                             tp.is_del = false ) SELECT id
                                                                                                FROM cte_name',
            [$id]
        )->fetchAll(PDO::FETCH_COLUMN, 0);
    }

    protected function query($where = [], string $fields = '*', $order_by = null, $limit = null, $group_by = null, $fetch = PDO::FETCH_ASSOC)
    {
        $where = $this->getWhere($where);
        return $this->Sql->getAll(
            $this->getQueryString($where, $fields, $order_by, $limit, $group_by),
            $fetch
        );
    }

    public function preparedSimple($str)
    {
        return $this->Sql->getPrepared($str);
    }

    protected function prepare($where = [], string $fields = '*', $order_by = null, $limit = null, $group_by = null, $fetch = PDO::FETCH_ASSOC)
    {
        if (!is_string($where)) {
            $where = $this->getWherePrepared($where);
        }
        $stmt = $this->Sql->getPrepared($this->getQueryString($where, $fields, $order_by, $limit, $group_by));
        if ($fetch) {
            $stmt->setFetchMode($fetch);
        }
        return $stmt;
    }

    protected function getPreprendSelectFields($fields)
    {
        if (!$this->isServiceTable && $fields[0] !== '*') {
            $fields = explode(',', $fields);
            foreach ($fields as &$f) {
                if (!strpos($f, ' as ')) {
                    $f = preg_replace('/[^a-z0-9_]/', '', $f);
                    if (!in_array($f, static::serviceFields) && !strpos($f, ' as ')) {
                        $f = $f . '->>\'v\' as ' . $f;
                    }
                }
            }
            $fields = implode(',', $fields);
        }
        return $fields;
    }

    public function getQueryString(string $where, string $fields = '*', $order_by = null, $limit = null, $group_by = null)
    {
        if (!$where) {
            $where = "TRUE";
        }

        if (!is_null($order_by)) {
            $order_by = ' order by ' . $order_by;
        }
        if (!is_null($limit)) {
            $limitArray = explode(',', $limit);
            $limit = "";
            if ($limitArray[1] ?? '' !== "") {
                $limit = ' limit ' . $limitArray[1];
            }
            if ($limitArray[0] > 0) {
                $limit .= ' offset ' . $limitArray[0];
            }
        }
        if (!is_null($group_by)) {
            $group_by = ' group by ' . $group_by;
        }
        $fields = $this->getPreprendSelectFields($fields);

        return 'select ' . $fields . ' from ' . $this->table . ' where ' . $where . $group_by . $order_by . $limit;
    }


    public function executePreparedSimple($stmt, $query, $vars)
    {
        if (is_bool($stmt)) {
            $cacheIt = $stmt;
            if ($cacheIt) {
                $cache_key = $query;
            } else {
                $cache_key = null;
            }

            /*Если запрос кеширующийся и есть кеш*/
            if ($cache_key && key_exists($cache_key, $this->preparedCache)) {
                $stmt = $this->preparedCache[$cache_key];
            } else {
                $stmt = $this->Sql->getPrepared($query);
                if ($cache_key) {
                    $this->preparedCache[$cache_key] = $stmt;
                }
            }
        }

        $this->Sql->executePrepared($stmt, $vars);
        return $stmt;
    }


    /**
     * @param PDOStatement|bool $stmt
     * @param array|\stdClass $where
     * @param string $fields
     * @param null $order_by
     * @param null $limit
     * @param null $group_by
     * @return mixed|PDOStatement|string
     */
    public function executePrepared($stmt, $where, string $fields = '*', $order_by = null, $limit = null, $group_by = null)
    {
        $isPreparedWhere = false;
        if (is_object($where)) {
            $isPreparedWhere = true;
            $params = $where->params;
            $where = $where->whereStr;
        } else {
            $params = $this->trainValuesPrepared($where);
        }

        if (is_bool($stmt)) {
            $cacheIt = $stmt;
            if ($cacheIt) {
                if ($isPreparedWhere) {
                    $whereKey = $where;
                } else {
                    $whereKey = array_map(
                        function ($v) {
                            return is_null($v) ? 0 : count((array)$v);
                        },
                        $where
                    );
                }

                $cache_key = json_encode(
                    ["w" => $whereKey, 'f' => $fields, 'o' => $order_by, 'l' => $limit, 'g' => $group_by]
                );
            } else {
                $cache_key = null;
            }

            /*Если запрос кеширующийся и есть кеш*/
            if ($cache_key && key_exists($cache_key, $this->preparedCache)) {
                $stmt = $this->preparedCache[$cache_key];
            } else {
                $stmt = $this->prepare($where, $fields, $order_by, $limit, $group_by);
                if ($cache_key) {
                    $this->preparedCache[$cache_key] = $stmt;
                }
            }
        }
        $this->Sql->executePrepared($stmt, $params);
        return $stmt;
    }

    protected function trainValuesPrepared($vars, $nullMeans = false)
    {
        $train = [];
        foreach ($vars as $val) {
            if (is_array($val)) {
                if (!empty($val)) {
                    array_push($train, ...$val);
                }
            } else {
                $train[] = $val;
            }
        }
        foreach ($train as $k => &$val) {
            if (is_bool($val)) {
                $val = $val ? 'TRUE' : 'FALSE';
            } elseif (is_null($val) && !$nullMeans) {
                unset($train[$k]);
            }
        }
        unset($val);
        $train = array_values($train);
        return $train;
    }


    public function getWhere($where)
    {
        $where_str = '';
        foreach ($where as $k => $v) {
            if (preg_match('/[a-z0-9_]+/i', $k)) {
                if ($where_str) {
                    $where_str .= " AND ";
                }
                $where_str .= '(';
                if (preg_match('/%LIKE%$/', $k)) {
                    $where_str .= self::quoteWhereField(substr($k, 0, -6)) . ' LIKE ' . $this->Sql->quote($v) . '';
                } elseif (preg_match('/^\d+$/', $k)) {
                    $where_str .= $v;
                } elseif (is_null($v) || '[[NULL]]' === $v) {
                    $q = self::quoteWhereField($k);
                    $where_str .= '(' . $q . ' is null)';
                } elseif ($v === '') {
                    $q = self::quoteWhereField($k);
                    $where_str .= '(' . $q . ' is null OR ' . $q . '=\'\')';
                } elseif ($v === false) {
                    $where_str .= self::quoteWhereField($k) . '  = false';
                } elseif ($v === true) {
                    $where_str .= self::quoteWhereField($k) . '  = true';
                } elseif (is_array($v)) {
                    if (empty($v)) {
                        if (preg_match('/NOTIN$/', $k)) {
                            $where_str .= 'true';
                        } else {
                            $where_str .= 'false';
                        }
                    } elseif (preg_match('/NOTIN$/', $k)) {
                        $where_str .= self::quoteWhereField(preg_replace(
                                '/NOTIN$/',
                                '',
                                $k
                            )) . ' NOT IN (' . implode(
                                ',',
                                $this->Sql->quote((array)$v, $k === 'id')
                            ) . ')';
                    } else {
                        $where_str .= self::quoteWhereField($k) . ' IN (' .
                            implode(
                                ',',
                                $this->Sql->quote((array)$v, $k === 'id')
                            ) . ')';
                    }
                } else {
                    $where_str .= self::quoteWhereField($k) . '=' . $this->Sql->quote($v, $k === 'id');
                }

                $where_str .= ')';
            }
        }
        if (!$where_str) {
            $where_str = ' true ';
        }
        return $where_str;
    }

    protected function getWherePrepared($where)
    {
        $whereStr = '';
        foreach ($where as $k => $v) {
            if (preg_match('/(!|<|>|<=|>=)?(N)?([a-z0-9_]+)/i', $k, $matches)) {
                if ($whereStr) {
                    $whereStr .= " AND ";
                }
                $whereStr .= '(';
                if (preg_match('/^\d+$/', $k)) {
                    debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
                    errorException::criticalException('Ошибка формирования запроса для prepared');
                } else {
                    if ($matches[1]) {
                        switch ($matches[1]) {
                            case '!':
                                $arrayOperator = ' NOT IN ';
                                $arrayEmpty = ' TRUE ';
                                if (is_null($v)) {
                                    $operator = " IS NOT NULL";
                                } else {
                                    $operator = " != ?";
                                }
                                break;
                            default:
                                if (is_array($v)) {
                                    throw new SqlException('Сравнение больше/меньше неприменимо к массивам');
                                }
                                $operator = " {$matches[1]} ?";
                        }
                        $k = $matches[3];
                    } else {
                        $arrayOperator = ' IN ';
                        $arrayEmpty = ' FALSE ';
                        if (is_null($v)) {
                            $operator = " IS NULL";
                        } else {
                            $operator = " = ?";
                        }
                    }
                    $f = self::quoteWhereField($k, $matches[2] ?? 'S');

                    if (is_array($v)) {
                        if (!empty($v)) {
                            $whereStr .= $f . $arrayOperator . '(?' . str_repeat(',?', count($v) - 1) . ')';
                        } else {
                            $whereStr .= $arrayEmpty;
                        }
                    } else {
                        $whereStr .= $f . $operator;
                    }
                }
                $whereStr .= ')';
            }
        }
        if (!$whereStr) {
            $whereStr = ' true ';
        }
        return $whereStr;
    }

    public function getField($field, $where = [], $order_by = null, $limit = '0,1', $group_by = null)
    {
        $field_val = null;
        if (is_null($limit)) {
            $field_val = [];
        }

        if ($r = $this->executePrepared(true, $where, $field, $order_by, $limit, $group_by)) {
            if ($limit === '0,1') {
                return $r->fetchColumn();
            } else {
                return $r->fetchAll(PDO::FETCH_COLUMN, 0);
            }
        }
        return $field_val;
    }

    public function getColumn($field, $where = [], $order_by = null, $limit = null, $group_by = null)
    {
        return $this->getField($field, $where, $order_by, $limit, $group_by);
    }

    public function getFieldIndexedById($field, $where = [], $order_by = null)
    {
        $field_val = [];

        foreach ($this->executePrepared(true, $where, $field . ',' . $this->idFieldName, $order_by) as $row) {
            $field_val[$row[$this->idFieldName]] = $row[$field];
        }
        return $field_val;
    }

    /*function __get($name)
    {
        if ($name == 'table') return $this->table;
        if ($name == 'idFieldName') return $this->idFieldName;

        throw new \Exception('Model not contents property '.$name);
    }*/

    public function getAllIndexedByField($where, $fields, $field, $order_by = null)
    {
        $datas = [];
        foreach ($this->query($where, $fields, $order_by) as $row) {
            $datas[$row[$field]] = $row;
        }
        return $datas;
    }

    public function getFieldIndexedByField($where, $keyField, $field, $order_by = null)
    {
        $datas = [];
        foreach ($this->query($where, $keyField . ',' . $field, $order_by) as $row) {
            $datas[$row[$keyField]] = $row[$field];
        }
        return $datas;
    }

    public function getAllIndexedById($where = [], $fields = '*', $order_by = null)
    {
        return $this->getAllIndexedByField($where, $fields, $this->idFieldName, $order_by);
    }

    public function getAllIds($where = [], $asKeis = false, $orderBy = null)
    {
        $rows = [];
        $fetched = static::query($where, $this->idFieldName, $orderBy, null, null, $fetch = PDO::FETCH_NUM);
        if ($asKeis) {
            foreach ($fetched as $row) {
                $rows[$row['0']] = true;
            }
        } else {
            foreach ($fetched as $row) {
                $rows[] = $row['0'];
            }
        }
        return $rows;
    }

    public function saveVars($id, $params)
    {
        return $this->updatePrepared(true, $params, [$this->idFieldName => $id]);
    }

    public function update($params, $where, $oldRow = null): int
    {
        return $this->updatePrepared(true, $params, $where);
    }

    public function updatePrepared($cacheIt, $params, $where)
    {
        $strVars = '';
        foreach ($params as $k => $v) {
            if (!ctype_digit(strval($k))) {
                if ($strVars !== '') {
                    $strVars .= ', ';
                }
                $strVars .= $k . '=?';
            } else {
                $strVars .= $v;
                unset($params[$k]);
            }
        }

        $whereString = $this->getWherePrepared($where);
        $query_string = 'update ' . $this->table . ' set ' . $strVars . ' where ' . $whereString;

        return $this->executePreparedSimple(
            $cacheIt,
            $query_string,
            array_merge($this->trainValuesPrepared($params, true), $this->trainValuesPrepared($where))
        )->rowCount();
    }

    /*
     * DELETE
     *
     * function insert($vars, $returning = 'idFieldName', $ignore = false)
    {
        if ($returning == 'idFieldName') $returning = $this->idFieldName;
        return $this->Sql->insert($this->table, $vars, $returning, $ignore);
    }*/

    public function insertPrepared($vars, $returning = 'idFieldName', $ignore = false, $cacheIt = true)
    {
        if ($returning === 'idFieldName') {
            $returning = $this->idFieldName;
        }
        if ($vars) {
            $strVars = '("' . implode('", "', array_keys($vars)) . '" )  VALUES (?' . str_repeat(
                    ', ?',
                    count($vars) - 1
                ) . ') ';
        } else {
            $strVars = ' DEFAULT VALUES ';
        }

        $query_string = 'insert into ' . $this->table . ' ' . $strVars . ' '
            . ($ignore ? ' ON CONFLICT DO NOTHING ' : '')
            . ($returning ? ' RETURNING ' . $returning : '');

        foreach ($vars as $k => &$val) {
            if (is_bool($val)) {
                $val = $val ? 'TRUE' : 'FALSE';
            } elseif (is_array($val)) {
                $val = json_encode($val, JSON_UNESCAPED_UNICODE);
            }
        }

        $stmt = $this->executePreparedSimple($cacheIt, $query_string, array_values($vars));

        if ($returning) {
            return $stmt->fetch(PDO::FETCH_COLUMN);
        }
        return $stmt->rowCount();
    }


    public function deleteById($id)
    {
        return $this->delete([$this->idFieldName => $id]);
    }

    public function delete($where, $ignore = 0)
    {
        if (empty($where)) {
            throw new \Exception('Данные не верны');
        }
        if ($where = $this->getWhere($where)) {
            $query_string = 'delete ' . ($ignore ? 'ignore' : '') . ' from ' . $this->table . ' where ' . $where;
            return $this->Sql->exec($query_string);
        }
    }

    public function deletePrepared(array $where, $ignore = false, $cacheIt = true)
    {
        $whereString = $this->getWherePrepared($where);
        $query_string = 'delete ' . ($ignore ? 'ignore' : '') . ' from ' . $this->table . ' where ' . $whereString;
        return $this->executePreparedSimple($cacheIt, $query_string, $this->trainValuesPrepared($where))->rowCount();
    }

    public function getById($id, $fields = "*")
    {
        return $this->executePrepared(true, [$this->idFieldName => $id], $fields)->fetch();
    }

    public function getPrepared($where = [], string $fields = "*", $order_by = null)
    {
        $stmt = $this->executePrepared(true, $where, $fields, $order_by, '0,1');
        return $stmt->fetch() ?? [];
    }

    public function get($where = [], string $fields = "*", $order_by = null)
    {
        if ($rows = $this->query($where, $fields, $order_by, '0,1')) {
            return $rows[0];
        } else {
            return [];
        }
    }
}
