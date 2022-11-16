<?php

namespace totum\common\Services;

use totum\common\configs\ConfParent;

class ServicesConnector
{

    protected static ?ServicesConnector $Connector = null;

    static function init(ConfParent $Conf)
    {
        if (!static::$Connector) {
            static::$Connector = new static($Conf);
        }
        return static::$Connector;
    }

    public function __construct(protected ConfParent $Config)
    {
    }

    public function sendRequest($type, $hash, $number, $key, $data)
    {
        $Data = [
            'number' => $number,
            'key' => $key,
            'hash' => $hash,
            'data' => $data];
        $context = stream_context_create(
            [
                'http' => [
                    'header' => "Content-type: application/json\r\nUser-Agent: TOTUM\r\nConnection: Close\r\n\r\n",
                    'method' => 'POST',
                    'content' => json_encode($Data)
                ]
            ]
        );
        return file_get_contents('https://services.ttmapp.ru/' . $type . '/', false, $context) === 'true';
    }

    public function setAnswer($request): void
    {
        if (!($hash = $request->getParsedBody()['hash'] ?? false)) {
            die('hash is empty');
        }
        if (!($key = $request->getParsedBody()['key'] ?? false)) {
            die('key is empty');
        }
        if ($key != $this->Config->services['answerkey']) {
            die('answerkey is not correct');
        }
        $this->Config->getServicesVarObject()->setVarValue($hash, $request->getParsedBody()['value'], 'done');
    }

}