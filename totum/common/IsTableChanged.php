<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 13.02.18
 * Time: 8:45
 */

namespace totum\common;

use totum\config\Conf;
use totum\models\Table;
use totum\models\UserV;
use totum\tableTypes\tableTypes;

class IsTableChanged
{
    private $ftokFile;
    const ftokProj = 'c'; //changed
    const ftokSProj = 's'; //subscribe to table
    const ftokDir = './schemas/';
    private $tableChanges;
    private $tableChangesSubscribes;
    private $tableId;
    private $cycleId;


    public function __construct($table_id, $cycle_id = 0, Conf $Config)
    {
        $this->tableChanges = new SharedFileUsage($Config->getTmpTableChangesDir() . $Config->getSchema() . '.tableChanges');
        $this->tableChangesSubscribes = new SharedFileUsage($Config->getTmpTableChangesDir() . $Config->getSchema() . '.tableChangesSubscribes');
        $this->tablestring = $table_id . ($cycle_id ? '.' . $cycle_id : '');
        $this->tableId = $table_id;
        $this->cycleId = $cycle_id;
    }

    public function setChanged($code, $timestamp)
    {
        $tablesTimes = $this->tableChangesSubscribes->read();
        $update = [];

        $delete = function ($tableid, $code_timestamp) {
            if (preg_replace('/^\d+\:/', '', $code_timestamp) < time() - 60) {
                return true;
            } else {
                return false;
            }
        };

        if (!empty($tablesTimes[$this->tablestring]) && $tablesTimes[$this->tablestring] > time()) {
            $update = [$this->tablestring => $code . ':' . $timestamp];
        }
        $this->tableChanges->update($update, $delete);
    }

    public function isChanged($code, Totum $Totum)
    {
        $this->subcribeToChanges();
        $Table = $Totum->getTable($this->tableId, $this->cycleId, true);
        $isChanged = $Table->getChangedString($code);
        $i = 0;


        while (!empty($isChanged['no']) && $i++ < 20) {
            sleep(3);
            $changes = $this->tableChanges->read();

            if (!empty($changes[$this->tablestring])) {
                list($str_code, $str_stamp) = explode(':', $changes[$this->tablestring]);
                if ($str_code !== $code) {
                    $isChanged = $Table->getChangedString($code);
                }
            }
        }
        return $isChanged;
    }

    public function subcribeToChanges()
    {
        $tablesTimes = $this->tableChangesSubscribes->read();
        if (empty($tablesTimes[$this->tablestring]) || $tablesTimes[$this->tablestring] < time() + 3600 * 3) {
            $deleteFunc = function ($tablestring, $timestamp) {
                if ($timestamp < time()) {
                    return true;
                }
            };
            $update = [$this->tablestring => time() + 3600 * 10];
            $this->tableChangesSubscribes->update($update, $deleteFunc);
        }
    }
}
