<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 05.12.17
 * Time: 13:08
 */

namespace totum\common;


class aLog
{
    const table = '_log';

    static function add($tableid, $cycleid, $rowid, array $fields)
    {
        $model = static::getModel();
        foreach ($fields as $k => $v) {

            $model->insert([
                'tableid' => $tableid,
                'cycleid' => $cycleid??0,
                'rowid' => $rowid??0,
                'field' => $k,
                'v' => static::getVar($v[0]),
                'modify_text' => static::getVar($v[1]),
                'action' => 1,
                'userid' => Auth::getUserId()
            ],
                false);
        }
    }

    static function innerLog($tableid, $cycleid, $rowid, $fieldName, $fieldComment, $fieldValue){
        $model = static::getModel();
        $model->insert([
            'tableid' => $tableid,
            'cycleid' => $cycleid??0,
            'rowid' => $rowid??0,
            'field' => $fieldName,
            'v' => static::getVar($fieldValue),
            'modify_text' => $fieldComment,
            'action' => 6,
            'userid' => Auth::getUserId()
        ],
            false);
    }

    static function modify($tableid, $cycleid, $rowid, array $fields)
    {
        $model = static::getModel();
        foreach ($fields as $k => $v) {
            $model->insert([
                'tableid' => $tableid,
                'cycleid' => $cycleid??0,
                'rowid' => $rowid??0,
                'field' => $k,
                'v' => static::getVar($v[0]),
                'modify_text' => static::getVar($v[1]),
                'action' => 2,
                'userid' => Auth::getUserId()
            ],
                false);
        }
    }

    static function clear($tableid, $cycleid, $rowid, array $fields)
    {
        $model = static::getModel();
        foreach ($fields as $k => $v) {
            $model->insert([
                'tableid' => $tableid,
                'cycleid' => $cycleid??0,
                'rowid' => $rowid??0,
                'field' => $k,
                'v' => static::getVar($v[0]),
                'modify_text' => static::getVar($v[1]),
                'action' => 3,
                'userid' => Auth::getUserId()
            ],
                false);
        }
    }
    static function pin($tableid, $cycleid, $rowid, array $fields)
    {

        $model = static::getModel();
        foreach ($fields as $k => $v) {
            $model->insert([
                'tableid' => $tableid,
                'cycleid' => $cycleid??0,
                'rowid' => $rowid??0,
                'field' => $k,
                'v' => static::getVar($v[0]),
                'modify_text' => static::getVar($v[1]),
                'action' => 5,
                'userid' => Auth::getUserId()
            ],
                false);
        }
    }

    static function delete($tableid, $cycleid, $rowid)
    {
        $model = static::getModel();
        $model->insert([
            'tableid' => $tableid,
            'cycleid' => $cycleid??0,
            'rowid' => $rowid??0,
            'action' => 4,
            'userid' => Auth::getUserId()
        ],
            false);
    }

    public static function getLogs($tableid, $cycleid, $rowid, $field)
    {
        if (empty($cycleid)) $cycleid = null;

        return static::getModel()->getAll([
            'tableid' => $tableid,
            'cycleid' => $cycleid??0,
            'rowid' => $rowid??0,
            'field' => $field
        ],
            'v as value, modify_text, action, userid as user_modify, to_char(dt, \'DD.MM.YY HH24:MI\') as dt_modify',
            'dt');
    }

    protected static function getVar($v)
    {
        if (is_array($v)) {
            $s= json_encode($v, JSON_UNESCAPED_UNICODE);
                // $s = base64_encode(gzencode ( $s));
            return $s;
        } else if (is_bool($v)) {
            return $v ? 'true' : 'false';
        }
        return $v;
    }

    protected static function getModel()
    {
        return Model::initService(static::table, null);
    }

}