<?php

namespace App\Tests;

use App\Jobs\SendJobToSimproJob;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Queue;
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
        $response = $this->xml('post', '/jobs', 'create_job__request.xml', ['HEADER_NAME' => 'HEADER_VALUE']);

        $response->assertStatus(Response::HTTP_OK);

        $this->assertEquals('', $response->getContent());

        $this->assertCount(1, Queue::pushedJobs()[SendJobToSimproJob::class]);

        Queue::assertPushed(SendJobToSimproJob::class, function (SendJobToSimproJob $job) {
            $this->assertEqualsFixture('create_job__data_from_riverside.json', $job->getDataFromRiverside());

            return true;
        });

        $this->assertChangesEqualsFixture('request_logs', 'create_job__request_logs_state.json', self::$originRequestLogs);
    }

    public function testCreateInvalidXml()
    {
        $response = $this->xml('post', '/jobs', 'create_job__invalid_xml__request.xml', ['HEADER_NAME' => 'HEADER_VALUE']);

        $response->assertStatus(Response::HTTP_BAD_REQUEST);

        $response->assertJson(['error' => "Can't parse XML payload"]);

        Queue::assertNothingPushed();

        $this->assertChangesEqualsFixture('request_logs', 'create_job__invalid_xml__request_logs_state.json', self::$originRequestLogs);
    }
}
