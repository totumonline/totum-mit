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

    if (preg_match('/^\/ServicesAnswer(\/|\?|$)/', $_SERVER['REQUEST_URI'])) {
        \totum\common\Services\ServicesConnector::init($Config)->setAnswer(ServerRequest::fromGlobals());
        die('true');
    } elseif (str_starts_with($_SERVER['REQUEST_URI'], '/go-licenser')) {
        if (str_starts_with($_SERVER['REQUEST_URI'], '/go-licenser-test')){
            die($_SERVER['HTTP_HOST'].'/'.$Config->getSchema().'/test');
        }
        try {
            $data = $Config->proGoModuleSocketSend(['method' => 'license', 'host' => $_SERVER['HTTP_HOST']]);
            die($_SERVER['HTTP_HOST'].'/'.$Config->getSchema().'/'.$data['str']);
        }catch (\Exception $e){
            die($e->getMessage());
        }
    }

    list($module, $lastPath) = $Config->getActivationData($_SERVER['REQUEST_URI']);
}


if (empty($module)) {
    $module = 'Table';
    $lastPath = '';
}
$controllerClass = 'totum\\moduls\\' . $module . '\\' . $module . 'Controller';
if (class_exists($controllerClass)) {
    if ($Config && !empty($Config->getHiddenHosts()[$Config->getFullHostName()])) {
        if(empty($Config->getHiddenHosts()[$Config->getFullHostName()][$module])){
            die($Config->getLangObj()->translate('The module is not available for this host.'));
        }else{
            if(is_array($Config->getHiddenHosts()[$Config->getFullHostName()][$module])){
                $Config->setHiddenHostSettings($Config->getHiddenHosts()[$Config->getFullHostName()][$module]);
            }
        }
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
