<?php

namespace App\Console;

use App\Jobs\UpdateSiteInfo\InitUpdateSitesInfoJob;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Carbon;

class Kernel extends ConsoleKernel
{
    protected $commands = [];

    protected function schedule(Schedule $schedule)
    {
        $schedule
            ->job(new InitUpdateSitesInfoJob(Carbon::now()))
            ->dailyAt(config('defaults.update_sites_info.time'));
    }

    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
