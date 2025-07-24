<?php

namespace totum\common;

use totum\common\configs\ConfParent;
use totum\common\Lang\RU;
use totum\config\Conf;
use totum\fieldTypes\Password;

class Auth
{
    public static $shadowRoles = [1, -2];
    public static $AuthStatuses = [
        'OK' => 0,
        'WRONG_PASSWORD' => 1,
        'BLOCKED_BY_CRACKING_PROTECTION' => 2,
    ];
    public static $userManageRoles = [-1];
    public static $userManageTables = ['users', 'auth_log', 'ttm__users_online'];
    protected static bool $isShadowedCreatorVal;

    public static function checkUserPass(Conf $Config, string $string, string $hash)
    {
        $passData = $Config->getSql()->get("select data->>'v' as data from tables_fields where table_name->>'v' = 'users' and name->>'v' = 'pass' limit 1");

        return Password::checkPassword($Config, $hash, $string, json_decode($passData['data'], true)['cryptoKey'] ?? null);
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

    public static function webInterfaceSessionStart($Config)
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
        session_write_close();
        return $User;
    }

    public static function isCanBeOnShadow(?User $User): bool
    {
        return key_exists('ShadowRole', $_SESSION) || count(array_intersect(
                static::$shadowRoles,
                $User->getRoles()
            )) > 0;
    }

    public static function getShadowRole(?User $User): int
    {
        if (key_exists('ShadowRole', $_SESSION)) {
            return $_SESSION['ShadowRole'] ?? 0;
        }
        return array_intersect(static::$shadowRoles, $User->getRoles())[0] ?? 0;
    }

    public static function getUsersForShadow(Conf $Config, User $User, $id = null, $q = ''): array
    {
        $_id = 'true';
        $_q = 'true';
        $params = [];
        $limitNum = 10;
        $limit = 'ORDER By id limit ' . $limitNum;

        if ($id) {
            $_id = 'id = ' . (int)$id;
            $limit = '';
        } elseif ($q) {
            $qs = preg_split('/\s+/', $q);
            $_q = '';
            foreach ($qs as $qEl) {
                if ($_q) {
                    $_q .= ' AND ';
                }
                if (str_contains($qEl, '@')) {
                    $_q .= "lower(email->>'v') like ?";
                    $params[] = '%' . mb_strtolower($qEl) . '%';
                } else {
                    $_q .= "lower(fio->>'v') like ?";
                    $params[] = '%' . mb_strtolower($qEl) . '%';
                }
            }

        }


        $_roles = 'true';
        if (Auth::getShadowRole($User) !== 1) {
            $_roles = "(roles->'v' @> '[\"1\"]'::jsonb) = FALSE";
        }

        $getQuery = function ($fields) use ($_q, $_roles, $_id) {
            return <<<SQL
select $fields from users where interface->>'v'='web' 
AND on_off->>'v'='true' AND is_del = false AND (login->>'v' NOT IN ('service', 'cron', 'anonim') OR login->>'v' is null)  
AND $_id AND $_roles AND $_q  

SQL;
        };

        if (!$id) {
            $countPrepared = $Config->getModel('users')->preparedSimple($getQuery('count(*)'));
            $countPrepared->execute($params);
            $count = $countPrepared->fetchColumn();
            $r = $Config->getModel('users')->preparedSimple($getQuery("id, fio->>'v' as fio ") . $limit);
            $r->execute($params);

            return [
                'sliced' => $count > $limitNum,
                'users' => $r->fetchAll(\PDO::FETCH_ASSOC)
            ];

        } else {
            $r = $Config->getModel('users')->preparedSimple($getQuery("id, fio->>'v' as fio "));
            $r->execute($params);
            $r = $r->fetchAll(\PDO::FETCH_ASSOC);
            return $r;
        }
    }

