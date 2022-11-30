<?php

namespace App\Exceptions;

use App\Resources\CreateJob\CreateJobResponseFailResource;
use Exception;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class CreateJobRequestException extends Exception
{
    public function render(): JsonResponse
    {
        $resource = new CreateJobResponseFailResource($this->message);

        return response()->json($resource->toArray(), Response::HTTP_BAD_REQUEST);
    }
}
