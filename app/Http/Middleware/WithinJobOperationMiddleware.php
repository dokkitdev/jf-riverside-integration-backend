<?php

namespace App\Http\Middleware;

use App\Models\JobOperation;
use App\Services\JobOperationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WithinJobOperationMiddleware
{
    protected static JobOperation $jobOperation;

    public static function getJobOperation(): JobOperation
    {
        return self::$jobOperation;
    }

    public function handle(Request $request, Closure $next, string $jobOperationType): Response
    {
        return app(JobOperationService::class)->withinJobOperation(function (JobOperation $jobOperation) use ($request, $next, $jobOperationType) {
            self::$jobOperation = $jobOperation;

            $jobOperation['operation'] = $jobOperationType;

            return $next($request);
        });
    }
}
