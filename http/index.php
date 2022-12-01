<?php

use GuzzleHttp\Psr7\ServerRequest;
use totum\config\Conf;

$GLOBALS['mktimeStart'] = microtime(true);

ignore_user_abort(false);

require __DIR__ . '/../vendor/autoload.php';

if (!class_exists(Conf::class)) {
    $Config = null;
    list($module, $lastPath) = ['install', ''];
} else {
    $Config = new Conf();
    if (is_callable([$Config, 'setHostSchema'])) {
        $Config->setHostSchema($_SERVER['HTTP_HOST']);
    }

    if ($_SERVER['REQUEST_URI'] === '/ServicesAnswer' || str_starts_with($_SERVER['REQUEST_URI'], '/ServicesAnswer?')) {
        \totum\common\Services\ServicesConnector::init($Config)->setAnswer(ServerRequest::fromGlobals());
        die('true');
    }
    list($module, $lastPath) = $Config->getActivationData($_SERVER['REQUEST_URI']);
}


if (empty($module)) {
    $module = 'Table';
    $lastPath = '';
}
$controllerClass = 'totum\\moduls\\' . $module . '\\' . $module . 'Controller';
if (class_exists($controllerClass)) {
    if ($Config && !empty($Config->getHiddenHosts()[$Config->getFullHostName()]) && empty($Config->getHiddenHosts()[$Config->getFullHostName()][$module])) {
        die($Config->getLangObj()->translate('The module is not available for this host.'));
    }

    /*
     * @var Controller $Controller
     * */
    $Controller = new $controllerClass($Config);

    $request = ServerRequest::fromGlobals();
    $response = $Controller->doIt($request, true);


    //$Config->getSql()->transactionRollBack();

} else {
    if ($Config) {
        $Lang = $Config->getLangObj();
    } else {
        $Lang = (new \totum\common\Lang\EN());
    }
    echo $Lang->translate('Not found: %s', [htmlspecialchars($controllerClass)]);
}
die;
?>