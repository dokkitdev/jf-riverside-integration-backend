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

    public function setUp(): void
    {
        parent::setUp();

        self::$originRequestLogs = self::$originRequestLogs ?? $this->getDataSet('request_logs');
        self::$originJobOperations = self::$originJobOperations ?? $this->getDataSet('job_operations');
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

        $this->mockHttpRequestService($this->getJsonFixture($requestsChainFixture));

        (new InitUpdateSitesInfoJob($time))->handle();

        Queue::assertPushed(UpdateSiteInfoJob::class, count($expectedJobIds));
        Queue::assertPushed(UpdateSiteInfoJob::class, function (UpdateSiteInfoJob $job) use (&$expectedJobIds) {
            $this->assertEquals(array_shift($expectedJobIds), $job->getJobId());
            return true;
        });
    }

    public function testInitUpdateSitesInfoNoSchedules()
    {
        $this->mockHttpRequestService($this->getJsonFixture('init_update_sites_info__no_schedules__requests_chain.json'));

        (new InitUpdateSitesInfoJob(Carbon::now()))->handle();

        Queue::assertNothingPushed();
    }

    public function testInitUpdateSitesInfoSimproRequestFailed()
    {
        $this->mockHttpRequestService($this->getJsonFixture('init_update_sites_info__simpro_request_failed__requests_chain.json'));

        try {
            (new InitUpdateSitesInfoJob(Carbon::now()))->handle();
        } catch (ApiClientHttpException $exception) {}

        Queue::assertNothingPushed();
    }

    public function testUpdateSiteInfoSuccess()
    {
        $this->mockHttpRequestService($this->getJsonFixture('update_ste_info__success__requests_chain.json'));

        (new UpdateSiteInfoJob(2))->handle();

        $this->assertChangesEqualsFixture('job_operations', 'update_ste_info__success__job_operations_state.json', self::$originJobOperations);
        $this->assertChangesEqualsFixture('request_logs', 'update_ste_info__success__request_logs_state.json', self::$originRequestLogs);
    }

    public function testUpdateSiteInfoError()
    {
        $this->mockHttpRequestService($this->getJsonFixture('update_ste_info__error__requests_chain.json'));

        try {
            (new UpdateSiteInfoJob(2))->handle();
        } catch (ApiClientHttpException $exception) {}

        $this->assertNoChanges('job_operations', self::$originJobOperations);
        $this->assertChangesEqualsFixture('request_logs', 'update_ste_info__error__request_logs_state.json', self::$originRequestLogs);
    }
}
