<?php

namespace App\Jobs\UpdateSiteInfo;

use App\Services\JobsFlowService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldQueue;

class UpdateSiteInfoJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 3600;

    protected int $jobId;
    protected int $jobOperationId;

    public function __construct(int $jobId, int $jobOperationId)
    {
        $this->jobId = $jobId;

        $this->jobOperationId = $jobOperationId;

        $this->onQueue('update_site_info');
    }

    public function handle()
    {
        app(JobsFlowService::class)->updateSiteInfo($this->jobId, $this->jobOperationId);
    }

    public function failed()
    {
        app(JobsFlowService::class)->failedQueueJob($this->jobOperationId);
    }

    public function uniqueId(): string
    {
        return (string) $this->jobId;
    }

    public function getJobId(): int
    {
        return $this->jobId;
    }

    public function getJobOperationId(): int
    {
        return $this->jobOperationId;
    }
}
