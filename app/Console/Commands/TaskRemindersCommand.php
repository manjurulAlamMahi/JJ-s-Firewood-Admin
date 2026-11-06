<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use App\Models\User;
use App\Models\IndividualTask;
use App\Helpers\FirebaseHelper;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TaskRemindersCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'reminders:task';

    /**
     * The console command description.
     */
    protected $description = 'Send notifications based on IndividualTask time field';

    /**
     * Execute the console command.
     */

    // Revised handle() method to avoid duplicate notifications for users with multiple assessments
    public function handle()
    {
        $nowUTC = now();
        Log::info("🔄 Scheduler running at {$nowUTC->format('H:i')} UTC");

        // Step 1: Get users with non-null FCM tokens
        $users = User::whereNotNull('fcm_token')->get();

        foreach ($users as $user) {

            // Step 2: Get latest assessment for each user
            $latestAssessment = $user->assessments()->latest()->first();
            if (!$latestAssessment) continue;

            // Step 3: Get care-plan of the latest assessment for each user
            $carePlan = $latestAssessment->carePlans()->first();
            if (!$carePlan) continue;

            // Step 4: care-plan all tasks → only individual tasks will be get
            $individualTasks = IndividualTask::whereHas('carePlanTask', function ($q) use ($carePlan) {
                $q->where('care_plan_id', $carePlan->id);
            })->get();

            $userTimezone = $user->timezone ?? 'UTC';

            foreach ($individualTasks as $task) {

                if (!$task->time) continue;

                $taskTimeLocal = Carbon::createFromFormat('H:i:s', $task->time, 'UTC')
                    ->setTimezone($userTimezone)
                    ->format('H:i');

                $nowLocal = $nowUTC->copy()->setTimezone($userTimezone)->format('H:i');

                if ($taskTimeLocal === $nowLocal) {

                    FirebaseHelper::sendFcmNotification(
                        $user->fcm_token,
                        "⏰ Task Reminder",
                        "{$task->title}",
                        $user->id
                    );

                    Log::info("📌 Task reminder sent for user_id={$user->id}, task_id={$task->id}, at $taskTimeLocal ({$userTimezone})");
                }
            }
        }

        $this->info('✅ IndividualTask notification check complete.');
    }
}
