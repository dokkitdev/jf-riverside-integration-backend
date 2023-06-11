<?php

namespace App\Support\QueueWithStreams;

use Illuminate\Database\Query\Builder;
use Illuminate\Queue\DatabaseQueue as BaseDatabaseQueue;
use Illuminate\Queue\Jobs\DatabaseJobRecord;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class DatabaseWithStreamsQueue extends BaseDatabaseQueue
{
    public function forceRetry(DatabaseWithStreamsJob $job, $delay): void
    {
        $this->database->transaction(function () use ($job, $delay) {
            if ($this->database->table($this->table)->lockForUpdate()->find($job->getJobId())) {
                $this->database
                    ->table($this->table)
                    ->where('id', $job->getJobId())
                    ->update([
                        'attempts' => DB::raw('attempts - 1'),
                        'available_at' => $this->availableAt($delay),
                        'reserved_at' => null,
                    ]);
            }
        });
    }

    protected function createObjectPayload($job, $queue): array
    {
        $payload = parent::createObjectPayload($job, $queue);

        $payload['stream'] = ($job instanceof WithStreamsInterface)
            ? $job->getStream()
            : null;

        return $payload;
    }

    protected function buildDatabaseRecord($queue, $payload, $availableAt, $attempts = 0): array
    {
        $record = parent::buildDatabaseRecord($queue, $payload, $availableAt, $attempts);

        $payload = json_decode($payload, true);

        $record['stream'] = Arr::get($payload, 'stream');

        return $record;
    }

    protected function getNextAvailableJob($queue): ?DatabaseJobRecord
    {
        $queue = $this->getQueue($queue);

        $job = $this->database
            ->table($this->table, 'current_row')
            ->lock($this->getLockForPopping())
            ->where('queue', $queue)
            ->where(function ($query) {
                $this->isAvailable($query);
                $this->isReservedButExpired($query);
            })
            ->where(function (Builder $query) use ($queue) {
                $query
                    ->orWhereNull('stream')
                    ->orWhereIn(
                        'id',
                        fn (Builder $subQuery) => $subQuery
                            ->from($this->table, 'stream_groups')
                            ->select(DB::raw('min(id)'))
                            ->where('queue', $queue)
                            ->whereNotNull('stream')
                            ->groupBy('stream')
                    );
            })
            ->orderBy('id')
            ->first();

        return $job ? new DatabaseJobRecord((object) $job) : null;
    }

    protected function marshalJob($queue, $job): DatabaseWithStreamsJob
    {
        $job = $this->markJobAsReserved($job);

        return new DatabaseWithStreamsJob(
            $this->container, $this, $job, $this->connectionName, $queue
        );
    }
}
