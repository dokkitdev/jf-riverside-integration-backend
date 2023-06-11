<?php

namespace App\Support\QueueWithStreams;

use Illuminate\Database\DatabaseManager;
use Illuminate\Queue\Connectors\ConnectorInterface;

class DatabaseWithStreamsConnector implements ConnectorInterface
{
    protected DatabaseManager $connections;

    public function __construct(DatabaseManager $connections)
    {
        $this->connections = $connections;
    }

    public function connect(array $config): DatabaseWithStreamsQueue
    {
        return new DatabaseWithStreamsQueue(
            $this->connections->connection($config['connection'] ?? null),
            $config['table'],
            $config['default_queue'],
            $config['retry_after'] ?? 60,
            $config['after_commit'] ?? null
        );
    }
}