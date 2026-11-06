<?php

namespace App\Console\Commands;

use Illuminate\Support\Facades\Log;
use App\Models\Reminder;
use Carbon\Carbon;
use App\Helpers\FirebaseHelper;

use Illuminate\Console\Command;

class SendRemindersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    // protected $signature = 'app:send-reminders-command';
    protected $signature = 'reminders:send';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check reminders and send notifications if conditions are met.';

    /**
     * Execute the console command.
     */


    public function handle()
    {
        $utcNow = now();
        Log::info("🔄 Scheduler running at {$utcNow->format('H:i')} UTC");

        $reminders = Reminder::with('user')->get();

        foreach ($reminders as $reminder) {

            if (!$reminder->user || !$reminder->user->fcm_token) {
                Log::warning("⚠️ Skipped reminder ID: {$reminder->id} - No user or missing FCM token");
                continue;
            }

            if (empty($reminder->reminder_time)) {
                Log::warning("⚠️ Skipped reminder ID: {$reminder->id} - Missing reminder_time");
                continue;
            }

            $userTimezone = $reminder->timezone ?? 'UTC';

            $userNow = $utcNow->copy()->setTimezone($userTimezone);

            $reminderTime = Carbon::parse($reminder->reminder_time, 'UTC')
                ->setTimezone($userTimezone);

            if ($userNow->format('H:i') === $reminderTime->format('H:i')) {
                $notificationMessage = "{$reminder->label}"; 

                Log::info("🔁 Sending DAILY reminder to user_id={$reminder->user->id}: $notificationMessage");

                FirebaseHelper::sendReminderNotification(
                    $reminder->user->fcm_token,
                    "⏰ Reminder",
                    $notificationMessage,
                    $reminder->user->id
                );
            } else {
                Log::info("➡️ Reminder ID={$reminder->id} not due yet. user_now={$userNow->format('H:i')} | reminder_time={$reminderTime->format('H:i')}");
            }
        }

        $this->info('✅ Reminder check complete.');
    }
}
