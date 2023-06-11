<?php

namespace App\Providers;

use App\Support\QueueWithStreams\DatabaseWithStreamsConnector;
use Illuminate\Support\ServiceProvider;

class QueueServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app['queue']->addConnector('database_with_streams', function () {
            return new DatabaseWithStreamsConnector($this->app['db']);
        });
    }
}