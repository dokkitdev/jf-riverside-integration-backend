<?php

namespace App\Services;

use App\Repositories\RequestLogRepository;
use RonasIT\Support\Services\EntityService;

/**
 * @property RequestLogRepository $repository
 * @mixin RequestLogRepository
 */
class RequestLogService extends EntityService
{
    public function __construct()
    {
        $this->setRepository(RequestLogRepository::class);
    }
}
