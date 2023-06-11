<?php

namespace App\ApiClients;

use App\Exceptions\ApiClientHttpException;
use App\Exceptions\RiversideApiRecordLockedException;
use App\Exceptions\WarningApiClientExceptions\RiversideApiAppointmentLoggedPreviouslyException;
use App\Exceptions\WarningApiClientExceptions\RiversideApiJobNotFoundException;
use App\Models\RequestLog;
use Illuminate\Support\Carbon;
use Psr\Http\Message\ResponseInterface;
use SimpleXMLElement;
use Exception;

class RiversideApiClient extends BaseApiClient
{
    protected const DATE_FORMAT = 'd/m/Y';
    protected const TIME_FORMAT = 'H:i:s';
    protected const TIME_FORMAT_SEND_APPOINTMENT = 'H:i';

    //patterns must be in lower case
    protected const ERROR_PATTERN_APPOINTMENT_LOGGED_PREVIOUSLY = '/.*already has an appointment logged.*/';
    protected const ERROR_PATTERN_NO_APPOINTMENT = '/.*there is no open appointment for job .*, appointment cannot be cancelled.*/';
    protected const ERROR_PATTERN_JOB_NOT_FOUND = '/.*job_no .*is invalid.* job .*not found.*/';
    protected const ERROR_PATTERN_RECORD_LOCKED = '/.*record locked.*/';

    protected const KNOWN_ERRORS = [
        [
            'pattern' => self::ERROR_PATTERN_APPOINTMENT_LOGGED_PREVIOUSLY,
            'exception_class' => RiversideApiAppointmentLoggedPreviouslyException::class,
        ],
        [
            'pattern' => self::ERROR_PATTERN_NO_APPOINTMENT,
            'exception_class' => null,
        ],
        [
            'pattern' => self::ERROR_PATTERN_JOB_NOT_FOUND,
            'exception_class' => RiversideApiJobNotFoundException::class,
        ],
        [
            'pattern' => self::ERROR_PATTERN_RECORD_LOCKED,
            'exception_class' => RiversideApiRecordLockedException::class,
        ],
    ];

    protected $baseUrl;
    protected $userName;
    protected $password;
    protected $secret;

    public function __construct()
    {
        parent::__construct();

        $this->baseUrl = config('services.riverside.api_url');
        $this->userName = config('services.riverside.user_name');
        $this->password = config('services.riverside.password');
        $this->secret = config('services.riverside.secret');
    }

    public function getJob(string $jobOrderNo): SimpleXMLElement
    {
        return $this->makeRequest('riverside_request_xml.get_job', [
            'job_order_no' => $jobOrderNo,
        ]);
    }

    public function sendSorVariations(string $jobOrderNo, array $sorElements)
    {
        $this->makeRequest('riverside_request_xml.send_sor_variations', [
            'job_order_no' => $jobOrderNo,
            'sor_elements' => $sorElements
        ]);
    }

    public function sendDairyEntry(string $jobOrderNo, string $descriptionNotes, string $entryDate, string $entryTime)
    {
        $this->makeRequest('riverside_request_xml.send_dairy_entry', [
            'job_order_no' => $jobOrderNo,
            'description_notes' => $descriptionNotes,
            'entry_date' => $entryDate,
            'entry_time' => Carbon::parse($entryTime)->format(self::TIME_FORMAT),
        ]);
    }

    public function notifyAboutCompletedJob(string $jobOrderNo, Carbon $date)
    {
        $this->makeRequest('riverside_request_xml.notify_about_completed_job', [
            'job_order_no' => $jobOrderNo,
            'date' => $date->format(self::DATE_FORMAT),
            'time' => $date->format(self::TIME_FORMAT),
        ]);
    }

    public function cancelAppointment(string $jobOrderNo)
    {
        $this->makeRequest('riverside_request_xml.appointment_cancel', [
            'job_order_no' => $jobOrderNo,
        ]);
    }

    public function sendAppointment(string $jobOrderNo, Carbon $scheduleDate, Carbon $scheduleStartTime)
    {
        $this->makeRequest('riverside_request_xml.appointment_send', [
            'job_order_no' => $jobOrderNo,
            'schedule_date' => $scheduleDate->format(self::DATE_FORMAT),
            'schedule_start_time' => $scheduleStartTime->format(self::TIME_FORMAT_SEND_APPOINTMENT),
        ]);
    }

    public function notifyAboutAbandonStatus(string $jobOrderNo, Carbon $date)
    {
        $this->makeRequest('riverside_request_xml.notify_about_status_abandon', [
            'job_order_no' => $jobOrderNo,
            'date' => $date->format(self::DATE_FORMAT),
            'time' => $date->format(self::TIME_FORMAT),
        ]);
    }

    public function notifyAboutNoAccessStatus(string $jobOrderNo, int $attemptsCount, Carbon $date)
    {
        $attemptsCount = ($attemptsCount > 3) ? 3 : $attemptsCount;

        $this->makeRequest('riverside_request_xml.notify_about_status_no_access', [
            'job_order_no' => $jobOrderNo,
            'memo' => "No Access {$attemptsCount}",
            'stage' => "27{$attemptsCount}",
            'date' => $date->format(self::DATE_FORMAT),
            'time' => $date->format(self::TIME_FORMAT),
        ]);
    }

    protected function getUrl(string $action): string
    {
        return $this->baseUrl;
    }

    protected function getHeaders(): array
    {
        return [
            'Accept' => 'application/xml',
            'Content-Type' => 'application/xml',
        ];
    }

    protected function getRequestDestination(): string
    {
        return RequestLog::DESTINATION_RIVERSIDE;
    }

    protected function makeRequest(string $view, array $data = []): SimpleXMLElement
    {
        $data['user_name'] = $this->userName;
        $data['password'] = $this->password;
        $data['secret'] = $this->secret;

        $renderedXML = view($view, $data)->render();

        $response = $this->rawRequest('post', '', $renderedXML);

        return $this->parseResponse($response);
    }

    protected function parseResponse(ResponseInterface $response): SimpleXMLElement
    {
        $responseContent = (string) $response->getBody();

        try {
            $xml = new SimpleXMLElement($responseContent);

            $isFailed = isset($xml->ErrorDetails);
        } catch (Exception $exception) {
            $isFailed = true;
        }

        if ($isFailed) {
            $this->handleFailResponse($this->getUrl(''), $response);
        }

        return $xml;
    }

    protected function handleFailResponse(string $url, ResponseInterface $response): void
    {
        $responseBody = strtolower($response->getBody());

        foreach (self::KNOWN_ERRORS as $knownError) {
            if (preg_match($knownError['pattern'], $responseBody)) {
                if (is_null($knownError['exception_class'])) {
                    //ignore the problem
                    return;
                } else {
                    throw new $knownError['exception_class']($url, $response);
                }
            }
        }

        throw new ApiClientHttpException($url, $response);
    }
}
