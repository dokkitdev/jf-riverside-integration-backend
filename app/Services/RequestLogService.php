<?php

namespace App\Services;

use App\Models\RequestLog;
use App\Repositories\RequestLogRepository;
use RonasIT\Support\Services\EntityService;

/**
 * @property RequestLogRepository $repository
 * @mixin RequestLogRepository
 */
class RequestLogService extends EntityService
{
    protected static int $currentRequestId;

    public static function getCurrentRequestId(): int
    {
        return self::$currentRequestId;
    }

    public function __construct()
    {
        $this->setRepository(RequestLogRepository::class);
    }

    public function create(array $data): RequestLog
    {
        $logEntry = $this->repository->create($data);

        if ($data['destination'] === RequestLog::DESTINATION_SELF) {
            self::$currentRequestId = $logEntry['id'];
        }

        return $logEntry;
    }
}
