<?php

namespace App\ApiClients;

use App\Models\RequestLog;
use Symfony\Component\HttpFoundation\Response;

class SimproApiClient extends BaseApiClient
{
    protected string $baseUrl;
    protected string $token;
    protected int $companyId;

    public function __construct()
    {
        parent::__construct();

        $this->baseUrl = config('services.simpro.api_url');
        $this->token = config('services.simpro.token');
        $this->companyId = config('services.simpro.company_id');
    }

    protected function getUrl(string $action): string
    {
        return "{$this->baseUrl}/api/v1.0/companies/{$this->companyId}{$action}";
    }

    protected function getHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => "Bearer {$this->token}",
        ];
    }

    protected function getRequestDestination(): string
    {
        return RequestLog::DESTINATION_SIMPRO;
    }

    protected function makeRequest(string $method, string $action, array $data = [], array $headers = [], int $expectedStatusCode = Response::HTTP_OK): ?array
    {
        $response = $this->rawRequest($method, $action, $data, $headers, $expectedStatusCode);

        return json_decode((string) $response->getBody(), true);
    }
}