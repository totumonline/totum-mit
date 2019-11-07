<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 30.08.17
 * Time: 15:51
 */

namespace totum\common;


use totum\models\Table;
use totum\tableTypes\tableTypes;

class Settings
{
    const TableNAME = "settings";

    protected static $Singleton;
    private static $settings;
    protected $Table, $params;

    protected function __construct()
    {
        $this->loadTable();
    }

    static function getSetting($name){
        if (empty(static::$settings)){
            static::$settings = json_decode(Table::getTableRowByName(static::TableNAME)['header'], true);
        }
        return static::$settings[$name]['v'];
    }

    static function init()
    {
        return static::$Singleton = static::$Singleton ?? new static();
    }

    function getParam($name)
    {
        return $this->params[$name]['v'] ?? null;
    }

    protected function loadTable()
    {
        if (!$this->Table) {
            $this->Table = tableTypes::getTable(Table::getTableRowByName(static::TableNAME));
            $this->params = $this->Table->params;
        }
    }
}