<?php

namespace App\Jobs;

use App\Resources\SimproApi\SimproWebhookResource;
use App\Services\JobsFlowService;
use App\Support\QueueWithStreams\WithStreamsInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldQueue;

class ProcessSimproWebhookJob implements ShouldQueue, WithStreamsInterface
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected SimproWebhookResource $simproWebhookResource;
    protected int $jobOperationId;

    public function __construct(SimproWebhookResource $simproWebhookResource, int $jobOperationId)
    {
        $this->simproWebhookResource = $simproWebhookResource;
        $this->jobOperationId = $jobOperationId;

        $this->onQueue('process_simpro_webhook');
    }

    public function getStream(): ?string
    {
        return $this->simproWebhookResource->jobId;
    }

    public function handle()
    {
        app(JobsFlowService::class)->processSimproWebhook($this->simproWebhookResource, $this->jobOperationId);
    }

    public function failed()
    {
        app(JobsFlowService::class)->failedQueueJob($this->jobOperationId);
    }

    public function getSimproWebhookResource(): SimproWebhookResource
    {
        return $this->simproWebhookResource;
    }

    public function getJobOperationId(): int
    {
        return $this->jobOperationId;
    }
}
