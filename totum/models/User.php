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
            Sql::exec('update ' . $this->table . ' set all_connected_users=\''.json_encode(['v'=>$this->getAllConnectedUsers($id, $userRow)]).'\' where ' . $this->getWhere(['id'=>$id]));
            if ($userRow['boss_id']) {
                $this->checkAndSaveConnectedUsers($userRow['boss_id']);
            }
        }

    }

    function update($params, $where, $ignore = 0, $oldRow = null): Int
    {

        if (array_key_exists('boss_id', $params)) {
            if ($params['boss_id'] && !$this->checkCanBeBoss($where['id'], $params['boss_id'])) {
                throw new errorException('Нельзя сделать начальником того, кто есть в подчиненных');
            }else{
                $oldBoss=UserV::init()->getFieldById('boss_id', $where['id']);
            }
        }


        $result = parent::update($params, $where, $ignore);

        if (array_key_exists('add_users', $params)) {
            $this->checkAndSaveConnectedUsers($where['id']);
        }

        if (array_key_exists('boss_id', $params)) {
            if (!empty($oldBoss)){
                $this->checkAndSaveConnectedUsers($oldBoss);
            }
            else
                $this->checkAndSaveConnectedUsers(json_decode($params['boss_id'], true)['v']);
        }
        return $result;
    }

    protected function checkCanBeBoss($id, $bossId)
    {
        $bossId=json_decode($bossId, true)['v'];

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
select * from subUsers WHERE ' . $this->getWhere(['id'=>$bossId]))
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

        if ($userRow['add_users']) $connectedUsers = array_merge($connectedUsers, json_decode($userRow['add_users']));
        $connectedUsers = array_merge($connectedUsers, UserV::init()->getField('id', ['boss_id' => $id], null, null));

        foreach ($connectedUsers as $userId) {
            if (!in_array($userId, $checkedUsers)) {
                $this->getAllConnectedUsers($userId, null, $checkedUsers);
            }
        }
        return $checkedUsers;
    }

}