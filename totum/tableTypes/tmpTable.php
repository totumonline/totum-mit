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
use totum\common\Log;
use totum\common\Model;
use totum\config\Conf;

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


    function __construct($tableRow, Cycle $Cycle, $light = false, $hash = null)
    {
        $this->model = Model::initService('_tmp_tables');

        if (is_null($hash)) {
            do {
                $hash = md5(microtime(true) . '_' . $tableRow['name'] . '_' . mt_srand());
                $this->key = ['table_name' => $tableRow['name'], 'user_id' => Auth::$aUser->getId(), 'hash' => $hash];
            } while ($this->model->getField('user_id', $this->key));

            $this->savedTbl = $this->tbl = $this->getNewTblForRecalculate();
            $this->isTableAdding = true;
            $this->sessHashName = $hash;
            $this->updated = $this->getUpdated();

            $this->model->insert(array_merge(['tbl' => json_encode($this->tbl,
                JSON_UNESCAPED_UNICODE), 'updated' => $this->updated, 'touched' => date('Y-m-d H:i')],
                $this->key),
                false);

        } else {
            $this->key = ['table_name' => $tableRow['name'], 'user_id' => Auth::$aUser->getId(), 'hash' => $hash];
            $this->loadDataRow(true);

        }
        $this->sessHashName = $hash;

        parent::__construct($tableRow, $Cycle, $light);
        static::$tmpTables[$tableRow['id'] . '_' . $hash] = $this;
    }


    public static function init($tableRow, $extraData = null, $light = false, $hash = null)
    {
        if (!is_null($hash) && key_exists($hashName = $tableRow['id'] . '_' . $hash, static::$tmpTables)) {
            return static::$tmpTables[$hashName];
        }
        return new static($tableRow, $extraData, $light, $hash);
    }

    function createTable()
    {

    }

    function getTableDataForInterface($withoutRows = false, $withoutRowsData = false)
    {

        $r = parent::getTableDataForInterface($withoutRows, $withoutRowsData);
        $this->saveTable();
        return $r;
    }

    function isTblUpdated($level = 0, $force = false)
    {

        $savedTbl = $this->savedTbl;
        $isOnSave = false;
        if (!$this->isOnSaving) {
            $isOnSave = true;
        }

        if ($isOnSave && $this->isTableDataChanged) {
            $this->updated = static::getUpdatedJson();
            $this->saveTable();

            foreach ($this->tbl['rows'] as $id => $row) {
                $oldRow = ($savedTbl['rows'][$id] ?? []);
                if ($oldRow && (!empty($row['is_del']) && empty($oldRow['is_del']))) $this->changeIds['deleted'][$id] = null;
                elseif (!empty($oldRow) && empty($row['is_del'])) {
                    if ($oldRow != $row) {
                        foreach ($row as $k => $v) {
                            if (($oldRow[$k] ?? null) != $v) {//Здесь проставляется changed для web (только ли это в web нужно?)
                                $this->changeIds['changed'][$id] = $this->changeIds['changed'][$id] ?? [];
                                $this->changeIds['changed'][$id][$k] = null;
                            }
                        }

                    }
                }
            }
            $this->changeIds['deleted'] = $this->changeIds['deleted'] + array_flip(array_keys(array_diff_key($savedTbl['rows'] ?? [],
                    $this->tbl['rows'] ?? [])));
            $this->changeIds['added'] = array_flip(array_keys(array_diff_key($this->tbl['rows'],
                $savedTbl['rows'])));
            $this->isOnSaving = false;

            return true;
        } else
            return false;
    }

    function modify($tableData, array $data)
    {
        $this->loadDataRow();
        return parent::modify($tableData, $data);
    }

    public function saveTable()
    {
        if ($this->key) {
            $this->model->update(['touched' => date('Y-m-d H:i'), 'tbl' => json_encode($this->tbl,
                JSON_UNESCAPED_UNICODE), 'updated' => $this->updated],
                $this->key);
            $saved = $this->savedTbl;
            $this->savedTbl = $this->tbl;
            $this->savedUpdated = $this->updated;
            $this->isTableDataChanged = false;
            $this->onSaveTable($this->tbl, $saved);

            Controller::setSomeTableChanged();
        }
        return true;
    }

    function addData($tbl)
    {
        $this->reCalculate([
            'add' => $tbl['tbl'],
            'modify' => ['params' => $tbl['params'] ?? []],
            'channel' => 'inner',
            'isTableAdding' => true
        ]);

        $this->saveTable();
    }

    function getTableRow()
    {
        return parent::getTableRow() + ['sess_hash' => $this->sessHashName];
    }

    function getValuesAndFormatsForClient($data, $viewType = 'web', $fields = null)
    {
        return parent::getValuesAndFormatsForClient($data, $viewType); // TODO: Change the autogenerated stub
    }

    protected function checkRightFillOrder($id_first, $id_last, $count)
    {
        return true;

    }

    protected function updateReceiverTables($level = 0)
    {

    }

    protected function getUpdated()
    {

        return $this->updated ?? static::getUpdatedJson();
    }

    function loadDataRow($fromConstructor = false, $force = false)
    {
        if ((empty($this->dataRow) || $force)) {
            if ($this->dataRow = $this->model->get($this->key)) {
                $this->tbl = json_decode($this->dataRow['tbl'], true);
                $this->indexRows();
                $this->loadedTbl = $this->savedTbl = $this->tbl;
                $this->updated = $this->dataRow['updated'];
                $this->model->update(['touched' => date('Y-m-d H:i')], $this->key);
            } else {
                throw new errorException ('Время жизни таблицы истекло. Повторите запрос данных.');
            }
        }
    }

    public function getFilteredData($channel)
    {
        return $this->tbl;
    }

    protected function loadModel()
    {

    }

    protected function _copyTableData(&$table, $settings)
    {

    }
}