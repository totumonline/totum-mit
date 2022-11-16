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
        $servicesConnect = ['number' => 1, 'key' => 1];
        $template = File::getContent($params['template'], $Config);

        $hash = $Config->getServicesVarObject()->getNewVarnameHash(3600);
        $connector = ServicesConnector::init($Config);
        if ($result = $connector->sendRequest('xslx', $hash, $servicesConnect['number'], $servicesConnect['key'], [
            'data' => [
                'template' => base64_encode($template),
                'data' => $params['data'],
            ]
        ])) {
            return $Config->getServicesVarObject()->waitVarValue($hash);
        } else {
            throw new errorException($result);
        }

    }

}