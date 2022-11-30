<?php

namespace App\Jobs;

use App\Resources\SimproApi\SimproWebhookResource;
use App\Services\JobsFlowService;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldQueue;

class ProcessSimproWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected SimproWebhookResource $simproWebhookResource;

    public function __construct(SimproWebhookResource $simproWebhookResource)
    {
        $this->simproWebhookResource = $simproWebhookResource;

        $this->onQueue('process_simpro_webhook');
    }

    public function handle()
    {
        app(JobsFlowService::class)->processSimproWebhook($this->simproWebhookResource);
    }

    public function failed()
    {
        app(JobsFlowService::class)->failedProcessingSimproWebhook($this->simproWebhookResource);
    }

    public function getSimproWebhookResource(): SimproWebhookResource
    {
        return $this->simproWebhookResource;
    }
}
