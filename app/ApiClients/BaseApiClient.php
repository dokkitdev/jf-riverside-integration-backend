<?php

namespace App\ApiClients;

use App\Exceptions\ApiClientHttpException;
use App\Services\RequestLogService;
use Psr\Http\Message\ResponseInterface;
use RonasIT\Support\Services\HttpRequestService;
use Symfony\Component\HttpFoundation\Response;

abstract class BaseApiClient
{
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

    protected function rawRequest(string $method, string $action, array $data = [], array $headers = [], int $expectedStatusCode = Response::HTTP_OK): ResponseInterface
    {
        $url = $this->getUrl($action);

        $headers = array_merge($this->getHeaders(), $headers);

        $requestData = ($method === 'delete') ? $headers : $data;

        $this->httpRequestService
            ->set('http_errors', false)
            ->$method($url, $requestData, $headers);

        $response = $this->httpRequestService->getResponse();

        $this->requestLogService->create([
            'destination' => $this->getRequestDestination(),
            'url' => $url,
            'method' => $method,
            'request_body' => json_encode($requestData),
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