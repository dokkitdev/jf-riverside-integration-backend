<?php

namespace App\Http\Controllers;

use App\Http\Requests\Jobs\CreateJobRequest;
use App\Services\JobsFlowService;

class JobController extends Controller
{
    public function create(CreateJobRequest $request, JobsFlowService $service)
    {
        $workOrderNo = $service->initSendingToSimpro($request->get('jobs'));

        return response([
            'IsSuccess' => true,
            'WorkOrderNo' => $workOrderNo,
            'DiagnosticMessage' => '',
        ]);
    }
}