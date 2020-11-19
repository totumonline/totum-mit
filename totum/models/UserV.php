<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 07.10.16
 * Time: 15:42
 */

namespace totum\models;

use totum\common\Model;

class UserV extends Model
{
    protected $isServiceTable = true;
    protected $users;

    public function getFio($id)
    {
        if (empty($this->users)) {
            $this->users = $this->getFieldIndexedById('fio', ['is_del' => false]);
        }
        return $this->users[$id] ?? 'Не найден';
    }
}
