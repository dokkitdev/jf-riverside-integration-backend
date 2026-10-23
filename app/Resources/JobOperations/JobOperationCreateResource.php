<?php

namespace App\Resources\JobOperations;

use App\Models\JobOperation;

class JobOperationCreateResource extends JobOperationResource
{
    public function __construct(int $jobId)
    {
        parent::__construct(
            $jobId,
            JobOperation::OPERATION_CREATE,
            JobOperation::STATUS_SUCCESS,
            null
        );
    }
}
