<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 13.03.17
 * Time: 16:41
 */

namespace totum\tableTypes;

use totum\common\Model;
use totum\common\Sql;
use totum\models\Table;
use totum\models\TablesFields;


class simpleTable extends RealTables
{
    protected static $simpeTables = [];

    static function init($tableRow, $extraData = null, $light = false)
    {
        if (is_object($extraData)) {
            return static::$simpeTables[$tableRow['id']] ?? (static::$simpeTables[$tableRow['id']] = parent::init($tableRow,
                    $extraData,
                    $light));
        } else return static::$simpeTables[$tableRow['id']] ?? (static::$simpeTables[$tableRow['id']] = parent::init($tableRow,
                null,
                $light,
                $extraData));
    }

    function createTable()
    {
        $fields = [];
        $fields[] = 'id SERIAL PRIMARY KEY NOT NULL';
        $fields[] = 'is_del BOOLEAN NOT NULL DEFAULT FALSE ';

        $fields = '(' . implode(',', $fields) . ')';
        Sql::exec('CREATE TABLE ' . $this->tableRow['name'] . $fields);

        if ($this->getTableRow()['with_order_field']) {
            $this->addOrderField();
        }

    }


    protected function duplicateRow($channel, $baseRow, $replaces, $addAfter)
    {
        $duplicatedRow = parent::duplicateRow($channel, $baseRow, $replaces, $addAfter);

        if ($this->getTableRow()['id'] === Table::$TableId) {

        }

        return $duplicatedRow;
    }
}