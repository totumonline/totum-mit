<?php

namespace totum\moduls\Commands;

use Psr\Http\Message\ServerRequestInterface;
use totum\common\controllers\Controller;
use totum\common\Crypt;
use totum\config\Conf;

class CommandsController extends Controller
{

    public function doIt(ServerRequestInterface $request, bool $output)
    {
        $Conf = new Conf();

        $params=$request->getParsedBody();
        if (empty($params['key']) || date('H-m-d') != @Crypt::getDeCrypted($params['key'], $Conf->getCryptKeyFileContent())){
            die('Auto check was not passed');
        }

       match (preg_replace('`^/Commands/`', '', $request->getUri()->getPath())){
           'reset-opcache' => $this->resetOpcache()
       };
    }

    private function resetOpcache()
    {
        if (function_exists('opcache_reset') && opcache_reset()) {
            echo "WEB OPcache was reset.";
        } else {
            echo "WEB OPcache reset is not available.";
        }

    }
}