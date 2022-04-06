<?php

namespace App\Services;

use App\ApiClients\RiversideApiClient;
use App\ApiClients\SimproApiClient;
use App\Jobs\NotifyRiversideAboutCreatedJobJob;
use App\Jobs\ProcessSimproWebhookJobCompletedJob;
use App\Jobs\ProcessSimproWebhookJobScheduleCreatedJob;
use App\Jobs\SendJobToSimproJob;
use App\Models\JobOperation;
use Illuminate\Support\Arr;
use Exception;
use Illuminate\Support\Carbon;

class JobsFlowService
{
    protected const RIVERSIDE_TO_SIMPRO_CONTACT_TYPE_MAP = [
        'EML' => 'Email',
        'MBL' => 'CellPhone',
        'TEL' => 'WorkPhone',
    ];

    protected const RIVERSIDE_TO_SIMPRO_CONTACT_PRIORITY_MAP = [
        '1' => 'Email',
        '2' => 'Mobile',
        '3' => 'Telephone',
    ];

    protected const RIVERSIDE_PRIORITY_4_HRS = 0;
    protected const RIVERSIDE_PRIORITY_12_HRS = 1;
    protected const RIVERSIDE_PRIORITY_5_DAYS = 2;
    protected const RIVERSIDE_PRIORITY_20_DAYS = 3;
    protected const RIVERSIDE_PRIORITY_56_DAYS = 5;

    protected const RIVERSIDE_PRIORITY_TO_SIMPRO_RESPONSE_TIME_MAP = [
        self::RIVERSIDE_PRIORITY_4_HRS => 3,
        self::RIVERSIDE_PRIORITY_12_HRS => 12,
        self::RIVERSIDE_PRIORITY_5_DAYS => 9,
        self::RIVERSIDE_PRIORITY_20_DAYS => 24,
        self::RIVERSIDE_PRIORITY_56_DAYS => 22,
    ];

    protected const RIVERSIDE_PRIORITY_DAYS_COUNT = [
        self::RIVERSIDE_PRIORITY_4_HRS => 0,
        self::RIVERSIDE_PRIORITY_12_HRS => 0,
        self::RIVERSIDE_PRIORITY_5_DAYS => 5,
        self::RIVERSIDE_PRIORITY_20_DAYS => 20,
        self::RIVERSIDE_PRIORITY_56_DAYS => 56,
    ];

    protected const RIVERSIDE_PRIORITY_HOURS_COUNT = [
        self::RIVERSIDE_PRIORITY_4_HRS => 4,
        self::RIVERSIDE_PRIORITY_12_HRS => 12,
    ];

    protected const SIMPRO_DUE_DATE_FORMAT = 'Y-m-d';
    protected const SIMPRO_DUE_TIME_FORMAT = 'H:i';

    protected const SIMPRO_WEBHOOK_JOB_COMPLETED = 'job.stage.complete';
    protected const SIMPRO_WEBHOOK_JOB_STATUS_CHANGED = 'job.status';
    protected const SIMPRO_WEBHOOK_JOB_SCHEDULE_CREATED = 'job.schedule.created';

    protected JobService $jobService;
    protected JobOperationService $jobOperationService;
    protected SimproApiClient $simproApiClient;
    protected RiversideApiClient $riversideApiClient;
    protected array $jobTypeCodesMap;
    protected int $defaultJobTypeCodeId;

    public function __construct()
    {
        $this->jobService = app(JobService::class);
        $this->jobOperationService = app(JobOperationService::class);
        $this->simproApiClient = app(SimproApiClient::class);
        $this->riversideApiClient = app(RiversideApiClient::class);
        $this->jobTypeCodesMap = config('services.simpro.job_type_codes_map');
        $this->defaultJobTypeCodeId = config('services.simpro.default_job_type_code_id');
    }

    public function initSendingToSimpro(array $dataFromRiverside)
    {
        //TODO: remove this check after actual XML format is defined
        if (
            !Arr::has($dataFromRiverside, 'location.place_ref') ||
            !Arr::has($dataFromRiverside, 'location.address1') ||
            !Arr::has($dataFromRiverside, 'job.job_no') ||
            !Arr::has($dataFromRiverside, 'job.initial_priority')
        ) {
            throw new Exception('Not all required fields are filled');
        }

        $job = $this->jobService->create([
            'order_no' => $dataFromRiverside['job']['job_no']
        ]);

        $this->jobOperationService->create([
            'job_id' => $job['id'],
            'operation' => JobOperation::OPERATION_RECEIVE_DATA_FROM_RIVERSIDE,
        ]);

        dispatch(new SendJobToSimproJob($job['id'], $dataFromRiverside));
    }

