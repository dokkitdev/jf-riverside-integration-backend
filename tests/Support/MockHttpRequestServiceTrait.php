<?php

namespace App\Tests\Support;

use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Illuminate\Support\Arr;
use RonasIT\Support\Services\HttpRequestService;

trait MockHttpRequestServiceTrait
{
    protected function mockHttpRequestService(array $requestsChain = []): void
    {
        $methods = array_unique(Arr::pluck($requestsChain, 'type'));
        $methods[] = 'getResponse';

        $mock = $this
            ->getMockBuilder(HttpRequestService::class)
            ->onlyMethods($methods)
            ->getMock();

        foreach ($requestsChain as $index => $request) {
            $response = $this->makeResponse($request['response']);

            $mock
                ->expects($this->at($index * 2))
                ->method($request['type'])
                ->with(...$request['arguments']);

            $mock
                ->expects($this->at(($index * 2) + 1))
                ->method('getResponse')
                ->willReturn($response);
        }

        $this->app->instance(HttpRequestService::class, $mock);
    }

    protected function makeResponse(array $response): GuzzleResponse
    {
        $responseData = is_array($response['fixture']) ? $response['fixture'] : $this->getJsonFixture($response['fixture']);

        return new GuzzleResponse(
            $response['status_code'],
            $responseData['headers'],
            json_encode($responseData['content'])
        );
    }
}
