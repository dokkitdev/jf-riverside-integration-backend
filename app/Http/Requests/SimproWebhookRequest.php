<?php

namespace App\Http\Requests;

use Exception;
use function Sentry\captureException;

class SimproWebhookRequest extends Request
{
    public function authorize(): bool
    {
        $secret = config('services.simpro.webhook_secret');
        $body = $this->getContent();
        $signature = $this->header('X-Response-Signature');

        return !empty($signature) && hash_equals($signature, hash_hmac('sha1', $body, $secret));
    }

    public function rules(): array
    {
        return [
            'ID' => 'required|string',
            'reference' => 'required|array',
            'reference.jobID' => 'integer', //TODO: add required_without
            'reference.statusID' => 'integer',
            //TODO: No documentation in Simpro site. Add remained rules after receiving corresponding requests.
        ];
    }

    public function validateResolved()
    {
        try {
            parent::validateResolved();
        } catch (Exception $exception) {
            captureException($exception);

            throw $exception;
        }
    }
}
