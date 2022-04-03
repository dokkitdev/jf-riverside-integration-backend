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

    protected array $dataFromRiverside;

    public function __construct(array $dataFromRiverside)
    {
        $this->dataFromRiverside = $dataFromRiverside;

        $this->onQueue('send_job_to_simpro');
    }

    public function handle()
    {
        app(JobsFlowService::class)->sendJobToSimpro($this->dataFromRiverside);
    }

    public function getDataFromRiverside(): array
    {
        return $this->dataFromRiverside;
    }
}
