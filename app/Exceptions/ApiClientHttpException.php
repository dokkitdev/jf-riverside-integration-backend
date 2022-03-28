<?php

namespace App\Exceptions;

use Exception;
use Sentry\Breadcrumb;
use function Sentry\addBreadcrumb;

class ApiClientHttpException extends Exception
{
    public function __construct(string $url, int $statusCode, string $body)
    {
        addBreadcrumb(new Breadcrumb(
            Breadcrumb::LEVEL_ERROR,
            Breadcrumb::TYPE_DEFAULT,
            'External API',
            'HTTP request failed',
            [
                'url' => $url,
                'status_code' => $statusCode,
                'response_body' => $body,
            ]
        ));

        parent::__construct("URL: {$url} Status code: {$statusCode}");
    }
}
