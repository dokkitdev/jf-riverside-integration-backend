<?php

namespace App\Exceptions;

use App\Dto\CreateJobResponseDto;
use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class CreateJobRequestException extends Exception
{
    public function render(): JsonResponse
    {
        return response()->json(CreateJobResponseDto::makeFail($this->message)->toArray(), Response::HTTP_BAD_REQUEST);
    }
}
