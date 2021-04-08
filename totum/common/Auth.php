<?php

namespace totum\common;

use totum\config\Conf;

class Auth
{
    public static $shadowRoles = [1, -2];
    public static $userManageRoles = [-1];
    public static $userManageTables=['users', 'auth_log', 'ttm__user_log', 'ttm__users_online'];

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

    public static function isCanBeOnShadow(?User $User): bool
    {
        return key_exists('ShadowRole', $_SESSION) || count(array_intersect(static::$shadowRoles, $User->getRoles())) > 0;
    }

    public static function getShadowRole(?User $User): int
    {
        if (key_exists('ShadowRole', $_SESSION)) {
            return $_SESSION['ShadowRole'] ?? 0;
        }
        return array_intersect(static::$shadowRoles, $User->getRoles())[0] ?? 0;
    }

    public static function getUsersForShadow(Conf $Config, User $User, $id = null): array
    {
        $_id = $id ? "id = ".(int)$id : 'true';
        $_roles = 'true';
        if (Auth::getShadowRole($User) !== 1) {
            $_roles = "(roles->'v' @> '[\"1\"]'::jsonb) = FALSE";
        }
        $r = $Config->getModel('users')->preparedSimple("select id, fio->>'v' as fio from users where interface->>'v'='web'" .
            " AND on_off->>'v'='true' AND login->>'v' NOT IN ('service', 'cron', 'anonim') " .
            " AND $_id AND $_roles"
        );
        $r->execute();
        $r = $r->fetchAll(\PDO::FETCH_ASSOC);
        return $r;
    }

    public static function getUserManageTables(Conf $Config)
    {
        return $Config->getModel('tables')->getAll(['name'=>Auth::$userManageTables], 'name, title', 'sort');
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
            return new User($userRow, $Config);
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

    public static function asUser($Config, $id, User $User)
    {
        $Config->setSessionCookieParams();
        session_start();
        if (empty($_SESSION['ShadowUserId']) || empty($_SESSION['ShadowRole'])) {
            $_SESSION['ShadowUserId'] = $User->getId();
            $_SESSION['ShadowRole'] = array_values(array_intersect(static::$shadowRoles, $User->getRoles()))[0];
        }
        $_SESSION['userId'] = $id;
        session_write_close();
    }

    public static function isUserOnShadow(): bool
    {
        return !empty($_SESSION['ShadowUserId']);
    }

    public static function isUserNotItself(): bool
    {
        return !empty($_SESSION['ShadowUserId']) && $_SESSION['ShadowUserId'] != $_SESSION['userId'];
    }
}
