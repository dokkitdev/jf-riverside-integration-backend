<?php

namespace App\Tests;

use App\Exceptions\ApiClientHttpException;
use App\Jobs\NotifyRiversideAboutCreatedJobJob;
use App\Jobs\ProcessSimproWebhookJobCompletedJob;
use App\Jobs\ProcessSimproWebhookJobScheduleCreatedJob;
use App\Jobs\ProcessSimproWebhookJobStatusChangedJob;
use App\Jobs\SendJobToSimproJob;
use App\Tests\Support\MockRiversideApiClientTrait;
use App\Tests\Support\MockSimproApiClientTrait;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\HttpFoundation\Response;

class JobTest extends TestCase
{
    use MockSimproApiClientTrait, MockRiversideApiClientTrait;

    protected static Collection $originRequestLogs;
    protected static Collection $originJobs;
    protected static Collection $originJobOperations;

    public function setUp(): void
    {
        parent::setUp();

        self::$originRequestLogs = self::$originRequestLogs ?? $this->getDataSet('request_logs');
        self::$originJobs = self::$originJobs ?? $this->getDataSet('jobs');
        self::$originJobOperations = self::$originJobOperations ?? $this->getDataSet('job_operations');
    }

    public function testCreate()
    {
        $response = $this->xml('post', '/jobs', 'create_job__request.xml', ['HEADER_NAME' => 'HEADER_VALUE']);

        $response->assertStatus(Response::HTTP_OK);

        $this->assertEquals('', $response->getContent());

        $this->assertCount(1, Queue::pushedJobs()[SendJobToSimproJob::class]);

        Queue::assertPushed(SendJobToSimproJob::class, function (SendJobToSimproJob $job) {
            $this->assertEquals(4, $job->getJobId());
            $this->assertEqualsFixture('create_job__data_from_riverside.json', $job->getDataFromRiverside());

            return true;
        });

        $this->assertChangesEqualsFixture('request_logs', 'create_job__request_logs_state.json', self::$originRequestLogs);
        $this->assertChangesEqualsFixture('jobs', 'create_job__jobs_state.json', self::$originJobs);
        $this->assertChangesEqualsFixture('job_operations', 'create_job__job_operations_state.json', self::$originJobOperations);
    }

    public function testCreateInvalidXml()
    {
        $response = $this->xml('post', '/jobs', 'create_job__invalid_xml__request.xml', ['HEADER_NAME' => 'HEADER_VALUE']);

        $response->assertStatus(Response::HTTP_BAD_REQUEST);

        $response->assertJson(['error' => "Can't parse XML payload"]);

        Queue::assertNothingPushed();

        $this->assertChangesEqualsFixture('request_logs', 'create_job__invalid_xml__request_logs_state.json', self::$originRequestLogs);
        $this->assertNoChanges('jobs', self::$originJobs);
        $this->assertNoChanges('job_operations', self::$originJobOperations);
    }

    public function testCreateCheckValidation()
    {
        $response = $this->xml('post', '/jobs', 'create_job__check_validation__request.xml', ['HEADER_NAME' => 'HEADER_VALUE']);

        $response->assertStatus(Response::HTTP_UNPROCESSABLE_ENTITY);

        $response->assertJson([
            'message' => 'The given data was invalid.',
            'errors' => [
                'location.place_ref' => ['The location.place ref field is required.'],
                'location.address1' => ['The location.address1 field is required.'],
                'job.job_no' => ['The job.job no field is required.'],
                'job.initial_priority' => ['The selected job.initial priority is invalid.'],
            ]
        ]);

        Queue::assertNothingPushed();

        $this->assertChangesEqualsFixture('request_logs', 'create_job__check_validation__request_logs_state.json', self::$originRequestLogs);
        $this->assertNoChanges('jobs', self::$originJobs);
        $this->assertNoChanges('job_operations', self::$originJobOperations);
    }

