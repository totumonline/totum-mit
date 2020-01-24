<?php

namespace totum\common;

use totum\tableTypes\aTable;

abstract class Controller
{
    const __isAuthNeeded = true;
    static $interfaceData;
    static $linksData;
    static $linksPanel;



    static $FullLogsTOO_BIG;
    protected static $Logs;
    protected static $FullLogs;
    protected static $FullLogsSize = 0;
    protected static $FullLogsTablesIndex;

    /**
     * @var Controller
     */
    protected static $activeController;
    private static $someTableChanged = false;

    protected $answerVars = array();

    function __construct()
    {
        static::$activeController = $this;
    }

    /**
     * @return Controller
     */
    public static function getActiveController(): Controller
    {
        return self::$activeController;
    }

    abstract function doIt($action);

    /**
     * @param string $type data|table|json|diagramm|notify
     * @param $data
     */
    static function addToInterfaceDatas(string $type, $data, $refresh = false, $elseData=[])
    {
        $data['refresh']=$refresh;
        $data['elseData']=$elseData;
        static::$interfaceData[] = [$type, $data];
    }

    static function addLinkLocation($uri, $target, $title, $postData = null, $width = null, $refresh = false, $elseData=[])
    {
        static::$linksData[] = ['uri' => $uri, 'target' => $target, 'title' => $title, 'postData' => $postData, 'width' => $width, 'refresh' => $refresh, 'elseData'=>$elseData];
    }

    static function getLinks()
    {
        return static::$linksData;
    }
    static function getPanels()
    {
        return static::$linksPanel;
    }

    static function getInterfaceDatas()
    {
        return static::$interfaceData;
    }

    /**
     * @param $path
     * @param string $type 'c'|'s'|'a'|'f'
     * @param $log
     */
    static function addLogVar(aTable $table, $path, $type = 'c', $log)
    {
        if (is_callable([static::$activeController, '__addLogVar'])) {
            static::$activeController->__addLogVar($table, $path, $type, $log);
        }
    }

    /**
     * @return bool
     */
    public static function isSomeTableChanged(): bool
    {
        return self::$someTableChanged;
    }

    /**
     * @param bool $someTableChanged
     */
    public static function setSomeTableChanged(bool $someTableChanged = true)
    {
        self::$someTableChanged = $someTableChanged;
    }

    public static function addLinkPanel($link, $id, $field, $refresh)
    {
        static::$linksPanel[] = ['uri' => $link, 'id' => $id, 'field' => $field, 'refresh'=>$refresh];
    }

    protected function __run($operation)
    {

        if (static::__isAuthNeeded) $this->__runAuth($operation);
        else $this->__runWithoutAuth($operation);

    }

    protected function __runAuth($action)
    {
        if (!Auth::isAuthorized()) {
            static::__UnauthorizedAnswer();
        } else {
            static::__actionRun($action);
        }
    }

    protected function __UnauthorizedAnswer()
    {
        header('HTTP/1.0 401 Unauthorized');
        $this->__addAnswerVar('error', 'auth_missed');
    }

    protected function __actionRun($action)
    {
        if (method_exists(get_called_class(), $action = 'action' . $action)) {
            $this->__processActionReturnData($this->$action());
        }
    }

    protected function __processActionReturnData($data)
    {
        if (is_array($data)) {
            foreach ($data as $k => $v) $this->__addAnswerVar($k, $v);
        } elseif (is_string($data) && strlen($data) > 0) $this->__addAnswerVar('error',
            $data);
    }

    protected function __runWithoutAuth($action)
    {
        static::__actionRun($action);
    }

    protected function __addAnswerVar($name, $var)
    {
        $this->answerVars[$name] = $var;
    }

    protected function __setAnswerArray($var)
    {
        $this->answerVars = $var;
    }

}