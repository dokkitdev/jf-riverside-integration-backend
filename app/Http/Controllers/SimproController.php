<?php

namespace App\Http\Controllers;

use App\Http\Requests\SimproWebhookRequest;
use App\Services\JobsFlowService;

class SimproController extends Controller
{
    public function webhook(SimproWebhookRequest $request, JobsFlowService $service)
    {
        $service->processSimproWebhook($request->all());

        return response(null);
    }
}