    public function testSendJobToSimproNewSite()
    {
        $this->mockSendJobToSimproNewSite();

        (new SendJobToSimproJob(1, $this->getJsonFixture('send_job_to_simpro__common__data_from_riverside.json')))->handle();

        $this->assertChangesEqualsFixture('request_logs', 'send_job_to_simpro__new_site__request_logs_state.json', self::$originRequestLogs);
        $this->assertChangesEqualsFixture('jobs', 'send_job_to_simpro__common__jobs_state.json', self::$originJobs);
        $this->assertChangesEqualsFixture('job_operations', 'send_job_to_simpro__common__job_operations_state.json', self::$originJobOperations);

        $this->assertCount(1, Queue::pushedJobs()[NotifyRiversideAboutCreatedJobJob::class]);

        Queue::assertPushed(NotifyRiversideAboutCreatedJobJob::class, function (NotifyRiversideAboutCreatedJobJob $job) {
            $this->assertEquals(1, $job->getJobId());
            $this->assertEquals(Carbon::parse('2018-11-11 11:11:11'), $job->getDate());

            return true;
        });
    }

    public function testSendJobToSimproSiteExists()
    {
        $this->mockSendJobToSimproSiteExists();

        (new SendJobToSimproJob(1, $this->getJsonFixture('send_job_to_simpro__common__data_from_riverside.json')))->handle();

        $this->assertChangesEqualsFixture('request_logs', 'send_job_to_simpro__site_exists__request_logs_state.json', self::$originRequestLogs);
        $this->assertChangesEqualsFixture('jobs', 'send_job_to_simpro__common__jobs_state.json', self::$originJobs);
        $this->assertChangesEqualsFixture('job_operations', 'send_job_to_simpro__common__job_operations_state.json', self::$originJobOperations);

        $this->assertCount(1, Queue::pushedJobs()[NotifyRiversideAboutCreatedJobJob::class]);

        Queue::assertPushed(NotifyRiversideAboutCreatedJobJob::class, function (NotifyRiversideAboutCreatedJobJob $job) {
            $this->assertEquals(1, $job->getJobId());
            $this->assertEquals(Carbon::parse('2018-11-11 11:11:11'), $job->getDate());

            return true;
        });
    }

    public function testSendJobToSimproPostSiteFailed()
    {
        $this->mockSendJobToSimproPostSiteFailed();

        try {
            (new SendJobToSimproJob(1, $this->getJsonFixture('send_job_to_simpro__common__data_from_riverside.json')))->handle();
        } catch (ApiClientHttpException $e) {}

        $this->assertChangesEqualsFixture('request_logs', 'send_job_to_simpro__post_site_failed__request_logs_state.json', self::$originRequestLogs);
        $this->assertNoChanges('jobs', self::$originJobs);
        $this->assertNoChanges('job_operations', self::$originJobOperations);

        Queue::assertNothingPushed();
    }

    public function testSendJobToSimproFindSiteFailed()
    {
        $this->mockSendJobToSimproFindSiteFailed();

        try {
            (new SendJobToSimproJob(1, $this->getJsonFixture('send_job_to_simpro__common__data_from_riverside.json')))->handle();
        } catch (ApiClientHttpException $e) {}

        $this->assertChangesEqualsFixture('request_logs', 'send_job_to_simpro__find_site_failed__request_logs_state.json', self::$originRequestLogs);
        $this->assertNoChanges('jobs', self::$originJobs);
        $this->assertNoChanges('job_operations', self::$originJobOperations);

        Queue::assertNothingPushed();
    }

    public function testSendJobToSimproPostJobFailed()
    {
        $this->mockSendJobToSimproPostJobFailed();

        try {
            (new SendJobToSimproJob(1, $this->getJsonFixture('send_job_to_simpro__common__data_from_riverside.json')))->handle();
        } catch (ApiClientHttpException $e) {}

        $this->assertChangesEqualsFixture('request_logs', 'send_job_to_simpro__post_job_failed__request_logs_state.json', self::$originRequestLogs);
        $this->assertNoChanges('jobs', self::$originJobs);
        $this->assertNoChanges('job_operations', self::$originJobOperations);

        Queue::assertNothingPushed();
    }

