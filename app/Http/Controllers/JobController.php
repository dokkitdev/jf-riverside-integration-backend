<?php

namespace App\Http\Controllers;

use App\Http\Requests\Jobs\CreateJobRequest;
use App\Resources\CreateJob\CreateJobResponseSuccessResource;
use App\Services\JobsFlowService;

class JobController extends Controller
{
    public function create(CreateJobRequest $request, JobsFlowService $service)
    {
        $simproJobId = $service->createJob($request->all());

        $resource = new CreateJobResponseSuccessResource($simproJobId);

        return response()->json($resource->toArray());
    }
}
