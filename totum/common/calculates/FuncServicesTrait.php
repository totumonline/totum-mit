<?php

namespace totum\common\calculates;

use totum\common\criticalErrorException;
use totum\common\errorException;
use totum\common\Services\ServicesConnector;
use totum\fieldTypes\File;

trait FuncServicesTrait
{

    protected function funcServiceXlsxParser($params)
    {
        throw new criticalErrorException($this->translate('This option works only in PRO.'));
    }

    protected function funcServiceAskOpenaiList($params)
    {
        throw new criticalErrorException($this->translate('This option works only in PRO.'));
    }

    protected function funcServiceAskOpenai($params)
    {
        throw new criticalErrorException($this->translate('This option works only in PRO.'));
    }

    protected function funcServiceXlsxGenerator($params)
    {
        throw new criticalErrorException($this->translate('This option works only in PRO.'));
    }


    protected function funcServiceDocxGenerator($params)
    {
        throw new criticalErrorException($this->translate('This option works only in PRO.'));
    }

    protected function funcServicePDFGenerator($params)
    {
        throw new criticalErrorException($this->translate('This option works only in PRO.'));

    }

}