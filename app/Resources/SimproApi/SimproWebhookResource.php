<?php

namespace App\Resources\SimproApi;

use App\Resources\Resource;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class SimproWebhookResource extends Resource
{
    public string $type;
    public string $description;
    public Carbon $dateTriggered;
    public int $jobId;
    public ?int $statusId;
    public ?int $scheduleId;

    public static function makeFromSimproRequestData(array $simproRequestData): self
    {
        return new self(
            $simproRequestData['ID'],
            $simproRequestData['description'],
            Carbon::parse($simproRequestData['date_triggered']),
            $simproRequestData['reference']['jobID'],
            Arr::get($simproRequestData, 'reference.statusID'),
            Arr::get($simproRequestData, 'reference.scheduleID')
        );
    }

    public function __construct(string $type, string $description, Carbon $dateTriggered, int $jobId, ?int $statusId, ?int $scheduleId)
    {
        $this->type = $type;
        $this->description = $description;
        $this->dateTriggered = $dateTriggered;
        $this->jobId = $jobId;
        $this->statusId = $statusId;
        $this->scheduleId = $scheduleId;
    }
}
