<?php

namespace App\Services;

use App\ApiClients\RiversideApiClient;
use App\ApiClients\SimproApiClient;
use App\Exceptions\CreateJobRequestException;
use App\Exceptions\RiversideApiAppointmentLoggedPreviouslyException;
use App\Jobs\ProcessSimproWebhookJob;
use App\Jobs\UpdateSiteInfo\UpdateSiteInfoJob;
use App\Models\Job;
use App\Models\JobOperation;
use App\Resources\JobOperations\JobOperationCreateResource;
use App\Resources\JobOperations\JobOperationHandleWebhookFailResource;
use App\Resources\JobOperations\JobOperationHandleWebhookSuccessResource;
use App\Resources\JobOperations\JobOperationRegisterWebhookResource;
use App\Resources\JobOperations\JobOperationUpdateSiteInfoResource;
use App\Resources\SimproApi\SimproWebhookResource;
use Illuminate\Support\Arr;
use Exception;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use SimpleXMLElement;

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

    protected const SIMPRO_WEBHOOK_TYPE_JOB_COMPLETED = 'job.stage.complete';
    protected const SIMPRO_WEBHOOK_TYPE_JOB_STATUS_CHANGED = 'job.status';
    protected const SIMPRO_WEBHOOK_TYPE_JOB_SCHEDULE_CREATED = 'job.schedule.created';

    protected const SIMPRO_WEBHOOK_TYPES = [
        self::SIMPRO_WEBHOOK_TYPE_JOB_COMPLETED,
        self::SIMPRO_WEBHOOK_TYPE_JOB_STATUS_CHANGED,
        self::SIMPRO_WEBHOOK_TYPE_JOB_SCHEDULE_CREATED,
    ];

    protected const SIMPRO_JOB_STATUS_ABANDON = 'abandon';
    protected const SIMPRO_JOB_STATUS_NO_ACCESS = 'no_access';

    protected const WEBHOOK_SKIP_REASON_NOT_USED_TYPE = 'not_used_type';
    protected const WEBHOOK_SKIP_REASON_NOT_USED_STATUS = 'not_used_status';
    protected const WEBHOOK_SKIP_REASON_EMPTY_PART_NO = 'empty_part_no';
    protected const WEBHOOK_SKIP_RIVERSIDE_ALREADY_HAS_APPOINTMENT = 'riverside_already_has_appointment';

    protected JobService $jobService;
    protected JobOperationService $jobOperationService;
    protected SimproApiClient $simproApiClient;
    protected RiversideApiClient $riversideApiClient;
    protected array $simproConfig;

    public function __construct()
    {
        $this->jobService = app(JobService::class);
        $this->jobOperationService = app(JobOperationService::class);
        $this->simproApiClient = app(SimproApiClient::class);
        $this->riversideApiClient = app(RiversideApiClient::class);
        $this->simproConfig = config('services.simpro');
    }

    public function createJob(array $jobDataFromRiverside): int
    {
        return DB::transaction(function () use ($jobDataFromRiverside) {
            $job = $this->jobService->create([
                'order_no' => $jobDataFromRiverside['job_no'],
            ]);

            try {
                $simproJobId = $this->sendJobToSimpro($job['id'], $jobDataFromRiverside);
            } catch (Exception $exception) {
                report($exception);

                throw new CreateJobRequestException('Failed sending to Simpro');
            }

            $this->jobOperationService->create(new JobOperationCreateResource($job['id']));

            return $simproJobId;
        });
    }

    public function initSimproWebhookProcessing(SimproWebhookResource $simproWebhookResource)
    {
        if (!in_array($simproWebhookResource->type, self::SIMPRO_WEBHOOK_TYPES)) {
            return;
        }

        $job = $this->jobService->findBy('simpro_job_id', $simproWebhookResource->jobId);

        if (is_null($job)) {
            return;
        }

        if (($simproWebhookResource->type === self::SIMPRO_WEBHOOK_TYPE_JOB_STATUS_CHANGED) && !in_array($simproWebhookResource->statusId, $this->simproConfig['status_ids'])) {
            $this->jobOperationService->create(new JobOperationRegisterWebhookResource($job['id'], $simproWebhookResource, self::WEBHOOK_SKIP_REASON_NOT_USED_STATUS));
        } else {
            $this->jobOperationService->create(new JobOperationRegisterWebhookResource($job['id'], $simproWebhookResource));

            dispatch(new ProcessSimproWebhookJob($simproWebhookResource));
        }
    }

    public function processSimproWebhook(SimproWebhookResource $simproWebhookResource)
    {
        $job = $this->jobService->findBy('simpro_job_id', $simproWebhookResource->jobId);

        switch ($simproWebhookResource->type) {
            case self::SIMPRO_WEBHOOK_TYPE_JOB_COMPLETED:
                $skipReason = $this->processSimproWebhookJobCompleted($job);
                break;
            case self::SIMPRO_WEBHOOK_TYPE_JOB_STATUS_CHANGED:
                $skipReason = $this->processSimproWebhookJobStatusChanged($job, $simproWebhookResource->statusId);
                break;
            case self::SIMPRO_WEBHOOK_TYPE_JOB_SCHEDULE_CREATED:
                $skipReason = $this->processSimproWebhookJobScheduleCreated($job, $simproWebhookResource->scheduleId);
                break;
            default:
                $skipReason = self::WEBHOOK_SKIP_REASON_NOT_USED_TYPE;
        }

        $this->jobOperationService->create(new JobOperationHandleWebhookSuccessResource($job['id'], $simproWebhookResource, $skipReason));
    }

    public function failedProcessingSimproWebhook(SimproWebhookResource $simproWebhookResource)
    {
        $job = $this->jobService->findBy('simpro_job_id', $simproWebhookResource->jobId);

        $this->jobOperationService->create(new JobOperationHandleWebhookFailResource($job['id'], $simproWebhookResource));
    }

    public function processSimproWebhookJobCompleted(Job $job): ?string
    {
        $simproJob = $this->simproApiClient->getJob($job['simpro_job_id']);

        $workOrders = [];
        $sorElements = [];

        foreach ($simproJob['Sections'] as $section) {
            foreach ($section['CostCenters'] as $costCenter) {
                if ($costCenter['Name'] === $this->simproConfig['sor_variations']['cost_center_name']) {
                    foreach ($costCenter['Items']['Prebuilds'] as $prebuild) {
                        if (Arr::has($prebuild, 'Prebuild.PartNo') && Arr::has($prebuild, 'Total.Qty')) {
                            $elementCode = Arr::get($prebuild, 'Prebuild.PartNo');

                            if (empty($elementCode)) {
                                return self::WEBHOOK_SKIP_REASON_EMPTY_PART_NO;
                            }

                            $sorElements[] = [
                                'element_code' => $elementCode,
                                'qty' => Arr::get($prebuild, 'Total.Qty')
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

        return null;
    }

    public function processSimproWebhookJobScheduleCreated(Job $job, int $simproJobScheduleId): ?string
    {
        if ($job['is_appointment_sent']) {
            $this->riversideApiClient->cancelAppointment($job['order_no']);
        }

        $simproSchedule = $this->simproApiClient->getSchedule($simproJobScheduleId);

        try {
            $this->riversideApiClient->sendAppointment($job['order_no'], $simproSchedule['Date'], $simproSchedule['Blocks'][0]['StartTime']);
            $skipReason = null;
        } catch (RiversideApiAppointmentLoggedPreviouslyException $exception) {
            $skipReason = self::WEBHOOK_SKIP_RIVERSIDE_ALREADY_HAS_APPOINTMENT;
        }

        $this->jobService->update($job['id'], [
            'is_appointment_sent' => true
        ]);

        return $skipReason;
    }

    public function processSimproWebhookJobStatusChanged(Job $job, int $simproStatusId): ?string
    {
        $status = array_search($simproStatusId, $this->simproConfig['status_ids']);

        $skipReason = null;

        switch ($status) {
            case self::SIMPRO_JOB_STATUS_ABANDON:
                $this->processAbandonStatus($job);
                break;
            case self::SIMPRO_JOB_STATUS_NO_ACCESS:
                $this->processNoAccessStatus($job);
                break;
            default:
                $skipReason = self::WEBHOOK_SKIP_REASON_NOT_USED_STATUS;
        }

        return $skipReason;
    }

    public function initUpdateSitesInfo(Carbon $dateTime)
    {
        $dateTime = ($dateTime->hour >= 12) ? $dateTime->clone()->addDay() : $dateTime;

        $jobs = $this->jobService->getByList($this->simproApiClient->getJobIdsWithScheduleOnDate($dateTime), 'simpro_job_id');

        foreach ($jobs as $job) {
            dispatch(new UpdateSiteInfoJob($job['id']));
        }
    }

    public function updateSiteInfo(int $jobId)
    {
        $job = $this->jobService->find($jobId);

        $simproJobDetails = $this->simproApiClient->getJob($job['simpro_job_id']);
        $riversideJobDetails = $this->riversideApiClient->getJob($job['order_no']);

        $this->simproApiClient->patchSite(
            $simproJobDetails['Site']['ID'],
            $this->convertJobDetailsToSimproSiteData($riversideJobDetails)
        );

        $this->jobOperationService->create(new JobOperationUpdateSiteInfoResource($jobId, JobOperation::STATUS_SUCCESS));
    }

    public function failedUpdateSiteInfo(int $jobId)
    {
        $this->jobOperationService->create(new JobOperationUpdateSiteInfoResource($jobId, JobOperation::STATUS_FAIL));
    }

    protected function sendJobToSimpro(int $jobId, array $dataFromRiverside): int
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

        return $simproJobId;
    }

    protected function convertJobDetailsToSimproSiteData(SimpleXMLElement $jobDetails): array
    {
        if (!isset($jobDetails->Jobs->Job->Location->Occupants->Occupant)) {
            throw new Exception('Incorrect "getjobsextcont" response format');
        }

        $location = $jobDetails->Jobs->Job->Location;
        $occupant = $jobDetails->Jobs->Job->Location->Occupants->Occupant;

        //TODO: add after it is implemented on Riverside side:
        //  PrimaryContact.Email, PrimaryContact.CellPhone, PrimaryContact.WorkPhone, PrimaryContact.PreferredNotificationMethod
        return [
            'Name' => $location['address1'],
            'Address' => [
                'Address' => $location['address1'],
                'City' => $location['address2'],
                'State' => $location['address3'],
                'PostalCode' => $location['post_code'],
            ],
            'PrimaryContact' => [
                'Title' => $occupant['Person_Title'],
                'GivenName' => $occupant['Person_Forenames'],
                'FamilyName' => $occupant['Person_Surname'],
            ],
        ];
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
        $attemptsCount = $this->simproApiClient->getStatusAttemptsCount($job['simpro_job_id'], $this->simproConfig['no_access_status_text']);

        $this->riversideApiClient->notifyAboutNoAccessStatus($job['order_no'], $attemptsCount, Carbon::now());
    }

    protected function convertDataFromRiversideToSimproJobData(array $dataFromRiverside, Carbon $now): array
    {
        list($responseTimeId, $dueDate, $dueTime) = $this->calculateSimproJobDatesAndPriority($dataFromRiverside['latest_priority'], $now);

        $typeCode = Arr::get($dataFromRiverside, 'job_type_code', '');

        $typeCodeId = Arr::get(
            $this->simproConfig['job_type_codes_map'],
            $typeCode,
            $this->simproConfig['default_job_type_code_id'],
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
            $description[] = "Job Type: {$dataFromRiverside['job_type']}";
        }
        if (!empty($dataFromRiverside['long_description'])) {
            $description[] = "Description: {$dataFromRiverside['long_description']}";
        }
        if (!empty($dataFromRiverside['sub_location_description'])) {
            $description[] = "Sub Location: {$dataFromRiverside['sub_location_description']}";
        }

        $appointmentInfo = [];
        if (!empty($dataFromRiverside['appointment_date'])) {
            $appointmentInfo[] = $dataFromRiverside['appointment_date'];
        }
        if (!empty($dataFromRiverside['appointmentment_slot'])) {
            $appointmentInfo[] = $dataFromRiverside['appointmentment_slot'];
        }

        if (!empty($appointmentInfo)) {
            $description[] = 'Appointment Information: ' . implode(' ', $appointmentInfo);
        }

        return empty($description) ? null : implode(', ', $description);
    }

    protected function calculateSimproJobDatesAndPriority(int $riversidePriority, Carbon $now): array
    {
        $responseTimeId = $this->simproConfig['riverside_priority_to_simpro_response_time_map'][$riversidePriority];

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
        $this->addFieldToSimproData($simproData, $dataFromRiverside, 'PrimaryContact.Title', 'occupants.person_title');
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
