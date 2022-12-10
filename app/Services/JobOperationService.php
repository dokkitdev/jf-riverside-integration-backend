<?php

namespace App\Services;

use App\Events\ApiCallEvent;
use App\Models\JobOperation;
use App\Models\RequestLog;
use App\Repositories\JobOperationRepository;
use Illuminate\Database\Eloquent\Collection;
use RonasIT\Support\Services\EntityService;
use Throwable;

/**
 * @property JobOperationRepository $repository
 * @mixin JobOperationRepository
 */
class JobOperationService extends EntityService
{
    /** @var RequestLog[] */
    protected static Collection $requestLogBuffer;
    protected static bool $withinJobOperationMode = false;

    public function __construct()
    {
        $this->setRepository(JobOperationRepository::class);
    }

    public function withinJobOperation(callable $callback, ?int $existedJobOperationId = null)
    {
        self::$requestLogBuffer = new Collection();
        self::$withinJobOperationMode = true;

        $jobOperation = isset($existedJobOperationId) ? $this->find($existedJobOperationId) : new JobOperation();

        try {
            $result = $callback($jobOperation);

            if (is_null($jobOperation['status'])) {
                $jobOperation['status'] = JobOperation::STATUS_FAIL;
            }
        } catch (Throwable $exception) {
            $jobOperation['status'] = JobOperation::STATUS_FAIL;

            throw $exception;
        } finally {
            if ($jobOperation->shouldBePersisted()) {
                $jobOperation->save();

                $jobOperation->requestLogs()->attach(self::$requestLogBuffer);
            }

            self::$requestLogBuffer = new Collection();
            self::$withinJobOperationMode = false;
        }

        return $result;
    }

    public function apiCallEventHandler(ApiCallEvent $event)
    {
        if (self::$withinJobOperationMode) {
            self::$requestLogBuffer[] = $event->getRequestLog();
        }
    }
}
