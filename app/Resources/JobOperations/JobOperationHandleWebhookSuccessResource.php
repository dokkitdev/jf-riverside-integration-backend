<?php

namespace App\Resources\JobOperations;

use App\Models\JobOperation;
use App\Resources\SimproApi\SimproWebhookResource;

class JobOperationHandleWebhookSuccessResource extends JobOperationResource
{
    public function __construct(int $jobId, SimproWebhookResource $webhook, ?string $skipReason = null)
    {
        $details = [
            'webhook_type' => $webhook->type,
            'webhook_description' => $webhook->description,
        ];

        if (isset($skipReason)) {
            $details['skip_reason'] = $skipReason;
            $status = JobOperation::STATUS_SKIP;
        } else {
            $status = JobOperation::STATUS_SUCCESS;
        }

        parent::__construct(
            $jobId,
            JobOperation::OPERATION_HANDLE_WEBHOOK,
            $status,
            $details,
        );
    }
}
