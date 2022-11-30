<?php

namespace App\Resources\CreateJob;

class CreateJobResponseSuccessResource extends CreateJobResponseResource
{
    public function __construct(int $simproJobId)
    {
        parent::__construct(true, $simproJobId, '');
    }
}
