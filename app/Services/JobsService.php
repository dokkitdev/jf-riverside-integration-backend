<?php

namespace App\Services;

use App\ApiClients\SimproApiClient;
use App\Jobs\SendJobToSimproJob;
use Illuminate\Support\Arr;
use Exception;
use Illuminate\Support\Carbon;

class JobsService
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

    protected SimproApiClient $simproApiClient;
    protected array $jobTypeCodesMap;

    public function __construct()
    {
        $this->simproApiClient = app(SimproApiClient::class);
        $this->jobTypeCodesMap = config('services.simpro.job_type_codes_map');
    }

    public function initSendingToSimpro(array $dataFromRiverside)
    {
        dispatch(new SendJobToSimproJob($dataFromRiverside));
    }

    public function sendJobToSimpro(array $dataFromRiverside): void
    {
        //TODO: remove this check after actual XML format is defined
        if (
            !Arr::has($dataFromRiverside, 'location.place_ref') ||
            !Arr::has($dataFromRiverside, 'location.address1') ||
            !Arr::has($dataFromRiverside, 'job.initial_priority')
        ) {
            throw new Exception('Not all required fields are filled');
        }

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

        $this->simproApiClient->postJob($siteId, $jobData);
    }

    protected function convertDataFromRiversideToSimproJobData(array $dataFromRiverside, Carbon $now): array
    {
        list($responseTimeId, $dueDate, $dueTime) = $this->calculateSimproJobDatesAndPriority($dataFromRiverside['job']['initial_priority'], $now);

        $simproData = [
            'Tags' => [
                Arr::get($this->jobTypeCodesMap, Arr::get($dataFromRiverside, 'job.jobs_type_code', 'default'))
            ],
            'ResponseTime' => $responseTimeId,
            'DueDate' => $dueDate,
            'DueTime' => $dueTime,
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
