<?php

namespace App\Services;

use App\Events\ApiCallEvent;
use App\Models\RequestLog;
use App\Repositories\RequestLogRepository;
use Illuminate\Support\Facades\Event;
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

    public function create(array $data): RequestLog
    {
        $requestLog = $this->repository->create($data);

        Event::dispatch(new ApiCallEvent($requestLog));

        return $requestLog;
    }
}
