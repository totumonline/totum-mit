<?php

namespace totum\common\controllers;

/*
 * TODO Вынести лишние данные из сессии и закрывать блокировку сессии сразу после проверки UserID, подумать про AuthController
 *
 * */

use totum\common\sql\SqlException;
use totum\common\User;
use totum\config\Conf;

abstract class interfaceController extends Controller
{
    public static $pageTemplate = 'page_template.php';
    public static $contentTemplate = '';
    public static $actionTemplate = '';

    protected $answerVars = [];

    protected $isAjax = false;
    protected $folder;
    /**
     * @var mixed|string
     */
    protected $totumPrefix;
    /**
     * @var User
     */
    protected $User;

    public function __construct(Conf $Config, $totumPrefix = '')
    {
        parent::__construct($Config, $totumPrefix);

        $controllerFile = (new \ReflectionClass(get_called_class()))->getFileName();

        $modul = preg_replace(
            '`^.*?([^' . DIRECTORY_SEPARATOR . ']+)' . DIRECTORY_SEPARATOR . '[^' . DIRECTORY_SEPARATOR . ']+$`',
            '$1',
            $controllerFile
        );

        $this->folder = dirname($controllerFile);
        $this->modulPath = $totumPrefix . '/' . $modul . '/';
        $this->totumPrefix = $totumPrefix;

        static::$pageTemplate = $this->Config->getTemplatesDir() . '/' . static::$pageTemplate;
    }

    protected function output($action)
    {
        if ($this->isAjax) {
            $this->outputJson();
        } else {
            if (!static::$contentTemplate) {
                static::$contentTemplate = $this->folder . '/__' . $action . '.php';
            }
            $this->outputHtmlTemplate();
        }
    }

    protected function outputJson()
    {
        if (empty($this->answerVars)) {
            $this->answerVars['error'] = 'Ошибка обработки запроса.';
        }

        $data = json_encode($this->answerVars, JSON_UNESCAPED_UNICODE);
        if ($this->answerVars && !$data) {
            $data['error'] = 'Ошибка обработки запроса.';
            if ($this->User && $this->User->isCreator()) {
                $data['error'] = 'Ошибка вывода не utf-содержимого или слишком большого пакета данных. ' . json_last_error_msg();
            }
            $data = json_encode($data, JSON_UNESCAPED_UNICODE);
        }
        if (empty($data)) {
            $data = '["error":"Пустой ответ сервера"]';
        }
        echo $data;
    }

    /*Emit it?*/
    protected function outputHtmlTemplate()
    {
        try {
            $settings = [];
            foreach (['h_og_title', 'h_og_description', 'h_og_image', 'h_title'] as $var) {
                $settings[$var] = $this->Config->getSettings($var);
            }
            $this->__addAnswerVar('settings', $settings, true);
        } catch (SqlException $e) {
            $this->Config->getLogger('sql')->error($e->getMessage(), $e->getTrace());
            $error = "Ошибка базы данных";
        }

        extract($this->answerVars);
        include static::$pageTemplate;
    }


    /**
     * @param string $to
     */
    protected function location($to = null)
    {
        $to = $this->totumPrefix . ($to ?? '/');
        header('location: ' . $to);
        die;
    }

    protected function __addAnswerVar($name, $var, $quote = false)
    {
        if ($quote || $name === 'error') {
            $funcQuote = function ($var) use ($name, &$funcQuote) {
                if (is_array($var)) {
                    foreach ($var as &$v) {
                        $v = $funcQuote($v);
                    }
                } else {
                    $var = htmlspecialchars($var);
                    if($name==='error'){
                        $var=str_replace('&lt;br/&gt;', '<br/><br/>', $var);
                    }
                }
                return $var;
            };
            $this->answerVars[$name] = $funcQuote($var);
        } else {
            $this->answerVars[$name] = $var;
        }
    }
}
