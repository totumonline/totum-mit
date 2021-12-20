<?php


namespace totum\models;

use totum\common\Model;

class TmpTables extends Model
{
    public const SERVICE_TABLES = [
        'insert_row' => '_insert_row',
        'linktodatajson' => '_linktodatajson',
        'linktoedit' => '_linktoedit',
    ];
    protected bool $isServiceTable = true;

    public function saveByHash($table_name, $User, $hash, $data, $isNewHash = false): bool
    {
        $key = ['table_name' => $table_name, 'user_id' => $User->getId(), 'hash' => $hash];
        if ($isNewHash && $this->getField('user_id', $key)) {
            return false;
        }

        if ($isNewHash) {
            $vars = array_merge(
                ['tbl' => json_encode($data, JSON_UNESCAPED_UNICODE), 'touched' => date('Y-m-d H:i')],
                $key
            );
            $this->insertPrepared(
                $vars,
                false
            );
        } else {
            $this->updatePrepared(
                false,
                ['tbl' => json_encode($data, JSON_UNESCAPED_UNICODE), 'touched' => date('Y-m-d H:i')],
                $key
            );
        }
        return true;
    }

    public function getNewHash(string $table_name, $User, array|string $data, $prefix = 't'): string
    {
        do {
            $hash = $prefix . '-' . md5(microtime(true) . rand());
        } while (!$this->saveByHash(
            $table_name,
            $User,
            $hash,
            $data,
            true
        ));
        return $hash;
    }

    public function deleteByHash($table_name, $User, $hash)
    {
        $this->executePreparedSimple(
            true,
            'delete from ' . $this->table . ' where table_name=? AND user_id=? AND hash=?',
            [
                $table_name, $User->getId(), $hash
            ]
        );
    }

    public function getByHash($table_name, $User, $hash, $json_decode = true)
    {
        $smtp = $this->executePreparedSimple(
            true,
            'UPDATE ' . $this->table . ' set touched=? where table_name=? AND user_id=? AND hash=? RETURNING tbl',
            [
                date('Y-m-d H:i'), $table_name, $User->getId(), $hash
            ]
        );
        if ($json_decode) {
            return json_decode($smtp->fetchColumn(), true);
        }
        return $smtp->fetchColumn();
    }
}
