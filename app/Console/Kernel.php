<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Process scheduled notifications every minute
        $schedule->command('notifications:process-scheduled')
                 ->everyMinute()
                 ->withoutOverlapping();
        
        // Automatic giveaway drawing - runs every minute to check for winners
        $schedule->command('giveaways:draw-winners')
                 ->everyMinute()
                 ->withoutOverlapping()
                 ->runInBackground();

        // Weather-triggered campaigns — evaluate rules against current
        // forecasts a few times a day (weather doesn't need minute-level
        // polling, and this keeps OpenWeatherMap usage low).
        $schedule->command('weather-campaigns:process')
                 ->everyThreeHours()
                 ->timezone('Australia/Perth')
                 ->withoutOverlapping()
                 ->runInBackground();

        // Daily end-of-day auto-delivery sweep.
        // Marks paid+verified orders as delivered if today is their scheduled
        // date. The command itself checks the system_settings toggle before
        // doing anything, so this schedule is safe to enable always —
        // when the admin toggle is off, the command exits with no-op.
        // 18:00 Perth time matches the configured end-of-day from admin.
        $schedule->command('orders:auto-deliver')
                 ->dailyAt('18:00')
                 ->timezone('Australia/Perth')
                 ->withoutOverlapping()
                 ->runInBackground();
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