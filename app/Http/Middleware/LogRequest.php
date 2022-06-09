<?php

namespace App\Http\Middleware;

use App\Models\RequestLog;
use App\Services\RequestLogService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\Response;

class LogRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestLogService = app(RequestLogService::class);

        $requestLog = $requestLogService->create([
            'destination' => RequestLog::DESTINATION_SELF,
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'request_body' => $request->getContent(),
            'request_headers' => json_encode($request->headers->all()),
        ]);

        $response = $next($request);

        $requestLogService->update($requestLog['id'], [
            'response_status_code' => $response->getStatusCode(),
            'response_body' => $response->getContent(),
            'response_headers' => json_encode(Arr::except($response->headers->all(), 'date')),
        ]);

        return $response;
    }
}
