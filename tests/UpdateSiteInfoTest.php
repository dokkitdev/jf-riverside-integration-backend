<?php

namespace App\Tests;

use App\Exceptions\ApiClientHttpException;
use App\Jobs\UpdateSiteInfo\InitUpdateSitesInfoJob;
use App\Jobs\UpdateSiteInfo\UpdateSiteInfoJob;
use App\Tests\Support\MockHttpRequestServiceTrait;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Queue;

class UpdateSiteInfoTest extends TestCase
{
    use MockHttpRequestServiceTrait;

    protected static Collection $originRequestLogs;
    protected static Collection $originJobOperations;
    protected static Collection $originJobOperationRequestLog;

    public function setUp(): void
    {
        parent::setUp();

        self::$originRequestLogs = self::$originRequestLogs ?? $this->getDataSet('request_logs');
        self::$originJobOperations = self::$originJobOperations ?? $this->getDataSet('job_operations');
        self::$originJobOperationRequestLog = self::$originJobOperationRequestLog ?? $this->getDataSet('job_operation_request_log');
    }

    public function testInitSitesInfoDispatch()
    {
        Carbon::setTestNow('2018-11-11 00:00:00');

        $this->artisan('schedule:run');

        Queue::assertPushed(InitUpdateSitesInfoJob::class, function (InitUpdateSitesInfoJob $job) {
            $this->assertEquals(Carbon::now(), $job->getDateTime());

            return true;
        });
    }

    public function getInitUpdateSitesInfoData()
    {
        return [
            [
                'time' => Carbon::parse('2018-11-11 00:00:00'),
                'requests_chain_fixture' => 'init_update_sites_info__search_today_schedules__requests_chain.json',
            ],
            [
                'time' => Carbon::parse('2018-11-11 11:59:59'),
                'requests_chain_fixture' => 'init_update_sites_info__search_today_schedules__requests_chain.json',
            ],
            [
                'time' => Carbon::parse('2018-11-11 12:00:00'),
                'requests_chain_fixture' => 'init_update_sites_info__search_tomorrow_schedules__requests_chain.json',
            ],
            [
                'time' => Carbon::parse('2018-11-11 23:59:59'),
                'requests_chain_fixture' => 'init_update_sites_info__search_tomorrow_schedules__requests_chain.json',
            ],
        ];
    }

    /**
     * @dataProvider getInitUpdateSitesInfoData
     */
    public function testInitUpdateSitesInfo($time, $requestsChainFixture)
    {
        $expectedJobIds = [2, 4, 6];
        $expectedJobOperationIds = [4, 5, 6];

        $this->mockHttpRequestService($this->getJsonFixture($requestsChainFixture));

        (new InitUpdateSitesInfoJob($time))->handle();

        Queue::assertPushed(UpdateSiteInfoJob::class, count($expectedJobIds));
        Queue::assertPushed(UpdateSiteInfoJob::class, function (UpdateSiteInfoJob $job) use (&$expectedJobIds, &$expectedJobOperationIds) {
            $this->assertEquals(array_shift($expectedJobIds), $job->getJobId());
            $this->assertEquals(array_shift($expectedJobOperationIds), $job->getJobOperationId());
            return true;
        });

        $this->assertChangesEqualsFixture('job_operations', 'init_update_sites_info__job_operations_state.json', self::$originJobOperations);
        $this->assertNoChanges('job_operation_request_log', self::$originJobOperationRequestLog);
    }

    public function testInitUpdateSitesInfoNoSchedules()
    {
        $this->mockHttpRequestService($this->getJsonFixture('init_update_sites_info__no_schedules__requests_chain.json'));

        (new InitUpdateSitesInfoJob(Carbon::now()))->handle();

        Queue::assertNothingPushed();

        $this->assertNoChanges('job_operations', self::$originJobOperations);
        $this->assertNoChanges('job_operation_request_log', self::$originJobOperationRequestLog);
    }

    public function testInitUpdateSitesInfoSimproRequestFailed()
    {
        $this->mockHttpRequestService($this->getJsonFixture('init_update_sites_info__simpro_request_failed__requests_chain.json'));

        try {
            (new InitUpdateSitesInfoJob(Carbon::now()))->handle();
        } catch (ApiClientHttpException $exception) {}

        Queue::assertNothingPushed();

        $this->assertNoChanges('job_operations', self::$originJobOperations);
        $this->assertNoChanges('job_operation_request_log', self::$originJobOperationRequestLog);
    }

    public function testUpdateSiteInfoSuccess()
    {
        $this->mockHttpRequestService($this->getJsonFixture('update_ste_info__success__requests_chain.json'));

        (new UpdateSiteInfoJob(2, 3))->handle();

        $this->assertChangesEqualsFixture('job_operations', 'update_ste_info__success__job_operations_state.json', self::$originJobOperations);
        $this->assertChangesEqualsFixture('request_logs', 'update_ste_info__success__request_logs_state.json', self::$originRequestLogs);
        $this->assertChangesEqualsFixture('job_operation_request_log', 'update_ste_info__success__job_operation_request_log_state.json', self::$originJobOperationRequestLog);
    }

    public function testUpdateSiteInfoError()
    {
        $this->mockHttpRequestService($this->getJsonFixture('update_ste_info__error__requests_chain.json'));

        $job = new UpdateSiteInfoJob(2, 3);

        try {
            $job->handle();
            $expectedExceptionThrown = false;
        } catch (ApiClientHttpException $exception) {
            $expectedExceptionThrown = true;
        }

        $this->assertTrue($expectedExceptionThrown);

        $this->assertChangesEqualsFixture('job_operations', 'update_ste_info__error__job_operations_state.json', self::$originJobOperations);
        $this->assertChangesEqualsFixture('request_logs', 'update_ste_info__error__request_logs_state.json', self::$originRequestLogs);
        $this->assertChangesEqualsFixture('job_operation_request_log', 'update_ste_info__error__job_operation_request_log_state.json', self::$originJobOperationRequestLog);
    }

    public function testUpdateSiteInfoQueueJobFailed()
    {
        (new UpdateSiteInfoJob(2, 3))->failed();

        $this->assertChangesEqualsFixture('job_operations', 'update_ste_info__queue_job_failed__job_operations_state.json', self::$originJobOperations);
        $this->assertNoChanges('job_operation_request_log', self::$originJobOperationRequestLog);
        $this->assertNoChanges('request_logs', self::$originRequestLogs);
    }
}
