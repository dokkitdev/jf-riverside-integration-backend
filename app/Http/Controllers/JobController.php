<?php

namespace App\Http\Controllers;

use App\Http\Requests\Jobs\CreateJobRequest;
use App\Services\JobsFlowService;

class JobController extends Controller
{
    public function create(CreateJobRequest $request, JobsFlowService $service)
    {
        $service->initSendingToSimpro($request->all());

        return response(null);
    }
}