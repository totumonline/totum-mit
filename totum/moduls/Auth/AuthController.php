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
use totum\common\Totum;

class AuthController extends interfaceController
{
    public function actionLogin(ServerRequestInterface $request)
    {
        $post = $request->getParsedBody();

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

            if (empty($post['recover'])) {
                if (empty($post['pass'])) {
                    return ['error' => 'Заполните поле Пароль'];
                }
                switch (Auth::passwordCheckingAndProtection($post['login'], $post['pass'], $userRow, $this->Config, 'web')) {
                    case Auth::$AuthStatuses['OK']:
                        Auth::webInterfaceSetAuth($userRow['id']);
                        $this->location($_GET['from'] ?? null, !key_exists('from', $_GET));
                        break;
                    case Auth::$AuthStatuses['WRONG_PASSWORD']:
                        return ['error' => 'Пароль не верен'];
                    case Auth::$AuthStatuses['BLOCKED_BY_CRACKING_PROTECTION']:
                        return ['error' => 'В связи с превышением количества попыток на ввод пароля ваш IP заблокирован'];
                }
            } else {
                if (!$Totum->getConfig()->getSettings('with_pass_recover')) {
                    return ['error' => 'Восстановление пароля через  email для данной базы отключено. Обратитесь к админинстратору решения.'];
                }

                if ($userRow = $userRow ?? Auth::getUserRowWithServiceRestriction($post['login'], $this->Config)) {
                    $email = $userRow['email'];
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

                        $login = $userRow['login'];
                        $email = $userRow['email'];
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
        }
    }

    public function doIt(ServerRequestInterface $request, bool $output)
    {
        $requestUri = preg_replace('/\?.*/', '', $request->getUri()->getPath());
        $requestAction = substr($requestUri, strlen($this->modulePath));
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
