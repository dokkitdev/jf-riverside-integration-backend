<?php

namespace App\Resources\CreateJob;

class CreateJobResponseResource
{
    protected bool $isSuccess;
    protected ?int $simproJobId;
    protected string $diagnosticMessage;

    public static function makeSuccess(int $simproJobId): self
    {
        return new self(true, $simproJobId, '');
    }

    public static function makeFail(string $diagnosticMessage): self
    {
        return new self(false, null, $diagnosticMessage);
    }

    public function __construct(bool $isSuccess, ?int $simproJobId, string $diagnosticMessage)
    {
        $this->isSuccess = $isSuccess;
        $this->simproJobId = $simproJobId;
        $this->diagnosticMessage = $diagnosticMessage;
    }

    public function toArray()
    {
        return [
            'IsSuccess' => $this->isSuccess,
            'WorkOrderNo' => $this->simproJobId ?? '',
            'DiagnosticMessage' => $this->diagnosticMessage,
        ];
    }
}
