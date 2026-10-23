<?php

namespace App\Jobs;

use App\Support\QueueWithStreams\WithStreamsInterface;
use App\Support\QueueWithStreams\WithStreamsTrait;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class TestJob implements ShouldQueue, WithStreamsInterface
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, WithStreamsTrait;

    protected const BACKOFF_SECONDS = [1, 5, 10, 20, 30];

    protected string $name;
    protected int $stream;
    protected Carbon $start;

    public function __construct(string $name, int $stream, Carbon $start)
    {
        $this->name = $name;
        $this->stream = $stream;
        $this->start = $start;

        $this->onQueue('process_simpro_webhook');
    }

    public function getStream(): ?string
    {
        return $this->stream;
    }

    public function handle()
    {
        $now = Carbon::now();

        $continue = ($this->start->addMinutes(5)->gt($now));

        Storage::append('test.txt', "{$now->format('H:i:s')}: {$this->name} - {$continue}", PHP_EOL);

        if ($continue) {
            $this->forceRetry(self::BACKOFF_SECONDS);
        } else {
            Storage::append('test.txt', "{$now->format('H:i:s')}: {$this->name} - success", PHP_EOL);
        }
    }
}

