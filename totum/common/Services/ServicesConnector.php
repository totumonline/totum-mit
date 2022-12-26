<?php

namespace totum\common\Services;

use GuzzleHttp\Psr7\ServerRequest;
use totum\common\configs\ConfParent;
use totum\common\Crypt;
use totum\common\errorException;
use totum\common\Totum;

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

    function serviceRequestFile($serviceName, array $data, $comment = null): string|false
    {
        $Config = $this->Config;
        $hash = $Config->getServicesVarObject()->getNewVarnameHash(3600);
        $connector = ServicesConnector::init($Config);

        if (!empty($comment)) {
            $comment = mb_substr((string)$comment, 0, 30);
            $data['comment'] = $comment;
        }
        $connector->sendRequest($serviceName, $hash, $data);
        $value = $Config->getServicesVarObject()->waitVarValue($hash);

        $context = stream_context_create(
            [
                'http' => [
                    'header' => "User-Agent: TOTUM\r\nConnection: Close\r\n\r\n",
                    'method' => 'GET',
                ],
                'ssl' => [
                    'verify_peer' => $Config->isCheckSsl(),
                    'verify_peer_name' => $Config->isCheckSsl(),
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

    public function sendRequest($type, $hash, $data)
    {
        $accountData = $this->getServicesAccountData();
        $Data = [
            'number' => $accountData['h_services_number'],
            'key' => $accountData['h_services_key'],
            'version' => Totum::VERSION,
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