<?php

namespace App\Tests\Support;

use Symfony\Component\HttpFoundation\Response;

trait MockRiversideApiClientTrait
{
    use MockHttpRequestServiceTrait;

    protected function mockNotifyRiversideAboutCreatedJob(string $requestFixture, string $responseFixture, int $statusCode = Response::HTTP_OK)
    {
        $expectedRequestXml = $this->getFixture($requestFixture);
        $responseXml = $this->getFixture($responseFixture);

        $this->mockHttpRequestService([
            [
                'type' => 'post',
                'arguments' => [
                    'https://oh.services.riverside.org.uk/test.openaccess/webservice.p',
                    $expectedRequestXml,
                    [
                        'Accept' => 'application/xml',
                        'Content-Type' => 'application/xml',
                    ],
                ],
                'response' => [
                    'status_code' => $statusCode,
                    'fixture' => [
                        'content' => $responseXml,
                        'headers' => [],
                    ],
                ],
            ]
        ]);
    }
}
