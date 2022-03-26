<?php

namespace App\Http\Requests\Jobs;

use App\Http\Requests\XmlRequest;
use SimpleXMLElement;

class CreateJobRequest extends XmlRequest
{
    public function rules(): array
    {
        //TODO: set after actual XML format is defined
        return [];
    }

    protected function convertXmlPayloadToArray(SimpleXMLElement $xml): array
    {
        return [
            'job' => isset($xml->Job) ? $this->convertXmlAttributesToArray($xml->Job) : null,
            'sor' => isset($xml->SOR) ? $this->convertXmlAttributesToArray($xml->SOR) : null,
            'location' => isset($xml->Location) ? $this->convertXmlAttributesToArray($xml->Location) : null,
            'occupants' => isset($xml->Occupants) ? $this->convertXmlAttributesToArray($xml->Occupants) : null,
            'contact' => isset($xml->Contact) ? $this->convertXmlAttributesToArray($xml->Contact) : null,
        ];
    }
}
