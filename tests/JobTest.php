<?php

namespace App\Tests;

use App\Exceptions\ApiClientHttpException;
use App\Jobs\SendJobToSimproJob;
use App\Tests\Support\MockSimproApiClientTrait;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\HttpFoundation\Response;

class JobTest extends TestCase
{
    use MockSimproApiClientTrait;

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

    public function testSendJobToSimproNewSite()
    {
        $this->mockSendJobToSimproNewSite();

        (new SendJobToSimproJob($this->getJsonFixture('create_job__data_from_riverside.json')))->handle();

        $this->assertChangesEqualsFixture('request_logs', 'send_job_to_simpro__new_site__request_logs_state.json', self::$originRequestLogs);
    }

    public function testSendJobToSimproSiteExists()
    {
        $this->mockSendJobToSimproSiteExists();

        (new SendJobToSimproJob($this->getJsonFixture('create_job__data_from_riverside.json')))->handle();

        $this->assertChangesEqualsFixture('request_logs', 'send_job_to_simpro__site_exists__request_logs_state.json', self::$originRequestLogs);
    }

    public function testSendJobToSimproPostSiteFailed()
    {
        $this->mockSendJobToSimproPostSiteFailed();

        try {
            (new SendJobToSimproJob($this->getJsonFixture('create_job__data_from_riverside.json')))->handle();
        } catch (ApiClientHttpException $e) {}

        $this->assertChangesEqualsFixture('request_logs', 'send_job_to_simpro__post_site_failed__request_logs_state.json', self::$originRequestLogs);
    }

    public function testSendJobToSimproFindSiteFailed()
    {
        $this->mockSendJobToSimproFindSiteFailed();

        try {
            (new SendJobToSimproJob($this->getJsonFixture('create_job__data_from_riverside.json')))->handle();
        } catch (ApiClientHttpException $e) {}

        $this->assertChangesEqualsFixture('request_logs', 'send_job_to_simpro__find_site_failed__request_logs_state.json', self::$originRequestLogs);
    }

    public function testSendJobToSimproPostJobFailed()
    {
        $this->mockSendJobToSimproPostJobFailed();

        try {
            (new SendJobToSimproJob($this->getJsonFixture('create_job__data_from_riverside.json')))->handle();
        } catch (ApiClientHttpException $e) {}

        $this->assertChangesEqualsFixture('request_logs', 'send_job_to_simpro__post_job_failed__request_logs_state.json', self::$originRequestLogs);
    }
}
