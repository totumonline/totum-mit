<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 28.12.17
 * Time: 13:49
 */

namespace totum\tableTypes;

use totum\common\Auth;
use totum\common\Controller;
use totum\common\Cycle;
use totum\common\errorException;
use totum\common\Model;
use totum\common\Totum;
use totum\common\User;

class tmpTable extends JsonTables
{
    protected $sessHashName;
    protected $model;
    /**
     * @var bool
     */


    protected static $tmpTables = [];
    /**
     * @var array
     */
    private $key;

    protected static function getKey($tableName, $hash, User $User)
    {
        return ['table_name' => $tableName, 'user_id' => $User->getId(), 'hash' => $hash];
    }

    public function __construct(Totum $Totum, $tableRow, Cycle $Cycle, $light = false, $hash = null)
    {
        $this->model = $Totum->getModel('_tmp_tables', true);
        $this->Totum = $Totum;
        $this->User = $Totum->getUser();
        if (is_null($hash)) {
            do {
                $hash = md5(microtime(true) . '_' . $tableRow['name'] . '_' . mt_srand());
                $this->key = static::getKey($tableRow['name'], $hash, $Totum->getUser());
            } while ($this->model->getField('user_id', $this->key));

            $this->savedTbl = $this->tbl = $this->getNewTblForRecalc();
            $this->isTableAdding = true;
            $this->sessHashName = $hash;
            $this->updated = $this->getUpdated();

            $tbl = json_encode($this->tbl, JSON_UNESCAPED_UNICODE);

            $this->model->insertPrepared(
                array_merge(
                    ['tbl' => $tbl, 'updated' => $this->updated, 'touched' => date('Y-m-d H:i')],
                    $this->key
                ),
                false
            );
        } else {
            $this->key = ['table_name' => $tableRow['name'], 'user_id' => $Totum->getUser()->getId(), 'hash' => $hash];
            $this->loadDataRow(true);
        }
        $this->sessHashName = $hash;

        parent::__construct($Totum, $tableRow, $Cycle, $light);
        static::$tmpTables[$tableRow['id'] . '_' . $hash] = $this;
    }


    public static function init(Totum $Totum, $tableRow, $extraData = null, $light = false, $hash = null)
    {
        if (!is_null($hash) && key_exists($hashName = $tableRow['id'] . '_' . $hash, static::$tmpTables)) {
            return static::$tmpTables[$hashName];
        }
        return new static($Totum, $tableRow, $extraData, $light, $hash);
    }

    public function createTable()
    {
    }

    public function isTblUpdated($level = 0, $force = false)
    {
        $savedTbl = $this->savedTbl;
        $isOnSave = false;
        if (!$this->isOnSaving) {
            $isOnSave = true;
        }

        if ($isOnSave && $this->isTableDataChanged) {
            $this->updated = $this->getUpdatedJson();
            $this->saveTable();

            foreach ($this->tbl['rows'] as $id => $row) {
                $oldRow = ($savedTbl['rows'][$id] ?? []);
                if ($oldRow && (!empty($row['is_del']) && empty($oldRow['is_del']))) {
                    $this->changeIds['deleted'][$id] = null;
                } elseif (!empty($oldRow) && empty($row['is_del'])) {
                    if ($oldRow !== $row) {
                        foreach ($row as $k => $v) {
                            if (($oldRow[$k] ?? null) !== $v) {//Здесь проставляется changed для web (только ли это в web нужно?)
                                $this->changeIds['changed'][$id] = $this->changeIds['changed'][$id] ?? [];
                                $this->changeIds['changed'][$id][$k] = null;
                            }
                        }
                    }
                }
            }
            $this->changeIds['deleted'] = $this->changeIds['deleted'] + array_flip(array_keys(array_diff_key(
                    $savedTbl['rows'] ?? [],
                    $this->tbl['rows'] ?? []
                )));
            $this->changeIds['added'] = array_flip(array_keys(array_diff_key(
                $this->tbl['rows'],
                $savedTbl['rows']
            )));
            $this->isOnSaving = false;

            return true;
        } else {
            return false;
        }
    }

    public function checkAndModify($tableData, array $data)
    {
        $this->loadDataRow();
        return parent::checkAndModify($tableData, $data);
    }

    public function saveTable()
    {
        if ($this->key) {
            $this->model->update(
                ['touched' => date('Y-m-d H:i'), 'tbl' => json_encode(
                    $this->tbl,
                    JSON_UNESCAPED_UNICODE
                ), 'updated' => $this->updated],
                $this->key
            );
            $saved = $this->savedTbl;
            $this->savedTbl = $this->tbl;
            $this->savedUpdated = $this->updated;
            $this->setIsTableDataChanged(false);
            $this->onSaveTable($this->tbl, $saved);

            $this->Totum->tableChanged($this->tableRow['name']);
        }
        return true;
    }

    public function addData($tbl)
    {
        $this->CalculateLog = $this->CalculateLog->getChildInstance(['addData' => true]);

        $this->reCalculate([
            'add' => $tbl['tbl'],
            'modify' => ['params' => $tbl['params'] ?? []],
            'channel' => 'inner',
            'isTableAdding' => true
        ]);

        $this->saveTable();
        $this->CalculateLog->addParam('result', 'saved');
        $this->CalculateLog = $this->CalculateLog->getParent();
    }

    public function getTableRow()
    {
        return parent::getTableRow() + ['sess_hash' => $this->sessHashName];
    }

    public function getUpdated()
    {
        return $this->updated ?? $this->getUpdatedJson();
    }

    public static function checkTableExists($tableName, $hash, $Totum)
    {
        return $Totum->getModel('_tmp_tables', true)->get(static::getKey($tableName, $hash, $Totum->getUser()));
    }

    public function loadDataRow($fromConstructor = false, $force = false)
    {
        if ((empty($this->dataRow) || $force)) {
            if ($this->dataRow = $this->model->get($this->key)) {
                $this->tbl = json_decode($this->dataRow['tbl'], true);
                $this->indexRows();
                $this->loadedTbl = $this->savedTbl = $this->tbl;
                $this->updated = $this->dataRow['updated'];
                $this->model->update(['touched' => date('Y-m-d H:i')], $this->key);
            } else {
                throw new errorException('Время жизни таблицы истекло. Повторите запрос данных.');
            }
        }
    }

    protected function loadModel()
    {
    }

    protected function _copyTableData(&$table, $settings)
    {
    }
}
