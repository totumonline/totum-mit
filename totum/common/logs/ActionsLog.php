<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 05.12.17
 * Time: 13:08
 */

namespace totum\common\logs;

use totum\common\Model;
use totum\common\Totum;

class ActionsLog
{
    protected const TABLE_NAME = '_log';
    /**
     * @var Totum
     */
    protected $Totum;
    /**
     * @var Model
     */
    private $model;

    public function __construct(Totum $Totum)
    {
        $this->model = $Totum->getModel(static::TABLE_NAME, true);
        $this->Totum = $Totum;
    }

    function add($tableid, $cycleid, $rowid, array $fields)
    {
        $model = static::getModel();
        foreach ($fields as $k => $v) {

            $model->insertPrepared([
                'tableid' => $tableid,
                'cycleid' => $cycleid ?? 0,
                'rowid' => $rowid ?? 0,
                'field' => $k,
                'v' => static::getVar($v[0]),
                'modify_text' => static::getVar($v[1]),
                'action' => 1,
                'userid' => $this->Totum->getUser()->getId()
            ],
                false);
        }
    }

    function innerLog($tableid, $cycleid, $rowid, $fieldName, $fieldComment, $fieldValue)
    {
        $model = static::getModel();
        $model->insertPrepared([
            'tableid' => $tableid,
            'cycleid' => $cycleid ?? 0,
            'rowid' => $rowid ?? 0,
            'field' => $fieldName,
            'v' => static::getVar($fieldValue),
            'modify_text' => $fieldComment,
            'action' => 6,
            'userid' => $this->Totum->getUser()->getId()
        ],
            false);
    }

    public function modify($tableid, $cycleid, $rowid, array $fields)
    {
        $model = static::getModel();
        foreach ($fields as $k => $v) {
            $model->insertPrepared([
                'tableid' => $tableid,
                'cycleid' => $cycleid ?? 0,
                'rowid' => $rowid ?? 0,
                'field' => $k,
                'v' => static::getVar($v[0]),
                'modify_text' => static::getVar($v[1]),
                'action' => 2,
                'userid' => $this->Totum->getUser()->getId()
            ],
                false);
        }
    }

    function clear($tableid, $cycleid, $rowid, array $fields)
    {
        $model = static::getModel();
        foreach ($fields as $k => $v) {
            $model->insertPrepared([
                'tableid' => $tableid,
                'cycleid' => $cycleid ?? 0,
                'rowid' => $rowid ?? 0,
                'field' => $k,
                'v' => static::getVar($v[0]),
                'modify_text' => static::getVar($v[1]),
                'action' => 3,
                'userid' => $this->Totum->getUser()->getId()
            ],
                false);
        }
    }

    function pin($tableid, $cycleid, $rowid, array $fields)
    {

        $model = static::getModel();
        foreach ($fields as $k => $v) {
            $model->insertPrepared([
                'tableid' => $tableid,
                'cycleid' => $cycleid ?? 0,
                'rowid' => $rowid ?? 0,
                'field' => $k,
                'v' => static::getVar($v[0]),
                'modify_text' => static::getVar($v[1]),
                'action' => 5,
                'userid' => $this->Totum->getUser()->getId()
            ],
                false);
        }
    }

    function delete($tableid, $cycleid, $rowid)
    {
        $model = static::getModel();
        $model->insertPrepared([
            'tableid' => $tableid,
            'cycleid' => $cycleid ?? 0,
            'rowid' => $rowid ?? 0,
            'action' => 4,
            'userid' => $this->Totum->getUser()->getId()
        ],
            false);
    }

    public function getLogs($tableid, $cycleid, $rowid, $field)
    {
        if (empty($cycleid)) $cycleid = null;

        return $this->getModel()->getAll([
            'tableid' => $tableid,
            'cycleid' => $cycleid ?? 0,
            'rowid' => $rowid ?? 0,
            'field' => $field
        ],
            'v as value, modify_text, action, userid as user_modify, to_char(dt, \'DD.MM.YY HH24:MI\') as dt_modify',
            'dt');
    }

    protected function getVar($v)
    {
        if (is_array($v)) {
            $s = json_encode($v, JSON_UNESCAPED_UNICODE);
            return $s;
        } elseif (is_bool($v)) {
            return $v ? 'true' : 'false';
        }
        return $v;
    }

    protected function getModel()
    {
        return $this->model;
    }

}