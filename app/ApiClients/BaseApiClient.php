<?php

namespace App\ApiClients;

use App\Exceptions\ApiClientHttpException;
use App\Services\RequestLogService;
use App\Support\HttpRequestService;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Response;

abstract class BaseApiClient
{
    protected const REQUEST_TIMEOUT_SECONDS = 300;

    protected HttpRequestService $httpRequestService;
    protected RequestLogService $requestLogService;

    abstract protected function getUrl(string $action): string;
    abstract protected function getHeaders(): array;
    abstract protected function getRequestDestination(): string;

    public function __construct()
    {
        $this->httpRequestService = app(HttpRequestService::class);
        $this->requestLogService = app(RequestLogService::class);
    }

    /** @param array|string $data */
    protected function rawRequest(string $method, string $action, $data, array $headers = [], int $expectedStatusCode = Response::HTTP_OK): ResponseInterface
    {
        $url = $this->getUrl($action);

        $headers = array_merge($this->getHeaders(), $headers);

        $requestData = ($method === 'delete') ? $headers : $data;

        $response = $this->httpRequestService
            ->set('http_errors', false)
            ->set('verify', false)
            ->set('timeout', self::REQUEST_TIMEOUT_SECONDS)
            ->$method($url, $requestData, $headers)
            ->getResponse();

        $this->requestLogService->create([
            'destination' => $this->getRequestDestination(),
            'url' => $url,
            'method' => $method,
            'request_body' => is_array($requestData) ? json_encode($requestData) : $requestData,
            'request_headers' => json_encode($headers),
            'response_status_code' => $response->getStatusCode(),
            'response_body' => (string) $response->getBody(),
            'response_headers' => json_encode($response->getHeaders()),
        ]);

        if ($response->getStatusCode() !== $expectedStatusCode) {
            throw new ApiClientHttpException($url, $response->getStatusCode(), (string) $response->getBody());
        }

        return $response;
    }
}