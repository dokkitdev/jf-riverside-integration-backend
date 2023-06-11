<?php

namespace App\Exceptions\WarningApiClientExceptions;

use App\Exceptions\ApiClientHttpException;

abstract class AbstractWarningApiClientException extends ApiClientHttpException
{
    abstract public function getWarningMessage(): string;
}
