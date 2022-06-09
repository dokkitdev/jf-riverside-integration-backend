<?php

namespace App\Services;

use App\ApiClients\RiversideApiClient;
use App\ApiClients\SimproApiClient;
use App\Jobs\NotifyRiversideAboutCreatedJobJob;
use App\Jobs\ProcessSimproWebhookJobCompletedJob;
use App\Jobs\ProcessSimproWebhookJobScheduleCreatedJob;
use App\Jobs\ProcessSimproWebhookJobStatusChangedJob;
use App\Jobs\SendJobToSimproJob;
use App\Models\Job;
use App\Models\JobOperation;
use Illuminate\Support\Arr;
use Exception;
use Illuminate\Support\Carbon;

class JobsFlowService
{
    const RIVERSIDE_PRIORITIES = [
        self::RIVERSIDE_PRIORITY_4_HRS,
        self::RIVERSIDE_PRIORITY_12_HRS,
        self::RIVERSIDE_PRIORITY_5_DAYS,
        self::RIVERSIDE_PRIORITY_20_DAYS,
        self::RIVERSIDE_PRIORITY_56_DAYS,
    ];

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
    protected int $simproStatusIdAbandon;
    protected int $simproStatusIdNoAccess;
    protected string $simproStatusTextNoAccess;
    protected string $simproSorVariationsCostCenterName;

    public function __construct()
    {
        $this->jobService = app(JobService::class);
        $this->jobOperationService = app(JobOperationService::class);
        $this->simproApiClient = app(SimproApiClient::class);
        $this->riversideApiClient = app(RiversideApiClient::class);
        $this->jobTypeCodesMap = config('services.simpro.job_type_codes_map');
        $this->defaultJobTypeCodeId = config('services.simpro.default_job_type_code_id');
        $this->simproStatusIdAbandon = config('services.simpro.status.abandon_id');
        $this->simproStatusIdNoAccess = config('services.simpro.status.no_access_id');
        $this->simproStatusTextNoAccess = config('services.simpro.status.no_access_text');
        $this->simproSorVariationsCostCenterName = config('services.simpro.sor_variations.cost_center_name');
    }

    public function initSendingToSimpro(array $jobsFromRiverside): array
    {
        foreach ($jobsFromRiverside as $jobDataFromRiverside) {
            $job = $this->jobService->create([
                'order_no' => $jobDataFromRiverside['job_no']
            ]);

            $this->jobOperationService->create([
                'job_id' => $job['id'],
                'operation' => JobOperation::OPERATION_RECEIVE_DATA_FROM_RIVERSIDE,
            ]);

            dispatch(new SendJobToSimproJob($job['id'], $jobDataFromRiverside));
        }

        return Arr::pluck($jobsFromRiverside, 'job_no');
    }

