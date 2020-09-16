<?php

namespace totum\common\logs;

use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;

class Log implements LoggerInterface
{
    use LoggerTrait;

    protected $logFileResource;
    /**
     * @var array
     */
    protected static $defaultLevels = ['error', 'debug', 'alert', 'critical', 'emergency', 'info', 'notice', 'warning'];
    /**
     * @var callable|\Closure
     */
    protected $templateCallback;

    /**
     * @var string
     */
    protected $logFilePath;
    /**
     * @var mixed
     */
    protected $levels;

    public function __construct(string $path, $levels = null, $templateCallback = null)
    {
        if (!$levels)
            $levels = static::$defaultLevels;
        $this->levels = array_flip($levels);

        $this->logFilePath = $path;
        $this->templateInit($templateCallback);

    }

    protected function templateInit($templateCallback = null)
    {
        if ($templateCallback) {
            if (!is_callable($templateCallback))
                throw new \Exception('Ошибка инициализации Логгера');
        } else {
            $templateCallback = function ($level, $message) {
                $date = date('d.m H:i');
                return "$date $level $message";
            };
        }
        $this->templateCallback = $templateCallback;
    }

    protected function printToFile($level, $message, array $context)
    {
        if (!empty($context)) {
            $message .= ' ' . @var_export($context, 1);
        }

        $message = preg_replace('/[\r\n\t ]+/m', ' ', $message);
        $row = ($this->templateCallback)($level, $message);
        $this->writeRow($row);

    }

    protected function writeRow($row)
    {
        if (empty($this->logFileResource)) {

            $this->logFileResource = fopen($this->logFilePath, 'a');
            fwrite($this->logFileResource, "-------" . PHP_EOL);
        }
        fwrite($this->logFileResource, $row . PHP_EOL);
    }

    public function __destroy()
    {
        fclose($this->logFileResource);
    }

    public function log($level, $message, array $context = array())
    {
        if (key_exists($level, $this->levels)) {
            $this->printToFile($level, $message, $context);
        }
    }


}