<?php

namespace App\Jobs;

use App\Services\JobsFlowService;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldQueue;

class ProcessSimproWebhookJobCompletedJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $jobId;

    public function __construct(int $jobId)
    {
        $this->jobId = $jobId;

        $this->onQueue('process_simpro_webhook');
    }

    public function handle()
    {
        app(JobsFlowService::class)->processSimproWebhookJobCompleted($this->jobId);
    }

    public function getJobId(): int
    {
        return $this->jobId;
    }
}
