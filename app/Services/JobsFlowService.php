<?php

namespace App\Services;

use App\ApiClients\RiversideApiClient;
use App\ApiClients\SimproApiClient;
use App\Exceptions\CreateJobRequestException;
use App\Exceptions\RiversideApiRecordLockedException;
use App\Exceptions\WarningApiClientExceptions\AbstractWarningApiClientException;
use App\Exceptions\WarningApiClientExceptions\RiversideApiAppointmentLoggedPreviouslyException;
use App\Jobs\ProcessSimproWebhookJob;
use App\Jobs\UpdateSiteInfo\UpdateSiteInfoJob;
use App\Models\Job;
use App\Models\JobOperation;
use App\Resources\SimproApi\SimproWebhookResource;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use SimpleXMLElement;
use Throwable;

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
        'eml' => 'Email',
        'mbl' => 'CellPhone',
        'tel' => 'AltPhone',
        'wtl' => 'WorkPhone',
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

    protected const NO_DESCRIPTION_NOTES_MESSAGE = 'No description notes found';

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

    public function createJob(array $jobDataFromRiverside, JobOperation $jobOperation): int
    {
        try {
            $simproJobId = $this->sendJobToSimpro($jobDataFromRiverside);
        } catch (Throwable $exception) {
            report($exception);

            $jobOperation['status'] = JobOperation::STATUS_FAIL;

            throw new CreateJobRequestException('Failed sending to Simpro');
        }

        $job = $this->jobService->create([
            'order_no' => $jobDataFromRiverside['job_no'],
            'simpro_job_id' => $simproJobId,
        ]);

        $jobOperation['job_id'] = $job['id'];
        $jobOperation['status'] = JobOperation::STATUS_SUCCESS;

        return $simproJobId;
    }

    public function initSimproWebhookProcessing(SimproWebhookResource $simproWebhookResource, JobOperation $jobOperation)
    {
        $job = $this->jobService->findBy('simpro_job_id', $simproWebhookResource->jobId);

        if (is_null($job)) {
            $jobOperation->interruptPersisting();
            return;
        }

        $jobOperation['job_id'] = $job['id'];
        $jobOperation['status'] = JobOperation::STATUS_IN_PROGRESS;
        $jobOperation['details'] = [
            'webhook_type' => $simproWebhookResource->type,
            'webhook_description' => $simproWebhookResource->description,
        ];

        $jobOperation->save();

        dispatch(new ProcessSimproWebhookJob($simproWebhookResource, $jobOperation['id']));
    }

    public function processSimproWebhook(SimproWebhookResource $simproWebhookResource, int $jobOperationId): JobOperation
    {
        return $this->jobOperationService->withinJobOperation(function (JobOperation $jobOperation) use ($simproWebhookResource) {
            $job = $this->jobService->findBy('simpro_job_id', $simproWebhookResource->jobId);

            $appendDetails = [];

            try {
                switch ($simproWebhookResource->type) {
                    case self::SIMPRO_WEBHOOK_TYPE_JOB_COMPLETED:
                        $skipReason = $this->processSimproWebhookJobCompleted($job, $simproWebhookResource->dateTriggered);
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

                if (is_null($skipReason)) {
                    $jobOperation['status'] = JobOperation::STATUS_SUCCESS;
                } else {
                    $jobOperation['status'] = JobOperation::STATUS_SKIP;
                    $appendDetails = [
                        'skip_reason' => $skipReason,
                    ];
                }
            } catch (AbstractWarningApiClientException $exception) {
                $jobOperation['status'] = JobOperation::STATUS_WARNING;
                $appendDetails = [
                    'warning_message' => $exception->getWarningMessage(),
                ];
            } catch (RiversideApiRecordLockedException $exception) {
                $jobOperation['status'] = JobOperation::STATUS_RETRY;
            }

            if (!empty($appendDetails)) {
                $jobOperation['details'] = array_merge(Arr::get($jobOperation, 'details', []), $appendDetails);
            }

            return $jobOperation;
        }, $jobOperationId);
    }

    public function initUpdateSitesInfo(Carbon $dateTime)
    {
        $dateTime = ($dateTime->hour >= 12) ? $dateTime->clone()->addDay() : $dateTime;

        $jobs = $this->jobService->getByList($this->simproApiClient->getJobIdsWithScheduleOnDate($dateTime), 'simpro_job_id');

        foreach ($jobs as $job) {
            $jobOperation = app(JobOperationService::class)->create([
                'job_id' => $job['id'],
                'operation' => JobOperation::OPERATION_UPDATE_SITE_INFO,
                'status' => JobOperation::STATUS_IN_PROGRESS,
            ]);

            dispatch(new UpdateSiteInfoJob($job['id'], $jobOperation['id']));
        }
    }

    public function updateSiteInfo(int $jobId, int $jobOperationId)
    {
        $this->jobOperationService->withinJobOperation(function (JobOperation $jobOperation) use ($jobId) {
            $job = $this->jobService->find($jobId);

            $simproJobDetails = $this->simproApiClient->getJob($job['simpro_job_id']);
            $riversideJobDetails = $this->riversideApiClient->getJob($job['order_no']);

            $simproSiteId = $simproJobDetails['Site']['ID'];

            $siteData = $this->convertJobDetailsToSimproSiteData($riversideJobDetails);
            if (isset($siteData)) {
                $this->simproApiClient->patchSite($simproSiteId, $siteData);
            }

            $siteContactData = $this->convertJobDetailsToSimproSiteContactData($riversideJobDetails);
            if (isset($siteContactData)) {
                $primarySiteContactId = $this->simproApiClient->findSitePrimaryContactId($simproSiteId);

                if (is_null($primarySiteContactId)) {
                    $this->simproApiClient->postSiteContact($simproSiteId, $siteContactData);
                } else {
                    $this->simproApiClient->patchSiteContact($simproSiteId, $primarySiteContactId, $siteContactData);
                }
            }

            $jobOperation['status'] = JobOperation::STATUS_SUCCESS;
        }, $jobOperationId);
    }

    public function failedQueueJob(int $jobOperationId)
    {
        $this->jobOperationService->update($jobOperationId, ['status' => JobOperation::STATUS_FAIL]);
    }

    protected function processSimproWebhookJobCompleted(Job $job, Carbon $webhookDateTriggered): ?string
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
            $descriptionNotes = substr(strip_tags($workOrder['DescriptionNotes']), 0, 80);

            if (empty($descriptionNotes)) {
                $descriptionNotes = self::NO_DESCRIPTION_NOTES_MESSAGE;
            }

            $this->riversideApiClient->sendDairyEntry(
                $job['order_no'],
                $descriptionNotes,
                $workOrder['WorkOrderDate'],
                $workOrder['DateModified']
            );
        }

        $this->riversideApiClient->notifyAboutCompletedJob($job['order_no'], $webhookDateTriggered);

        return null;
    }

    protected function processSimproWebhookJobScheduleCreated(Job $job, int $simproJobScheduleId): ?string
    {
        $this->cancelAppointment($job);

        $simproSchedule = $this->simproApiClient->getSchedule($simproJobScheduleId);

        try {
            $this->riversideApiClient->sendAppointment(
                $job['order_no'],
                Carbon::parse($simproSchedule['Date']),
                Carbon::parse($simproSchedule['Blocks'][0]['StartTime']),
            );
            $skipReason = null;
        } catch (RiversideApiAppointmentLoggedPreviouslyException $exception) {
            $this->jobService->update($job['id'], [
                'is_appointment_sent' => true
            ]);

            throw $exception;
        }

        $this->jobService->update($job['id'], [
            'is_appointment_sent' => true
        ]);

        return $skipReason;
    }

    protected function processSimproWebhookJobStatusChanged(Job $job, int $simproStatusId): ?string
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

    protected function sendJobToSimpro(array $dataFromRiverside): int
    {
        $now = Carbon::now();

        $siteUprn = $dataFromRiverside['location']['place_ref'];
        $siteData = $this->convertDataFromRiversideToSimproSiteData($dataFromRiverside);
        $siteContactData = $this->convertDataFromRiversideToSiteContactData($dataFromRiverside);
        $jobData = $this->convertDataFromRiversideToSimproJobData($dataFromRiverside, $now);

        $siteId = $this->simproApiClient->findSiteIdByUprn($siteUprn);
        $primarySiteContactId = null;

        if (isset($siteId)) {
            $this->simproApiClient->patchSite($siteId, $siteData);

            if (isset($siteContactData)) {
                $primarySiteContactId = $this->simproApiClient->findSitePrimaryContactId($siteId);
            }
        } else {
            $siteId = $this->simproApiClient->postSite($siteUprn, $siteData);
        }

        if (isset($siteContactData)) {
            if (is_null($primarySiteContactId)) {
                $this->simproApiClient->postSiteContact($siteId, $siteContactData);
            } else {
                $this->simproApiClient->patchSiteContact($siteId, $primarySiteContactId, $siteContactData);
            }
        }

        return $this->simproApiClient->postJob($siteId, $jobData);
    }

    protected function convertJobDetailsToSimproSiteData(SimpleXMLElement $jobDetails): ?array
    {
        if (!isset($jobDetails->Jobs->Job->Location)) {
            return null;
        }

        $dataFromRiverside = [
            'location' => $this->convertXmlAttributesToArray($jobDetails->Jobs->Job->Location)
        ];

        $siteData = $this->convertDataFromRiversideToSimproSiteData($dataFromRiverside);

        return empty($siteData)
            ? null
            : $siteData;
    }

    protected function convertJobDetailsToSimproSiteContactData(SimpleXMLElement $jobDetails): ?array
    {
        if (!isset($jobDetails->Jobs->Job->Location->Occupants->Occupant)) {
            return null;
        }

        $dataFromRiverside = [
            'occupants' => [],
        ];

        foreach ($jobDetails->Jobs->Job->Location->Occupants->Occupant as $occupant) {
            $jobDataOccupant = $this->convertXmlAttributesToArray($occupant) + [
                'contacts' => [],
            ];

            if (isset($occupant->Contacts->Contact)) {
                foreach ($occupant->Contacts->Contact as $contact) {
                    $jobDataOccupant['contacts'][] = $this->convertXmlAttributesToArray($contact);
                }
            }

            $dataFromRiverside['occupants'][] = $jobDataOccupant;
        }

        return $this->convertDataFromRiversideToSiteContactData($dataFromRiverside);
    }

    protected function processAbandonStatus(Job $job)
    {
        $this->cancelAppointment($job);

        $this->riversideApiClient->notifyAboutAbandonStatus($job['order_no'], Carbon::now());
    }

    protected function processNoAccessStatus(Job $job)
    {
        $attemptsCount = $this->simproApiClient->getStatusAttemptsCount($job['simpro_job_id'], $this->simproConfig['no_access_status_text']);

        $this->riversideApiClient->notifyAboutNoAccessStatus($job['order_no'], $attemptsCount, Carbon::now());
    }

    protected function cancelAppointment(Job $job)
    {
        if ($job['is_appointment_sent']) {
            $this->riversideApiClient->cancelAppointment($job['order_no']);

            $this->jobService->update($job['id'], [
                'is_appointment_sent' => false
            ]);
        }
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

        $this->addFieldToSimproData($simproData, $dataFromRiverside, 'Name', 'location.address1');
        $this->addFieldToSimproData($simproData, $dataFromRiverside, 'Address.Address', 'location.address1');
        $this->addFieldToSimproData($simproData, $dataFromRiverside, 'Address.City', 'location.address2');
        $this->addFieldToSimproData($simproData, $dataFromRiverside, 'Address.State', 'location.address3');
        $this->addFieldToSimproData($simproData, $dataFromRiverside, 'Address.PostalCode', 'location.post_code');

        return $simproData;
    }

    protected function convertDataFromRiversideToSiteContactData(array $dataFromRiverside): ?array
    {
        $primaryOccupant = null;

        foreach ($dataFromRiverside['occupants'] as $occupant) {
            if (strtolower(Arr::get($occupant, 'main_contact', '')) === 'true') {
                $primaryOccupant = $occupant;
                break;
            }

            if (is_null($primaryOccupant)) {
                $primaryOccupant = $occupant;
            }
        }

        if (is_null($primaryOccupant)) {
            return null;
        }

        $simproData = [];

        $this->addFieldToSimproData($simproData, $primaryOccupant, 'Title', 'person_title');
        $this->addFieldToSimproData($simproData, $primaryOccupant, 'GivenName', 'person_forenames');
        $this->addFieldToSimproData($simproData, $primaryOccupant, 'FamilyName', 'person_surname');

        foreach ($primaryOccupant['contacts'] as $contact) {
            $simproField = Arr::get(
                self::RIVERSIDE_TO_SIMPRO_CONTACT_TYPE_MAP,
                strtolower(Arr::get($contact, 'contact_type'))
            );

            if (isset($simproField)) {
                $simproData[$simproField] = Arr::get($contact, 'contact_details', '');
            }
        }

        if (empty($simproData)) {
            return null;
        }

        $simproData['PrimaryContact'] = true;

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

    protected function convertXmlAttributesToArray(SimpleXMLElement $xml): array
    {
        $result = [];

        foreach ($xml->attributes() as $attribute => $value) {
            $result[strtolower($attribute)] = (string) $value;
        }

        return $result;
    }
}
