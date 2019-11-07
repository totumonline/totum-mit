<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 09.08.2018
 * Time: 15:15
 */

namespace totum\common;


use totum\models\Table;
use totum\tableTypes\aTable;

class reCalcLogItem
{
    protected $name;
    protected $children = [];
    protected $count;
    protected $parent;

    static $topObject;

    private $mkTimeStart;
    private $allTime = 0;
    private $cycle;
    private $memoryStart;
    private $memoryDelta;
    private $selects = [];



    public function __construct($name, $parent = null)
    {
        $this->name = $name;
        if (!static::$topObject) static::$topObject = $this;
        else $this->parent = $parent;

        list($this->tableName, $this->cycle) = explode('/', $name.'/');
    }

    /**
     * @return reCalcLogItem
     */
    public function getParent()
    {
        $this->active(false);
        return $this->parent ?? $this;
    }

    function addCount()
    {
        $this->count++;
    }

    function active($isActive)
    {
        if ($isActive) {
            $this->mkTimeStart = microtime(true);
            $this->memoryStart = memory_get_usage();
        } else {
            if ($this->mkTimeStart) {
                $this->allTime += microtime(true) - $this->mkTimeStart;
                $this->memoryDelta += memory_get_usage() - $this->memoryStart;
            }
        }
    }

    function getChild($name, $isReCalc = false): reCalcLogItem
    {
         if ($name === $this->name && !$isReCalc) {
             return $this;
         }

        if (array_key_exists($name, $this->children)) {
            $obj = $this->children[$name];
        } else {
            $obj = $this->children[$name] = new reCalcLogItem($name, $this);
        }
        if ($isReCalc) $obj->addCount();
        $obj->active(true);
        return $obj;
    }

    /**
     * @return array
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    public function getNameForLog()
    {
        if ($this->name === '') return 'Пересчеты';

        return Table::getTableRowByName($this->tableName)['title'] . ($this->cycle ? ' / цикл ' . $this->cycle : '') . ($this->count > 1 ? ' ' . $this->count . ' раз' : "");
    }

    function addSelects(aTable $fromTable, aTable $toTable)
    {
        $fromTable = $fromTable->getTableRow()['name'] . '/' . ($fromTable->getCycle() ? $fromTable->getCycle()->getId() : '');
        $toTable = $toTable->getTableRow()['name'] . '/' . ($toTable->getCycle() ? $toTable->getCycle()->getId() : '');
        if ($fromTable != $toTable) {
            $this->selects[$fromTable] = $this->selects[$fromTable] ?? [];
            if (!array_key_exists($toTable, $this->selects[$fromTable])) {
                $this->selects[$fromTable][$toTable] = 1;
            } else {
                $this->selects[$fromTable][$toTable]++;
            }
        }
    }

    static function getAllLog()
    {
        if ($o = static::$topObject) {
            $getChildren = function ($o) use (&$getChildren) {
                $children = [];

                foreach ($o->getChildren() as $ch) {
                    $children[] = $getChildren($ch);
                }
                if ($o->allTime > 0.03)
                    $children[] = [
                        "text" => round($o->allTime, 2) + ' сек.',
                        "type" => "clocks"
                    ];
                if (($Mbs = $o->memoryDelta / 1024 / 1024) > 1)
                    $children[] = [
                        "text" => ($o->memoryDelta > 0 ? '+' : '') . round($Mbs, 2) . ' Mb.',
                        "type" => "mbs"
                    ];
                if ($o->selects) {
                    $selectsSum = 0;
                    $selects=[];
                    foreach ($o->selects as $from => $tos) {

                        $selcTos=[];
                        foreach ($tos as $n => $count) {
                            list($n, $cycle) = explode('/', $n);
                            $selectsSum += $count;
                            $tToRow = Table::getTableRowByName($n);
                            $selcTos[] = [
                                'text'=>''.$tToRow['title'].($cycle? ' / цикл '.$cycle:'').' - '.$count.' шт.',
                                "type" => 'table_'.$tToRow['type']
                            ];
                        }
                        list($from, $cycle) = explode('/', $from);
                        $tFromRow = Table::getTableRowByName($from);
                        $selects[] = [
                            'text'=>'В т. "'.$tFromRow['title'].($cycle? ' / цикл '.$cycle:'').'" к таблицам: ',
                            "type" => 'table_'.$tFromRow['type'],
                            'children'=>$selcTos
                        ];
                    }
                    $children[] = [
                        "text" => "Селектов " . $selectsSum,
                        "type" => "selects",
                        "children" => $selects

                    ];
                }
                $log = [
                    "text" => $o->getNameForLog(),
                    "type" => "recalcs"
                ];
                if ($children) {
                    $log["children"] = $children;
                }
                return $log;


            };

            return $getChildren($o);
        }


    }

}