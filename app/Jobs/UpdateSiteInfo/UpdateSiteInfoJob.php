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

    protected int $jobId;

    public function __construct(int $jobId)
    {
        $this->jobId = $jobId;

        $this->onQueue('update_site_info');
    }

    public function handle()
    {
        app(JobsFlowService::class)->updateSiteInfo($this->jobId);
    }

    public function failed()
    {
        app(JobsFlowService::class)->failedUpdateSiteInfo($this->jobId);
    }

    public function uniqueId(): string
    {
        return (string) $this->jobId;
    }

    public function getJobId(): int
    {
        return $this->jobId;
    }
}
