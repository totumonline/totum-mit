<?php


namespace totum\moduls\Remotes;


use totum\common\Auth;
use totum\common\CalculateAction;
use totum\common\Controller;
use totum\common\Model;
use totum\tableTypes\RealTables;
use totum\tableTypes\tableTypes;

class RemotesController extends Controller
{
    public function doIt($action)
    {
        $remoteSelect = Model::init('ttm__remotes')->get(['on_off' => 'true', 'name' => $action], '*');
        $error = null;
        $data = null;
        if ($remoteSelect) {
            $remote = Model::getClearValuesRealTableRow($remoteSelect);
            $remote_row = RealTables::decodeRow($remoteSelect);
            if ($remote['remotes_user']) {
                if (Auth::simpleAuth($remote['remotes_user'])) {
                    $table=tableTypes::getTableByName('ttm__remotes');
                    try {
                        $calc = new CalculateAction($remote['code']);
                        $data = $calc->execAction('CODE',
                            $remote_row,
                            $remote_row,
                            $table->getTbl(),
                            $table->getTbl(),
                            $table,
                            [
                                'get' => $_GET ?? [],
                                'post' => $_POST ?? [],
                                'input' => file_get_contents('php://input'),
                                'headers' => ($headers = getallheaders()) ? $headers : []
                            ]);

                    } catch (\Exception $e) {
                        $error = $e->getMessage();
                    }
                } else {
                    $error = 'Ошибка авторизации пользователя';
                }


            } else $error = 'Remote не подключен к пользователю';

            switch ($remote['return']) {
                case 'simple':
                    if ($error) {
                        echo 'error';
                    } else echo 'success';
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