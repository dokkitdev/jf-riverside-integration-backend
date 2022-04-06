<?php

namespace App\Jobs;

use App\Services\JobsFlowService;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldQueue;

class ProcessSimproWebhookJobScheduleCreatedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $jobId;
    protected int $simproJobScheduleId;

    public function __construct(int $jobId, int $simproJobScheduleId)
    {
        $this->jobId = $jobId;
        $this->simproJobScheduleId = $simproJobScheduleId;

        $this->onQueue('process_simpro_webhook');
    }

    public function handle()
    {
        app(JobsFlowService::class)->processSimproWebhookJobScheduleCreated($this->jobId, $this->simproJobScheduleId);
    }

    public function getJobId(): int
    {
        return $this->jobId;
    }

    public function getSimproJobScheduleId(): int
    {
        return $this->simproJobScheduleId;
    }
}
