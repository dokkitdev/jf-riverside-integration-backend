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

    protected string $jobOrderNo;
    protected Carbon $date;

    public function __construct(string $jobOrderNo, Carbon $date)
    {
        $this->jobOrderNo = $jobOrderNo;
        $this->date = $date;

        $this->onQueue('send_job_to_simpro');
    }

    public function handle()
    {
        app(JobsFlowService::class)->notifyRiversideAboutCreatedJob($this->jobOrderNo, $this->date);
    }

    public function getJobOrderNo(): string
    {
        return $this->jobOrderNo;
    }

    public function getDate(): Carbon
    {
        return $this->date;
    }
}
