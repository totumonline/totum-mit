<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 19.10.16
 * Time: 12:54
 */

namespace totum\moduls\Auth;

use Exception;
use Psr\Http\Message\ServerRequestInterface;
use totum\common\controllers\interfaceController;
use totum\common\Auth;
use totum\common\WithPathMessTrait;
use totum\common\Totum;
use totum\models\User;
use totum\tableTypes\tableTypes;

class AuthController extends interfaceController
{
    public function actionLogin(ServerRequestInterface $request)
    {
        $post=$request->getParsedBody();

        $this->Config->setSessionCookieParams();
        session_start();
        if (!empty($_SESSION['userId'])) {
            $this->location();
            die;
        }
        $Totum = new Totum($this->Config);

        $this->__addAnswerVar('with_pass_recover', $this->Config->getSettings('with_pass_recover'));
        $this->__addAnswerVar('schema_name', $this->Config->getSettings('totum_name'), true);

        if (!empty($post)) {
            $SendLetter = function ($email, $login, $pass) {
                $template = $this->Config->getModel('print_templates')->get(['name' => 'main_email'], 'styles, html');

                $template["body"] = preg_replace_callback(
                    '/{(domain|domen|login|pass)}/',
                    function ($match) use ($pass, $email, $login) {
                        switch ($match[1]) {
                            case 'domen':
                            case 'domain':
                                return $this->Config->getFullHostName();
                            case 'pass':
                                return $pass;
                            case 'login':
                                return empty($login) ? $email : $login;
                        }
                        return null;
                    },
                    $template["html"]
                );

                $this->Config->sendMail(
                    $email,
                    'Учетные данные в ' . $_SERVER['HTTP_HOST'],
                    '<style>' . $template["styles"] . '</style>' . $template["body"] . '',
                    null,
                    []
                );
            };


            $getNewPass = function () {
                $letters = 'abdfjhijklmnqrstuvwxz';
                return $letters{mt_rand(0, strlen($letters) - 1)} . $letters{mt_rand(
                    0,
                    strlen($letters) - 1
                )} . str_pad(mt_rand(1, 9999), 4, 0);
            };

            if (empty($post['login'])) {
                return ['error' => 'Заполните поле Логин/Email'];
            }


            $userRow = null;

            $getUserRow = function () use (&$userRow, $post) {
                if (!is_null($userRow)) {
                    return $userRow;
                }

                /*блокируем возможность авторизоваться под сервисными логинами*/
                if ($post['login'] === 'cron' || $post['login'] === 'service') {
                    $userRow = false;
                } elseif (strpos($post['login'], '@') !== false) {
                    $userRow = User::init($this->Config)->get(['email' => strtolower($post['login']), 'is_del' => false, 'interface' => 'web']);
                } else {
                    $userRow = User::init($this->Config)->get(['login' => $post['login'], 'is_del' => false, 'interface' => 'web']);

                    if (!$userRow) {
                        $userRow = false;
                    }
                }
                return $userRow;
            };


            /*Защита от подбора пароля*/
            if (empty($post['recover']) && $ip = ($_SERVER['REMOTE_ADDR'] ?? null)) {
                if ($authLogTableRow = $this->Config->getTableRow('auth_log')) {
                    $now_date = date_create();
                    if (($vremya_blokirovki = $this->Config->getSettings('h_time')) && ($error_count = $this->Config->getSettings('error_count'))) {
                        $BlockDate = date_create()->modify('-' . $vremya_blokirovki . 'minutes');
                        $block_date = $BlockDate->format('Y-m-d H:i');


                        if ($blocked = $this->Config->getModel('auth_log')->get(['user_ip' => $ip, 'datetime->>\'v\'>=\'' . $block_date . '\'', 'status' => 2])) {
                            return ['error' => 'В связи с превышением количества попыток на ввод пароля ваш IP заблокирован'];
                        } else {
                            if (($userRow = $getUserRow()) && json_decode(
                                $userRow['pass'],
                                true
                            )['v'] === md5($post['pass'])) {
                                $status = 0;
                            } else {
                                $count = $this->Config->getModel('auth_log')->getField(
                                    'count(*) as cnt',
                                    ['user_ip' => $ip, 'status' => 1, '>=datetime' => $block_date ]
                                );
                                $count++;
                                if ($count >= $error_count) {
                                    $status = 2;
                                } else {
                                    $status = 1;
                                }
                            }
                            $this->Config->getSql()->insert(
                                'auth_log',
                                [
                                    'datetime' => json_encode(['v' => $now_date->format('Y-m-d H:i')])
                                    , 'user_ip' => json_encode(['v' => $ip])
                                    , 'login' => json_encode(['v' => $post['login']])
                                    , 'status' => json_encode(['v' => strval($status)])
                                ],
                                false
                            );
                        }
                    }
                }
            }


            $userRow = $getUserRow();


            if (!empty($post['recover'])) {
                if (!$Totum->getConfig()->getSettings('with_pass_recover')) {
                    return ['error' => 'Восстановление пароля через  email для данной базы отключено. Обратитесь к админинстратору решения.'];
                }

                if ($userRow) {
                    $email = json_decode($userRow['email'], true)['v'];
                    if (empty($email)) {
                        return ['error' => 'Email для этого login не задан'];
                    }
                    $User = Auth::serviceUserStart($this->Config);
                    $Totum = new Totum($this->Config, $User);
                    $pass = $getNewPass();

                    try {
                        $Totum->getTable('users')->actionSet(
                            ['pass' => $pass],
                            [['field' => 'id', 'operator' => '=', 'value' => $userRow['id']]]
                        );
                        Auth::webInterfaceRemoveAuth();

                        $login = json_decode($userRow['login'], true)['v'];
                        $email = json_decode($userRow['email'], true)['v'];
                        $SendLetter($email, $login, $pass);

                        return ['error' => 'Письмо с новым паролем отправлено на ваш Email. Проверьте ваш ящик через пару минут.'];
                    } catch (Exception $e) {
                        Auth::webInterfaceRemoveAuth();

                        return ['error' => 'Письмо не отправлено' . $e->getMessage()];
                    }
                } else {
                    return ['error' => 'Пользователь с указанным Логин/Email не найден'];
                }
            }

            if ($userRow) {
                if (Auth::checkUserPass($post['pass'], json_decode($userRow['pass'], true)['v'])) {
                    Auth::webInterfaceSetAuth($userRow['id']);

                    $this->location();
                    die;
                } else {
                    return ['error' => 'Пароль не верен'];
                }
            } else {
                return ['error' => 'Пользователь не найден'];
            }
        }
    }

    public function doIt(ServerRequestInterface $request, bool $output)
    {
        $requestUri = preg_replace('/\?.*/', '', $request->getUri()->getPath());
        $requestAction = substr($requestUri, strlen($this->modulPath));
        $action = explode('/', $requestAction, 2)[0] ?? 'Main';

        try {
            $this->__run($action, $request);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            $this->__addAnswerVar('error', $message);
        }
        $this->output($action);
    }

    public function actionLogout()
    {
        Auth::webInterfaceRemoveAuth();
        $this->location();
        die;
    }
}
