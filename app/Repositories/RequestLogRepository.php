<?php

namespace App\Repositories;

use App\Models\RequestLog;
use RonasIT\Support\Repositories\BaseRepository;

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
