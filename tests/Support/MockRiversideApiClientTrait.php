<?php

namespace App\Tests\Support;

use Symfony\Component\HttpFoundation\Response;

trait MockRiversideApiClientTrait
{
    use MockHttpRequestServiceTrait;

    protected function mockNotifyRiversideAboutCreatedJob(string $requestFixture, string $responseFixture, int $statusCode = Response::HTTP_OK)
    {
        $this->mockHttpRequestService([
            [
                'request' => [
                    'type' => 'post',
                    'url' => 'https://oh.services.riverside.org.uk/test.openaccess/webservice.p',
                    'data' => $requestFixture,
                    'headers' => [
                        'Accept' => 'application/xml',
                        'Content-Type' => 'application/xml',
                    ]
                ],
                'response' => [
                    'status_code' => $statusCode,
                    'data' => $responseFixture,
                    'headers' => [],
                ],
            ]
        ]);
    }
}
