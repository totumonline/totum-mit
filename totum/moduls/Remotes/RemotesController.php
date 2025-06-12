<?php


namespace totum\moduls\Remotes;

use Psr\Http\Message\ServerRequestInterface;
use totum\common\Auth;
use totum\common\calculates\CalculateAction;
use totum\common\controllers\Controller;
use totum\common\criticalErrorException;
use totum\common\Lang\RU;
use totum\common\Model;
use totum\common\tableSaveOrDeadLockException;
use totum\common\Totum;
use totum\tableTypes\RealTables;

class RemotesController extends Controller
{
    public function doIt(ServerRequestInterface $request, bool $output)
    {
        die($this->translate('This option works only in PRO.'));
    }


}
