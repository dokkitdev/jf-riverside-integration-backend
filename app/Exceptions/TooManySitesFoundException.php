<?php

namespace App\Exceptions;

use Exception;
use Sentry\Breadcrumb;
use function Sentry\addBreadcrumb;

class TooManySitesFoundException extends Exception
{
    public function __construct(string $uprn, array $foundSites)
    {
        addBreadcrumb(new Breadcrumb(
            Breadcrumb::LEVEL_ERROR,
            Breadcrumb::TYPE_DEFAULT,
            'External API',
            'Too many sites found',
            [
                'uprn' => $uprn,
                'found_sites' => $foundSites,
            ]
        ));

        parent::__construct("Too many sites found");
    }
}
