<?php

namespace App\Resources\CreateJob;

class CreateJobResponseFailResource extends CreateJobResponseResource
{
    public function __construct(string $diagnosticMessage)
    {
        parent::__construct(false, null, $diagnosticMessage);
    }
}
