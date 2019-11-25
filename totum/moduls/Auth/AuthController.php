<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 19.10.16
 * Time: 12:54
 */

namespace totum\moduls\Auth;


use totum\common\errorException;
use totum\common\interfaceController;
use totum\common\Auth;
use totum\common\Mail;
use totum\common\Model;
use totum\common\Sql;
use totum\config\Conf;
use totum\models\Table;
use totum\models\TablesFields;
use totum\models\User;
use totum\models\UserV;
use totum\tableTypes\tableTypes;

class AuthController extends interfaceController
{
    const __isAuthNeeded = false;


    public function __construct($modulName, $inModuleUri)
    {
        Auth::webInterfaceSessionStart();
        parent::__construct($modulName, $inModuleUri);
    }

    function actionLogin()
    {
        if (Auth::isAuthorized()) {
            $this->location();
            die;
        }

        $this->__addAnswerVar('with_pass_recover', $with_pass_recover = UserV::getSetting('with_pass_recover'));
        $this->__addAnswerVar('with_register',
            $with_register = (UserV::getSetting('h_service') && (UserV::getSetting('h_default_web_roles'))));


        if (!empty($_POST)) {


            $SendLetter = function ($email, $login, $pass) {
                $template = tableTypes::getTableByName('print_templates')->getByParams(['where' => [
                    ['field' => 'name', 'operator' => '=', 'value' => 'main_email']
                ], 'field' => ['styles', 'html']],
                    'row');

                $template["body"] = preg_replace_callback('/\{(domain|domen|login|pass)\}/',
                    function ($match) use ($pass, $email, $login) {
                        switch ($match[1]) {
                            case 'domen':
                            case 'domain':
                                return Conf::getFullHostName();
                            case 'pass':
                                return $pass;
                            case 'login':
                                return empty($login) ? $email : $login;
                        }
                    },
                    $template["html"]);

                Mail::send($email,
                    'Учетные данные в ' . $_SERVER['HTTP_HOST'],
                    '<style>' . $template["styles"] . '</style>' . $template["body"] . '',
                    null,
                    [],
                    true);

            };


            $getNewPass = function () {
                $letters = 'abdfjhijklmnqrstuvwxz';
                return $letters{mt_rand(0, strlen($letters) - 1)} . $letters{mt_rand(0,
                        strlen($letters) - 1)} . str_pad(mt_rand(1, 9999), 4, 0);
            };

            if (!empty($_POST['register'])) {

                if (!$with_register) return ['error' => 'Регистрация для этой базы закрыта'];

                Sql::transactionStart();
                Auth::ServiceUserStart();
                $pass = $getNewPass();
                $_POST['pass'] = trim($_POST['pass']);
                if (!empty($_POST['pass'])) {
                    $pass = $_POST['pass'];
                }

                try {
                    $User = tableTypes::getTableByName('users')->actionInsert(['email' => strtolower($_POST['login']), 'pass' => $pass, 'login' => "", 'fio' => strtolower($_POST['login']), 'is_outer' => true, 'roles' => UserV::getSetting('h_default_web_roles')]);
                    Auth::webInterfaceRemoveAuth();
                    $SendLetter(strtolower($_POST['login']), '', $pass);
                    Sql::transactionCommit();


                    return ['error' => 'Письмо с новым паролем отправлено на указанный Email. Проверьте ваш ящик через пару минут.'];
                } catch (\Exception $e) {
                    Auth::webInterfaceRemoveAuth();
                    return ['error' => 'Письмо не отправлено: ' . $e->getMessage() . '; регистрация не удалась'];
                }


            }

            if (empty($_POST['login'])) return ['error' => 'Заполните поле Логин/Email'];


            $userRow = null;

            $getUserRow = function () use (&$userRow) {

                if (!is_null($userRow)) return $userRow;

                /*блокируем возможность авторизоваться под сервисными логинами*/
                if ($_POST['login'] == 'cron' || $_POST['login'] == 'service') $userRow = false;
                else if (strpos($_POST['login'], '@') !== false) {
                    $userRow = User::init()->get(['email' => strtolower($_POST['login']), 'is_del' => false, 'interface' => 'web']);
                } else {
                    $userRow = User::init()->get(['login' => $_POST['login'], 'is_del' => false, 'interface' => 'web']);

                    if (!$userRow) $userRow = false;
                }
                return $userRow;
            };


            /*Защита от подбора пароля*/
            if (empty($_POST['recover']) && $ip = ($_SERVER['REMOTE_ADDR'] ?? null)) {

                if ($authLogTableRow = Table::getTableRowByName('auth_log')) {
                    $now_date = date_create();
                    $settings = json_decode($authLogTableRow['header'], true);


                    if (($vremya_blokirovki = $settings['h_time']['v']) && ($error_count = $settings['error_count']['v'])) {
                        $BlockDate = date_create()->modify('-' . $vremya_blokirovki . 'minutes');
                        $block_date = $BlockDate->format('Y-m-d H:i');


                        if ($blocked = Model::init('auth_log')->get(['user_ip' => $ip, 'datetime->>\'v\'>=\'' . $block_date . '\'', 'status' => 2])) {
                            return ['error' => 'В связи с превышением количества попыток на ввод пароля ваш IP заблокирован'];
                        } else {
                            if (($userRow = $getUserRow()) && json_decode($userRow['pass'],
                                    true)['v'] == md5($_POST['pass'])) {
                                $status = 0;

                            } else {
                                $count = Model::init('auth_log')->getField('count(*) as cnt',
                                    ['user_ip' => $ip, 'status' => 1, 'datetime->>\'v\'>=\'' . $block_date . '\'']);
                                $count++;
                                if ($count >= $error_count) {
                                    $status = 2;
                                } else {
                                    $status = 1;
                                }
                            }
                            Sql::insert('auth_log',
                                [
                                    'datetime' => json_encode(['v' => $now_date->format('Y-m-d H:i')])
                                    , 'user_ip' => json_encode(['v' => $ip])
                                    , 'login' => json_encode(['v' => $_POST['login']])
                                    , 'status' => json_encode(['v' => strval($status)])
                                ]
                                ,
                                false);

                        }
                    }
                }
            }


            $userRow = $getUserRow();


            if (!empty($_POST['recover'])) {
                if (!$with_pass_recover) return ['error' => 'Восстановление пароля через  email для данной базы отключено. Обратитесь к админинстратору решения.'];

                if ($userRow) {
                    $email = json_decode($userRow['email'], true)['v'];
                    if (empty($email)) {
                        return ['error' => 'Email для этого login не задан'];
                    }
                    Auth::ServiceUserStart();
                    $pass = $getNewPass();

                    try {
                        $User = tableTypes::getTableByName('users')->actionSet(['pass' => $pass],
                            [['field' => 'id', 'operator' => '=', 'value' => $userRow['id']]]);
                        Auth::webInterfaceRemoveAuth();

                        $login = json_decode($userRow['login'], true)['v'];
                        $email = json_decode($userRow['email'], true)['v'];
                        $SendLetter($email, $login, $pass);

                        return ['error' => 'Письмо с новым паролем отправлено на ваш Email. Проверьте ваш ящик через пару минут.'];
                    } catch (\Exception $e) {
                        Auth::webInterfaceRemoveAuth();

                        return ['error' => 'Письмо не отправлено' . $e->getMessage()];
                    }

                } else {
                    return ['error' => 'Пользователь с указанным Логин/Email не найден'];
                }
            }

            if ($userRow) {
                if (json_decode($userRow['pass'], true)['v'] === md5($_POST['pass'])) {
                    Auth::webInterfaceSetAuth($userRow['id']);

                    $this->location();
                    die;
                } else return ['error' => 'Пароль не верен'];
            } else {
                return ['error' => 'Пользователь не найден'];
            }
        }
    }

    function actionLogout()
    {
        if (Auth::isAuthorized()) {
            Auth::webInterfaceRemoveAuth();
        }

        $this->location();
        die;
    }

}