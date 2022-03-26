<?php

namespace App\Http\Requests;

use SimpleXMLElement;
use Exception;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

abstract class XmlRequest extends Request
{
    abstract protected function convertXmlPayloadToArray(SimpleXMLElement $element): array;

    public function validateResolved()
    {
        $this->parseXmlPayload();

        parent::validateResolved();
    }

    protected function parseXmlPayload()
    {
        try {
            $xml = new SimpleXMLElement($this->getContent());
        } catch (Exception $exception) {
            throw new BadRequestHttpException("Can't parse XML payload");
        }

        $this->replace($this->convertXmlPayloadToArray($xml));
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