<?php

namespace App\Jobs\UpdateSiteInfo;

use App\Services\JobsFlowService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Carbon;

class InitUpdateSitesInfoJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 3600;

    protected Carbon $dateTime;

    public function __construct(Carbon $dateTime)
    {
        $this->dateTime = $dateTime;

        $this->onQueue('update_site_info');
    }

    public function handle()
    {
        app(JobsFlowService::class)->initUpdateSitesInfo($this->dateTime);
    }

    public function getDateTime(): Carbon
    {
        return $this->dateTime;
    }
}
