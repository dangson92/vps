<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use App\Models\MonitoringStat;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Monitoring checks every 5 minutes
        $schedule->command('monitoring:check')->everyFiveMinutes();
        
        // SSL renewal check daily at 2 AM
        $schedule->command('ssl:renew')->dailyAt('02:00');
        
        // Log parsing daily at 1 AM
        $schedule->command('logs:parse')->dailyAt('01:00');
        
        // Cleanup old monitoring data monthly
        $schedule->command('model:prune', ['--model' => [MonitoringStat::class]])->monthly();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}