<?php

namespace totum\common\calculates;

use totum\common\errorException;
use totum\common\Services\ServicesConnector;
use totum\fieldTypes\File;

trait FuncServicesTrait
{

    protected function serviceRequest(\totum\config\Conf $Config, $serviceName, array $data): string|false
    {
        $hash = $Config->getServicesVarObject()->getNewVarnameHash(3600);
        $connector = ServicesConnector::init($Config);

        $connector->sendRequest($serviceName, $hash, $data);
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
                throw new errorException('Generator error: ' . $value['error']);
            }
            if (!empty($value['link'])) {
                throw new errorException('Wrong data from service server: ' . $http_response_header);
            } else {
                throw new errorException('Unknown error');
            }
        }

        return $file;
    }

    protected function funcServiceXlsxGenerator($params)
    {
        $params = $this->getParamsArray($params);
        $this->__checkNotEmptyParams($params, ['template']);
        $this->__checkNotArrayParams($params, ['template']);
        $this->__checkListParam($params, ['data']);

        $Config = $this->Table->getTotum()->getConfig();
        $template = File::getContent($params['template'], $Config);

        return $this->serviceRequest($Config, 'xlsx', [
            'template' => base64_encode($template),
            'data' => $params['data'],
            'pdf' => match ($params['pdf'] ?? false) {
                'true', true => true,
                default => false
            },
        ]);
    }

    protected function funcServiceDocxGenerator($params)
    {
        $params = $this->getParamsArray($params);
        $this->__checkNotEmptyParams($params, ['template']);
        $this->__checkNotArrayParams($params, ['template']);
        $this->__checkListParam($params, ['data']);

        $Config = $this->Table->getTotum()->getConfig();
        $template = File::getContent($params['template'], $Config);

        return $this->serviceRequest($Config, 'docx', [
            'template' => base64_encode($template),
            'data' => $params['data'],
            'pdf' => match ($params['pdf'] ?? false) {
                'true', true => true,
                default => false
            },
        ]);
    }

    protected function funcServicePDFGenerator($params)
    {
        $params = $this->getParamsArray($params);
        $type = match ($params['type'] ?? false) {
            'html', 'xlsx', 'docx' => $params['type'],
            default => throw new errorException($this->translate('The [[%s]] parameter is not correct.', 'type'))
        };

        $this->__checkNotArrayParams($params, ['file', 'filestring']);
        if (!empty($params['filestring'])) {
            $file = (string)$params['filestring'];
        } elseif (!empty($params['file'])) {
            $file = File::getContent($params['file'], $this->Table->getTotum()->getConfig());
        } else {
            throw new errorException($this->translate('Fill in the parameter [[%s]].',
                ['file']));
        }

        $Config = $this->Table->getTotum()->getConfig();
        return $this->serviceRequest($Config, 'pdf', [
            'file' => base64_encode($file),
            'type' => $type
        ]);
    }
}