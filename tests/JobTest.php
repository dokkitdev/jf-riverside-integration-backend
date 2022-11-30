<?php

namespace App\Tests;

use App\Jobs\ProcessSimproWebhookJob;
use App\Resources\SimproApi\SimproWebhookResource;
use App\Tests\Support\MockRiversideApiClientTrait;
use App\Tests\Support\MockSimproApiClientTrait;
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

    public function testCreateWithNewSite()
    {
        $this->mockHttpRequestService($this->getJsonFixture('create_job__new_site__requests_chain.json'));

        $response = $this->xml('post', '/jobs', 'create_job__new_site__request.xml', ['HEADER_NAME' => 'HEADER_VALUE']);

        $response->assertStatus(Response::HTTP_OK);

        $this->assertEqualsFixture('create_job__new_site__response.json', $response->json());

        Queue::assertNothingPushed();

        $this->assertChangesEqualsFixture('request_logs', 'create_job__new_site__request_logs_state.json', self::$originRequestLogs);
        $this->assertChangesEqualsFixture('jobs', 'create_job__new_site__jobs_state.json', self::$originJobs);
        $this->assertChangesEqualsFixture('job_operations', 'create_job__new_site__job_operations_state.json', self::$originJobOperations);
    }

    public function testCreateWithExistedSite()
    {
        $this->mockHttpRequestService($this->getJsonFixture('create_job__existed_site__requests_chain.json'));

        $response = $this->xml('post', '/jobs', 'create_job__existed_site__request.xml', ['HEADER_NAME' => 'HEADER_VALUE']);

        $response->assertStatus(Response::HTTP_OK);

        $this->assertEqualsFixture('create_job__existed_site__response.json', $response->json());

        Queue::assertNothingPushed();

        $this->assertChangesEqualsFixture('request_logs', 'create_job__existed_site__request_logs_state.json', self::$originRequestLogs);
        $this->assertChangesEqualsFixture('jobs', 'create_job__existed_site__jobs_state.json', self::$originJobs);
        $this->assertChangesEqualsFixture('job_operations', 'create_job__existed_site__job_operations_state.json', self::$originJobOperations);
    }

    public function testCreateMultipleJobs()
    {
        $response = $this->xml('post', '/jobs', 'create_job__multiple_jobs__request.xml', ['HEADER_NAME' => 'HEADER_VALUE']);

        $response->assertStatus(Response::HTTP_BAD_REQUEST);

        $this->assertEqualsFixture('create_job__multiple_jobs__response.json', $response->json());

        Queue::assertNothingPushed();

        $this->assertChangesEqualsFixture('request_logs', 'create_job__multiple_jobs__request_logs_state.json', self::$originRequestLogs);
        $this->assertNoChanges('jobs', self::$originJobs);
        $this->assertNoChanges('job_operations', self::$originJobOperations);
    }

    public function testCreateInvalidXml()
    {
        $response = $this->xml('post', '/jobs', 'create_job__invalid_xml__request.xml', ['HEADER_NAME' => 'HEADER_VALUE']);

        $response->assertStatus(Response::HTTP_BAD_REQUEST);

        $this->assertEqualsFixture('create_job__invalid_xml__response.json', $response->json());

        Queue::assertNothingPushed();

        $this->assertChangesEqualsFixture('request_logs', 'create_job__invalid_xml__request_logs_state.json', self::$originRequestLogs);
        $this->assertNoChanges('jobs', self::$originJobs);
        $this->assertNoChanges('job_operations', self::$originJobOperations);
    }

    public function testCreateCheckValidation()
    {
        $response = $this->xml('post', '/jobs', 'create_job__check_validation__request.xml', ['HEADER_NAME' => 'HEADER_VALUE']);

        $response->assertStatus(Response::HTTP_BAD_REQUEST);

        $this->assertEqualsFixture('create_job__check_validation__response.json', $response->json());

        Queue::assertNothingPushed();

        $this->assertChangesEqualsFixture('request_logs', 'create_job__check_validation__request_logs_state.json', self::$originRequestLogs);
        $this->assertNoChanges('jobs', self::$originJobs);
        $this->assertNoChanges('job_operations', self::$originJobOperations);
    }

    public function testCreateSimproRequestFailed()
    {
        $this->mockHttpRequestService($this->getJsonFixture('create_job__simpro_request_failed__requests_chain.json'));

        $response = $this->xml('post', '/jobs', 'create_job__simpro_request_failed__request.xml', ['HEADER_NAME' => 'HEADER_VALUE']);

        $response->assertStatus(Response::HTTP_BAD_REQUEST);

        $this->assertEqualsFixture('create_job__simpro_request_failed__response.json', $response->json());

        Queue::assertNothingPushed();

        $this->assertChangesEqualsFixture('request_logs', 'create_job__simpro_request_failed__request_logs_state.json', self::$originRequestLogs);
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

        Queue::assertNothingPushed();

        $this->assertChangesEqualsFixture('request_logs', 'webhook_job.status__unknown_status__request_logs_state.json', self::$originRequestLogs);
        $this->assertChangesEqualsFixture('job_operations', 'webhook_job.status__unknown_status__job_operations_state.json', self::$originJobOperations);
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

    public function testProcessingSimproWebhookJobCompletedEmptyPartNo()
    {
        $this->mockHttpRequestService($this->getJsonFixture('process_webhook_job.stage.complete__empty_part_no__requests_chain.json'));

        $webhookData = $this->getJsonFixture('process_webhook_job.stage.complete__empty_part_no__webhook_data.json');
        $webhookResource = SimproWebhookResource::makeFromSimproRequestData($webhookData);
        (new ProcessSimproWebhookJob($webhookResource))->handle();

        $this->assertChangesEqualsFixture('request_logs', 'process_webhook_job.stage.complete__empty_part_no__request_logs_state.json', self::$originRequestLogs);
        $this->assertChangesEqualsFixture('job_operations', 'process_webhook_job.stage.complete__empty_part_no__job_operations_logs_state.json', self::$originJobOperations);
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

    public function testProcessingSimproWebhookJobScheduleCreatedRiversideAlreadyHasAppointment()
    {
        $this->mockHttpRequestService($this->getJsonFixture('process_webhook_job.schedule.created__riverside_already_has_appointment__requests_chain.json'));

        $webhookData = $this->getJsonFixture('process_webhook_job.schedule.created__riverside_already_has_appointment__webhook_data.json');
        $webhookResource = SimproWebhookResource::makeFromSimproRequestData($webhookData);
        (new ProcessSimproWebhookJob($webhookResource))->handle();

        $this->assertChangesEqualsFixture('request_logs', 'process_webhook_job.schedule.created__riverside_already_has_appointment__request_logs_state.json', self::$originRequestLogs);
        $this->assertChangesEqualsFixture('jobs', 'process_webhook_job.schedule.created__riverside_already_has_appointment__jobs_state.json', self::$originJobs);
        $this->assertChangesEqualsFixture('job_operations', 'process_webhook_job.schedule.created__riverside_already_has_appointment__job_operations_state.json', self::$originJobOperations);
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

    public function testProcessingSimproWebhookJobStatusChangedNotUsedStatus()
    {
        $webhookData = $this->getJsonFixture('process_webhook_job.status__not_used_status__webhook_data.json');
        $webhookResource = SimproWebhookResource::makeFromSimproRequestData($webhookData);
        (new ProcessSimproWebhookJob($webhookResource))->handle();

        $this->assertNoChanges('request_logs', self::$originRequestLogs);
        $this->assertNoChanges('jobs', self::$originJobs);
        $this->assertChangesEqualsFixture('job_operations', 'process_webhook_job.status__not_used_status__job_operations_state.json', self::$originJobOperations);
    }

    public function testProcessingSimproWebhookNotUsedType()
    {
        $webhookData = $this->getJsonFixture('process_webhook__not_used_type__webhook_data.json');
        $webhookResource = SimproWebhookResource::makeFromSimproRequestData($webhookData);
        (new ProcessSimproWebhookJob($webhookResource))->handle();

        $this->assertNoChanges('request_logs', self::$originRequestLogs);
        $this->assertNoChanges('jobs', self::$originJobs);
        $this->assertChangesEqualsFixture('job_operations', 'process_webhook__not_used_type__job_operations_state.json', self::$originJobOperations);
    }
}
