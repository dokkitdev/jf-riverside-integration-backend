<?php

namespace App\Tests\Support;

use App\Support\HttpRequestService;
use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Illuminate\Support\Arr;

trait MockHttpRequestServiceTrait
{
    protected function mockHttpRequestService(array $requestsChain = []): void
    {
        $methods = array_unique(Arr::pluck($requestsChain, 'request.type'));
        $methods[] = 'getResponse';

        $mock = $this
            ->getMockBuilder(HttpRequestService::class)
            ->onlyMethods($methods)
            ->getMock();

        foreach ($requestsChain as $index => $call) {
            $response = $this->makeResponse($call['response']);

            $requestData = is_string($call['request']['data']) && file_exists($this->getFixturePath($call['request']['data']))
                ? $this->getFixture($call['request']['data'])
                : $call['request']['data'];

            $mock
                ->expects($this->at($index * 2))
                ->method($call['request']['type'])
                ->with($call['request']['url'], $requestData, $call['request']['headers']);

            $mock
                ->expects($this->at(($index * 2) + 1))
                ->method('getResponse')
                ->willReturn($response);
        }

        $this->app->instance(HttpRequestService::class, $mock);
    }

    protected function makeResponse(array $response): GuzzleResponse
    {
        if (is_array($response['data'])) {
            $response['data'] = json_encode($response['data']);
        } elseif (!empty($response['data']) && file_exists($this->getFixturePath($response['data']))) {
            $response['data'] = $this->getFixture($response['data']);
        }

        return new GuzzleResponse(
            $response['status_code'],
            $response['headers'],
            $response['data']
        );
    }
}
