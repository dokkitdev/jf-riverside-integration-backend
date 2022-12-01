<?php

namespace App\Resources\JobOperations;

use App\Resources\Resource;

class JobOperationResource extends Resource
{
    public int $jobId;
    public string $operation;
    public string $status;
    public ?array $details;

    public function __construct(int $jobId, string $operation, string $status, ?array $details)
    {
        $this->jobId = $jobId;
        $this->operation = $operation;
        $this->status = $status;
        $this->details = $details;
    }

    public function toArray(): array
    {
        return [
            'job_id' => $this->jobId,
            'operation' => $this->operation,
            'status' => $this->status,
            'details' => $this->details,
        ];
    }
}
