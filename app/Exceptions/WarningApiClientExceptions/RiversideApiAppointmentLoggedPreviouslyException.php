<?php

namespace App\Exceptions\WarningApiClientExceptions;

class RiversideApiAppointmentLoggedPreviouslyException extends AbstractWarningApiClientException
{
    public function getWarningMessage(): string
    {
        return 'Riverside: already has an appointment logged';
    }
}
