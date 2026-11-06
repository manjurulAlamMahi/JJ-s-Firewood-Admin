<?php
namespace App\Console\Commands;

use App\Jobs\DispatchNotificationJob;
use App\Models\NotificationQueue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessScheduledNotifications extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'notifications:process-scheduled';

    /**
     * The console command description.
     */
    protected $description = 'Process scheduled notifications that are ready to send';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking for scheduled notifications...');

        // Find notifications that are ready to send
        $notifications = NotificationQueue::query()
            ->where('status', 'scheduled')
            ->where('next_run_at', '<=', now())
            ->get();

        if ($notifications->isEmpty()) {
            $this->info('No notifications ready to send.');
            return 0;
        }

        $this->info("Found {$notifications->count()} notification(s) ready to send.");

        foreach ($notifications as $notification) {
            try {
                $this->info("Dispatching notification ID: {$notification->id}");
                
                // CRITICAL FIX: Mark as 'running' IMMEDIATELY before dispatching
                // This prevents the next cron run from picking it up again
                $notification->status = 'running';
                $notification->save();
                
                // Dispatch the job
                DispatchNotificationJob::dispatch($notification->id);
                
                Log::info("Scheduled notification dispatched", [
                    'notification_id' => $notification->id,
                    'channel' => $notification->channel,
                ]);
                
            } catch (\Throwable $e) {
                $this->error("Failed to dispatch notification ID {$notification->id}: {$e->getMessage()}");
                
                // Revert status on failure
                $notification->status = 'scheduled';
                $notification->save();
                
                Log::error("Failed to dispatch scheduled notification", [
                    'notification_id' => $notification->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info('Finished processing scheduled notifications.');
        return 0;
    }
}