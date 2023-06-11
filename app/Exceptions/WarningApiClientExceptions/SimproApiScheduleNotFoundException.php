<?php

namespace App\Exceptions\WarningApiClientExceptions;

class SimproApiScheduleNotFoundException extends AbstractWarningApiClientException
{
    public function getWarningMessage(): string
    {
        return 'Simpro: schedule not found';
    }
}
