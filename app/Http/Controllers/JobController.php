<?php

namespace App\Http\Controllers;

use App\Http\Requests\Jobs\CreateJobRequest;
use App\Services\JobsService;

class JobController extends Controller
{
    public function create(CreateJobRequest $request, JobsService $service)
    {
        $service->initSendingToSimpro($request->all());

        return response(null);
    }
}