    public function testNotifyRiversideAboutCreatedJob()
    {
        $this->mockNotifyRiversideAboutCreatedJob(
            'mock_notify_riverside_about_create_job__request.xml',
            'mock_notify_riverside_about_create_job__response__success.xml'
        );

        (new NotifyRiversideAboutCreatedJobJob(1, Carbon::now()))->handle();

        $this->assertChangesEqualsFixture('request_logs', 'notify_riverside_about_created_job__request_logs_state.json', self::$originRequestLogs);
        $this->assertNoChanges('jobs', self::$originJobs);
        $this->assertChangesEqualsFixture('job_operations', 'notify_riverside_about_created_job__job_operations_state.json', self::$originJobOperations);
    }

    public function testNotifyRiversideAboutCreatedJobHttpErrorCode()
    {
        $this->mockNotifyRiversideAboutCreatedJob(
            'mock_notify_riverside_about_create_job__request.xml',
            'mock_notify_riverside_about_create_job__response__success.xml',
            Response::HTTP_INTERNAL_SERVER_ERROR
        );

        try {
            (new NotifyRiversideAboutCreatedJobJob(1, Carbon::now()))->handle();
        } catch (ApiClientHttpException $e) {}

        $this->assertChangesEqualsFixture('request_logs', 'notify_riverside_about_created_job__http_error_code__request_logs_state.json', self::$originRequestLogs);
        $this->assertNoChanges('jobs', self::$originJobs);
        $this->assertNoChanges('job_operations', self::$originJobOperations);
    }

    public function testNotifyRiversideAboutCreatedJobErrorInResponseBody()
    {
        $this->mockNotifyRiversideAboutCreatedJob(
            'mock_notify_riverside_about_create_job__request.xml',
            'mock_notify_riverside_about_create_job__response__failed.xml'
        );

        try {
            (new NotifyRiversideAboutCreatedJobJob(1, Carbon::now()))->handle();
        } catch (ApiClientHttpException $e) {}

        $this->assertChangesEqualsFixture('request_logs', 'notify_riverside_about_created_job__error_in_response_body__request_logs_state.json', self::$originRequestLogs);
        $this->assertNoChanges('jobs', self::$originJobs);
        $this->assertNoChanges('job_operations', self::$originJobOperations);
    }

    public function testSimproWebhookIncorrectSignature()
    {
        list ($data,) = $this->getJsonFixture('webhook_job.stage.complete__job_exists_in_local_db__request.json');

        $response = $this->json('post', '/simpro-webhook', $data, [
            'x-response-signature' => 'incorrect_signature',
        ]);

        $response->assertStatus(Response::HTTP_FORBIDDEN);

        Queue::assertNothingPushed();

        $this->assertChangesEqualsFixture('request_logs', 'webhook_job.stage.complete__incorrect_signature__request_logs_state.json', self::$originRequestLogs);
        $this->assertNoChanges('job_operations', self::$originJobOperations);
    }

    public function testSimproWebhookIncorrectNoSignatureHeader()
    {
        list ($data,) = $this->getJsonFixture('webhook_job.stage.complete__job_exists_in_local_db__request.json');

        $response = $this->json('post', '/simpro-webhook', $data);

        $response->assertStatus(Response::HTTP_FORBIDDEN);

        Queue::assertNothingPushed();

        $this->assertChangesEqualsFixture('request_logs', 'webhook_job.stage.complete__no_signature_header__request_logs_state.json', self::$originRequestLogs);
        $this->assertNoChanges('job_operations', self::$originJobOperations);
    }

    public function testSimproWebhookJobCompletedJobExistsInLocalDb()
    {
        list ($data, $signature) = $this->getJsonFixture('webhook_job.stage.complete__job_exists_in_local_db__request.json');

        $response = $this->json('post', '/simpro-webhook', $data, [
            'x-response-signature' => $signature,
        ]);

        $response->assertStatus(Response::HTTP_OK);

        $this->assertCount(1, Queue::pushedJobs());
        $this->assertCount(1, Queue::pushedJobs()[ProcessSimproWebhookJobCompletedJob::class]);

        Queue::assertPushed(ProcessSimproWebhookJobCompletedJob::class, function (ProcessSimproWebhookJobCompletedJob $job) {
            $this->assertEquals(2, $job->getJobId());

            return true;
        });

        $this->assertChangesEqualsFixture('request_logs', 'webhook_job.stage.complete__job_exists_in_local_db__request_logs_state.json', self::$originRequestLogs);
        $this->assertChangesEqualsFixture('job_operations', 'webhook_job.stage.complete__job_exists_in_local_db__job_operations_state.json', self::$originJobOperations);
    }

