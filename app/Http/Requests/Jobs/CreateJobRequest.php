<?php

namespace App\Http\Requests\Jobs;

use App\Http\Requests\XmlRequest;
use App\Services\JobsFlowService;
use SimpleXMLElement;

class CreateJobRequest extends XmlRequest
{
    public function rules(): array
    {
        $availablePriorities = implode(',', JobsFlowService::RIVERSIDE_PRIORITIES);

        return [
            'jobs' => 'array|present|min:1',
            'jobs.*.location.place_ref' => 'required|string',
            'jobs.*.location.address1' => 'required|string',
            'jobs.*.job_no' => 'required|string',
            'jobs.*.latest_priority' => "required|integer|in:{$availablePriorities}",
        ];
    }

    protected function convertXmlPayloadToArray(SimpleXMLElement $xml): array
    {
        $result = [
            'jobs' => []
        ];

        foreach ($xml->contractor as $contractor) {
            if (!isset($contractor->contracts->contract)) {
                continue;
            }

            foreach ($contractor->contracts->contract as $contract) {
                if (!isset($contract->jobs->job)) {
                    continue;
                }

                foreach ($contract->jobs->job as $job) {
                    $jobData = $this->convertXmlAttributesToArray($job) + [
                        'location' => isset($job->location) ? $this->convertXmlAttributesToArray($job->location) : null,
                        'occupants' => null,
                        'contact' => null,
                        'sors' => [],
                    ];

                    if (isset($job->sors->sor)) {
                        foreach ($job->sors->sor as $sor) {
                            $jobData['sors'][] = $this->convertXmlAttributesToArray($sor);
                        }
                    }

                    $result['jobs'][] = $jobData;
                }
            }
        }

        return $result;
    }
}
