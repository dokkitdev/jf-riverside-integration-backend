<?php

namespace App\Support\QueueWithStreams;

use Illuminate\Queue\Jobs\DatabaseJob;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class DatabaseWithStreamsJob extends DatabaseJob
{
    public function forceRetry($delay = 0): void
    {
        $this->released = true;

        if (is_array($delay)) {
            $delay = $this->getBackoffDelay($delay);
        }

        $this->database->forceRetry($this, $delay);
    }

    protected function getBackoffDelay(array $backoff): int
    {
        $cacheKey = "queue_jobs:backoff:{$this->uuid()}";

        $count = Cache::increment($cacheKey, 0);
        Cache::put($cacheKey, $count + 1, Carbon::now()->addDay());

        return (int) ($backoff[$count] ?? last($backoff));
    }
}
