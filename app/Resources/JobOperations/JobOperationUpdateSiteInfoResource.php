<?php

namespace App\Resources\JobOperations;

use App\Models\JobOperation;

class JobOperationUpdateSiteInfoResource extends JobOperationResource
{
    public function __construct(int $jobId, string $status)
    {
        parent::__construct(
            $jobId,
            JobOperation::OPERATION_UPDATE_SITE_INFO,
            $status,
            null
        );
    }
}
