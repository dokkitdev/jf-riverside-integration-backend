<?php

namespace App\Resources\JobOperations;

use App\Models\JobOperation;

class JobOperationNotifyRiversideAboutCreatedJobResource extends JobOperationResource
{
    public function __construct(int $jobId, string $status)
    {
        parent::__construct(
            $jobId,
            JobOperation::OPERATION_NOTIFY_RIVERSIDE_ABOUT_CREATED_JOB,
            $status,
            null
        );
    }
}