    public function testSimproWebhookJobCompletedJobNotExistsInLocalDb()
    {
        list ($data, $signature) = $this->getJsonFixture('webhook_job.stage.complete__job_not_exists_in_local_db__request.json');

        $response = $this->json('post', '/simpro-webhook', $data, [
            'x-response-signature' => $signature,
        ]);

        $response->assertStatus(Response::HTTP_OK);

        Queue::assertNothingPushed();

        $this->assertChangesEqualsFixture('request_logs', 'webhook_job.stage.complete__job_not_exists_in_local_db__request_logs_state.json', self::$originRequestLogs);
        $this->assertNoChanges('job_operations', self::$originJobOperations);
    }

    public function testSimproWebhookJobScheduleCreatedJobExistsInLocalDb()
    {
        list ($data, $signature) = $this->getJsonFixture('webhook_job.schedule.created__job_exists_in_local_db__request.json');

        $response = $this->json('post', '/simpro-webhook', $data, [
            'x-response-signature' => $signature,
        ]);

        $response->assertStatus(Response::HTTP_OK);

        $this->assertCount(1, Queue::pushedJobs());
        $this->assertCount(1, Queue::pushedJobs()[ProcessSimproWebhookJobScheduleCreatedJob::class]);

        Queue::assertPushed(ProcessSimproWebhookJobScheduleCreatedJob::class, function (ProcessSimproWebhookJobScheduleCreatedJob $job) {
            $this->assertEquals(2, $job->getJobId());
            $this->assertEquals(4321, $job->getSimproJobScheduleId());

            return true;
        });

        $this->assertChangesEqualsFixture('request_logs', 'webhook_job.schedule.created__job_exists_in_local_db__request_logs_state.json', self::$originRequestLogs);
        $this->assertChangesEqualsFixture('job_operations', 'webhook_job.schedule.created__job_exists_in_local_db__job_operations_state.json', self::$originJobOperations);
    }

    public function testSimproWebhookJobScheduleCreatedJobNotExistsInLocalDb()
    {
        list ($data, $signature) = $this->getJsonFixture('webhook_job.schedule.created__job_not_exists_in_local_db__request.json');

        $response = $this->json('post', '/simpro-webhook', $data, [
            'x-response-signature' => $signature,
        ]);

        $response->assertStatus(Response::HTTP_OK);

        Queue::assertNothingPushed();

        $this->assertChangesEqualsFixture('request_logs', 'webhook_job.schedule.created__job_not_exists_in_local_db__request_logs_state.json', self::$originRequestLogs);
        $this->assertNoChanges('job_operations', self::$originJobOperations);
    }

    public function testSimproWebhookJobStatusChangedStatusIdAbandon()
    {
        list ($data, $signature) = $this->getJsonFixture('webhook_job.status__status_id_abandon__request.json');

        $response = $this->json('post', '/simpro-webhook', $data, [
            'x-response-signature' => $signature,
        ]);

        $response->assertStatus(Response::HTTP_OK);

        $this->assertCount(1, Queue::pushedJobs());
        $this->assertCount(1, Queue::pushedJobs()[ProcessSimproWebhookJobStatusChangedJob::class]);

        Queue::assertPushed(ProcessSimproWebhookJobStatusChangedJob::class, function (ProcessSimproWebhookJobStatusChangedJob $job) {
            $this->assertEquals(2, $job->getJobId());
            $this->assertEquals(34, $job->getSimproStatusId());

            return true;
        });

        $this->assertChangesEqualsFixture('request_logs', 'webhook_job.status__status_id_abandon__request_logs_state.json', self::$originRequestLogs);
        $this->assertChangesEqualsFixture('job_operations', 'webhook_job.status__status_id_abandon__job_operations_state.json', self::$originJobOperations);
    }

