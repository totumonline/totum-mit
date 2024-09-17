<?php
/**
 * Created by PhpStorm.
 * User: tatiana
 * Date: 2018-12-21
 * Time: 14:19
 */

namespace totum\moduls\install;

use Psr\Http\Message\ServerRequestInterface;
use totum\common\controllers\interfaceController;
use totum\common\TotumInstall;

class installController extends interfaceController
{
    public static $pageTemplate = 'page_install_template.php';
    /**
     * @var mixed|string
     */
    protected string $lang = 'en';
    protected mixed $LangObj;

    /** @noinspection PhpMissingParentConstructorInspection */
    public function __construct()
    {

    }

    public function actionMain(ServerRequestInterface $serverRequest)
    {
        $post = $serverRequest->getParsedBody();
        $this->lang = $post['lang'] ?? 'en';

        set_time_limit(120);
        $done = false;
        if (!empty($post)) {
            try {
                $post['schema_exists'] = $post['schema_exists'] === '1';

                $TotumInstall = new TotumInstall($post, $post['user_login']);
                $TotumInstall->install(function ($file) {
                    return dirname(__FILE__) . DIRECTORY_SEPARATOR . $file;
                });
                $done = true;
            } catch (\Exception $exception) {
                $this->__addAnswerVar('error', $exception->getMessage() . "\n\n" . $exception->getTraceAsString());
                if (!empty($Sql)) {
                    $Sql->transactionRollBack();
                }
            }
        }

        if ($done) {
            static::$contentTemplate = dirname(__FILE__) . DIRECTORY_SEPARATOR . '__done.php';
        } elseif (file_exists($filename = __DIR__ . '/../../../Conf.php')) {
            static::$contentTemplate = dirname(__FILE__) . DIRECTORY_SEPARATOR . '__formForDocker.php';
        } else {
            static::$contentTemplate = dirname(__FILE__) . DIRECTORY_SEPARATOR . '__form.php';
        }
    }


    public function doIt(ServerRequestInterface $request, bool $output)
    {
        $this->actionMain($request);
        if ($output) {
            $this->output('Main');
        }
    }

    public function outputHtmlTemplate()
    {
        extract($this->answerVars);

        include dirname(__FILE__) . DIRECTORY_SEPARATOR . 'page_install_template.php';
    }

    protected function translate(string $str, mixed $vars = []): string
    {
        if ($this->Config) {
            return parent::translate($str, $vars);
        }
        $this->LangObj = $this->LangObj ?? new ('totum\\common\\Lang\\' . strtoupper($this->lang))();
        return $this->LangObj->translate($str, $vars);
    }
}
