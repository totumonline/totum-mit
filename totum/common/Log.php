<?php

namespace totum\common;

use totum\config\Conf;

class Log
{

    static $logFileResources = [];
    static $logFilePaths = [];
    static $childMD5 = null;
    static $microtime;
    static $off = false;

    static function calcs($str)
    {
        static::__print('calcs', $str);
    }
    static function error($str, $fullString = true)
    {
        static::__print('error', $str, $fullString );
    }

    static function sql($str, $fullString = false)
    {
        if (is_string($str) && preg_match('/update|insert|delete/i', $str)) $fullString = true;

        static::__print('sql', $str, $fullString);
    }

    static function __print($name, $str, $fullString = false)
    {
        if (static::$off) return;

        if (empty(static::$childMD5)) static::$childMD5 = md5(microtime(1));

        if (empty(static::$logFilePaths)){
            static::$logFilePaths = Conf::getLogPaths();
        }
        if (empty(static::$logFilePaths[$name])) return false;


        if (is_array($str)) {
            $str = @var_export($str, 1);
        }

        $str = preg_replace('/[\r\n\t ]+/m', ' ', $str);

        if (empty(static::$logFileResources[$name])) {
            static::$logFileResources[$name] = fopen(static::$logFilePaths[$name], 'a');

            fwrite(static::$logFileResources[$name],
                '***********************' . date('d.m H:i',
                    $_SERVER['REQUEST_TIME']) . ' ' .
                (!empty($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : 'ssh')
                . (!empty($_POST['method']) ? ' - ' . $_POST['method'] : '')
                . (!empty($_SESSION['auth']) ? ' ' . $_SESSION['auth']['login'] : '')
                . ' ' . (!empty($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'ssh') . '***********************' . PHP_EOL
            );
        }

        $diff = 0;
        if (!empty(static::$microtime)) {
            $diff = bcadd(microtime(true), -static::$microtime, 5);
        }

        static::$microtime = microtime(true);
        if (!$fullString) {
            $str = substr($str, 0, 500);
        }
        fwrite(static::$logFileResources[$name],
            (Auth::getUserLogin()??'notAuthed') . ' ' . date('d.m H:i') . " $diff $str" . PHP_EOL);
    }

    public static function cron($str)
    {
        static::__print('cron', $str, true);
    }
}