<?php

namespace App\Http\Controllers;

use App\Http\Requests\Jobs\CreateJobRequest;
use App\Services\JobsFlowService;

class JobController extends Controller
{
    public function create(CreateJobRequest $request, JobsFlowService $service)
    {
        $simproJobId = $service->initSendingToSimpro($request->all('jobs'));

        return response([
            'IsSuccess' => true,
            'WorkOrderNo' => $simproJobId,
            'DiagnosticMessage' => '',
        ]);
    }
}