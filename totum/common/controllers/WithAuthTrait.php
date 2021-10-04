<?php


namespace totum\common\controllers;

use Psr\Http\Message\ServerRequestInterface;
use totum\common\Auth;

trait WithAuthTrait
{
    protected function __run($action, ServerRequestInterface $request)
    {
        $this->User = Auth::webInterfaceSessionStart($this->Config);

        if (!$this->User) {
            $this->__UnauthorizedAnswer($request);
        } else {
            $this->__actionRun($action, $request);
        }
    }

    protected function __UnauthorizedAnswer(ServerRequestInterface $request)
    {
        if ($request->getParsedBody()['ajax'] ?? false) {
            echo json_encode(['error' => $this->Config->getLangObj()->translate('Authorization lost.')]);
            die;
        }
        header('HTTP/1.0 401 Unauthorized');
        header('location: /Auth/Login/?from=' . urlencode($_SERVER['REQUEST_URI']));
        die;
    }
}