    public function initSimproWebhookProcessing(array $simproWebhookData)
    {
        $type = $simproWebhookData['ID'];

        switch ($type) {
            case self::SIMPRO_WEBHOOK_JOB_COMPLETED:
                $this->initProcessSimproWebhookJobCompleted($simproWebhookData['reference']['jobID']);
                break;
            case self::SIMPRO_WEBHOOK_JOB_STATUS_CHANGED:
                //TODO: implement
                break;
            case self::SIMPRO_WEBHOOK_JOB_SCHEDULE_CREATED:
                //TODO: payload format might not be guessed. Check format after receiving test requests.
                $this->initProcessSimproWebhookScheduleCreated(
                    $simproWebhookData['reference']['jobID'],
                    $simproWebhookData['reference']['scheduleID'],
                );
                break;
            default:
                throw new Exception('Unknown Simpro webhook');
        }
    }

    public function sendJobToSimpro(int $jobId, array $dataFromRiverside): void
    {
        $now = Carbon::now();

        $siteUprn = $dataFromRiverside['location']['place_ref'];
        $siteData = $this->convertDataFromRiversideToSimproSiteData($dataFromRiverside);
        $jobData = $this->convertDataFromRiversideToSimproJobData($dataFromRiverside, $now);

        $siteId = $this->simproApiClient->findSiteIdByUprn($siteUprn);

        if (isset($siteId)) {
            $this->simproApiClient->patchSite($siteId, $siteData);
        } else {
            $siteId = $this->simproApiClient->postSite($siteUprn, $siteData);
        }

        $simproJobId = $this->simproApiClient->postJob($siteId, $jobData);

        $this->jobService->update($jobId, [
            'simpro_job_id' => $simproJobId
        ]);

        $this->jobOperationService->create([
            'job_id' => $jobId,
            'operation' => JobOperation::OPERATION_CREATE_JOB_IN_SIMPRO,
        ]);

        dispatch(new NotifyRiversideAboutCreatedJobJob($jobId, $now));
    }

    public function notifyRiversideAboutCreatedJob(int $jobId, Carbon $date)
    {
        $job = $this->jobService->find($jobId);

        $this->riversideApiClient->notifyAboutCreatedJob($job['order_no'], $date);

        $this->jobOperationService->create([
            'job_id' => $jobId,
            'operation' => JobOperation::OPERATION_NOTIFY_RIVERSIDE_ABOUT_CRATED_JOB,
        ]);
    }

    public function processSimproWebhookJobCompleted(int $jobId)
    {
        $job = $this->jobService->find($jobId);

        $this->riversideApiClient->sendSorData($job['order_no']);

        $workOrders = $this->simproApiClient->getWorkOrders($job['simpro_job_id']);

        foreach ($workOrders as $workOrder) {
            $this->riversideApiClient->sendDairyEntry(
                $job['order_no'],
                $workOrder['DescriptionNotes'],
                $workOrder['WorkOrderDate'],
                $workOrder['DateModified']
            );
        }

        $this->riversideApiClient->notifyAboutCompletedJob($job['order_no'], Carbon::now());

        $this->jobOperationService->create([
            'job_id' => $job['id'],
            'operation' => JobOperation::OPERATION_NOTIFY_RIVERSIDE_ABOUT_COMPLETED_JOB
        ]);
    }

    public function processSimproWebhookJobScheduleCreated(int $jobId, int $simproJobScheduleId)
    {
    }

    protected function initProcessSimproWebhookJobCompleted(int $simproJobId)
    {
        $job = $this->jobService->findBy('simpro_job_id', $simproJobId);

        if (isset($job)) {
            $this->jobOperationService->create([
                'job_id' => $job['id'],
                'operation' => JobOperation::OPERATION_RECEIVE_WEBHOOK_JOB_COMPLETE
            ]);

            dispatch(new ProcessSimproWebhookJobCompletedJob($job['id']));
        }
    }

    protected function initProcessSimproWebhookScheduleCreated(int $simproJobId, int $simproJobScheduleId)
    {
        $job = $this->jobService->findBy('simpro_job_id', $simproJobId);

        if (isset($job)) {
            $this->jobOperationService->create([
                'job_id' => $job['id'],
                'operation' => JobOperation::OPERATION_RECEIVE_WEBHOOK_JOB_SCHEDULE_CREATED
            ]);

            dispatch(new ProcessSimproWebhookJobScheduleCreatedJob($job['id'], $simproJobScheduleId));
        }
    }

    protected function convertDataFromRiversideToSimproJobData(array $dataFromRiverside, Carbon $now): array
    {
        list($responseTimeId, $dueDate, $dueTime) = $this->calculateSimproJobDatesAndPriority($dataFromRiverside['job']['initial_priority'], $now);

        $typeCode = Arr::get($dataFromRiverside, 'job.jobs_type_code', '');

        $typeCodeId = Arr::get(
            $this->jobTypeCodesMap,
            $typeCode,
            $this->defaultJobTypeCodeId,
        );

        $simproData = [
            'ResponseTime' => $responseTimeId,
            'DueDate' => $dueDate,
            'DueTime' => $dueTime,
            'Sections' => [
                [
                    'CostCenters' => [
                        [
                            'CostCenter' => $typeCodeId,
                            'Description' => $typeCode,
                            'Stage' => 'Pending',
                        ]
                    ]
                ]
            ]
        ];

        $description = $this->generateSimproDescription($dataFromRiverside);
        if (isset($description)) {
            $simproData['Description'] = $description;
        }

        $notes = $this->generateSimproNotes($dataFromRiverside);
        if (isset($notes)) {
            $simproData['Notes'] = $notes;
        }

        $this->addFieldToSimproData($simproData, $dataFromRiverside, 'OrderNo', 'job.job_no');
        $this->addFieldToSimproData($simproData, $dataFromRiverside, 'Name', 'job.short_description');

        return $simproData;
    }

