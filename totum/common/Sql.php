<?php

namespace totum\common;

use \PDO;
use totum\config\Conf;

class Sql
{

    /**
     * @var \PDO
     */
    static $PDO;
    static $lastQuery = null;
    protected static $onCommit = [];
    protected static $logging = 1;
    protected static $startedTransactionsCounter = 0;
    protected static $singleton;

    protected static $sqlObj;

    static function clear()
    {
        static::transactionRollBack();
        static::$PDO = null;
        static::$onCommit = [];
    }

    protected function __construct()
    {
    }

    static function addOnCommit($func)
    {
        static::$onCommit[] = $func;
    }

    static function prepare($query_string)
    {
        self::log($query_string);
        $r = self::$PDO->prepare($query_string);
        if (($error = self::$PDO->errorInfo()) && $error[2]) throw new SqlExeption($error[2] . '/ СХЕМА: ' . Conf::getSchema());
        return $r;
    }

    static function log($query, $fullString = false)
    {
        if ($fullString) {

            Log::sql(debug_backtrace());
        }
        if (self::$logging) {
            Log::sql($query, $fullString);
            //Log::sql(array_slice(debug_backtrace(0, 4), 3), true);
        }
    }

    static function insert($table, $vars, $returning = null, $ignore = false)
    {
        $vars2 = array();
        foreach ($vars as $k => $v) {
            $vars2['"' . $k . '"'] =
                (is_null($v) || $v === '[[NULL]]' ? 'null' :
                    (is_bool($v) ?
                        ($v === false ? 'false' : 'true')
                        :
                        Sql::quote($v)
                    )
                );
        }
        if ($vars2) {
            $query_string = 'INSERT INTO ' . $table . ' (' . implode(',',
                    array_keys($vars2)) . ') VALUES (' . implode(',',
                    array_values($vars2)) . ') '
                . ($ignore ? ' ON CONFLICT DO NOTHING ' : '')
                . ($returning ? ' RETURNING ' . $returning : '');
        } else {
            $query_string = 'INSERT INTO ' . $table . ' DEFAULT VALUES'
                . ($ignore ? ' ON CONFLICT DO NOTHING ' : '')
                . ($returning ? ' RETURNING ' . $returning : '');
        }

        if ($returning) return static::getField($query_string);
        else return static::exec($query_string);

    }

    static function quote($var, $isMastBeInteger = false)
    {
        if (!self::$PDO) {
            static::__getConnection();
        }
        if (is_array($var)) {
            return

                array_map(function ($v) use ($isMastBeInteger) {
                    if (is_array($v)) debug_print_backtrace();
                    return static::quote($v, $isMastBeInteger);
                },
                    $var);
        }
        if ($isMastBeInteger) {
            if (!ctype_digit($strVar = strval($var))) {
                if (!is_numeric($strVar)) {
                    return 'NULL';
                }
                return self::$PDO->quote($var) . '::NUMERIC';
            }
        }

        if (is_bool($var)) return "'" . ($var ? 'true' : 'false') . "'";

        return self::$PDO->quote($var);
    }

    static function getField($query_string, $vars = array())
    {
        $query_string = self::__getQueryString($query_string, $vars);
        $r = self::__exec($query_string);
        return $r->fetchColumn();
    }

    static function exec($query_string, $vars = array()): Int
    {
        $query_string = self::__getQueryString($query_string, $vars);
        return self::__exec($query_string)->rowCount();
    }

    static function get($query_string, $vars = array())
    {
        $query_string = self::__getQueryString($query_string, $vars);
        $r = self::__exec($query_string);
        return $r->fetch(PDO::FETCH_ASSOC);
    }

    static function getAll($query_string, $fetch = PDO::FETCH_ASSOC)
    {
        $query_string = self::__getQueryString($query_string);
        $r = self::__exec($query_string);
        return $r->fetchAll($fetch);
    }

    static function getFieldArray($query_string, $vars = array())
    {
        $query_string = self::__getQueryString($query_string, $vars);
        $r = self::__exec($query_string);
        return $r->fetchAll(PDO::FETCH_COLUMN, 0);
    }

