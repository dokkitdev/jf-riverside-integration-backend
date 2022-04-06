<?php

namespace App\Jobs;

use App\Services\JobsFlowService;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Carbon;

class NotifyRiversideAboutCreatedJobJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $jobId;
    protected Carbon $date;

    public function __construct(int $jobId, Carbon $date)
    {
        $this->jobId = $jobId;
        $this->date = $date;

        $this->onQueue('send_job_to_simpro');
    }

    public function handle()
    {
        app(JobsFlowService::class)->notifyRiversideAboutCreatedJob($this->jobId, $this->date);
    }

    public function getJobId(): string
    {
        return $this->jobId;
    }

    public function getDate(): Carbon
    {
        return $this->date;
    }
}
