<?php

namespace App\Resources\JobOperations;

use App\Models\JobOperation;
use App\Resources\SimproApi\SimproWebhookResource;

class JobOperationHandleWebhookFailResource extends JobOperationResource
{
    public function __construct(int $jobId, SimproWebhookResource $webhook)
    {
        parent::__construct(
            $jobId,
            JobOperation::OPERATION_HANDLE_WEBHOOK,
            JobOperation::STATUS_FAIL,
            [
                'webhook_type' => $webhook->type,
                'webhook_description' => $webhook->description,
            ],
        );
    }
}