    static function transactionStart()
    {
        self::log('start_transaction:' . static::$startedTransactionsCounter);

        if (!static::$startedTransactionsCounter) {
            self::exec('START TRANSACTION');
            if (!static::$sqlObj) {
                static::$sqlObj = new static();
            }
        }
        static::$startedTransactionsCounter++;


    }

    static function transactionCommit()
    {
        static::$startedTransactionsCounter--;
        self::log('commit:' . static::$startedTransactionsCounter);
        if (!static::$startedTransactionsCounter) {

            foreach (static::$onCommit as $func) $func();
            self::exec('COMMIT');
        }

    }

    static function stopLogging($text)
    {
        self::log($text);
        self::$logging = 0;
    }

    static function startLogging($text)
    {
        self::$logging = 1;
        self::log($text);
    }

    static function transactionRollBack()
    {
        if (self::$startedTransactionsCounter > 0) {
            self::$startedTransactionsCounter = 0;
            self::exec('ROLLBACK');
        }
    }

    public static function quoteFields($var)
    {


        if (is_array($var)) {
            return

                array_map(function ($v) {
                    if (is_array($v)) debug_print_backtrace();
                    return static::quoteFields($v);
                },
                    $var);
        }

        return '"' . preg_replace('/[^A-Za-z0-9_]+/', '', $var) . '"';
    }

    protected static function __getQueryString($query_string, $vars = null)
    {
        if ($vars) {
            $vars2 = array();
            foreach ($vars as $k => $v) {
                if (is_array($v)) {
                    $_vv = array();
                    foreach ($v as $_v) {
                        $_vv[] = (is_null($_v) ? 'NULL' : static::quote($_v));
                    }
                    $vars2["[$k]"] = '(' . implode(',', $_vv) . ')';
                } else
                    $vars2["[$k]"] = (is_null($v) ? 'NULL' : static::quote($v));
            }
            $vars = $vars2;
            $query_string = str_replace(array_keys($vars), array_values($vars), $query_string);
        }
        return $query_string;
    }

    static function __exec($query_string): \PDOStatement
    {
        if (!self::$PDO) {
            static::__getConnection();
        }
        static::$lastQuery = ['str' => $query_string, 'error' => ''];

        $microtime = microtime(1);
        $r = self::$PDO->query($query_string);
        if (($error = self::$PDO->errorInfo()) && $error[2]) {
            self::log($error[2] . ' >>>' . $query_string, true);
            static::$lastQuery['error'] = $error[2];
            $i = 3;
            $dbgPrint = '';
            foreach (debug_backtrace() as $line) {
                if ($line['class'] != static::class) {
                    $dbgPrint .= "\n" . preg_replace('`.*/([^/]*/[^/]*)$`',
                            '$1',
                            $line['file']) . '(' . $line['line'] . ')';
                    if (--$i <= 0) {
                        break;
                    }
                }
            }
            throw new SqlExeption($error[2] . $dbgPrint);
        }
        $query_time = round(microtime(1) - $microtime, 3);
        $query_time_pad = str_pad($query_time, 5, '0', STR_PAD_LEFT);

        static::$lastQuery['time'] = $query_time_pad;
        static::$lastQuery['rows'] = $r->rowCount();

        self::log($query_time_pad . ' (' . static::$lastQuery['rows'] . 'rows) >> ' . $query_string);
        return $r;
    }

    static function ___getNewConnection()
    {
        $conf = Conf::getDb();

        try {
            $PDO = new PDO($conf['dsn'], $conf['username'], $conf['password']);
            if ($PDO->errorInfo()[0] != 0) {
                throw new \Exception(self::$PDO->errorInfo()[2]);
            }
            if (empty($conf['schema'])) {
                die('Хост неопределен');
            }
            $PDO->exec('SET search_path TO "' . $conf['schema'] . '"');

            if ($PDO->errorInfo()[0] != 0) {
                throw new \Exception($PDO->errorInfo()[2]);
            }
        } catch (\Exception $e) {
            throw new SqlExeption('Ошибка подключения к базе данных. Попробуйте позже:' . $e->getMessage(), 0, $e);
        }
        return $PDO;
    }

    protected static function __getConnection()
    {
        if (empty(static::$singleton)) static::$singleton = new static();
        self::$PDO = static::___getNewConnection();
    }

    function __destruct()
    {
        static::transactionRollBack();
    }
}

class SqlExeption extends \Exception
{

}