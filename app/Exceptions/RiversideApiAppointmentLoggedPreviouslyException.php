<?php

namespace App\Exceptions;

class RiversideApiAppointmentLoggedPreviouslyException extends ApiClientHttpException
{
    public function __construct(ApiClientHttpException $initialException)
    {
        parent::__construct($initialException->getUrl(), $initialException->getResponse(), $initialException);
    }
}