    public static function getUserManageTables(Conf $Config, User $User)
    {
        $tables = Auth::$userManageTables;
        if (in_array(-2, $User->getRoles())) {
            $tables[] = 'ttm__user_log';
        }
        return $Config->getModel('tables')->getAll(['name' => $tables], 'name, title', 'sort');
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
            die($Config->getLangObj()->translate('User %s is not configured. Contact your system administrator.',
                'service'));
        }
    }

    public static function getUserRowWithServiceRestriction($login, Conf $Config, $interface = 'web')
    {
        /*block the ability to log in with service logins*/
        if ($login !== 'cron' && $login !== 'service') {
            $where = ['on_off' => true, 'is_del' => false, 'interface' => $interface];

            if (str_contains($login, '@')) {
                $where['email'] = strtolower($login);
            } else {
                if ($interface === 'web') {
                    $whereObject = new \stdClass();
                    $whereObject->whereStr = '';
                    foreach ($where as $k => $v) {
                        $whereObject->params[] = $v;
                        if ($whereObject->whereStr != '') {
                            $whereObject->whereStr .= ' AND ';
                        }
                        if ($k === 'is_del') {
                            $whereObject->whereStr .= ' is_del = ?';
                        } else {
                            $whereObject->whereStr .= ' ' . $k . '->>\'v\' = ?';
                        }
                    }

                    $whereObject->params[] = mb_strtolower($login);
                    $whereObject->whereStr .= ' AND lower(login->>\'v\') = ?';

                    $where = $whereObject;
                } else {
                    $where['login'] = $login;
                }
            }
            if ($UserRow = static::getUserWhere($Config, $where, false)) {
                return $UserRow;
            }
        }
        return false;
    }

    public static function passwordCheckingAndProtection($login, $pass, &$userRow, Conf $Config, $interface): int
    {
        $ip = ($_SERVER['REMOTE_ADDR'] ?? null);
        $now_date = date_create();
        $login = mb_strtolower($login);

        if (($block_time = $Config->getSettings('h_time')) && ($error_count = (int)$Config->getSettings('error_count'))) {
            $BlockDate = date_create()->modify('-' . $block_time . 'minutes');
            $block_date = $BlockDate->format('Y-m-d H:i');
        }

        if ($block_time && $Config->getModel('auth_log')->get(['user_ip' => $ip, 'login' => $login, 'datetime->>\'v\'>=\'' . $block_date . '\'', 'status' => 2])) {
            return static::$AuthStatuses['BLOCKED_BY_CRACKING_PROTECTION'];
        } else {
            if (($userRow = $userRow ?? Auth::getUserRowWithServiceRestriction(
                    $login,
                    $Config,
                    $interface
                )) && static::checkUserPass($Config,
                    $pass,
                    $userRow['pass']
                )) {
                $status = static::$AuthStatuses['OK'];
            } elseif (!$block_time || !$error_count) {
                $status = static::$AuthStatuses['WRONG_PASSWORD'];
            } else {
                $count = static::$AuthStatuses['WRONG_PASSWORD'];
                $statuses = $Config->getModel('auth_log')->getAll(
                    ['user_ip' => $ip, 'login' => $login, 'datetime->>\'v\'>=\'' . $block_date . '\''],
                    'status',
                    'id desc'
                );
                foreach ($statuses as $st) {
                    if ($st["status"] != 1) {
                        break;
                    } else {
                        $count++;
                    }
                }

                if ($count >= $error_count) {
                    $status = static::$AuthStatuses['BLOCKED_BY_CRACKING_PROTECTION'];
                } else {
                    $status = static::$AuthStatuses['WRONG_PASSWORD'];
                }
            }
        }

        $Config->getSql()->insert(
            'auth_log',
            [
                'datetime' => json_encode(['v' => $now_date->format('Y-m-d H:i')])
                , 'user_ip' => json_encode(['v' => $ip])
                , 'login' => json_encode(['v' => $login])
                , 'status' => json_encode(['v' => strval($status)])
            ],
            false
        );
        return $status;
    }

    public static function getUserById(Conf $Config, $userId)
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
        session_write_close();
    }

    public static function webInterfaceRemoveAuth()
    {
        @session_start();
        @session_destroy();
    }

    public static function asUser($Config, $id, User $User)
    {
        $Config->setSessionCookieParams();
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
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

    public static function getShadowedUser($Config): ?User
    {
        if ($_SESSION['ShadowUserId']) {
            return static::loadAuthUser($Config, $_SESSION['ShadowUserId'], false);
        }
        return null;
    }

    public static function isShadowedCreator(Conf $Conf)
    {
        return static::$isShadowedCreatorVal ?? (static::$isShadowedCreatorVal = static::isUserNotItself() && static::getShadowedUser($Conf)?->isCreator());
    }
}
