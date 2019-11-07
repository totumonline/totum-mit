<?php

namespace totum\common;

use totum\models\Table;

class Model
{

    const serviceFields = ['id', 'is_del', 'updated', 'cycle_id', 'header', 'tbl', 'tbl_name', 'n', '__all__', '__eye_groups'];
    const reservedSqlWords = ['all', 'analyse', 'analyze', 'and', 'any', 'array', 'as', 'asc', 'asymmetric', 'both', 'case', 'cast', 'check', 'collate', 'column', 'constraint', 'create', 'current_catalog', 'current_date', 'current_role', 'current_time', 'current_timestamp', 'current_user', 'default', 'deferrable', 'desc', 'distinct', 'do', 'else', 'end', 'except', 'false', 'fetch', 'for', 'foreign', 'from', 'grant', 'group', 'having', 'in', 'initially', 'intersect', 'into', 'lateral', 'leading', 'limit', 'localtime', 'localtimestamp', 'not', 'null', 'offset', 'on', 'only', 'or', 'order', 'placing', 'primary', 'references', 'returning', 'select', 'session_user', 'some', 'symmetric', /*'table',*/ 'then', 'to', 'trailing', 'true', 'union', 'unique', 'user', 'using', 'variadic', 'when', 'where', 'window', 'with'];
    static $tablesModels = [
        'users' => 'User'
        , 'users__v' => 'UserV'
        , 'tree' => 'Tree'
        , 'tree__v' => 'TreeV'
        , 'tables_fields' => 'TablesFields'
        , 'tables' => 'Table'
        , 'calcstable_cycle_version' => 'CalcsTableCycleVersion'
        , 'tables_nonproject_calcs' => 'NonProjectCalcs'
        , 'tables_calcs_connects' => 'TablesCalcsConnects'
    ];


    protected static $Instance = [];
    protected $table, $idFieldName = 'id', $isServiceTable = false;

    static function isServiceField($fName)
    {
        return in_array($fName, static::serviceFields);
    }

    protected function __construct($table, $idField = null, $isService = null)
    {
        $this->table = $table;
        if ($idField) {
            $this->idFieldName = $idField;
        }
        if ($isService) {
            $this->isServiceTable = true;
        }
    }

    static function initService($table = '', $idField = null): Model
    {
        return static::init($table, $idField, true);

    }

    static function init($table = '', $idField = null, $isService = null): Model
    {
        if (empty($table)) {
            $className = get_called_class();
            $classNameMini = preg_replace('`^.*?([a-zA-Z]+)$`', '$1', get_called_class());
            $table = array_flip(static::$tablesModels)[$classNameMini];
        } else {
            $className = static::class;
            if (!empty(static::$tablesModels[$table])) {
                $className = '\\totum\\models\\' . static::$tablesModels[$table];
            }
        }

        static::$Instance[$table . ($isService ? '!!!' : '')] = static::$Instance[$table . ($isService ? '!!!' : '')] ?? new $className($table,
                $idField,
                $isService);
        return static::$Instance[$table . ($isService ? '!!!' : '')];
    }

    static function getClearValuesRealTableRow($row)
    {
        array_walk($row,
            function (&$v, $k) {
                if (!Model::isServiceField($k)) $v = json_decode($v, true)['v'];
            });
        return $row;
    }

    static function getClearValuesRow($row)
    {
        $row = array_map(function ($v) {
            if (is_array($v) && array_key_exists('v', $v)) return $v['v'];
            return $v;
        },
            $row);
        return $row;
    }

    function quoteField($field)
    {
        $field = preg_replace('/^([a-z]+\.)(.*)$/', '$1"$2"', $field);
        if (!$this->isServiceTable && !in_array($field, static::serviceFields) && !strpos($field, ' as ')) {
            $field = '(' . $field . '->>\'v\')';
        }
        return $field;
    }

    function getTableName()
    {
        return $this->table;
    }

