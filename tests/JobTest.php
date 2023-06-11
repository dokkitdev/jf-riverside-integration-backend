<?php

namespace App\Tests;

use App\Jobs\ProcessSimproWebhookJob;
use App\Resources\SimproApi\SimproWebhookResource;
use App\Tests\Support\MockRiversideApiClientTrait;
use App\Tests\Support\MockSimproApiClientTrait;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\HttpFoundation\Response;

class JobTest extends TestCase
{
    use MockSimproApiClientTrait, MockRiversideApiClientTrait;

    protected $requiredOriginStates = [
        'request_logs',
        'jobs',
        'job_operations',
        'job_operation_request_log',
    ];

    public function getTestCreateData(): array
    {
        return [
            ['create_job__new_site'],
            ['create_job__new_site__no_primary_contact_data'],
            ['create_job__new_site__no_valuable_contact_data'],
            ['create_job__new_site__no_contact_data_at_all'],
            ['create_job__existed_site__no_primary_contact_in_simpro'],
            ['create_job__existed_site__exists_primary_contact_in_simpro'],
            ['create_job__existed_site__no_valuable_contact_data'],
        ];
    }

    /**
     * @dataProvider getTestCreateData
     * @providedTestCase
     */
    public function testCreateJob(): void
    {
        $this->mockHttpRequestService($this->getJsonFixture('requests_chain.json'));

        $response = $this->xml('post', '/jobs', 'request.xml', ['HEADER_NAME' => 'HEADER_VALUE']);

        $response->assertStatus(Response::HTTP_OK);

        $this->assertEqualsFixture('response.json', $response->json());

        Queue::assertNothingPushed();

        $this->assertChangesEqualsFixture('request_logs');
        $this->assertChangesEqualsFixture('jobs');
        $this->assertChangesEqualsFixture('job_operations');
        $this->assertChangesEqualsFixture('job_operation_request_log');
    }

    public function getTestCreateJobRejectedData(): array
    {
        return [
            ['create_job__multiple_jobs'],
            ['create_job__invalid_xml'],
            ['create_job__check_validation'],
        ];
    }

    /**
     * @dataProvider getTestCreateJobRejectedData
     * @providedTestCase
     */
    public function testCreateJobRejected(): void
    {
        $response = $this->xml('post', '/jobs', 'request.xml', ['HEADER_NAME' => 'HEADER_VALUE']);

        $response->assertStatus(Response::HTTP_BAD_REQUEST);

        $this->assertEqualsFixture('response.json', $response->json());

        Queue::assertNothingPushed();

        $this->assertChangesEqualsFixture('request_logs');
        $this->assertChangesEqualsFixture('job_operations');
        $this->assertChangesEqualsFixture('job_operation_request_log');
        $this->assertNoChanges('jobs');
    }

    /**
     * @testCase create_job__simpro_request_failed
     */
    public function testCreateSimproRequestFailed(): void
    {
        $this->mockHttpRequestService($this->getJsonFixture('requests_chain.json'));

        $response = $this->xml('post', '/jobs', 'request.xml', ['HEADER_NAME' => 'HEADER_VALUE']);

        $response->assertStatus(Response::HTTP_BAD_REQUEST);

        $this->assertEqualsFixture('response.json', $response->json());

        Queue::assertNothingPushed();

        $this->assertChangesEqualsFixture('request_logs');
        $this->assertChangesEqualsFixture('job_operations');
        $this->assertChangesEqualsFixture('job_operation_request_log');
        $this->assertNoChanges('jobs');
    }

    public function getTestRegisterWebhookForbiddenData(): array
    {
        return [
            [
                'headers' => [
                    'x-response-signature' => 'incorrect_signature',
                ],
                'status_code' => Response::HTTP_FORBIDDEN,
                'test_case' => 'register_webhook__forbidden__incorrect_signature',
            ],
            [
                'headers' => [],
                'status_code' => Response::HTTP_FORBIDDEN,
                'test_case' => 'register_webhook__forbidden__no_signature_header',
            ]
        ];
    }

    /**
     * @dataProvider getTestRegisterWebhookForbiddenData
     * @providedTestCase
     */
    public function testRegisterWebhookForbidden($headers, $statusCode): void
    {
        $data = $this->getJsonFixture('request.json');

        $response = $this->json('post', '/simpro-webhook', $data, $headers);

        $response->assertStatus($statusCode);

        Queue::assertNothingPushed();

        $this->assertChangesEqualsFixture('request_logs');
        $this->assertChangesEqualsFixture('job_operations');
        $this->assertChangesEqualsFixture('job_operation_request_log');
    }

    /**
     * @testCase register_webhook__skip__job_not_exists_in_local_db
     */
    public function testRegisterWebhookSkipJobNotExistsInLocalDb(): void
    {
        list($data, $signature) = $this->getJsonFixture('request.json');

        $response = $this->json('post', '/simpro-webhook', $data, [
            'x-response-signature' => $signature,
        ]);

        $response->assertStatus(Response::HTTP_OK);

        Queue::assertNothingPushed();

        $this->assertChangesEqualsFixture('request_logs');
        $this->assertNoChanges('job_operations');
        $this->assertNoChanges('job_operation_request_log');
    }

