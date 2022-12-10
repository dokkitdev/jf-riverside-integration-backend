<?php

namespace App\Http\Controllers;

use App\Http\Middleware\WithinJobOperationMiddleware;
use App\Http\Requests\SimproWebhookRequest;
use App\Resources\SimproApi\SimproWebhookResource;
use App\Services\JobsFlowService;

class SimproController extends Controller
{
    public function webhook(SimproWebhookRequest $request, JobsFlowService $service)
    {
        $service->initSimproWebhookProcessing(
            SimproWebhookResource::makeFromSimproRequestData($request->all()),
            WithinJobOperationMiddleware::getJobOperation(),
        );

        return response(null);
    }
}
