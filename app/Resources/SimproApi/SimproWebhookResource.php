<?php

namespace App\Resources\SimproApi;

use App\Resources\Resource;
use Illuminate\Support\Arr;

class SimproWebhookResource extends Resource
{
    //TODO: implement description usage for creating job operations
    public string $type;
    public string $description;
    public int $jobId;
    public ?int $statusId;
    public ?int $scheduleId;

    public static function makeFromSimproRequestData(array $simproRequestData): self
    {
        return new self(
            $simproRequestData['ID'],
            $simproRequestData['description'],
            $simproRequestData['reference']['jobID'],
            Arr::get($simproRequestData, 'reference.statusID'),
            Arr::get($simproRequestData, 'reference.scheduleID')
        );
    }

    public function __construct(string $type, string $description, int $jobId, ?int $statusId, ?int $scheduleId)
    {
        $this->type = $type;
        $this->description = $description;
        $this->jobId = $jobId;
        $this->statusId = $statusId;
        $this->scheduleId = $scheduleId;
    }
}