    /**
     * @testCase register_webhook__success
     */
    public function testRegisterWebhookSuccess(): void
    {
        list ($data, $signature) = $this->getJsonFixture('request.json');

        $response = $this->json('post', '/simpro-webhook', $data, [
            'x-response-signature' => $signature,
        ]);

        $response->assertStatus(Response::HTTP_OK);

        $this->assertCount(1, Queue::pushedJobs());

        Queue::assertPushed(ProcessSimproWebhookJob::class, function (ProcessSimproWebhookJob $job) {
            $this->assertEqualsFixture('queue_job_data.json', $job->getSimproWebhookResource());
            $this->assertEquals(8, $job->getJobOperationId());

            return true;
        });

        $this->assertChangesEqualsFixture('request_logs');
        $this->assertChangesEqualsFixture('job_operations');
        $this->assertChangesEqualsFixture('job_operation_request_log');
    }

    public function getTestProcessWebhookData(): array
    {
        return [
            ['process_webhook__job_stage_complete'],
            ['process_webhook__job_stage_complete__no_cost_centers'],
            ['process_webhook__job_stage_complete__no_sections'],
            ['process_webhook__job_stage_complete__empty_part_no'],
            ['process_webhook__job_stage_complete__wrong_status__pattern_1'],
            ['process_webhook__job_stage_complete__wrong_status__pattern_2'],
            ['process_webhook__job_schedule_created__appointment_not_sent_previously'],
            ['process_webhook__job_schedule_created__riverside_already_has_appointment'],
            ['process_webhook__job_schedule_created__appointment_sent_previously'],
            ['process_webhook__job_schedule_created__riverside_unexpectedly_doesnt_have_appointment'],
            ['process_webhook__job_schedule_created__schedule_not_found'],
            ['process_webhook__job_schedule_created__job_not_found'],
            ['process_webhook__job_status__abandon__appointment_not_sent_previously'],
            ['process_webhook__job_status__abandon__appointment_sent_previously'],
            ['process_webhook__job_status__abandon__riverside_unexpectedly_doesnt_have_appointment'],
            ['process_webhook__job_status__abandon__wrong_status__pattern_3'],
            ['process_webhook__job_status__any_status__job_not_found'],
            ['process_webhook__job_status__no_access'],
            ['process_webhook__job_status__no_access__many_log_entries'],
        ];
    }

    /**
     * @dataProvider getTestProcessWebhookData
     * @providedTestCase
     */
    public function testProcessWebhook(): void
    {
        $this->mockHttpRequestService($this->getJsonFixture('requests_chain.json'));

        $webhookData = $this->getJsonFixture('webhook_data.json');
        $webhookResource = SimproWebhookResource::makeFromSimproRequestData($webhookData);
        (new ProcessSimproWebhookJob($webhookResource, 7))->handle();

        $this->assertChangesEqualsFixture('request_logs');
        $this->assertChangesEqualsFixture('jobs');
        $this->assertChangesEqualsFixture('job_operations');
        $this->assertChangesEqualsFixture('job_operation_request_log');
    }

    /**
     * @testCase process_webhook__job_locked_in_riverside
     */
    public function testProcessWebhookJobLockedInRiverside(): void
    {
        $this->mockHttpRequestService($this->getJsonFixture('requests_chain.json'));

        $webhookData = $this->getJsonFixture('webhook_data.json');

        $job = $this
            ->getMockBuilder(ProcessSimproWebhookJob::class)
            ->setConstructorArgs([
                SimproWebhookResource::makeFromSimproRequestData($webhookData),
                7,
            ])
            ->onlyMethods(['forceRetry'])
            ->getMock();

        $job->method('forceRetry')->with([60, 300, 600, 1200, 1800]);

        $job->handle();

        $this->assertChangesEqualsFixture('request_logs');
        $this->assertChangesEqualsFixture('job_operations');
        $this->assertChangesEqualsFixture('job_operation_request_log');

        $this->assertNoChanges('jobs');
    }

    public function getTestProcessWebhookSkipImmediatelyData(): array
    {
        return [
            ['process_webhook__skip_immediately__not_used_type'],
            ['process_webhook__skip_immediately__job_status__not_used_status'],
        ];
    }

    /**
     * @dataProvider getTestProcessWebhookSkipImmediatelyData
     * @providedTestCase
     */
    public function testProcessWebhookSkipImmediately(): void
    {
        $webhookData = $this->getJsonFixture('webhook_data.json');
        $webhookResource = SimproWebhookResource::makeFromSimproRequestData($webhookData);
        (new ProcessSimproWebhookJob($webhookResource, 7))->handle();

        $this->assertNoChanges('request_logs');
        $this->assertNoChanges('jobs');
        $this->assertNoChanges('job_operation_request_log');

        $this->assertChangesEqualsFixture('job_operations');
    }
}
