<?php

namespace App\Tests;

use App\Exceptions\ApiClientHttpException;
use App\Jobs\NotifyRiversideAboutCreatedJobJob;
use App\Jobs\ProcessSimproWebhookJob;
use App\Jobs\SendJobToSimproJob;
use App\Resources\SimproApi\SimproWebhookResource;
use App\Tests\Support\MockRiversideApiClientTrait;
use App\Tests\Support\MockSimproApiClientTrait;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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

        $this->assertEqualsFixture('create_job__response.json', $response->json());

        $this->assertCount(7, Queue::pushedJobs()[SendJobToSimproJob::class]);

        $expectedQueueJobsData = $this->getJsonFixture('create_job__queue_jobs_data.json');

        Queue::assertPushed(SendJobToSimproJob::class, function (SendJobToSimproJob $job) use (&$expectedQueueJobsData) {
            $expectedData = array_shift($expectedQueueJobsData);

            $this->assertEquals($expectedData['job_id'], $job->getJobId());
            $this->assertEquals($expectedData['data_from_riverside'], $job->getDataFromRiverside());

            return true;
        });

        $this->assertChangesEqualsFixture('request_logs', 'create_job__request_logs_state.json', self::$originRequestLogs);
        $this->assertChangesEqualsFixture('jobs', 'create_job__jobs_state.json', self::$originJobs);
        $this->assertChangesEqualsFixture('job_operations', 'create_job__job_operations_state.json', self::$originJobOperations);
    }

    public function testCreateAllJobsAlreadyExist()
    {
        $response = $this->xml('post', '/jobs', 'create_job__all_jobs_already_exist__request.xml', ['HEADER_NAME' => 'HEADER_VALUE']);

        $response->assertStatus(Response::HTTP_OK);

        $this->assertEqualsFixture('create_job__all_jobs_already_exist__response.json', $response->json());

        Queue::assertNothingPushed();

        $this->assertChangesEqualsFixture('request_logs', 'create_job__all_jobs_already_exist__request_logs_state.json', self::$originRequestLogs);
        $this->assertChangesEqualsFixture('jobs', 'create_job__all_jobs_already_exist__jobs_state.json', self::$originJobs);
        $this->assertNoChanges('job_operations', self::$originJobOperations);
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

        $this->assertEqualsFixture('create_job__check_validation__response.json', $response->json());

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

    public function testSendJobToSimproFailed()
    {
        (new SendJobToSimproJob(1, []))->failed();

        $this->assertChangesEqualsFixture('job_operations', 'send_job_to_simpro__failed__job_operations_state.json', self::$originJobOperations);
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

    public function testNotifyRiversideAboutCreatedJobFailed()
    {
        (new NotifyRiversideAboutCreatedJobJob(1, Carbon::now()))->failed();

        $this->assertChangesEqualsFixture('job_operations', 'notify_riverside_about_created_job__failed__job_operations_state.json', self::$originJobOperations);
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

        Queue::assertPushed(ProcessSimproWebhookJob::class, function (ProcessSimproWebhookJob $job) {
            $this->assertEqualsFixture('webhook_job.stage.complete__job_exists_in_local_db__queue_job_data.json', $job->getSimproWebhookResource());

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

        Queue::assertPushed(ProcessSimproWebhookJob::class, function (ProcessSimproWebhookJob $job) {
            $this->assertEqualsFixture('webhook_job.schedule.created__job_exists_in_local_db__queue_job_data.json', $job->getSimproWebhookResource());

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

        Queue::assertPushed(ProcessSimproWebhookJob::class, function (ProcessSimproWebhookJob $job) {
            $this->assertEqualsFixture('webhook_job.status__status_id_abandon__queue_job_data.json', $job->getSimproWebhookResource());

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

        Queue::assertPushed(ProcessSimproWebhookJob::class, function (ProcessSimproWebhookJob $job) {
            $this->assertEqualsFixture('webhook_job.status__status_id_no_access__queue_job_data.json', $job->getSimproWebhookResource());

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

    public function testSimproWebhookJobStatusChangedUnknownStatus()
    {
        list ($data, $signature) = $this->getJsonFixture('webhook_job.status__unknown_status__request.json');

        $response = $this->json('post', '/simpro-webhook', $data, [
            'x-response-signature' => $signature,
        ]);

        $response->assertStatus(Response::HTTP_OK);

        $this->assertCount(1, Queue::pushedJobs());

        Queue::assertPushed(ProcessSimproWebhookJob::class, function (ProcessSimproWebhookJob $job) {
            $this->assertEqualsFixture('webhook_job.status__unknown_status__queue_job_data.json', $job->getSimproWebhookResource());

            return true;
        });

        $this->assertChangesEqualsFixture('request_logs', 'webhook_job.status__unknown_status__request_logs_state.json', self::$originRequestLogs);
        $this->assertNoChanges('job_operations', self::$originJobOperations);
    }

    public function testProcessingSimproWebhookJobCompleted()
    {
        $this->mockHttpRequestService($this->getJsonFixture('process_webhook_job.stage.complete__requests_chain.json'));

        $webhookData = $this->getJsonFixture('process_webhook_job.stage.complete__webhook_data.json');
        $webhookResource = SimproWebhookResource::makeFromSimproRequestData($webhookData);
        (new ProcessSimproWebhookJob($webhookResource))->handle();

        $this->assertChangesEqualsFixture('request_logs', 'process_webhook_job.stage.complete__request_logs_state.json', self::$originRequestLogs);
        $this->assertChangesEqualsFixture('job_operations', 'process_webhook_job.stage.complete__job_operations_logs_state.json', self::$originJobOperations);
    }

    public function testProcessingSimproWebhookJobCompletedNoCostCenters()
    {
        $this->mockHttpRequestService($this->getJsonFixture('process_webhook_job.stage.complete__no_cost_centers__requests_chain.json'));

        $webhookData = $this->getJsonFixture('process_webhook_job.stage.complete__no_cost_centers__webhook_data.json');
        $webhookResource = SimproWebhookResource::makeFromSimproRequestData($webhookData);
        (new ProcessSimproWebhookJob($webhookResource))->handle();

        $this->assertChangesEqualsFixture('request_logs', 'process_webhook_job.stage.complete__no_cost_centers__request_logs_state.json', self::$originRequestLogs);
        $this->assertChangesEqualsFixture('job_operations', 'process_webhook_job.stage.complete__no_cost_centers__job_operations_logs_state.json', self::$originJobOperations);
    }

    public function testProcessingSimproWebhookJobCompletedNoSections()
    {
        $this->mockHttpRequestService($this->getJsonFixture('process_webhook_job.stage.complete__no_sections__requests_chain.json'));

        $webhookData = $this->getJsonFixture('process_webhook_job.stage.complete__no_sections__webhook_data.json');
        $webhookResource = SimproWebhookResource::makeFromSimproRequestData($webhookData);
        (new ProcessSimproWebhookJob($webhookResource))->handle();

        $this->assertChangesEqualsFixture('request_logs', 'process_webhook_job.stage.complete__no_sections__request_logs_state.json', self::$originRequestLogs);
        $this->assertChangesEqualsFixture('job_operations', 'process_webhook_job.stage.complete__no_sections__job_operations_logs_state.json', self::$originJobOperations);
    }

    public function testProcessingSimproWebhookJobScheduleCreatedAppointmentSent()
    {
        DB::table('jobs')->where('id', 2)->update(['is_appointment_sent' => true]);
        $originJobs = $this->getDataSet('jobs');

        $this->mockHttpRequestService($this->getJsonFixture('process_webhook_job.schedule.created__appointment_sent__requests_chain.json'));

        $webhookData = $this->getJsonFixture('process_webhook_job.schedule.created__appointment_sent__webhook_data.json');
        $webhookResource = SimproWebhookResource::makeFromSimproRequestData($webhookData);
        (new ProcessSimproWebhookJob($webhookResource))->handle();

        $this->assertChangesEqualsFixture('request_logs', 'process_webhook_job.schedule.created__appointment_sent__request_logs_state.json', self::$originRequestLogs);
        $this->assertNoChanges('jobs', $originJobs);
        $this->assertChangesEqualsFixture('job_operations', 'process_webhook_job.schedule.created__appointment_sent__job_operations_state.json', self::$originJobOperations);
    }

    public function testProcessingSimproWebhookJobScheduleCreatedAppointmentNotSent()
    {
        $this->mockHttpRequestService($this->getJsonFixture('process_webhook_job.schedule.created__appointment_not_sent__requests_chain.json'));

        $webhookData = $this->getJsonFixture('process_webhook_job.schedule.created__appointment_not_sent__webhook_data.json');
        $webhookResource = SimproWebhookResource::makeFromSimproRequestData($webhookData);
        (new ProcessSimproWebhookJob($webhookResource))->handle();

        $this->assertChangesEqualsFixture('request_logs', 'process_webhook_job.schedule.created__appointment_not_sent__request_logs_state.json', self::$originRequestLogs);
        $this->assertChangesEqualsFixture('jobs', 'process_webhook_job.schedule.created__appointment_not_sent__jobs_state.json', self::$originJobs);
        $this->assertChangesEqualsFixture('job_operations', 'process_webhook_job.schedule.created__appointment_not_sent__job_operations_state.json', self::$originJobOperations);
    }

    public function testProcessingSimproWebhookJobStatusChangedStatusAbandonAppointmentNotSent()
    {
        $this->mockHttpRequestService($this->getJsonFixture('process_webhook_job.status__abandon__appointment_not_sent__requests_chain.json'));

        $webhookData = $this->getJsonFixture('process_webhook_job.status__abandon__appointment_not_sent__webhook_data.json');
        $webhookResource = SimproWebhookResource::makeFromSimproRequestData($webhookData);
        (new ProcessSimproWebhookJob($webhookResource))->handle();

        $this->assertChangesEqualsFixture('request_logs', 'process_webhook_job.status__abandon__appointment_not_sent__request_logs_state.json', self::$originRequestLogs);
        $this->assertChangesEqualsFixture('jobs', 'process_webhook_job.status__abandon__appointment_not_sent__jobs_state.json', self::$originJobs);
        $this->assertChangesEqualsFixture('job_operations', 'process_webhook_job.status__abandon__appointment_not_sent__job_operations_state.json', self::$originJobOperations);
    }

    public function testProcessingSimproWebhookJobStatusChangedStatusAbandonAppointmentSent()
    {
        $this->mockHttpRequestService($this->getJsonFixture('process_webhook_job.status__abandon__appointment_sent__requests_chain.json'));

        $webhookData = $this->getJsonFixture('process_webhook_job.status__abandon__appointment_sent__webhook_data.json');
        $webhookResource = SimproWebhookResource::makeFromSimproRequestData($webhookData);
        (new ProcessSimproWebhookJob($webhookResource))->handle();

        $this->assertChangesEqualsFixture('request_logs', 'process_webhook_job.status__abandon__appointment_sent__request_logs_state.json', self::$originRequestLogs);
        $this->assertChangesEqualsFixture('jobs', 'process_webhook_job.status__abandon__appointment_sent__jobs_state.json', self::$originJobs);
        $this->assertChangesEqualsFixture('job_operations', 'process_webhook_job.status__abandon__appointment_sent__job_operations_state.json', self::$originJobOperations);
    }

    public function testProcessingSimproWebhookJobStatusChangedStatusNoAccess()
    {
        $this->mockHttpRequestService($this->getJsonFixture('process_webhook_job.status__no_access__requests_chain.json'));

        $webhookData = $this->getJsonFixture('process_webhook_job.status__no_access__webhook_data.json');
        $webhookResource = SimproWebhookResource::makeFromSimproRequestData($webhookData);
        (new ProcessSimproWebhookJob($webhookResource))->handle();

        $this->assertChangesEqualsFixture('request_logs', 'process_webhook_job.status__no_access__request_logs_state.json', self::$originRequestLogs);
        $this->assertChangesEqualsFixture('jobs', 'process_webhook_job.status__no_access__jobs_state.json', self::$originJobs);
        $this->assertChangesEqualsFixture('job_operations', 'process_webhook_job.status__no_access__job_operations_state.json', self::$originJobOperations);
    }

    public function testProcessingSimproWebhookJobStatusChangedStatusNoAccessManyLogEntries()
    {
        $this->mockHttpRequestService($this->getJsonFixture('process_webhook_job.status__no_access__many_log_entries__requests_chain.json'));

        $webhookData = $this->getJsonFixture('process_webhook_job.status__no_access__many_log_entries__webhook_data.json');
        $webhookResource = SimproWebhookResource::makeFromSimproRequestData($webhookData);
        (new ProcessSimproWebhookJob($webhookResource))->handle();

        $this->assertChangesEqualsFixture('request_logs', 'process_webhook_job.status__no_access__many_log_entries__request_logs_state.json', self::$originRequestLogs);
        $this->assertChangesEqualsFixture('jobs', 'process_webhook_job.status__no_access__many_log_entries__jobs_state.json', self::$originJobs);
        $this->assertChangesEqualsFixture('job_operations', 'process_webhook_job.status__no_access__many_log_entries__job_operations_state.json', self::$originJobOperations);
    }
}
