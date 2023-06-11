<?php

namespace App\Jobs;

use App\Models\JobOperation;
use App\Resources\SimproApi\SimproWebhookResource;
use App\Services\JobsFlowService;
use App\Support\QueueWithStreams\WithStreamsInterface;
use App\Support\QueueWithStreams\WithStreamsTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldQueue;

class ProcessSimproWebhookJob implements ShouldQueue, WithStreamsInterface
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, WithStreamsTrait;

    protected const BACKOFF_SECONDS = [60, 300, 600, 1200, 1800];

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
        $jobOperation = app(JobsFlowService::class)->processSimproWebhook($this->simproWebhookResource, $this->jobOperationId);

        if ($jobOperation['status'] === JobOperation::STATUS_RETRY) {
            $this->forceRetry(self::BACKOFF_SECONDS);
        }
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
