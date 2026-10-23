<?php

namespace App\Jobs;

use App\Services\JobsFlowService;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendJobToSimproJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $jobId;
    protected array $dataFromRiverside;

    public function __construct(int $jobId, array $dataFromRiverside)
    {
        $this->jobId = $jobId;
        $this->dataFromRiverside = $dataFromRiverside;

        $this->onQueue('send_job_to_simpro');
    }

    public function handle()
    {
        app(JobsFlowService::class)->sendJobToSimpro($this->jobId, $this->dataFromRiverside);
    }

    public function getJobId(): int
    {
        return $this->jobId;
    }

    public function getDataFromRiverside(): array
    {
        return $this->dataFromRiverside;
    }
}