    public function initSimproWebhookProcessing(array $simproWebhookData)
    {
        $type = $simproWebhookData['ID'];

        switch ($type) {
            case self::SIMPRO_WEBHOOK_JOB_COMPLETED:
                $this->initProcessSimproWebhookJobCompleted($simproWebhookData['reference']['jobID']);
                break;
            case self::SIMPRO_WEBHOOK_JOB_STATUS_CHANGED:
                $this->initProcessSimproWebhookJobStatusChange(
                    $simproWebhookData['reference']['jobID'],
                    $simproWebhookData['reference']['statusID'],
                );
                break;
            case self::SIMPRO_WEBHOOK_JOB_SCHEDULE_CREATED:
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

        $simproJob = $this->simproApiClient->getJob($job['simpro_job_id']);

        $workOrders = [];
        $sorElements = [];

        foreach ($simproJob['Sections'] as $section) {
            foreach ($section['CostCenters'] as $costCenter) {
                if ($costCenter['Name'] === $this->simproSorVariationsCostCenterName) {
                    foreach ($costCenter['Items']['Catalogs'] as $catalog) {
                        if (Arr::has($catalog, 'Catalog.PartNo') && Arr::has($catalog, 'Total.Qty')) {
                            $sorElements[] = [
                                'element_code' => Arr::get($catalog, 'Catalog.PartNo'),
                                'qty' => Arr::get($catalog, 'Total.Qty')
                            ];
                        }
                    }
                }

                $workOrders = array_merge($workOrders, $this->simproApiClient->getWorkOrders($job['simpro_job_id'], $section['ID'], $costCenter['ID']));
            }
        }

        if (!empty($sorElements)) {
            $this->riversideApiClient->sendSorVariations($job['order_no'], $sorElements);
        }

        foreach ($workOrders as $workOrder) {
            $this->riversideApiClient->sendDairyEntry(
                $job['order_no'],
                substr(strip_tags($workOrder['DescriptionNotes']), 0, 80),
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
        $job = $this->jobService->find($jobId);

        $this->riversideApiClient->cancelAppointment($job['order_no']);

        $simproSchedule = $this->simproApiClient->getSchedule($simproJobScheduleId);

        $this->riversideApiClient->sendAppointment($job['order_no'], $simproSchedule['Date'], $simproSchedule['Blocks'][0]['StartTime']);

        $this->jobService->update($jobId, [
            'is_appointment_sent' => true
        ]);

        $this->jobOperationService->create([
            'job_id' => $jobId,
            'operation' => JobOperation::OPERATION_NOTIFY_RIVERSIDE_ABOUT_CREATED_SCHEDULE
        ]);
    }

    public function processSimproWebhookJobStatusChanged(int $jobId, int $simproStatusId)
    {
        $job = $this->jobService->find($jobId);

        switch ($simproStatusId) {
            case $this->simproStatusIdAbandon:
                $this->processAbandonStatus($job);
                break;
            case $this->simproStatusIdNoAccess:
                $this->processNoAccessStatus($job);
                break;
            default:
                throw new Exception('Unknown Simpro status ID');
        }

        $this->jobOperationService->create([
            'job_id' => $jobId,
            'operation' => JobOperation::OPERATION_NOTIFY_RIVERSIDE_ABOUT_CHANGED_JOB_STATUS
        ]);
    }

    protected function processAbandonStatus(Job $job)
    {
        if ($job['is_appointment_sent']) {
            $this->riversideApiClient->cancelAppointment($job['order_no']);

            $this->jobService->update($job['id'], [
                'is_appointment_sent' => false
            ]);
        }

        $this->riversideApiClient->notifyAboutAbandonStatus($job['order_no'], Carbon::now());
    }

    protected function processNoAccessStatus(Job $job)
    {
        $attemptsCount = $this->simproApiClient->getStatusAttemptsCount($job['simpro_job_id'], $this->simproStatusTextNoAccess);

        $this->riversideApiClient->notifyAboutNoAccessStatus($job['order_no'], $attemptsCount, Carbon::now());
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

    protected function initProcessSimproWebhookJobStatusChange(int $simproJobId, int $simproStatusId)
    {
        $job = $this->jobService->findBy('simpro_job_id', $simproJobId);

        if (
            isset($job) &&
            (($simproStatusId === $this->simproStatusIdNoAccess) || ($simproStatusId === $this->simproStatusIdAbandon))
        ) {
            $this->jobOperationService->create([
                'job_id' => $job['id'],
                'operation' => JobOperation::OPERATION_RECEIVE_WEBHOOK_JOB_STATUS_CHANGED
            ]);

            dispatch(new ProcessSimproWebhookJobStatusChangedJob($job['id'], $simproStatusId));
        }
    }

    protected function convertDataFromRiversideToSimproJobData(array $dataFromRiverside, Carbon $now): array
    {
        list($responseTimeId, $dueDate, $dueTime) = $this->calculateSimproJobDatesAndPriority($dataFromRiverside['latest_priority'], $now);

        $typeCode = Arr::get($dataFromRiverside, 'job_type_code', '');

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

        $this->addFieldToSimproData($simproData, $dataFromRiverside, 'OrderNo', 'job_no');
        $this->addFieldToSimproData($simproData, $dataFromRiverside, 'Name', 'short_description');

        return $simproData;
    }

    protected function generateSimproNotes(array $dataFromRiverside)
    {
        $notes = [];

        if (!empty($dataFromRiverside['access_info'])) {
            $notes[] = $dataFromRiverside['access_info'];
        }

        foreach ($dataFromRiverside['sors'] as $sor) {
            if (!empty($sor['element_code'])) {
                $notes[] = $sor['element_code'];
            }

            if (!empty($sor['element_description'])) {
                $notes[] = $sor['element_description'];
            }
        }

        return empty($notes) ? null : implode(' ', $notes);
    }

    protected function generateSimproDescription(array $dataFromRiverside): ?string
    {
        $description = [];
        if (!empty($dataFromRiverside['job_type'])) {
            $description[] = $dataFromRiverside['job_type'];
        }
        if (!empty($dataFromRiverside['long_description'])) {
            $description[] = $dataFromRiverside['long_description'];
        }
        if (!empty($dataFromRiverside['sub_location_description'])) {
            $description[] = $dataFromRiverside['sub_location_description'];
        }

        return empty($description) ? null : implode(' ', $description);
    }

    protected function calculateSimproJobDatesAndPriority(int $riversidePriority, Carbon $now): array
    {
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

        if (isset($dataFromRiverside['contact']['contact_type'])) {
            $contactField = Arr::get(self::RIVERSIDE_TO_SIMPRO_CONTACT_TYPE_MAP, $dataFromRiverside['contact']['contact_type']);
            if (isset($contactField)) {
                $this->addFieldToSimproData($simproData, $dataFromRiverside, "PrimaryContact.{$contactField}", 'contact.contact_details');
            }
        }

        if (isset($dataFromRiverside['contact']['priority'])) {
            $priorityValue = Arr::get(self::RIVERSIDE_TO_SIMPRO_CONTACT_PRIORITY_MAP, $dataFromRiverside['contact']['priority']);
            if (isset($priorityValue)) {
                Arr::set($simproData, 'PrimaryContact.PreferredNotificationMethod', $priorityValue);
            }
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
