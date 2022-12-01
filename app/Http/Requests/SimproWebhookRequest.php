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
            'description' => 'present|string',
            'reference' => 'required|array',
            'reference.jobID' => 'required|integer',
            'reference.statusID' => 'required_if:ID,job.status|integer',
            'reference.scheduleID' => 'required_if:ID,job.schedule.created|integer',
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
