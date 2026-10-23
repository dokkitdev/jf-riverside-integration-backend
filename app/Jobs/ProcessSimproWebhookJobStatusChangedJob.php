<?php

namespace App\Jobs;

use App\Services\JobsFlowService;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldQueue;

class ProcessSimproWebhookJobStatusChangedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $jobId;
    protected int $simproStatusId;

    public function __construct(int $jobId, int $simproStatusId)
    {
        $this->jobId = $jobId;
        $this->simproStatusId = $simproStatusId;

        $this->onQueue('process_simpro_webhook');
    }

    public function handle()
    {
        app(JobsFlowService::class)->processSimproWebhookJobStatusChanged($this->jobId, $this->simproStatusId);
    }

    public function getJobId(): int
    {
        return $this->jobId;
    }

    public function getSimproStatusId(): int
    {
        return $this->simproStatusId;
    }
}
