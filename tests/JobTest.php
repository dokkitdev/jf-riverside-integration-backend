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
    protected static Collection $originJobOperationRequestLog;

    protected int $nextJobOperationId = 5;

    public function setUp(): void
    {
        parent::setUp();

        self::$originRequestLogs = self::$originRequestLogs ?? $this->getDataSet('request_logs');
        self::$originJobs = self::$originJobs ?? $this->getDataSet('jobs');
        self::$originJobOperations = self::$originJobOperations ?? $this->getDataSet('job_operations');
        self::$originJobOperationRequestLog = self::$originJobOperationRequestLog ?? $this->getDataSet('job_operation_request_log');
    }

    public function getTestCreateData()
    {
        return [
            ['test_case' => 'create_job__new_site'],
            ['test_case' => 'create_job__new_site__no_primary_contact_data'],
            ['test_case' => 'create_job__new_site__no_valuable_contact_data'],
            ['test_case' => 'create_job__new_site__no_contact_data_at_all'],
            ['test_case' => 'create_job__existed_site__no_primary_contact_in_simpro'],
            ['test_case' => 'create_job__existed_site__exists_primary_contact_in_simpro'],
            ['test_case' => 'create_job__existed_site__no_valuable_contact_data'],
        ];
    }

    /** @dataProvider getTestCreateData */
    public function testCreateWithNewSite($testCase)
    {
        $this->mockHttpRequestService($this->getJsonFixture("{$testCase}/requests_chain.json"));

        $response = $this->xml('post', '/jobs', "{$testCase}/request.xml", ['HEADER_NAME' => 'HEADER_VALUE']);

        $response->assertStatus(Response::HTTP_OK);

        $this->assertEqualsFixture("{$testCase}/response.json", $response->json());

        Queue::assertNothingPushed();

        $this->assertChangesEqualsFixture('request_logs', "{$testCase}/request_logs_state.json", self::$originRequestLogs);
        $this->assertChangesEqualsFixture('jobs', "{$testCase}/jobs_state.json", self::$originJobs);
        $this->assertChangesEqualsFixture('job_operations', "{$testCase}/job_operations_state.json", self::$originJobOperations);
        $this->assertChangesEqualsFixture('job_operation_request_log', "{$testCase}/job_operation_request_log_state.json", self::$originJobOperationRequestLog);
    }

    public function testCreateMultipleJobs()
    {
        $response = $this->xml('post', '/jobs', 'create_job__multiple_jobs__request.xml', ['HEADER_NAME' => 'HEADER_VALUE']);

        $response->assertStatus(Response::HTTP_BAD_REQUEST);

        $this->assertEqualsFixture('create_job__multiple_jobs__response.json', $response->json());

        Queue::assertNothingPushed();

        $this->assertChangesEqualsFixture('request_logs', 'create_job__multiple_jobs__request_logs_state.json', self::$originRequestLogs);
        $this->assertChangesEqualsFixture('job_operations', 'create_job__multiple_jobs__job_operations_state.json', self::$originJobOperations);
        $this->assertChangesEqualsFixture('job_operation_request_log', 'create_job__multiple_jobs__job_operation_request_log_state.json', self::$originJobOperationRequestLog);
        $this->assertNoChanges('jobs', self::$originJobs);
    }

    public function testCreateInvalidXml()
    {
        $response = $this->xml('post', '/jobs', 'create_job__invalid_xml__request.xml', ['HEADER_NAME' => 'HEADER_VALUE']);

        $response->assertStatus(Response::HTTP_BAD_REQUEST);

        $this->assertEqualsFixture('create_job__invalid_xml__response.json', $response->json());

        Queue::assertNothingPushed();

        $this->assertChangesEqualsFixture('request_logs', 'create_job__invalid_xml__request_logs_state.json', self::$originRequestLogs);
        $this->assertChangesEqualsFixture('job_operations', 'create_job__invalid_xml__job_operations_state.json', self::$originJobOperations);
        $this->assertChangesEqualsFixture('job_operation_request_log', 'create_job__invalid_xml__job_operation_request_log_state.json', self::$originJobOperationRequestLog);
        $this->assertNoChanges('jobs', self::$originJobs);
    }

    public function testCreateCheckValidation()
    {
        $response = $this->xml('post', '/jobs', 'create_job__check_validation__request.xml', ['HEADER_NAME' => 'HEADER_VALUE']);

        $response->assertStatus(Response::HTTP_BAD_REQUEST);

        $this->assertEqualsFixture('create_job__check_validation__response.json', $response->json());

        Queue::assertNothingPushed();

        $this->assertChangesEqualsFixture('request_logs', 'create_job__check_validation__request_logs_state.json', self::$originRequestLogs);
        $this->assertChangesEqualsFixture('job_operations', 'create_job__check_validation__job_operations_state.json', self::$originJobOperations);
        $this->assertChangesEqualsFixture('job_operation_request_log', 'create_job__check_validation__job_operation_request_log_state.json', self::$originJobOperationRequestLog);
        $this->assertNoChanges('jobs', self::$originJobs);
    }

    public function testCreateSimproRequestFailed()
    {
        $this->mockHttpRequestService($this->getJsonFixture('create_job__simpro_request_failed__requests_chain.json'));

        $response = $this->xml('post', '/jobs', 'create_job__simpro_request_failed__request.xml', ['HEADER_NAME' => 'HEADER_VALUE']);

        $response->assertStatus(Response::HTTP_BAD_REQUEST);

        $this->assertEqualsFixture('create_job__simpro_request_failed__response.json', $response->json());

        Queue::assertNothingPushed();

        $this->assertChangesEqualsFixture('request_logs', 'create_job__simpro_request_failed__request_logs_state.json', self::$originRequestLogs);
        $this->assertChangesEqualsFixture('job_operations', 'create_job__simpro_request_failed__job_operations_state.json', self::$originJobOperations);
        $this->assertChangesEqualsFixture('job_operation_request_log', 'create_job__simpro_request_failed__job_operation_request_log_state.json', self::$originJobOperationRequestLog);
        $this->assertNoChanges('jobs', self::$originJobs);
    }

    public function testSimproWebhookIncorrectSignature()
    {
        list ($data, $signature) = $this->getJsonFixture('webhook__incorrect_signature__request.json');

        $response = $this->json('post', '/simpro-webhook', $data, [
            'x-response-signature' => $signature,
        ]);

        $response->assertStatus(Response::HTTP_FORBIDDEN);

        Queue::assertNothingPushed();

        $this->assertChangesEqualsFixture('request_logs', 'webhook__incorrect_signature__request_logs_state.json', self::$originRequestLogs);
        $this->assertChangesEqualsFixture('job_operations', 'webhook__incorrect_signature__job_operations_state.json', self::$originJobOperations);
        $this->assertChangesEqualsFixture('job_operation_request_log', 'webhook__incorrect_signature__job_operation_request_log_state.json', self::$originJobOperationRequestLog);
    }

    public function testSimproWebhookIncorrectNoSignatureHeader()
    {
        list ($data,) = $this->getJsonFixture('webhook__no_signature_header__request.json');

        $response = $this->json('post', '/simpro-webhook', $data);

        $response->assertStatus(Response::HTTP_FORBIDDEN);

        Queue::assertNothingPushed();

        $this->assertChangesEqualsFixture('request_logs', 'webhook__no_signature_header__request_logs_state.json', self::$originRequestLogs);
        $this->assertChangesEqualsFixture('job_operations', 'webhook__no_signature_header__job_operations_state.json', self::$originJobOperations);
        $this->assertChangesEqualsFixture('job_operation_request_log', 'webhook__no_signature_header__job_operation_request_log_state.json', self::$originJobOperationRequestLog);
    }

    public function testSimproWebhookJobExistsInLocalDb()
    {
        list ($data, $signature) = $this->getJsonFixture('webhook__job_exists_in_local_db__request.json');

        $response = $this->json('post', '/simpro-webhook', $data, [
            'x-response-signature' => $signature,
        ]);

        $response->assertStatus(Response::HTTP_OK);

        $this->assertCount(1, Queue::pushedJobs());

        Queue::assertPushed(ProcessSimproWebhookJob::class, function (ProcessSimproWebhookJob $job) {
            $this->assertEqualsFixture('webhook__job_exists_in_local_db__queue_job_data.json', $job->getSimproWebhookResource());
            $this->assertEquals($this->nextJobOperationId, $job->getJobOperationId());

            return true;
        });

        $this->assertChangesEqualsFixture('request_logs', 'webhook__job_exists_in_local_db__request_logs_state.json', self::$originRequestLogs);
        $this->assertChangesEqualsFixture('job_operations', 'webhook__job_exists_in_local_db__job_operations_state.json', self::$originJobOperations);
        $this->assertChangesEqualsFixture('job_operation_request_log', 'webhook__job_exists_in_local_db__job_operation_request_log_state.json', self::$originJobOperationRequestLog);
    }

    public function testSimproWebhookJobNotExistsInLocalDb()
    {
        list ($data, $signature) = $this->getJsonFixture('webhook__job_not_exists_in_local_db__request.json');

        $response = $this->json('post', '/simpro-webhook', $data, [
            'x-response-signature' => $signature,
        ]);

        $response->assertStatus(Response::HTTP_OK);

        Queue::assertNothingPushed();

        $this->assertChangesEqualsFixture('request_logs', 'webhook__job_not_exists_in_local_db__request_logs_state.json', self::$originRequestLogs);
        $this->assertNoChanges('job_operations', self::$originJobOperations);
        $this->assertNoChanges('job_operation_request_log', self::$originJobOperationRequestLog);
    }

    public function testProcessingSimproWebhookJobCompleted()
    {
        $this->mockHttpRequestService($this->getJsonFixture('process_webhook_job.stage.complete__requests_chain.json'));

        $webhookData = $this->getJsonFixture('process_webhook_job.stage.complete__webhook_data.json');
        $webhookResource = SimproWebhookResource::makeFromSimproRequestData($webhookData);
        (new ProcessSimproWebhookJob($webhookResource, 4))->handle();

        $this->assertChangesEqualsFixture('request_logs', 'process_webhook_job.stage.complete__request_logs_state.json', self::$originRequestLogs);
        $this->assertChangesEqualsFixture('job_operations', 'process_webhook_job.stage.complete__job_operations_logs_state.json', self::$originJobOperations);
        $this->assertChangesEqualsFixture('job_operation_request_log', 'process_webhook_job.stage.complete__job_operation_request_log_state.json', self::$originJobOperationRequestLog);
    }

    public function testProcessingSimproWebhookJobCompletedNoCostCenters()
    {
        $this->mockHttpRequestService($this->getJsonFixture('process_webhook_job.stage.complete__no_cost_centers__requests_chain.json'));

        $webhookData = $this->getJsonFixture('process_webhook_job.stage.complete__no_cost_centers__webhook_data.json');
        $webhookResource = SimproWebhookResource::makeFromSimproRequestData($webhookData);
        (new ProcessSimproWebhookJob($webhookResource, 4))->handle();

        $this->assertChangesEqualsFixture('request_logs', 'process_webhook_job.stage.complete__no_cost_centers__request_logs_state.json', self::$originRequestLogs);
        $this->assertChangesEqualsFixture('job_operations', 'process_webhook_job.stage.complete__no_cost_centers__job_operations_logs_state.json', self::$originJobOperations);
        $this->assertChangesEqualsFixture('job_operation_request_log', 'process_webhook_job.stage.complete__no_cost_centers__job_operation_request_log_state.json', self::$originJobOperationRequestLog);
    }

    public function testProcessingSimproWebhookJobCompletedNoSections()
    {
        $this->mockHttpRequestService($this->getJsonFixture('process_webhook_job.stage.complete__no_sections__requests_chain.json'));

        $webhookData = $this->getJsonFixture('process_webhook_job.stage.complete__no_sections__webhook_data.json');
        $webhookResource = SimproWebhookResource::makeFromSimproRequestData($webhookData);
        (new ProcessSimproWebhookJob($webhookResource, 4))->handle();

        $this->assertChangesEqualsFixture('request_logs', 'process_webhook_job.stage.complete__no_sections__request_logs_state.json', self::$originRequestLogs);
        $this->assertChangesEqualsFixture('job_operations', 'process_webhook_job.stage.complete__no_sections__job_operations_logs_state.json', self::$originJobOperations);
        $this->assertChangesEqualsFixture('job_operation_request_log', 'process_webhook_job.stage.complete__no_sections__job_operation_request_log_state.json', self::$originJobOperationRequestLog);
    }

    public function testProcessingSimproWebhookJobCompletedEmptyPartNo()
    {
        $this->mockHttpRequestService($this->getJsonFixture('process_webhook_job.stage.complete__empty_part_no__requests_chain.json'));

        $webhookData = $this->getJsonFixture('process_webhook_job.stage.complete__empty_part_no__webhook_data.json');
        $webhookResource = SimproWebhookResource::makeFromSimproRequestData($webhookData);
        (new ProcessSimproWebhookJob($webhookResource, 4))->handle();

        $this->assertChangesEqualsFixture('request_logs', 'process_webhook_job.stage.complete__empty_part_no__request_logs_state.json', self::$originRequestLogs);
        $this->assertChangesEqualsFixture('job_operations', 'process_webhook_job.stage.complete__empty_part_no__job_operations_logs_state.json', self::$originJobOperations);
        $this->assertChangesEqualsFixture('job_operation_request_log', 'process_webhook_job.stage.complete__empty_part_no__job_operation_request_log_state.json', self::$originJobOperationRequestLog);
    }

    public function getTestProcessingSimproWebhookJobScheduleCreatedData()
    {
        return [
            [
                'test_case' => 'process_webhook__job_schedule_created__appointment_sent',
                'job_operation_id' => 3,
            ],
            [
                'test_case' => 'process_webhook__job_schedule_created__appointment_not_sent',
                'job_operation_id' => 4,
            ],
            [
                'test_case' => 'process_webhook__job_schedule_created__riverside_already_has_appointment',
                'job_operation_id' => 4,
            ],
        ];
    }

    /** @dataProvider getTestProcessingSimproWebhookJobScheduleCreatedData */
    public function testProcessingSimproWebhookJobScheduleCreatedAppointmentSent($testCase, $jobOperationId)
    {
        $this->mockHttpRequestService($this->getJsonFixture("{$testCase}/requests_chain.json"));

        $webhookData = $this->getJsonFixture("{$testCase}/webhook_data.json");
        $webhookResource = SimproWebhookResource::makeFromSimproRequestData($webhookData);
        (new ProcessSimproWebhookJob($webhookResource, $jobOperationId))->handle();

        $this->assertChangesEqualsFixture('request_logs', "{$testCase}/request_logs_state.json", self::$originRequestLogs);
        $this->assertChangesEqualsFixture('jobs', "{$testCase}/jobs_state.json", self::$originJobs);
        $this->assertChangesEqualsFixture('job_operations', "{$testCase}/job_operations_state.json", self::$originJobOperations);
        $this->assertChangesEqualsFixture('job_operation_request_log', "{$testCase}/job_operation_request_log_state.json", self::$originJobOperationRequestLog);
    }

    public function getTestProcessingSimproWebhookJobStatusChangedStatusAbandon()
    {
        return [
            [
                'test_case' => 'process_webhook__job_status__abandon__appointment_not_sent',
                'job_operation_id' => 4,
            ],
            [
                'test_case' => 'process_webhook__job_status__abandon__appointment_sent',
                'job_operation_id' => 3,
            ],
        ];
    }

    /** @dataProvider getTestProcessingSimproWebhookJobStatusChangedStatusAbandon */
    public function testProcessingSimproWebhookJobStatusChangedStatusAbandonAppointmentNotSent($testCase, $jobOperationId)
    {
        $this->mockHttpRequestService($this->getJsonFixture("{$testCase}/requests_chain.json"));

        $webhookData = $this->getJsonFixture("{$testCase}/webhook_data.json");
        $webhookResource = SimproWebhookResource::makeFromSimproRequestData($webhookData);
        (new ProcessSimproWebhookJob($webhookResource, $jobOperationId))->handle();

        $this->assertChangesEqualsFixture('request_logs', "{$testCase}/request_logs_state.json", self::$originRequestLogs);
        $this->assertChangesEqualsFixture('jobs', "{$testCase}/jobs_state.json", self::$originJobs);
        $this->assertChangesEqualsFixture('job_operations', "{$testCase}/job_operations_state.json", self::$originJobOperations);
        $this->assertChangesEqualsFixture('job_operation_request_log', "{$testCase}/job_operation_request_log_state.json", self::$originJobOperationRequestLog);
    }

    public function testProcessingSimproWebhookJobStatusChangedStatusNoAccess()
    {
        $this->mockHttpRequestService($this->getJsonFixture('process_webhook_job.status__no_access__requests_chain.json'));

        $webhookData = $this->getJsonFixture('process_webhook_job.status__no_access__webhook_data.json');
        $webhookResource = SimproWebhookResource::makeFromSimproRequestData($webhookData);
        (new ProcessSimproWebhookJob($webhookResource, 4))->handle();

        $this->assertChangesEqualsFixture('request_logs', 'process_webhook_job.status__no_access__request_logs_state.json', self::$originRequestLogs);
        $this->assertChangesEqualsFixture('jobs', 'process_webhook_job.status__no_access__jobs_state.json', self::$originJobs);
        $this->assertChangesEqualsFixture('job_operations', 'process_webhook_job.status__no_access__job_operations_state.json', self::$originJobOperations);
        $this->assertChangesEqualsFixture('job_operation_request_log', 'process_webhook_job.status__no_access__job_operation_request_log_state.json', self::$originJobOperationRequestLog);
    }

    public function testProcessingSimproWebhookJobStatusChangedStatusNoAccessManyLogEntries()
    {
        $this->mockHttpRequestService($this->getJsonFixture('process_webhook_job.status__no_access__many_log_entries__requests_chain.json'));

        $webhookData = $this->getJsonFixture('process_webhook_job.status__no_access__many_log_entries__webhook_data.json');
        $webhookResource = SimproWebhookResource::makeFromSimproRequestData($webhookData);
        (new ProcessSimproWebhookJob($webhookResource, 4))->handle();

        $this->assertChangesEqualsFixture('request_logs', 'process_webhook_job.status__no_access__many_log_entries__request_logs_state.json', self::$originRequestLogs);
        $this->assertChangesEqualsFixture('jobs', 'process_webhook_job.status__no_access__many_log_entries__jobs_state.json', self::$originJobs);
        $this->assertChangesEqualsFixture('job_operations', 'process_webhook_job.status__no_access__many_log_entries__job_operations_state.json', self::$originJobOperations);
        $this->assertChangesEqualsFixture('job_operation_request_log', 'process_webhook_job.status__no_access__many_log_entries__job_operation_request_log_state.json', self::$originJobOperationRequestLog);
    }

    public function testProcessingSimproWebhookJobStatusChangedNotUsedStatus()
    {
        $webhookData = $this->getJsonFixture('process_webhook_job.status__not_used_status__webhook_data.json');
        $webhookResource = SimproWebhookResource::makeFromSimproRequestData($webhookData);
        (new ProcessSimproWebhookJob($webhookResource, 4))->handle();

        $this->assertNoChanges('request_logs', self::$originRequestLogs);
        $this->assertNoChanges('jobs', self::$originJobs);
        $this->assertChangesEqualsFixture('job_operations', 'process_webhook_job.status__not_used_status__job_operations_state.json', self::$originJobOperations);
        $this->assertNoChanges('job_operation_request_log', self::$originJobOperationRequestLog);
    }

    public function testProcessingSimproWebhookNotUsedType()
    {
        $webhookData = $this->getJsonFixture('process_webhook__not_used_type__webhook_data.json');
        $webhookResource = SimproWebhookResource::makeFromSimproRequestData($webhookData);
        (new ProcessSimproWebhookJob($webhookResource, 4))->handle();

        $this->assertNoChanges('request_logs', self::$originRequestLogs);
        $this->assertNoChanges('jobs', self::$originJobs);
        $this->assertChangesEqualsFixture('job_operations', 'process_webhook__not_used_type__job_operations_state.json', self::$originJobOperations);
        $this->assertNoChanges('job_operation_request_log', self::$originJobOperationRequestLog);
    }
}
