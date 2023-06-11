<?php

namespace App\Exceptions;

use Exception;
use Psr\Http\Message\ResponseInterface;
use Sentry\Breadcrumb;
use function Sentry\addBreadcrumb;

class ApiClientHttpException extends Exception
{
    protected string $url;
    protected ResponseInterface $response;

    public function __construct(string $url, ResponseInterface $response, ?Exception $previousException = null)
    {
        $this->url = $url;
        $this->response = $response;

        addBreadcrumb(new Breadcrumb(
            Breadcrumb::LEVEL_ERROR,
            Breadcrumb::TYPE_DEFAULT,
            'External API',
            'HTTP request failed',
            [
                'url' => $url,
                'status_code' => $response->getStatusCode(),
                'response_body' => (string) $response->getBody(),
            ]
        ));

        parent::__construct("URL: {$url} Status code: {$response->getStatusCode()}", 0, $previousException);
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }
}
