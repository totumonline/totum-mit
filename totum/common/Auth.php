<?php

namespace totum\common;

use totum\config\Conf;

class Auth
{
    public static function checkUserPass($string, $hash)
    {
        return $hash === md5($string);
    }

    public static function loadAuthUserByLogin(Conf $Config, $userLogin, $UpdateActive)
    {
        $where = ['login' => $userLogin, 'is_del' => false, 'on_off' => "true"];
        if ($userRow = static::getUserWhere($Config, $where, $UpdateActive)) {
            return new User($userRow, $Config);
        }
    }

    public static function loadAuthUser(Conf $Config, $userId, $UpdateActive)
    {
        $where = ['id' => $userId, 'is_del' => false, 'on_off' => "true"];

        if ($userRow = static::getUserWhere($Config, $where, $UpdateActive)) {
            return new User($userRow, $Config);
        }

        return null;
    }

    public static function webInterfaceSessionStart($Config, $close = true)
    {
        $User = null;
        $Config->setSessionCookieParams();
        session_start();
        if (!empty($_SESSION['userId'])) {
            if (!($User = static::loadAuthUser($Config, $_SESSION['userId'], true))) {
                $_SESSION = [];
            } else {
                Crypt::setKeySess();
            }
        }
        if ($close) {
            session_write_close();
        }
        return $User;
    }


    protected static function getUserWhere(Conf $Config, $where, $UpdateActive = true)
    {
        $r = $Config->getModel('users')->getPrepared($where, '*');
        if ($UpdateActive) {
            $dt = ['activ_datetime' => json_encode(['v' => date('Y-m-d H:i:s')], JSON_UNESCAPED_UNICODE)];
            $Config->getModel('users')->update($dt, ['id' => $r['id']]);
        }
        $r = Model::getClearValuesWithExtract($r);
        return $r;
    }

    public static function serviceUserStart(Conf $Config)
    {
        if ($userRow = static::getUserWhere($Config, ['login' => 'service'])) {
            return new User($Config, $userRow);
        } else {
            die('Пользователь service не настроен. Обратитесь к администратору системы');
        }
    }

    public static function authUserWithPass(Conf $Config, $login, $pass, $interface = 'web')
    {
        $where = ['on_off' => "true", 'login' => $login, 'pass' => md5($pass), 'interface' => $interface, 'is_del' => false];
        if ($userRow = static::getUserWhere($Config, $where)) {
            return new User($userRow, $Config);
        }
    }

    public static function getUserById(Conf $Config, $userId)
    {
        $where = ['id' => $userId, 'is_del' => false, 'on_off' => "true"];
        if ($userRow = static::getUserWhere($Config, $where)) {
            return new User($userRow, $Config);
        }
    }

    public static function simpleAuth(Conf $Config, $userId)
    {
        $where = ['id' => $userId, 'is_del' => false, 'on_off' => "true"];
        if ($userRow = static::getUserWhere($Config, $where)) {
            return new User($userRow, $Config);
        }
    }

    public static function webInterfaceSetAuth($userId)
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION['userId'] = $userId;
    }

    public static function webInterfaceRemoveAuth()
    {
        session_start();
        session_destroy();
    }

    public static function reUserFromCreator($Config, $id, $creatorId)
    {
        $Config->setSessionCookieParams();
        session_start();
        if (empty($_SESSION['CretorId'])) {
            $_SESSION['CretorId'] = $creatorId;
        }
        $_SESSION['userId'] = $id;
        session_write_close();
    }

    public static function isCreatorOnShadow()
    {
        return !empty($_SESSION['CretorId']);
    }

    public static function isCreatorNotItself()
    {
        return !empty($_SESSION['CretorId']) && $_SESSION['CretorId'] != $_SESSION['userId'];
    }
}