    public function testSimproWebhookJobStatusChangedStatusIdNoAccess()
    {
        list ($data, $signature) = $this->getJsonFixture('webhook_job.status__status_id_no_access__request.json');

        $response = $this->json('post', '/simpro-webhook', $data, [
            'x-response-signature' => $signature,
        ]);

        $response->assertStatus(Response::HTTP_OK);

        $this->assertCount(1, Queue::pushedJobs());
        $this->assertCount(1, Queue::pushedJobs()[ProcessSimproWebhookJobStatusChangedJob::class]);

        Queue::assertPushed(ProcessSimproWebhookJobStatusChangedJob::class, function (ProcessSimproWebhookJobStatusChangedJob $job) {
            $this->assertEquals(2, $job->getJobId());
            $this->assertEquals(38, $job->getSimproStatusId());

            return true;
        });

        $this->assertChangesEqualsFixture('request_logs', 'webhook_job.status__status_id_no_access__request_logs_state.json', self::$originRequestLogs);
        $this->assertChangesEqualsFixture('job_operations', 'webhook_job.status__status_id_no_access__job_operations_state.json', self::$originJobOperations);
    }

    public function testSimproWebhookJobStatusChangedJobNotExistsInLocalDb()
    {
        list ($data, $signature) = $this->getJsonFixture('webhook_job.status__job_not_exists_in_local_db__request.json');

        $response = $this->json('post', '/simpro-webhook', $data, [
            'x-response-signature' => $signature,
        ]);

        $response->assertStatus(Response::HTTP_OK);

        Queue::assertNothingPushed();

        $this->assertChangesEqualsFixture('request_logs', 'webhook_job.status__job_not_exists_in_local_db__request_logs_state.json', self::$originRequestLogs);
        $this->assertNoChanges('job_operations', self::$originJobOperations);
    }

    public function testSimproWebhookJobStatusChangedStatusIdSkipProcessing()
    {
        list ($data, $signature) = $this->getJsonFixture('webhook_job.status__status_id_skip_processing__request.json');

        $response = $this->json('post', '/simpro-webhook', $data, [
            'x-response-signature' => $signature,
        ]);

        $response->assertStatus(Response::HTTP_OK);

        Queue::assertNothingPushed();

        $this->assertChangesEqualsFixture('request_logs', 'webhook_job.status__status_id_skip_processing__request_logs_state.json', self::$originRequestLogs);
        $this->assertNoChanges('job_operations', self::$originJobOperations);
    }

    public function testProcessingSimproWebhookJobCompleted()
    {
        $this->mockHttpRequestService($this->getJsonFixture('mock_webhook_job.stage.complete__simpro_and_riverside_requests.json'));

        (new ProcessSimproWebhookJobCompletedJob(2))->handle();

        $this->assertChangesEqualsFixture('request_logs', 'webhook_job.stage.complete__request_logs_state.json', self::$originRequestLogs);
        $this->assertChangesEqualsFixture('job_operations', 'webhook_job.stage.complete__job_operations_logs_state.json', self::$originJobOperations);
    }

    public function testProcessingSimproWebhookJobCompletedNoCostCenters()
    {
        $this->mockHttpRequestService($this->getJsonFixture('mock_webhook_job.stage.complete__no_cost_centers__simpro_and_riverside_requests.json'));

        (new ProcessSimproWebhookJobCompletedJob(2))->handle();

        $this->assertChangesEqualsFixture('request_logs', 'webhook_job.stage.complete__no_cost_centers__request_logs_state.json', self::$originRequestLogs);
        $this->assertChangesEqualsFixture('job_operations', 'webhook_job.stage.complete__job_operations_logs_state.json', self::$originJobOperations);
    }

    public function testProcessingSimproWebhookJobCompletedNoSections()
    {
        $this->mockHttpRequestService($this->getJsonFixture('mock_webhook_job.stage.complete__no_sections__simpro_and_riverside_requests.json'));

        (new ProcessSimproWebhookJobCompletedJob(2))->handle();

        $this->assertChangesEqualsFixture('request_logs', 'webhook_job.stage.complete__no_sections__request_logs_state.json', self::$originRequestLogs);
        $this->assertChangesEqualsFixture('job_operations', 'webhook_job.stage.complete__job_operations_logs_state.json', self::$originJobOperations);
    }

