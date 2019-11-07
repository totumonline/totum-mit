<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 21.03.17
 * Time: 10:31
 */

namespace totum\common;


class JsonTable
{
    protected $data, $fields, $sortedFields, $newData = [], $Project;

    function __construct(String $tbl, Array $fields, $Project = null)
    {
        $this->data = json_decode($tbl, true);
        $this->fields = $fields;
        $this->Project = $Project;
    }

    function getData()
    {
        return $this->data;
    }

    function calculate($modifyData = null, $addData = null)
    {
        foreach (['params', 'column', 'footer'] as $category) {

            if ($columns = $this->getSortedFields()[$category]??[]) {

                $this->newData[$category] = [];

                if ($category == 'column') {
                    $newId = 1;
                    foreach ($this->data['table']??[] as $row) {

                        if ($newId<=$row['id']) $newId=$row['id']+1;

                        $newRow = ['id' => $row['id']];
                        if (!empty($row['is_del'])) $newRow['is_del']=true;
                        $this->newData[$category][] = $newRow;
                    }
                    if (!empty($addData)){
                        $newRow = ['id' => $newId];
                        $this->newData[$category][]=$newRow;
                        $modifyData[$newId]=$addData;
                    }
                }

                foreach ($columns as $column) {
                    if ($category == 'column') {
                        foreach ($this->newData[$category] as &$thisRow){
                            $this->calculateColumn($column, $modifyData[$thisRow['id']]??null, $thisRow, $this->data[$category][$thisRow['id']][$column['name']]??null);
                        }
                    } else {
                        $thisRow= &$this->newData[$category];
                        $this->calculateColumn($column, $modifyData[$category]??null, $thisRow, $this->data[$category][$column['name']]??null);
                    }
                }

            }

        }
        return $this->newData;
    }

    protected function calculateColumn($column, $modifyData, $thisRow, $oldData){

    }

    protected function getSortedFields()
    {
        if (is_null($this->sortedFields)) {
            foreach ($this->fields as $field) {
                $this->sortedFields[$field['category']] = $this->sortedFields[$field['category']]??[];
                $this->sortedFields[$field['category']][] = $field;
            }
        }
        return $this->sortedFields;
    }
}