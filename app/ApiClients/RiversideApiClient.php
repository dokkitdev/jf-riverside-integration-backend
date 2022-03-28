<?php

namespace App\ApiClients;

use App\Exceptions\ApiClientHttpException;
use App\Models\RequestLog;
use Illuminate\Support\Carbon;
use Psr\Http\Message\ResponseInterface;
use SimpleXMLElement;
use Exception;

class RiversideApiClient extends BaseApiClient
{
    protected const DATE_FORMAT = 'd/m/Y';
    protected const TIME_FORMAT = 'H:i:s';

    protected $baseUrl;
    protected $userName;
    protected $password;
    protected $secret;

    public function __construct()
    {
        parent::__construct();

        $this->baseUrl = config('services.riverside.api_url');
        $this->userName = config('services.riverside.user_name');
        $this->password = config('services.riverside.password');
        $this->secret = config('services.riverside.secret');
    }

    public function notifyAboutCreatedJob(string $jobOrderNo, Carbon $date)
    {
        $this->makeRequest('riverside_request_xml.notify_about_created_job', [
            'job_order_no' => $jobOrderNo,
            'date' => $date->format(self::DATE_FORMAT),
            'time' => $date->format(self::TIME_FORMAT),
        ]);
    }

    protected function getUrl(string $action): string
    {
        return $this->baseUrl;
    }

    protected function getHeaders(): array
    {
        return [
            'Accept' => 'application/xml',
            'Content-Type' => 'application/xml',
        ];
    }

    protected function getRequestDestination(): string
    {
        return RequestLog::DESTINATION_RIVERSIDE;
    }

    protected function makeRequest(string $view, array $data = []): void
    {
        $data['user_name'] = $this->userName;
        $data['password'] = $this->password;
        $data['secret'] = $this->secret;

        $renderedXML = view($view, $data)->render();

        $response = $this->rawRequest('post', '', $renderedXML);

        $this->checkResponseXmlForErrors($response);
    }

    protected function checkResponseXmlForErrors(ResponseInterface $response): void
    {
        $responseContent = (string) $response->getBody();

        try {
            $xml = new SimpleXMLElement($responseContent);

            //TODO: make sure that "ErrorDetails" field not exists in successful responses
            $isFailed = isset($xml->ErrorDetails);
        } catch (Exception $exception) {
            $isFailed = true;
        }

        if ($isFailed) {
            throw new ApiClientHttpException($this->getUrl(''), $response->getStatusCode(), $responseContent);
        }
    }
}