    function getAll($where = array(), $fields = '*', $order_by = null, $limit = null, $group_by = null)
    {
        return static::query($where, $fields, $order_by, $limit, $group_by);
    }

    function query($where = array(), $fields = '*', $order_by = null, $limit = null, $group_by = null, $fetch = \PDO::FETCH_ASSOC)
    {
        if (!is_null($order_by)) $order_by = ' order by ' . $order_by;
        if (!is_null($limit)) {
            $limit = explode(',', $limit);
            $limit = ' limit ' . $limit[1];
            if ($limit[0] > 0) {
                $limit .= ' offset ' . $limit[0];
            }
        }
        if (!is_null($group_by)) $group_by = ' group by ' . $group_by;

        if (!$this->isServiceTable && $fields != '*') {
            $fields = explode(',', $fields);
            foreach ($fields as &$f) {
                $f = trim($f);
                if (!in_array($f, static::serviceFields) && !strpos($f, ' as ')) {
                    $f = $f . '->>\'v\' as ' . $f;
                }
            }
            $fields = implode(',', $fields);
        }

        return Sql::getAll('select ' . $fields . ' from ' . $this->table . ' where ' . $this->getWhere($where) . $group_by . $order_by . $limit,
            $fetch);
    }

    function getWhere($where)
    {
        $where_str = '';
        foreach ($where as $k => $v) {
            if (preg_match('/[a-z0-9_]+/i', $k)) {
                if ($where_str) $where_str .= " AND ";
                $where_str .= '(';
                if (preg_match('/%LIKE%$/', $k)) {
                    $where_str .= self::quoteField(substr($k, 0, -6)) . ' LIKE ' . Sql::quote($v) . '';
                } elseif (preg_match('/^\d+$/', $k)) {
                    $where_str .= $v;
                } else if (is_null($v) || '[[NULL]]' === $v) {
                    $q=self::quoteField($k);
                    $where_str .=   '('.$q.' is null)';
                }
                else if ($v==='') {
                    $q=self::quoteField($k);
                    $where_str .=   '('.$q.' is null OR '.$q.'=\'\')';

                } else if ($v === false)
                    $where_str .= self::quoteField($k) . '  = false';
                else if ($v === true)
                    $where_str .= self::quoteField($k) . '  = true';
                else if (is_array($v)) {
                    if (empty($v)) {
                        if (preg_match('/NOTIN$/', $k))
                            $where_str .= 'true';
                        else
                            $where_str .= 'false';
                    } else {
                        if (preg_match('/NOTIN$/', $k))
                            $where_str .= self::quoteField(preg_replace('/NOTIN$/',
                                    '',
                                    $k)) . ' NOT IN (' . implode(',', Sql::quote((array)$v, $k === 'id')) . ')';
                        else
                            $where_str .= self::quoteField($k) . ' IN (' . implode(',',
                                    Sql::quote((array)$v, $k === 'id')) . ')';
                    }

                } else
                    $where_str .= self::quoteField($k) . '=' . Sql::quote($v, $k === 'id');

                $where_str .= ')';
            }
        }
        if (!$where_str) $where_str = ' true ';
        return $where_str;
    }

    function getFieldById($field, $id, $order = null)
    {
        return $this->get([$this->idFieldName => $id], $field, $order)[$field] ?? null;
    }

    function getField($field, $where = array(), $order_by = null, $limit = '0,1', $group_by = null)
    {
        $field_val = null;
        if (is_null($limit))
            $field_val = [];

        if ($rows = static::query($where, $field, $order_by, $limit, $group_by, \PDO::FETCH_NUM)) {

            if ($limit == '0,1')
                $field_val = $rows[0][0];
            else {
                foreach ($rows as $row)
                    $field_val[] = $row[0];
            }
        }
        return $field_val;
    }

