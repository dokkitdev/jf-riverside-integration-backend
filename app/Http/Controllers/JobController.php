<?php

namespace App\Http\Controllers;

use App\Http\Requests\Jobs\CreateJobRequest;

class JobController extends Controller
{
    public function create(CreateJobRequest $request)
    {
        return response(null);
    }
}