<?php
namespace totum\common;
/*
 * TODO Вынести лишние данные из сессии и закрывать блокировку сессии сразу после проверки UserID, подумать про AuthController
 *
 * */

use totum\main\AutoloadAndErrorHandlers;
use totum\models\Table;
use totum\models\UserV;
use totum\tableTypes\aTable;
use totum\tableTypes\tableTypes;

abstract class interfaceController extends Controller
{
    static $pageTemplate;
    static $contentTemplate = '';
    protected $answerVars = [];
    static $actionTemplate = '';
    protected static $withAuth = true;
    

    protected $isAjax = false, $folder = '', $inModuleUri;

    function __construct($modulName, $inModuleUri)
    {
        parent::__construct();

        $this->inModuleUri=$inModuleUri;
        if(static::__isAuthNeeded){
            Auth::webInterfaceSessionStart();
        }

        //$this->folder = preg_replace('/^app\/(.*)\/[^\/]+$/', '$1', str_replace('\\', '/', get_called_class()));

        $this->folder = dirname((new \ReflectionClass(get_called_class()))->getFileName());

        static::$pageTemplate = AutoloadAndErrorHandlers::getTemplatesDir().'/page_template.php';

        if (!empty($_REQUEST['ajax'])) {
            $this->isAjax = true;
        } else {
            $this->__addAnswerVar('Module', $modulName);
            $this->__addAnswerVar('isCreatorView', Auth::isCreator());

        }
    }

    protected function __runAuth($action)
    {
        if (!Auth::isAuthorized()) {

            if (empty($_POST['ajax'])){
            $this->location('/Auth/Login/');
            }else{
                echo json_encode(['error'=>'Потеряна авторизация. Обновите страницу']);
            }
            die;
        } else {
            static::__actionRun($action);
        }
    }

    protected function errorAnswer($error){
        extract($this->answerVars);
        include static::$pageTemplate;
    }

    function doIt($inAction)
    {
        $action=$inAction;
        static::$actionTemplate = $action;


        if ($this->isAjax) $action = 'Ajax' . $action;

        try{
            $this->__run($action);
        }
        catch (tableSaveException $e){

            unset($this->Table);

            tableTypes::$tables=[];
            Table::$tableRowsByName = [];
            Table::$tableRowsById = [];
            aTable::$recalcLogPointer = null;
            aTable::$isActionRecalculateDone = false;

            Controller::$interfaceData=null;
            Controller::$linksData=null;
            Controller::$linksPanel=null;
            Controller::$FullLogs=null;
            Controller::$Logs=null;
            Controller::$FullLogsSize=0;
            Controller::$FullLogsTablesIndex=null;

            Field::$fields=[];

            reCalcLogItem::$topObject=null;


            UserV::clear();

            Calculate::$calcLog=[];

            static::$FullLogs=static::$Logs=[];


            Sql::clear();
            Cycle::clearProjects();


            $this->doIt($inAction);

            //Mail::send('tatianap.php@gmail.com', $e->getMessage(), $_SERVER['REQUEST_URI'].' $_POST: '.json_encode($_POST, JSON_UNESCAPED_UNICODE));


            return;
        }
        catch (\Exception $e){
            $this->__addAnswerVar('error', $e->getMessage());
        }


        if ($this->isAjax) {
            if (empty($this->answerVars)) {
                $this->answerVars['error'] = 'Ошибка обработки запроса.';
            }

            $data= json_encode($this->answerVars, JSON_UNESCAPED_UNICODE);
            if($this->answerVars && !$data){
                $data['error'] = 'Ошибка обработки запроса.';
                if(Auth::isCreator()){
                    $data['error'] = 'Ошибка вывода не utf-содержимого или слишком большого пакета данных. '.(array_key_exists('FullLO GS', $this->answerVars)?'Попробуйте отключить вывод логов':'');
                 //   var_dump($this->answerVars); die;
                    //$data['error'].=;
                }
                $data= json_encode($data, JSON_UNESCAPED_UNICODE);
            }
            /*fwrite(fopen('test.log', 'a+'), "\n\n".substr(json_encode([$_SERVER["HTTP_HOST"],$_SERVER['REQUEST_URI'], $_SERVER['REMOTE_ADDR'], $data], JSON_UNESCAPED_UNICODE), 0, 230));*/
            if(empty($data)){
                $data='["error":"Пустой ответ сервера"]';
            }
            echo $data;
            die;
        } else {
            if (isset($this->Table)){
                $this->__addAnswerVar('title', $this->Table->getTableRow()['title']);
            }
            if (!static::$contentTemplate) {
                static::$contentTemplate = $this->folder . '/__' . static::$actionTemplate . '.php';
            }

            extract($this->answerVars);

            include static::$pageTemplate;
        }
    }

    function location($to='/Main/'){
        header('location: '.$to);
    }
}