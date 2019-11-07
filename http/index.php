<?php

use totum\config\Conf;

$php_value = phpversion();
if (version_compare($php_value, '7.0') == -1) {
    echo 'Currently installed PHP version (' . $php_value . ') is not supported. Minimal required PHP version is 7.0';
    die();
}

$GLOBALS['mktimeStart'] = microtime(true);


ini_set('log_errors', 1);
ini_set('display_errors', 1);
ini_set('error_reporting', E_ALL);
ignore_user_abort(false);

require_once '../totum/AutoloadAndErrorHandlers.php';

if (file_exists('Conf.php')) {
    require_once 'Conf.php';
    set_time_limit(Conf::timeLimit);
    $uri = $uri ?? $_SERVER['REQUEST_URI'];
    list($module, $action, $inModuleUri) = getUriData($uri);

} else {

    list($module, $action, $inModuleUri) = ['install', 'Main', ''];

}

//\totum\common\Sql::transactionStart();


if (!empty($module) && !empty($action)) {
    $controllerClass = 'totum\\moduls\\' . $module . '\\' . $module . 'Controller';
    if (class_exists($controllerClass)) {
        $Controller = new $controllerClass($module, $inModuleUri);
        $Controller->doIt($action);
        die;
    } else die('Не найдено ' . $controllerClass);

} else {
    header('location: /Main/');
    die;
}


function getUriData($server_request_uri)
{
    $module = '';
    $action = '';
    $moduleUri = '';
    if ($qs = strpos($server_request_uri, '?')) {
        $uri = substr($server_request_uri, 0, $qs);
    } else {
        $uri = $server_request_uri;
    }
    $uri = substr($uri, 1);

    $uri_data = explode('/', $uri);
    if ($uri_data) {
        $module = $uri_data[0];
        $moduleUri = substr($uri, strlen($module) + 1);
        if (!empty($uri_data[1])) $action = $uri_data[1];
        else {
            $action = 'Main';
        }
    }
    return array($module, $action, $moduleUri);
}

?>