<?php

namespace App\ApiClients;

use App\Exceptions\TooManySitesFoundException;
use App\Models\RequestLog;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\Response;

class SimproApiClient extends BaseApiClient
{
    protected const JOB_TYPE = 'Service';
    protected const JOB_INITIAL_STAGE = 'Pending';

    protected string $baseUrl;
    protected string $token;
    protected int $companyId;
    protected int $customerId;
    protected int $uprnFieldId;
    protected int $jobInitialStatusId;

    public function __construct()
    {
        parent::__construct();

        $this->baseUrl = config('services.simpro.api_url');
        $this->token = config('services.simpro.token');
        $this->companyId = config('services.simpro.company_id');
        $this->customerId = config('services.simpro.customer_id');
        $this->uprnFieldId = config('services.simpro.uprn_field_id');
        $this->jobInitialStatusId = config('services.simpro.job_initial_status_id');
    }

    public function findSiteIdByUprn(string $uprn): ?int
    {
        $response = $this->makeRequest('get', '/sites/', [
            'CustomFields.Value' => $uprn,
            'CustomFields.CustomField.ID' => $this->uprnFieldId,
            'columns' => 'ID'
        ]);

        if (empty($response)) {
            return null;
        } elseif (count($response) > 1) {
            throw new TooManySitesFoundException($uprn, $response);
        }

        return Arr::get(Arr::first($response), 'ID');
    }

    public function postSite(string $uprn, array $data): int
    {
        $data['Customers'] = [$this->customerId];
        $uprnData = [
            'Value' => $uprn
        ];

        $response = $this->makeRequest('post', '/sites/', $data, [], Response::HTTP_CREATED);

        $newSiteId = $response['ID'];

        $this->makeRequest('patch', "/sites/{$newSiteId}/customFields/{$this->uprnFieldId}", $uprnData, [],Response::HTTP_NO_CONTENT);

        return $newSiteId;
    }

    public function patchSite(int $siteId, array $data): void
    {
        $data['Customers'] = [$this->customerId];

        $this->makeRequest('patch', "/sites/{$siteId}", $data, [], Response::HTTP_NO_CONTENT);
    }

    public function postJob(int $siteId, array $data): int
    {
        $data['Site'] = $siteId;
        $data['Type'] = self::JOB_TYPE;
        $data['Stage'] = self::JOB_INITIAL_STAGE;
        $data['Customer'] = $this->customerId;
        $data['Status'] = $this->jobInitialStatusId;

        $response = $this->makeRequest('post', "/jobs/", $data, [], Response::HTTP_CREATED);

        return $response['ID'];
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