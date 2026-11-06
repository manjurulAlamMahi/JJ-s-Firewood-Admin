<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\NotificationQueue;
use App\Jobs\DispatchNotificationJob;
use Illuminate\Support\Facades\Log;

class RunDueNotifications extends Command
{
    protected $signature = 'notifications:run-due';
    protected $description = 'Dispatch due notifications from notifications_queue';

    public function handle(): int
    {
        $now = now();

        $due = NotificationQueue::query()
            ->whereIn('status', ['scheduled'])
            ->where(function ($q) use ($now) {
                $q->whereNotNull('scheduled_at')->where('scheduled_at', '<=', $now)
                  ->orWhereNotNull('next_run_at')->where('next_run_at', '<=', $now);
            })
            ->limit(25)
            ->get();

        if ($due->isEmpty()) {
            $this->info('No notifications due.');
            return self::SUCCESS;
        }

        foreach ($due as $n) {
            try {
                // CRITICAL FIX: Mark as running BEFORE dispatching
                // This prevents other commands/cron runs from dispatching the same notification
                $n->status = 'running';
                $n->save();
                
                DispatchNotificationJob::dispatch($n->id);
                
                Log::info("Notification dispatched by run-due command", [
                    'notification_id' => $n->id,
                    'channel' => $n->channel,
                ]);
                
            } catch (\Throwable $e) {
                // Revert status on failure
                $n->status = 'scheduled';
                $n->save();
                
                Log::error("Failed to dispatch notification", [
                    'notification_id' => $n->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->info('Queued ' . $due->count() . ' notification(s).');
        return self::SUCCESS;
    }
}