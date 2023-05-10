<?php

namespace App\Exceptions;

use Exception;
use Sentry\Breadcrumb;
use function Sentry\addBreadcrumb;

class ApiClientHttpException extends Exception
{
    protected string $url;
    protected int $statusCode;
    protected string $responseBody;

    public function __construct(string $url, int $statusCode, string $responseBody, ?Exception $previousException = null)
    {
        $this->url = $url;
        $this->statusCode = $statusCode;
        $this->responseBody = $responseBody;

        addBreadcrumb(new Breadcrumb(
            Breadcrumb::LEVEL_ERROR,
            Breadcrumb::TYPE_DEFAULT,
            'External API',
            'HTTP request failed',
            [
                'url' => $url,
                'status_code' => $statusCode,
                'response_body' => $responseBody,
            ]
        ));

        parent::__construct("URL: {$url} Status code: {$statusCode}", 0, $previousException);
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function getResponseBody(): string
    {
        return $this->responseBody;
    }
}
