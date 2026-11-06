<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\PushNotification;
use App\Notifications\CategoryNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendAdminPushNotificationJob implements ShouldQueue
{
    use Queueable;

    protected $notification;

    /**
     * Create a new job instance.
     */
    public function __construct(PushNotification $notification)
    {
        $this->notification = $notification;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Get all active users
            $users = User::where('is_guest', false)->get();
            
            Log::info("Sending admin push notification to {$users->count()} users", [
                'notification_id' => $this->notification->id,
                'category' => $this->notification->category,
                'use_preferences' => $this->notification->use_preferences,
            ]);

            foreach ($users as $user) {
                // If preferences should be respected, check them
                if ($this->notification->use_preferences) {
                    $preference = $user->preferenceFor($this->notification->category);
                    
                    // Skip if both email and push are disabled
                    if (!$preference['email'] && !$preference['push']) {
                        Log::info("Skipping notification for user {$user->id} - preferences disabled");
                        continue;
                    }
                }

                // Send the notification using CategoryNotification
                $notificationData = [
                    'subject' => $this->notification->title,
                    'body' => $this->notification->message,
                    'title' => $this->notification->title,
                    'data' => [
                        'notification_id' => $this->notification->id,
                        'type' => 'admin_push',
                    ],
                ];

                if ($this->notification->product_id) {
                    $notificationData['screen'] = 'product_details';
                    $notificationData['data']['product_id'] = $this->notification->product_id;
                }

                $user->notify(new CategoryNotification($this->notification->category, $notificationData));

                Log::info("Notification sent to user {$user->id}");
            }

            // Mark as sent
            $this->notification->update(['is_send' => 1]);
            Log::info("Admin notification send job completed", ['notification_id' => $this->notification->id]);

        } catch (\Exception $e) {
            Log::error("Error sending admin notification", [
                'notification_id' => $this->notification->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
