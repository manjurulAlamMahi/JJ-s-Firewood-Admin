<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('notifications:process-scheduled')->everyMinute()->name('process-scheduled-notifications')->withoutOverlapping();
Schedule::command('giveaways:draw-winners')->everyMinute()->name('giveaways-draw-winners')->withoutOverlapping()->runInBackground();
Schedule::command('queue:work --stop-when-empty --tries=3 --max-time=50')->everyMinute()->name('queue-worker')->withoutOverlapping(5)->runInBackground();
Schedule::command('orders:auto-deliver')->dailyAt('18:00')->timezone('Australia/Perth')->name('orders-auto-deliver')->withoutOverlapping()->runInBackground();
Schedule::command('tokens:validate --limit=500')->weeklyOn(1, '03:00')->name('validate-fcm-tokens')->withoutOverlapping()->runInBackground();

// FUTURE RELEASE - off until launch. Uncomment to enable.
// Schedule::command('notifications:send')->everyMinute()->name('notifications-send');
// Schedule::command('cart:send-abandoned-reminders')->hourly()->name('cart-abandoned-reminders')->withoutOverlapping();
// Schedule::command('weather-campaigns:process')->everyThreeHours()->timezone('Australia/Perth')->name('weather-campaigns')->withoutOverlapping()->runInBackground();
