<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 13.03.17
 * Time: 16:41
 */

namespace totum\tableTypes;

use totum\common\Sql;


class simpleTable extends RealTables
{
    protected static $simpleTables = [];

    static function init($tableRow, $extraData = null, $light = false)
    {
        return static::$simpleTables[$tableRow['id']] ?? (static::$simpleTables[$tableRow['id']] = parent::init($tableRow,
                null,
                $light));
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
}