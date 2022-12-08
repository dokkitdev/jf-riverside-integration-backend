<?php

namespace App\Providers;

use App\Events\ApiCallEvent;
use App\Services\JobOperationService;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Event;

class EventServiceProvider extends ServiceProvider
{
    public function boot()
    {
        Event::listen(
            ApiCallEvent::class,
            [JobOperationService::class, 'apiCallEventHandler']
        );
    }
}
