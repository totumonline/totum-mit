<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 07.10.16
 * Time: 15:42
 */

namespace totum\models;

use totum\common\Lang\RU;
use totum\common\Model;

class UserV extends Model
{
    protected bool $isServiceTable = true;
    protected $users;

    public function getFio($id, $oneUser = false)
    {
        if ($oneUser) {
            return $this->getField('fio', ['id' => $id]);
        }

        if (empty($this->users)) {
            $this->users = $this->getFieldIndexedById('fio');
        }
        return $this->users[$id] ?? $this->translate('User not found');
    }

}
