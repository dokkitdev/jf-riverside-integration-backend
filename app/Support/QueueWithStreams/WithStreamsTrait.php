<?php

namespace App\Support\QueueWithStreams;

trait WithStreamsTrait
{
    public function forceRetry($delay = 0): void
    {
        if ($this->job instanceof DatabaseWithStreamsJob) {
            $this->job->forceRetry($delay);
        }
    }
}
