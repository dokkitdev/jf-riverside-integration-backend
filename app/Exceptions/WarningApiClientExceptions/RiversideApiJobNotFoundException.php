<?php

namespace App\Exceptions\WarningApiClientExceptions;

class RiversideApiJobNotFoundException extends AbstractWarningApiClientException
{
    public function getWarningMessage(): string
    {
        return 'Riverside: job not found';
    }
}
