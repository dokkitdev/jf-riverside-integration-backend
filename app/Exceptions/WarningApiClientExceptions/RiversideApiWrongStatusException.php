<?php

namespace App\Exceptions\WarningApiClientExceptions;

class RiversideApiWrongStatusException extends AbstractWarningApiClientException
{
    public function getWarningMessage(): string
    {
        return 'Riverside: wrong status';
    }
}
