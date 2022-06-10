<?php

namespace App\Repositories;

use App\Models\RequestLog;

/**
 * @property RequestLog $model
 */
class RequestLogRepository extends BaseRepository
{
    public function __construct()
    {
        $this->setModel(RequestLog::class);
    }
}
