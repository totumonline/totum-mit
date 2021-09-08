<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 11.01.17
 * Time: 13:51
 */

namespace totum\common;

use Composer\Config;
use totum\common\configs\ConfParent;
use totum\common\sql\Sql;
use totum\common\WithPathMessTrait;
use totum\tableTypes\aTable;

class errorException extends \Exception
{
    use WithPathMessTrait;

    public function __get($name)
    {
        return $this->$name;
    }

    public function __construct($message, $code = 0)
    {
        parent::__construct($message, $code);
    }

    public static function tableUpdatedException(aTable $aTable)
    {
        $aTable->getTotum()->transactionRollback();
        throw new tableSaveException($aTable->getLangObj()->translate('Table [[%s]] has been changed. Update the table to make the changes.',
            $aTable->getTableRow()['title']));
    }

    /**
     * @param $error
     * @param aTable|ConfParent|Totum|null $contextObject
     * @throws criticalErrorException
     */
    public static function criticalException($error, $contextObject = null)
    {
        match (true) {
            is_a($contextObject, aTable::class) => $contextObject->getTotum()->transactionRollback(),
            is_a($contextObject, Totum::class) => $contextObject->transactionRollback(),
            is_a($contextObject, ConfParent::class) => $contextObject->getSql()->transactionRollBack(),
            is_a($contextObject, Sql::class) => $contextObject->transactionRollBack(),
        };
        throw new criticalErrorException($error);
    }
}
