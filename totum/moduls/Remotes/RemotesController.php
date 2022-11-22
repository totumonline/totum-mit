<?php


namespace totum\moduls\Remotes;

use Psr\Http\Message\ServerRequestInterface;
use totum\common\Auth;
use totum\common\calculates\CalculateAction;
use totum\common\controllers\Controller;
use totum\common\Lang\RU;
use totum\common\Model;
use totum\common\tableSaveOrDeadLockException;
use totum\common\Totum;
use totum\tableTypes\RealTables;

class RemotesController extends Controller
{
    public function doIt(ServerRequestInterface $request, bool $output)
    {
        $requestUri = preg_replace('/\?.*/', '', $request->getUri()->getPath());
        $requestPath = substr($requestUri, strlen($this->totumPrefix . 'Remotes/'));

        $remoteSelect = $this->Config->getModel('ttm__remotes')->get(
            ['on_off' => 'true', 'name' => $requestPath],
            '*'
        );
        $error = null;
        $data = null;
        if ($remoteSelect) {
            $remote = Model::getClearValuesWithExtract($remoteSelect);
            $remote_row = RealTables::decodeRow($remoteSelect);
            if ($remote['remotes_user']) {
                if ($User = Auth::getUserById($this->Config, $remote['remotes_user'])) {

                    $tries = 0;
                    do {
                        $onceMore = false;
                        try {
                            $data = $this->action($User, $remote, $remote_row, $request);
                        } catch (tableSaveOrDeadLockException $exception) {
                            $this->Config = $this->Config->getClearConf();
                            if (++$tries < 5) {
                                $onceMore = true;
                            } else {
                                $error = $this->translate('Conflicts of access to the table error');
                            }
                        } catch (\Exception $e) {
                            $error = $e->getMessage();

                        }
                    } while ($onceMore);

                } else {
                    $error = $this->translate('Authorization error');
                }
            } else {
                $error = $this->translate('Remote is not connected to the user');
            }

            switch ($remote['return']) {
                case 'error':
                    if ($error) {
                        header($_SERVER['SERVER_PROTOCOL'] . ' 500 ' . $error, true, 500);
                        echo $error;
                    } else {
                        echo is_array($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : $data;
                    }
                    break;
                case 'simple':
                    if ($error) {
                        echo 'error';
                    } else {
                        echo 'success';
                    }
                    break;
                case 'json':
                    if ($error) {
                        $data = ['error' => $error];
                    }
                    echo json_encode($data, JSON_UNESCAPED_UNICODE);
                    break;
                case 'string':
                    echo $data;
                    break;
                default:
                    if (is_array($data)) {
                        foreach ($data['headers'] ?? [] as $h => $v) {
                            header((!is_numeric($h) ? $h . ':' : '') . $v);
                        }
                        echo $data['body'];
                    } else {
                        echo 'Error script answer format';
                    }

            }
        } else {
            echo $this->translate('Remote is not active or does not exist');
            die;
        }
    }

    protected function action($User, $remote, $remote_row, ServerRequestInterface $request)
    {
        $Totum = new Totum($this->Config, $User);
        $Totum->transactionStart();
        $table = $Totum->getTable('ttm__remotes');

        $calc = new CalculateAction($remote['code']);
        $data = $calc->execAction(
            'CODE',
            $remote_row,
            $remote_row,
            $table->getTbl(),
            $table->getTbl(),
            $table,
            'exec',
            [
                'get' => $request->getQueryParams() ?? [],
                'post' => $request->getParsedBody() ?? [],
                'input' => (string)$request->getBody(),
                'remoteIp' => $request->getServerParams()['REMOTE_ADDR'],
                'headers' => ($headers = $request->getHeaders()) ? $headers : []
            ]
        );

        $Totum->transactionCommit();
        return $data;
    }
}
