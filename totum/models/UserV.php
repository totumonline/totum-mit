<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 07.10.16
 * Time: 15:42
 */

namespace totum\models;


use totum\common\Model;
use totum\common\Sql;
use totum\common\TextErrorException;

class UserV extends Model
{
    protected $isServiceTable = true;
    static protected $settings;
    static protected $users;

    static function clear(){
        static::$settings=null;
        static::$users=null;
    }
    static function getSetting($name){
        if (empty(static::$settings)){
            static::$settings = json_decode(Table::getTableRowByName('users')['header'], true);
        }
        return static::$settings[$name]['v'];
    }

    public function getFio($id)
    {
        if (empty(static::$users)){
            static::$users = $this->getFieldIndexedById('fio', ['is_del'=>false]);
        }
        return static::$users[$id]??'Не найден';
    }
}