<?php

namespace totum\main;

use totum\common\Log;
use totum\config\Conf;


class AutoloadAndErrorHandlers
{
    static $errors = [];

    static function connectExtClasses()
    {
        if (file_exists($fName = dirname(__FILE__) . DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR . 'CalculateExtentions.php')) {
            include($fName);
            $GLOBALS['CalculateExtentions'] = $CalculateExtentions ?? new \stdClass();
        }
        if (file_exists($fName = dirname(__FILE__) . DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR . 'CalculateActionExtentions.php')) {
            include($fName);
            $GLOBALS['CalculateActionExtentions'] = $CalculateActionExtentions ?? new \stdClass();
        }
    }

    public static function autoload($class_name)
    {
        if ($class_name[0] == '\\') {
            $class_name = substr($class_name, 1);
        }
        $paths = explode('\\', $class_name);

        if ($paths[0] == 'totum') array_shift($paths);
        else return;
        include_once dirname(__FILE__) . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $paths) . '.php';
    }

    public static function shutdown()
    {
        $error = error_get_last();

        if ($error !== NULL) {
            $errno = $error["type"];
            $errfile = $error["file"];
            $errline = $error["line"];
            $errstr = $error["message"];


            if ($errno === E_ERROR) {
                $errorStr = $errstr;
            } else $errorStr = $errstr;

            if (!empty($_POST['ajax'])) {
                echo json_encode(['error' => $errorStr], JSON_UNESCAPED_UNICODE);
            } else
                echo $errorStr;

            static::error_handler($errno, $errorStr, $errfile, $errline);
        }

        if (!empty(static::$errors)) {


            if (!empty($_SERVER['HTTP_HOST'])) {
                $title = $_SERVER['HTTP_HOST'] . ' ' . ($_POST['method'] ?? 'GET');
                $mess = $_SERVER['REQUEST_URI'] . ' ';

                foreach (static::$errors as $fline => $str) {
                    $mess .= "\n\n" . $fline . '      ' . $str;
                }

                if (!empty($_POST)) $mess .= "\n\n" . print_r($_POST, 1);

            } else {
                $title = 'cron new-totum';
                $mess = '';

                foreach (static::$errors as $fline => $str) {
                    $mess .= "\n\n" . $fline . '      ' . $str;
                }
            }
            Log::error('ERROR ' . $mess, true);
        }
    }

    public static function error_handler($errno, $errstr, $errfile, $errline)
    {
        static::$errors[$errfile . ':' . $errline] = $errstr;
        return true;
    }

    public static function getTemplatesDir()
    {
        return dirname(__FILE__) . '/templates';
    }
}

chdir(dirname(__FILE__) . '/../');
spl_autoload_register(['totum\main\AutoloadAndErrorHandlers', 'autoload'], true, true);
register_shutdown_function(['totum\main\AutoloadAndErrorHandlers', 'shutdown']);
if (file_exists('Conf.php')) {
    set_error_handler(['totum\main\AutoloadAndErrorHandlers', 'error_handler']);
}
AutoloadAndErrorHandlers::connectExtClasses();
