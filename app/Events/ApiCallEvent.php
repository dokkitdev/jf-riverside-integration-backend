<?php

namespace App\Events;

use App\Models\RequestLog;

class ApiCallEvent
{
    protected RequestLog $requestLog;

    public function __construct(RequestLog $requestLog)
    {
        $this->requestLog = $requestLog;
    }

    public function getRequestLog(): RequestLog
    {
        return $this->requestLog;
    }
}
