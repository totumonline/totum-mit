<?php

namespace totum\common\calculates;

use totum\common\errorException;
use totum\common\Services\ServicesConnector;
use totum\fieldTypes\File;

trait FuncServicesTrait
{

    protected function funcServiceXslxGenerator($params)
    {
        $params = $this->getParamsArray($params);
        $this->__checkNotEmptyParams($params, ['template']);
        $this->__checkNotArrayParams($params, ['template']);
        $this->__checkListParam($params, ['data']);

        $Config = $this->Table->getTotum()->getConfig();
        $template = File::getContent($params['template'], $Config);

        $hash = $Config->getServicesVarObject()->getNewVarnameHash(3600);
        $connector = ServicesConnector::init($Config);

        $connector->sendRequest('xlsx', $hash, [
            'template' => base64_encode($template),
            'data' => $params['data'],
        ]);
        $value = $Config->getServicesVarObject()->waitVarValue($hash);

        $context = stream_context_create(
            [
                'http' => [
                    'header' => "User-Agent: TOTUM\r\nConnection: Close\r\n\r\n",
                    'method' => 'GET',
                ],
                'ssl' => [
                    'verify_peer' => $this->Table->getTotum()->getConfig()->isCheckSsl(),
                    'verify_peer_name' => $this->Table->getTotum()->getConfig()->isCheckSsl(),
                ],
            ]
        );

        if (empty($value['link']) || !($file = @file_get_contents($value['link'], true, $context))) {
            if (!empty($value['error'])) {
                throw new errorException('Xlsx generator error: ' . $value['error']);
            }
            throw new errorException('Wrong data from service server: ' . $http_response_header[0]);
        }

        return $file;
    }

}