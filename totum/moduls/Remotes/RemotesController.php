<?php


namespace totum\moduls\Remotes;

use Psr\Http\Message\ServerRequestInterface;
use totum\common\Auth;
use totum\common\calculates\CalculateAction;
use totum\common\controllers\Controller;
use totum\common\Model;
use totum\common\Totum;
use totum\tableTypes\RealTables;

class RemotesController extends Controller
{
    public function doIt(ServerRequestInterface $request, bool $output)
    {
        $requestUri = preg_replace('/\?.*/', '', $request->getUri()->getPath());
        $requestPath = substr($requestUri, strlen($this->totumPrefix.'Remotes/'));

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
                if ($User = Auth::simpleAuth($this->Config, $remote['remotes_user'])) {
                    $this->Totum = new Totum($this->Config, $User);
                    $table = $this->Totum->getTable('ttm__remotes');
                    try {
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
                                'input' => $request->getBody()->getContents(),
                                'headers' => ($headers = $request->getHeaders()) ? $headers : []
                            ]
                        );
                    } catch (\Exception $e) {
                        $error = $e->getMessage();
                    }
                } else {
                    $error = 'Ошибка авторизации пользователя';
                }
            } else {
                $error = 'Remote не подключен к пользователю';
            }

            switch ($remote['return']) {
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
                    foreach ($data['headers'] as $h => $v) {
                        header($h . ':' . $v);
                    }
                    echo $data['body'];
            }
        } else {
            echo $error = 'Remote не активен или не существует';
            die;
        }
    }
}
