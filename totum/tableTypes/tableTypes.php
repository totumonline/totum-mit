<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 13.03.17
 * Time: 16:39
 */

namespace totum\tableTypes;

use totum\common\errorException;
use totum\common\Cycle;
use totum\models\Table;

class tableTypes
{
    static $tables = [];

    static function getTableByName($nameTable, $force = false)
    {
        return static::getTable(Table::getTableRowByName($nameTable, $force));
    }

    static function getTableClass($tableRow)
    {
        $table = '\\totum\\tableTypes\\' . $tableRow['type'] . 'Table';

        return $table;

    }

    static function isRealTable($tableRow)
    {
        return in_array($tableRow['type'], ['simple', 'cycles']);
    }

    /**
     * @param $tableRow
     * @param null $extraData
     * @param bool $light - используется в isTableChanged.php
     * @return aTable
     * @throws errorException
     */
    static function getTable($tableRow, $extraData = null, $light = false): aTable
    {
        if (empty($tableRow['type'])) {
            debug_print_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            throw new errorException('Внутренняя ошибка: не указан тип таблицы');
        }
        if (is_array($tableRow['type'])) {
            debug_print_backtrace();
            die;
        }

        $table = '\\totum\\tableTypes\\' . $tableRow['type'] . 'Table';

        $cacheString = $tableRow['id'] . ';' . $extraData;

        if($tableRow['type']=='tmp' && empty($extraData)){

            /** @var tmpTable $tableTmp */
            /** @var tmpTable $table */
            $tableTmp = $table::init($tableRow, Cycle::init(0, 0), $light, $extraData);
            $cacheString = $tableRow['id'] . ';' . $tableTmp->getTableRow()['sess_hash'];
            static::$tables[$cacheString] = $tableTmp;

        } else if (!(static::$tables[$cacheString] ?? false)) {

            switch ($tableRow['type']) {
                case 'globcalcs':
                    $GlobalCycle = Cycle::init(0, 0);
                    static::$tables[$cacheString] = $GlobalCycle->getTable($tableRow, $light);
                    break;
                case 'calcs':
                    $Cycle = Cycle::init($extraData, $tableRow['tree_node_id']);
                    static::$tables[$cacheString] = $Cycle->getTable($tableRow, $light);
                    break;
                case 'tmp':
                    static::$tables[$cacheString] = $table::init($tableRow, Cycle::init(0, 0), $light, $extraData);
                    break;
                default:
                    static::$tables[$cacheString] = $table::init($tableRow, $extraData, $light);
            }
        }

        return static::$tables[$cacheString];
    }
}