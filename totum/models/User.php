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
use totum\common\sql\Sql;
use totum\models\traits\WithTotumTrait;

class User extends Model
{
    use WithTotumTrait;

    public function insertPrepared($vars, $returning = 'idFieldName', $ignore = false, $cacheIt = true)
    {
        $id = parent::insertPrepared($vars, $returning, $ignore, $cacheIt);
        $this->saveConnectedUsers($id);
        return $id;
    }

    protected function saveConnectedUsers($id, $userRow = null)
    {
        if (!is_null($userRow) || $userRow = $this->Totum->getNamedModel(UserV::class)->getById($id)) {
            $this->executePreparedSimple(
                true,
                'update ' . $this->table . ' set all_connected_users=? where id=?',
                [json_encode(['v' => $this->getAllConnectedUsers(
                    $id,
                    $userRow
                )]), $id]
            );

            if ($userRow['boss_id']) {
                $this->saveConnectedUsers($userRow['boss_id']);
            }
        }
    }

    public function update($params, $where, $oldRow = null): int
    {
        $decoded=[];
        foreach ($params as $key => $param) {
            if ($decode = json_decode($param, true)) {
                $decoded[$key] = $decode['v'];
            } else {
                $decoded[$key] = $param;
            }
        }

        if (array_key_exists('boss_id', $decoded)) {
            if ($decoded['boss_id'] && !$this->checkCanBeBoss($where['id'], $decoded['boss_id'])) {
                throw new errorException('Нельзя сделать начальником того, кто есть в подчиненных');
            }
            if (key_exists('boss_id', $oldRow)) {
                $oldBoss = $oldRow['boss_id']['v'];
            } else {
                $oldBoss = $this->Totum->getNamedModel(UserV::class)->getField('boss_id', ['id' => $where['id']]);
            }
        }


        $result = parent::update($params, $where);

        if (array_key_exists('add_users', $decoded)) {
            foreach ($decoded['add_users'] as $addId) {
                if (!$this->checkCanBeBoss($addId, $where['id'])) {
                    throw new errorException('Нельзя добавить в доступы начальника');
                }
            }
            $this->saveConnectedUsers($where['id']);
        }

        if (array_key_exists('boss_id', $decoded)) {
            if (!empty($oldBoss)) {
                $this->saveConnectedUsers($oldBoss);
            }
            $this->saveConnectedUsers($decoded['boss_id']);
        }
        return $result;
    }

    protected function checkCanBeBoss($id, $bossId)
    {
        if ($id != $bossId && !$this->Sql->get('with RECURSIVE subUsers AS
(
    select id, boss_id
    from users__v
    where boss_id=' . $id . '

    UNION
      SELECT users__v.id, users__v.boss_id from users__v
      join subUsers s ON s.id=users__v.boss_id
)
select * from subUsers WHERE ' . $this->getWhere(['id' => $bossId]))
        ) {
            return true;
        }

        return false;
    }

    public function getAllConnectedUsers($id, $userRow = null, &$checkedUsers = [])
    {
        $connectedUsers = [$id];
        $checkedUsers[] = (int)$id;
        if (is_null($userRow)) {
            $userRow = $this->Totum->getNamedModel(UserV::class)->getById($id);
        }

        if ($userRow['add_users']) {
            $connectedUsers = array_merge($connectedUsers, json_decode($userRow['add_users'], true));
        }
        $connectedUsers = array_merge(
            $connectedUsers,
            $this->Totum->getNamedModel(UserV::class)->getField('id', ['boss_id' => $id], null, null)
        );

        foreach ($connectedUsers as $userId) {
            if (!in_array($userId, $checkedUsers)) {
                $this->getAllConnectedUsers($userId, null, $checkedUsers);
            }
        }
        return $checkedUsers;
    }
}
