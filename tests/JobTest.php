<?php

namespace App\Tests;

use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\Response;

class JobTest extends TestCase
{
    protected static Collection $originRequestLogs;

    public function setUp(): void
    {
        parent::setUp();

        self::$originRequestLogs = self::$originRequestLogs ?? $this->getDataSet('request_logs');
    }

    public function testCreate()
    {
        $response = $this->call(
            'post',
            '/jobs',
            [], [], [],
            $this->transformHeadersToServerVars([
                'HEADER_NAME' => 'HEADER_VALUE'
            ]),
            $this->getFixture('create_job_request.xml')
        );

        $response->assertStatus(Response::HTTP_OK);

        $this->assertChangesEqualsFixture('request_logs', 'create_job_request_logs_state.json', self::$originRequestLogs);
    }
}