    protected function generateSimproNotes(array $dataFromRiverside)
    {
        $notes = [];
        if (!empty($dataFromRiverside['job']['access_info'])) {
            $notes[] = $dataFromRiverside['job']['access_info'];
        }
        if (!empty($dataFromRiverside['sor']['element_code'])) {
            $notes[] = $dataFromRiverside['sor']['element_code'];
        }
        if (!empty($dataFromRiverside['sor']['element_description'])) {
            $notes[] = $dataFromRiverside['sor']['element_description'];
        }

        return empty($notes) ? null : implode(' ', $notes);
    }

    protected function generateSimproDescription(array $dataFromRiverside): ?string
    {
        $description = [];
        if (!empty($dataFromRiverside['job']['job_type'])) {
            $description[] = $dataFromRiverside['job']['job_type'];
        }
        if (!empty($dataFromRiverside['job']['long_description'])) {
            $description[] = $dataFromRiverside['job']['long_description'];
        }
        if (!empty($dataFromRiverside['job']['sub_location_description'])) {
            $description[] = $dataFromRiverside['job']['sub_location_description'];
        }

        return empty($description) ? null : implode(' ', $description);
    }

    protected function calculateSimproJobDatesAndPriority(int $riversidePriority, Carbon $now): array
    {
        //TODO: Don't forget to add request validation to accept requests with values from the map only
        $responseTimeId = self::RIVERSIDE_PRIORITY_TO_SIMPRO_RESPONSE_TIME_MAP[$riversidePriority];

        $dueDate = $now
            ->clone()
            ->addDays(self::RIVERSIDE_PRIORITY_DAYS_COUNT[$riversidePriority])
            ->format(self::SIMPRO_DUE_DATE_FORMAT);

        $hoursCount = Arr::get(self::RIVERSIDE_PRIORITY_HOURS_COUNT, $riversidePriority);
        $dueTime = isset($hoursCount)
            ? $now->clone()->addHours($hoursCount)->format(self::SIMPRO_DUE_TIME_FORMAT)
            : null;

        return [$responseTimeId, $dueDate, $dueTime];
    }

    protected function convertDataFromRiversideToSimproSiteData(array $dataFromRiverside): array
    {
        $simproData = [];

        $contactField = Arr::get(self::RIVERSIDE_TO_SIMPRO_CONTACT_TYPE_MAP, Arr::get($dataFromRiverside, 'contact.contact_type'));
        if (isset($contactField)) {
            $this->addFieldToSimproData($simproData, $dataFromRiverside, "PrimaryContact.{$contactField}", 'contact.contact_details');
        }

        $priorityValue = Arr::get(self::RIVERSIDE_TO_SIMPRO_CONTACT_PRIORITY_MAP, Arr::get($dataFromRiverside, 'contact.priority'));
        if (isset($priorityValue)) {
            Arr::set($simproData, 'PrimaryContact.PreferredNotificationMethod', $priorityValue);
        }

        $this->addFieldToSimproData($simproData, $dataFromRiverside, 'Name', 'location.address1');
        $this->addFieldToSimproData($simproData, $dataFromRiverside, 'Address.Address', 'location.address1');
        $this->addFieldToSimproData($simproData, $dataFromRiverside, 'Address.City', 'location.address2');
        $this->addFieldToSimproData($simproData, $dataFromRiverside, 'Address.State', 'location.address3');
        $this->addFieldToSimproData($simproData, $dataFromRiverside, 'Address.PostalCode', 'location.post_code');
        $this->addFieldToSimproData($simproData, $dataFromRiverside, 'PrimaryContact.Title', 'occupants.test_Person_Title');
        $this->addFieldToSimproData($simproData, $dataFromRiverside, 'PrimaryContact.GivenName', 'occupants.person_forenames');
        $this->addFieldToSimproData($simproData, $dataFromRiverside, 'PrimaryContact.FamilyName', 'occupants.person_surname');

        return $simproData;
    }

    protected function addFieldToSimproData(array &$simproData, array $dataFromRiverside, string $simproField, string $riversideField): void
    {
        if (Arr::has($dataFromRiverside, $riversideField)) {
            Arr::set(
                $simproData,
                $simproField,
                Arr::get($dataFromRiverside, $riversideField)
            );
        }
    }
}
