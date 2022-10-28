<?php

namespace App\Http\Requests\Jobs;

use App\Http\Requests\XmlRequest;
use App\Services\JobsFlowService;
use SimpleXMLElement;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class CreateJobRequest extends XmlRequest
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

    protected function convertXmlPayloadToArray(SimpleXMLElement $xml): array
    {
        $jobs = [];

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

                    $jobs[] = $jobData;
                }
            }
        }

        if (count($jobs) !== 1) {
            throw new ConflictHttpException('Request must contain one job');
        }

        return $jobs[0];
    }
}
