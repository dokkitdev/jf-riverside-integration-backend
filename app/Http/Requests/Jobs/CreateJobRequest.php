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
            'location.place_ref' => 'required|string',
            'location.address1' => 'required|string',
            'job.job_no' => 'required|string',
            'job.initial_priority' => "required|integer|in:{$availablePriorities}",
        ];
    }

    protected function convertXmlPayloadToArray(SimpleXMLElement $xml): array
    {
        if (!isset($xml->contractor->contracts->contract->jobs->job)) {
            return [];
        }

        $jobXml = $xml->contractor->contracts->contract->jobs->job;

        return [
            'job' => $this->convertXmlAttributesToArray($jobXml),
            'sor' => isset($jobXml->sors->sor) ? $this->convertXmlAttributesToArray($jobXml->sors->sor) : null,
            'location' => isset($jobXml->location) ? $this->convertXmlAttributesToArray($jobXml->location) : null,
            'occupants' => null,
            'contact' => null,
        ];
    }
}
