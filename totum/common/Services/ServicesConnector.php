<?php

namespace totum\common\Services;

use GuzzleHttp\Psr7\ServerRequest;
use totum\common\configs\ConfParent;
use totum\common\Crypt;
use totum\common\errorException;

class ServicesConnector
{

    protected static ?ServicesConnector $Connector = null;
    protected array|null $servicesAccountData = null;

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

    public function sendRequest($type, $hash, $data)
    {
        $accountData = $this->getServicesAccountData();
        $Data = [
            'number' => $accountData['h_services_number'],
            'key' => $accountData['h_services_key'],
            'hash' => $hash,
            'data' => $data
        ];
        $context = stream_context_create(
            [
                'http' => [
                    'header' => "Content-type: application/json\r\nUser-Agent: TOTUM\r\nConnection: Close\r\n\r\n",
                    'method' => 'POST',
                    'content' => json_encode($Data)
                ],
                'ssl' => [
                    'verify_peer' => $this->Config->isCheckSsl(),
                    'verify_peer_name' => $this->Config->isCheckSsl(),
                ],
            ]
        );
        $result = file_get_contents($accountData['h_services_url'] . '/' . $type . '/', false, $context);
        if ($result === 'true') {
            return true;
        } else {
            throw new errorException($result);
        }
    }

    public function setAnswer(ServerRequest $request): void
    {
        if (($request->getQueryParams()['check_domain_key'] ?? false) === 'true') {
            die($this->getServicesAccountData()['h_service_domain_check_key'] ?: 'empty');
        }

        $body = json_decode($request->getBody(), true);

        if (!($hash = $body['hash'] ?? false)) {
            die('hash is empty');
        }

        if (empty($data = $body['data'])) {
            die('data is empty');
        }
        $this->Config->getServicesVarObject()->setVarValue($hash, $data, 'done');
    }

    protected function getServicesAccountData()
    {
        if (is_null($this->servicesAccountData)) {
            $header = $this->Config->getSql()->get(
                <<<SQL
select header  from tables where name->>'v'='ttm__services'
SQL
            );
            $header = json_decode($header['header'], true);
            $this->servicesAccountData['h_services_url'] = $header['h_services_url']['v'] ?? '';
            $this->servicesAccountData['h_service_domain_check_key'] = $header['h_service_domain_check_key']['v'] ?? '';
            $this->servicesAccountData['h_services_number'] = $header['h_services_number']['v'] ?? '';
            $this->servicesAccountData['h_services_key'] = Crypt::getDeCrypted($header['h_services_key']['v'] ?? '',
                $this->Config->getCryptKeyFileContent());
        }
        return $this->servicesAccountData;

    }

}