<?php

namespace App\Services;

use App\Jobs\SendJobToSimproJob;

class JobsService
{
    public function initSendingToSimpro(array $dataFromRiverside)
    {
        dispatch(new SendJobToSimproJob($dataFromRiverside));
    }

    public function sendJobToSimpro(array $dataFromRiverside)
    {

    }
}
