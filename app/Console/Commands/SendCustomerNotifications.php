<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Helpers\FirebaseHelper;
use Illuminate\Console\Command;
use App\Models\PushNotification;
use Illuminate\Support\Facades\Log;
use App\Models\CustomerNotification;

class SendCustomerNotifications extends Command
{
    protected $signature = 'notifications:send';
    protected $description = 'Send active customer notifications to all users with FCM tokens';

    public function handle()
    {
        $startTime = microtime(true);

        $notifications = PushNotification::where('status', true)
            ->where('is_send', false)
            ->get();

        if ($notifications->isEmpty()) {
            Log::info('➡️ No new customer notifications to send.');
            return;
        }

        $users = User::whereNotNull('fcm_token')->get();

        if ($users->isEmpty()) {
            Log::warning('⚠️ No users found with FCM tokens. No notifications sent.');
            return;
        }

        $totalUsers = $users->count();
        $totalNotifications = $notifications->count();
        $totalDelivered = 0;

        Log::info("🚀 Starting Notification Broadcast | Users with FCM: $totalUsers | Pending Notifications: $totalNotifications");

        foreach ($notifications as $notification) {

            Log::info("📢 Sending Notification ID {$notification->id}: '{$notification->title}'");

            $sentToUsers = 0;

            foreach ($users as $user) {

                FirebaseHelper::sendNotification(
                    $user->fcm_token,
                    $notification->title,
                    $notification->message ?? ''
                );

                $sentToUsers++;

                // Per-user detailed log
                Log::info("📨 Sent to User ID: {$user->id}, Name: {$user->name}");
            }

            // Summary for this notification
            Log::info("✅ Notification ID {$notification->id} sent to {$sentToUsers} users.");

            $totalDelivered += $sentToUsers;

            // Mark notification as sent
            $notification->update(['is_send' => true]);
        }

        $timeTaken = round(microtime(true) - $startTime, 2);

        // Final summary
        Log::info("📊 SUMMARY REPORT");
        Log::info("👥 Users with FCM Tokens: $totalUsers");
        Log::info("🔔 Notifications Processed: $totalNotifications");
        Log::info("📬 Total Deliveries: $totalDelivered");
        Log::info("⏱ Execution Time: {$timeTaken} seconds");

        $this->info('✅ Customer notifications check complete.');
    }
}
