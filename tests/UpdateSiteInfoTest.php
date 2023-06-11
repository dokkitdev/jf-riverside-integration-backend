<?php

namespace App\Tests;

use App\Exceptions\ApiClientHttpException;
use App\Jobs\UpdateSiteInfo\InitUpdateSitesInfoJob;
use App\Jobs\UpdateSiteInfo\UpdateSiteInfoJob;
use App\Tests\Support\MockHttpRequestServiceTrait;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;

class UpdateSiteInfoTest extends TestCase
{
    use MockHttpRequestServiceTrait;

    protected $requiredOriginStates = [
        'request_logs',
        'job_operations',
        'job_operation_request_log',
    ];

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
                'requests_chain_fixture' => 'search_today_schedules__requests_chain.json',
            ],
            [
                'time' => Carbon::parse('2018-11-11 11:59:59'),
                'requests_chain_fixture' => 'search_today_schedules__requests_chain.json',
            ],
            [
                'time' => Carbon::parse('2018-11-11 12:00:00'),
                'requests_chain_fixture' => 'search_tomorrow_schedules__requests_chain.json',
            ],
            [
                'time' => Carbon::parse('2018-11-11 23:59:59'),
                'requests_chain_fixture' => 'search_tomorrow_schedules__requests_chain.json',
            ],
        ];
    }

    /**
     * @dataProvider getInitUpdateSitesInfoData
     * @testCase init_update_sites_info
     */
    public function testInitUpdateSitesInfo($time, $requestsChainFixture)
    {
        $expectedJobIds = [6, 8, 10];
        $expectedJobOperationIds = [4, 5, 6];

        $this->mockHttpRequestService($this->getJsonFixture($requestsChainFixture));

        (new InitUpdateSitesInfoJob($time))->handle();

        Queue::assertPushed(UpdateSiteInfoJob::class, count($expectedJobIds));
        Queue::assertPushed(UpdateSiteInfoJob::class, function (UpdateSiteInfoJob $job) use (&$expectedJobIds, &$expectedJobOperationIds) {
            $this->assertEquals(array_shift($expectedJobIds), $job->getJobId());
            $this->assertEquals(array_shift($expectedJobOperationIds), $job->getJobOperationId());
            return true;
        });

        $this->assertChangesEqualsFixture('job_operations');
        $this->assertNoChanges('job_operation_request_log');
    }

    /**
     * @testCase init_update_sites_info
     */
    public function testInitUpdateSitesInfoNoSchedules()
    {
        $this->mockHttpRequestService($this->getJsonFixture('no_schedules__requests_chain.json'));

        (new InitUpdateSitesInfoJob(Carbon::now()))->handle();

        Queue::assertNothingPushed();

        $this->assertNoChanges('job_operations');
        $this->assertNoChanges('job_operation_request_log');
    }

    /**
     * @testCase init_update_sites_info
     */
    public function testInitUpdateSitesInfoSimproRequestFailed()
    {
        $this->mockHttpRequestService($this->getJsonFixture('simpro_request_failed__requests_chain.json'));

        try {
            (new InitUpdateSitesInfoJob(Carbon::now()))->handle();
        } catch (ApiClientHttpException $exception) {}

        Queue::assertNothingPushed();

        $this->assertNoChanges('job_operations');
        $this->assertNoChanges('job_operation_request_log');
    }

    public function getTestUpdateSiteInfoSuccessData()
    {
        return [
            ['update_site_info__no_primary_contact_in_simpro'],
            ['update_site_info__exists_primary_contact_in_simpro'],
            ['update_site_info__no_valuable_site_and_contact_data'],
            ['update_site_info__no_site_and_contact'],
            ['update_site_info__no_main_contact_in_riverside_data'],
        ];
    }

    /**
     * @dataProvider getTestUpdateSiteInfoSuccessData
     * @providedTestCase
     */
    public function testUpdateSiteInfoSuccess($testCase)
    {
        $this->mockHttpRequestService($this->getJsonFixture('/requests_chain.json'));

        (new UpdateSiteInfoJob(3, 4))->handle();

        $this->assertChangesEqualsFixture('job_operations');
        $this->assertChangesEqualsFixture('request_logs');
        $this->assertChangesEqualsFixture('job_operation_request_log');
    }

    /**
     * @testCase update_site_info__error
     */
    public function testUpdateSiteInfoError()
    {
        $this->mockHttpRequestService($this->getJsonFixture('requests_chain.json'));

        $job = new UpdateSiteInfoJob(3, 4);

        try {
            $job->handle();
            $expectedExceptionThrown = false;
        } catch (ApiClientHttpException $exception) {
            $expectedExceptionThrown = true;
        }

        $this->assertTrue($expectedExceptionThrown);

        $this->assertChangesEqualsFixture('job_operations');
        $this->assertChangesEqualsFixture('request_logs');
        $this->assertChangesEqualsFixture('job_operation_request_log');
    }

    /**
     * @testCase update_ste_info__queue_job_failed
     */
    public function testUpdateSiteInfoQueueJobFailed()
    {
        (new UpdateSiteInfoJob(3, 4))->failed();

        $this->assertChangesEqualsFixture('job_operations');
        $this->assertNoChanges('job_operation_request_log');
        $this->assertNoChanges('request_logs');
    }
}
