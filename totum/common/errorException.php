<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 11.01.17
 * Time: 13:51
 */

namespace totum\common;


use totum\config\Conf;
use totum\tableTypes\aTable;

class errorException extends \Exception
{
    protected $message;
    public $log;
    protected $pathMess;

    function __get($name)
    {
        return $this->$name;
    }

    function __construct($message, $code = 0)
    {
        parent::__construct($message, $code);
    }

    function addPath($path)
    {
        if ($this->pathMess == '') $this->pathMess = $path;
        else {
            $this->pathMess = $this->getPathMess() . '; ' . $path;
        }
    }

    static function tableUpdatedException(aTable $aTable)
    {
        // Mail::send('tatianap.php@gmail.com', Conf::getSchema().'/'.$aTable->getTableRow()['name'], $_SERVER['REQUEST_URI'].' $_POST: '.json_encode($_POST, JSON_UNESCAPED_UNICODE));
        throw new tableSaveException('Таблица [[' . $aTable->getTableRow()['title'] . ']] была изменена. Обновите таблицу для проведения изменений');
    }
    static function criticalException($error)
    {
        throw new criticalErrorException($error);
    }

    /**
     * @return mixed
     */
    public function getPathMess()
    {
        return $this->pathMess;
    }

}