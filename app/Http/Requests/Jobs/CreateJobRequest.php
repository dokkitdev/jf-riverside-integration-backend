<?php

namespace App\Http\Requests\Jobs;

use App\Exceptions\CreateJobRequestException;
use App\Http\Requests\Request;
use App\Services\JobsFlowService;
use Illuminate\Validation\ValidationException;
use SimpleXMLElement;
use Exception;

class CreateJobRequest extends Request
{
    public function rules(): array
    {
        $availablePriorities = implode(',', JobsFlowService::RIVERSIDE_PRIORITIES);

        return [
            'location.place_ref' => 'required|string',
            'location.address1' => 'required|string',
            'job_no' => 'required|string|unique:jobs,order_no',
            'latest_priority' => "required|integer|in:{$availablePriorities}",
        ];
    }

    public function validateResolved()
    {
        try {
            $this->parseXmlPayload();

            parent::validateResolved();
        } catch (ValidationException $exception) {
            throw new CreateJobRequestException(json_encode($exception->errors()));
        }
    }

    protected function parseXmlPayload()
    {
        try {
            $xml = new SimpleXMLElement($this->getContent());
        } catch (Exception $exception) {
            throw new CreateJobRequestException("Can't parse XML payload");
        }

        $this->replace($this->convertXmlPayloadToArray($xml));
    }

    protected function convertXmlPayloadToArray(SimpleXMLElement $xml): array
    {
        $jobs = [];

        if (!isset($xml->contractor)) {
            throw new CreateJobRequestException('Unexpected format');
        }

        foreach ($xml->contractor as $contractor) {
            if (!isset($contractor->contracts->contract)) {
                throw new CreateJobRequestException('Unexpected format');
            }

            foreach ($contractor->contracts->contract as $contract) {
                if (!isset($contract->jobs->job)) {
                    throw new CreateJobRequestException('Unexpected format');
                }

                foreach ($contract->jobs->job as $job) {
                    $jobData = $this->convertXmlAttributesToArray($job) + [
                            'location' => isset($job->location) ? $this->convertXmlAttributesToArray($job->location) : null,
                            'sors' => [],
                        ];

                    if (isset($job->sors->sor)) {
                        foreach ($job->sors->sor as $sor) {
                            $jobData['sors'][] = $this->convertXmlAttributesToArray($sor);
                        }
                    }

                    $jobs[] = $jobData;
                }
            }
        }

        if (count($jobs) !== 1) {
            throw new CreateJobRequestException('Request must contain one job');
        }

        return $jobs[0];
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
