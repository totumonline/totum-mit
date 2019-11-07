<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 21.03.17
 * Time: 10:03
 */

namespace totum\tableTypes;


use totum\common\Controller;
use totum\common\errorException;
use totum\common\JsonTable;
use totum\common\Model;
use totum\models\NonProjectCalcs;
use totum\models\TablesCalcsConnects;
use totum\models\TablesFields;

class globcalcsTable extends JsonTables
{
    public function saveTable()
    {
        if ($this->savedUpdated == $this->updated) return;

        /*if(empty($GLOBALS['test'])){
            $GLOBALS['test']=1;
            errorException::tableUpdatedException($this);
        }
*/

        $updateWhere=[
            'tbl_name' => $this->tableRow['name']
        ];
        if($this->getTableRow()['actual']!='disable'){
            $updateWhere['updated']=$this->savedUpdated;
        }

        if (!$this->model->update([
            'tbl' => $this->getPreparedTbl(),
            'updated' => $this->updated
        ],
            $updateWhere)
        ) {
            errorException::tableUpdatedException($this);
        }
        $saved = $this->savedTbl;
        $this->savedTbl = $this->tbl;
        $this->savedUpdated = $this->updated;
        $this->isTableDataChanged=false;
        $this->markTableChanged();

        $this->onSaveTable($this->tbl, $saved);

        Controller::setSomeTableChanged();
        return true;
    }

    function createTable()
    {
        $this->model->insert(['tbl_name' => $this->tableRow['name'], 'updated' => $updated = static::getUpdatedJson()]);
        $this->savedUpdated = $this->updated = $updated;

        $this->onCreateTable();
        //$this->updateFromSource(-1);
    }

    protected function addInSourceTables($SourceTableRow)
    {
        if ($SourceTableRow === ['globcalcs']) {
            if ($SourceTableRow['id']!=$this->tableRow['id'])
            $this->source_tables[$SourceTableRow['id']] = true;
        }
    }

     function loadDataRow($fromConstructor = false, $force = false)
    {
        if (empty($this->dataRow) || $force) {
            $this->dataRow = $this->model->getById($this->tableRow['name']);
            $this->tbl = json_decode($this->dataRow['tbl']??'[]', true);

            $this->indexRows();
            $this->loadedTbl = $this->savedTbl = $this->tbl;
        }
    }


    protected function loadModel()
    {
        $this->model = NonProjectCalcs::init();
        $this->modelConnects = TablesCalcsConnects::init();
    }

}