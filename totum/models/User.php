<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 07.10.16
 * Time: 15:42
 */

namespace totum\models;


use totum\common\errorException;
use totum\common\Model;
use totum\common\Sql;
use totum\common\TextErrorException;

class User extends Model
{
    function insert($vars, $returning = 'idFieldName', $ignore = false)
    {
        $id = parent::insert($vars);
        $this->checkAndSaveConnectedUsers($id);
        return $id;
    }

    function checkAndSaveConnectedUsers($id, $userRow = null)
    {
        if (!is_null($userRow) || $userRow = UserV::init()->getById($id)) {
            Sql::exec('update ' . $this->table . ' set all_connected_users=\'' . json_encode(['v' => $this->getAllConnectedUsers($id,
                    $userRow)]) . '\' where ' . $this->getWhere(['id' => $id]));
            if ($userRow['boss_id']) {
                $this->checkAndSaveConnectedUsers($userRow['boss_id']);
            }
        }

    }

    function update($upParams, $where, $ignore = 0, $oldRow = null): Int
    {
        $params = [];
        foreach ($upParams as $key => $param) {
            if ($decode = json_decode($param, true)) {
                $params[$key] = $decode['v'];
            } else $params[$key] = $param;
        }

        if (array_key_exists('boss_id', $params)) {
            if ($params['boss_id'] && !$this->checkCanBeBoss($where['id'], $params['boss_id'])) {
                throw new errorException('Нельзя сделать начальником того, кто есть в подчиненных');
            }
            if (key_exists('boss_id', $oldRow)) {
                $oldBoss = $oldRow['boss_id']['v'];
            } else {
                $oldBoss = UserV::init()->getFieldById('boss_id', $where['id']);
            }

        }


        $result = parent::update($upParams, $where, $ignore);

        if (array_key_exists('add_users', $params)) {
            foreach ($params['add_users'] as $addId) {
                if (!$this->checkCanBeBoss($addId, $where['id'])) {
                    throw new errorException('Нельзя добавить в доступы начальника');
                }
            }
            $this->checkAndSaveConnectedUsers($where['id']);
        }

        if (array_key_exists('boss_id', $params)) {
            if (!empty($oldBoss)){
                $this->checkAndSaveConnectedUsers($oldBoss);
            }
            $this->checkAndSaveConnectedUsers($params['boss_id']);
        }
        return $result;
    }

    protected function checkCanBeBoss($id, $bossId)
    {
        if ($id == $bossId) ;
        elseif (Sql::get('with RECURSIVE subUsers AS
(
    select id, boss_id
    from users__v
    where boss_id=' . $id . '

    UNION
      SELECT users__v.id, users__v.boss_id from users__v
      join subUsers s ON s.id=users__v.boss_id
)
select * from subUsers WHERE ' . $this->getWhere(['id' => $bossId]))
        ) ;
        else return true;
        return false;

    }

    function getAllConnectedUsers($id, $userRow = null, &$checkedUsers = [])
    {
        $connectedUsers = [$id];
        $checkedUsers[] = (int)$id;
        if (is_null($userRow))
            $userRow = UserV::init()->getById($id);

        if ($userRow['add_users']) {
            $connectedUsers = array_merge($connectedUsers, json_decode($userRow['add_users'], true));
        }
        $connectedUsers = array_merge($connectedUsers, UserV::init()->getField('id', ['boss_id' => $id], null, null));

        foreach ($connectedUsers as $userId) {
            if (!in_array($userId, $checkedUsers)) {
                $this->getAllConnectedUsers($userId, null, $checkedUsers);
            }
        }
        return $checkedUsers;
    }

}