    public function testProcessingSimproWebhookJobScheduleCreated()
    {
        $this->mockHttpRequestService($this->getJsonFixture('mock_webhook_job.schedule.created__simpro_and_riverside_requests.json'));

        (new ProcessSimproWebhookJobScheduleCreatedJob(2, 4321))->handle();

        $this->assertChangesEqualsFixture('request_logs', 'webhook_job.schedule.complete__no_sections__request_logs_state.json', self::$originRequestLogs);
        $this->assertChangesEqualsFixture('jobs', 'webhook_job.schedule.complete__jobs_state.json', self::$originJobs);
        $this->assertChangesEqualsFixture('job_operations', 'webhook_job.schedule.complete__job_operations_state.json', self::$originJobOperations);
    }

    public function testProcessingSimproWebhookJobStatusChangedStatusAbandonAppointmentNotSent()
    {
        $this->mockHttpRequestService($this->getJsonFixture('mock_webhook_job.status__abandon__appointment_not_sent__simpro_and_riverside_requests.json'));

        (new ProcessSimproWebhookJobStatusChangedJob(2, 34))->handle();

        $this->assertChangesEqualsFixture('request_logs', 'webhook_job.status__abandon__appointment_not_sent__request_logs_state.json', self::$originRequestLogs);
        $this->assertChangesEqualsFixture('jobs', 'webhook_job.status__abandon__appointment_not_sent__jobs_state.json', self::$originJobs);
        $this->assertChangesEqualsFixture('job_operations', 'webhook_job.status__abandon__appointment_not_sent__job_operations_state.json', self::$originJobOperations);
    }

    public function testProcessingSimproWebhookJobStatusChangedStatusAbandonAppointmentSent()
    {
        $this->mockHttpRequestService($this->getJsonFixture('mock_webhook_job.status__abandon__appointment_sent__simpro_and_riverside_requests.json'));

        (new ProcessSimproWebhookJobStatusChangedJob(3, 34))->handle();

        $this->assertChangesEqualsFixture('request_logs', 'webhook_job.status__abandon__appointment_sent__request_logs_state.json', self::$originRequestLogs);
        $this->assertChangesEqualsFixture('jobs', 'webhook_job.status__abandon__appointment_sent__jobs_state.json', self::$originJobs);
        $this->assertChangesEqualsFixture('job_operations', 'webhook_job.status__abandon__appointment_sent__job_operations_state.json', self::$originJobOperations);
    }

    public function testProcessingSimproWebhookJobStatusChangedStatusNoAccess()
    {
        $this->mockHttpRequestService($this->getJsonFixture('mock_webhook_job.status__no_access__simpro_and_riverside_requests.json'));

        (new ProcessSimproWebhookJobStatusChangedJob(2, 38))->handle();

        $this->assertChangesEqualsFixture('request_logs', 'webhook_job.status__no_access__request_logs_state.json', self::$originRequestLogs);
        $this->assertChangesEqualsFixture('jobs', 'webhook_job.status__no_access__jobs_state.json', self::$originJobs);
        $this->assertChangesEqualsFixture('job_operations', 'webhook_job.status__no_access__job_operations_state.json', self::$originJobOperations);
    }

    public function testProcessingSimproWebhookJobStatusChangedStatusNoAccessManyLogEntries()
    {
        $this->mockHttpRequestService($this->getJsonFixture('mock_webhook_job.status__no_access__many_log_entries__simpro_and_riverside_requests.json'));

        (new ProcessSimproWebhookJobStatusChangedJob(2, 38))->handle();

        $this->assertChangesEqualsFixture('request_logs', 'webhook_job.status__no_access__many_log_entries__request_logs_state.json', self::$originRequestLogs);
        $this->assertChangesEqualsFixture('jobs', 'webhook_job.status__no_access__many_log_entries__jobs_state.json', self::$originJobs);
        $this->assertChangesEqualsFixture('job_operations', 'webhook_job.status__no_access__many_log_entries__job_operations_state.json', self::$originJobOperations);
    }
}
