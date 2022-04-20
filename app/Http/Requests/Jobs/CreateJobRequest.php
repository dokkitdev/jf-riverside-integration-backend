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
        if (!isset($xml->Jobs->Job)) {
            return [];
        }

        $jobXml = $xml->Jobs->Job;

        return [
            'job' => $this->convertXmlAttributesToArray($jobXml),
            'sor' => isset($jobXml->SORs->SOR) ? $this->convertXmlAttributesToArray($jobXml->SORs->SOR) : null,
            'location' => isset($jobXml->Location) ? $this->convertXmlAttributesToArray($jobXml->Location) : null,
            'occupants' => isset($jobXml->Location->Occupants->Occupant) ? $this->convertXmlAttributesToArray($jobXml->Location->Occupants->Occupant) : null,
            'contact' => isset($jobXml->Location->Occupants->Occupant->Contacts->Contact) ? $this->convertXmlAttributesToArray($jobXml->Location->Occupants->Occupant->Contacts->Contact) : null,
        ];
    }
}
