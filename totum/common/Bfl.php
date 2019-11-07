<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 2018-12-05
 * Time: 11:26
 */

namespace totum\common;


use totum\config\Conf;

class Bfl
{
    protected static $PDO;

    static function add($category, $type, $data)
    {
        try {
            static::__connection()->exec('insert into _bfl (uid, cat, type, data) values ( '
                . (Auth::getUserId() ?? 'null') . ','
                . Sql::quote($category) . ', '
                . Sql::quote($type) . ', '
                . Sql::quote(json_encode($data, JSON_UNESCAPED_UNICODE))
                . ')');
            if (($error = static::__connection()->errorInfo()) && $error[2]) {
                throw new SqlExeption($error[2]);
            }
        } catch (SqlExeption $exception) {

            static::__connection()->exec('CREATE TABLE _bfl(
  dt timestamp NOT NULL default NOW()::timestamp,
  uid bigint,
  cat text,
  type text,
  data jsonb
)');
            if (($error = static::__connection()->errorInfo()) && $error[2]) {
                Mail::send(Conf::adminEmail,
                    'Ошибка bfl',
                    'Схема ' . Conf::getSchema() . '-> ' . $exception->getMessage() . ' ' . $error[2] . '
                     Данные не записаны: ' . json_encode([$category, $type, $data],
                        JSON_UNESCAPED_UNICODE) . '',
                    '',
                    '',
                    true);
            } else {
                static::add($category, $type, $data);
            }
        }
    }
    static function get($category, $type, $date_from='', $date_to='')
    {
        try {
            $r=Sql::getAll('select dt date, data from _bfl where cat='.Sql::quote($category).' AND type='.Sql::quote($type).' AND '.($dates?'()':'true'));
            return $r;

        }catch (\Exception $e){
            return [];
        }
    }


    protected static function __connection(): \PDO
    {
        static::$PDO = static::$PDO ?? Sql::___getNewConnection();
        return static::$PDO;
    }
}