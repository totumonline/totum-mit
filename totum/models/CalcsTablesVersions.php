<?php


namespace totum\models;


use totum\common\errorException;
use totum\common\Model;

class CalcsTablesVersions extends Model
{
    protected $cacheDefVersions=[];

    public function getDefaultVersion($tableName)
    {
        if(!key_exists($tableName, $this->cacheDefVersions)){
            $this->cacheDefVersions[$tableName]=$this->executePrepared(true,
                ['table_name' => $tableName, 'is_default' => "true"], 'version')->fetch()['version'];

            if (!$this->cacheDefVersions[$tableName]) throw new errorException('Нет версии по-умолчанию для таблицы ' . $tableName);
        }
        return $this->cacheDefVersions[$tableName];
    }

}