    function getFieldIndexedById($field, $where = array(), $order_by = null)
    {
        $field_val = [];
        if ($rows = static::query($where, $field . ',' . $this->idFieldName, $order_by)) {
            foreach ($rows as $row)
                $field_val[$row[$this->idFieldName]] = $row[$field];
        }
        return $field_val;
    }

    function __get($name)
    {
        if ($name == 'table') return $this->table;
        if ($name == 'idFieldName') return $this->idFieldName;
    }

    function __call($name, $arguments)
    {
        if (strpos($name, 'getAllIndexedBy') === 0) {
            $field = str_replace('getAllIndexedBy', '', $name);
            $where = empty($arguments[0]) ? null : $arguments[0];
            $fields = empty($arguments[1]) ? null : $arguments[1];
            $order_by = empty($arguments[1]) ? null : $arguments[1];

            return $this->getAllIndexedByField($where, $fields, $field, $order_by);
        }
    }

    function getAllIndexedByField($where, $fields, $field, $order_by = null)
    {
        $datas = array();
        foreach ($this->query($where, $fields, $order_by) as $row) {
            $datas[$row[$field]] = $row;
        }
        return $datas;
    }

    function getFieldIndexedByField($where, $keyField, $field, $order_by = null)
    {
        $datas = array();
        foreach ($this->query($where, $keyField . ',' . $field, $order_by) as $row) {
            $datas[$row[$keyField]] = $row[$field];
        }
        return $datas;
    }

    function getAllIndexedById($where = array(), $fields = '*', $order_by = null)
    {
        return $this->getAllIndexedByField($where, $fields, $this->idFieldName, $order_by);
    }

    function getAllIds($where = array(), $asKeis = false, $orderBy = null)
    {
        $rows = [];
        $fetched = static::query($where, $this->idFieldName, $orderBy, null, null, $fetch = \PDO::FETCH_NUM);
        if ($asKeis) {
            foreach ($fetched as $row)
                $rows[$row['0']] = true;
        } else {
            foreach ($fetched as $row)
                $rows[] = $row['0'];
        }
        return $rows;
    }

    function saveVars($id, $params, $ignore = 0)
    {
        return $this->update($params, array($this->idFieldName => $id), $ignore);
    }

    function update($params, $where, $ignore = 0, $oldRow = null): Int
    {

        $query_part = '';
        if (!is_array($params)) $params = array($params);
        foreach ($params as $param => $val) {

            if (preg_match('/[a-z0-9_]+/i', $param)) {
                if (!empty($query_part)) $query_part .= ', ';
                if (ctype_digit($param . '')) $query_part .= ' ' . $val;
                elseif (is_null($val) || $val === '[[NULL]]') {
                    $query_part .= '"' . $param . '" = NULL';
                } else
                    $query_part .= '"' . $param . '" =' . Sql::quote($val);
            }
        }

        return Sql::exec('update ' . ($ignore ? 'IGNORE' : '') . ' ' . $this->table . ' set ' . $query_part . ' where ' . $this->getWhere($where));
    }

    function insert($vars, $returning = 'idFieldName', $ignore = false)
    {
        if ($returning == 'idFieldName') $returning = $this->idFieldName;
        return Sql::insert($this->table, $vars, $returning, $ignore);
    }


    function deleteById($id)
    {
        return $this->delete([$this->idFieldName => $id]);
    }

    function delete($where, $ignore = 0)
    {
        if (empty($where)) {
            throw new Exception('Данные не верны');
        }
        if ($where = $this->getWhere($where)) {
            $query_string = 'delete ' . ($ignore ? 'ignore' : '') . ' from ' . $this->table . ' where ' . $where;
            return Sql::exec($query_string);
        }
    }

    function getById($id, $fields = "*")
    {
        return $this->get(array($this->idFieldName => $id), $fields);
    }

    function get($where = array(), $fields = "*", $order_by = null)
    {
        if ($rows = $this->query($where, $fields, $order_by, '0,1')) {
            return $rows[0];
        } else return array();
    }

}