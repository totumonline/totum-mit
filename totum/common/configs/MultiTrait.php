<?php


namespace totum\common\configs;

trait MultiTrait
{
    public function getFilesDir()
    {
        $dir = $this->baseDir . 'http/fls/' . ($this->getHostForDir($this->getFullHostName())) . '/';
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return $dir;
    }

    protected function getHostForDir($host)
    {
        return preg_replace(
            '`^(www.)?(.+)$`',
            '$2',
            $host
        );
    }
    public function setHostSchema($hostName = null, $schemaName = null)
    {
        if ($hostName) {
            $this->hostName = $hostName;
            $this->schemaName = $schemaName ?? $this->getSchemas()[$hostName];
        } elseif ($schemaName) {
            $this->schemaName = $schemaName;
            $this->hostName = $hostName ?? array_flip($this->getSchemas())[$schemaName];
        }
    }
    public function getClearConf()
    {
        $Conf= new static($this->env, false);
        $Conf->setHostSchema($this->hostName, $this->schemaName);
        return $Conf;
    }
}
