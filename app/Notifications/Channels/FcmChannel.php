<?php

namespace App\Notifications\Channels;

use App\Helpers\FirebaseHelper;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class FcmChannel
{
    public function __construct()
    {
        // No longer using PushService
    }

    /**
     * Send the given notification.
     */
    public function send($notifiable, Notification $notification)
    {
        // Get the FCM token from the notifiable (ONLY fcm_token as per user request)
        if (!$token = $notifiable->fcm_token ?? null) {
            Log::debug('FCM Channel: No FCM token found for user - Skipping push', ['user_id' => $notifiable->id ?? null, 'name' => $notifiable->name ?? 'Unknown']);
            return;
        }

        // Check if the notification has a toFcm method
        if (!method_exists($notification, 'toFcm')) {
            Log::warning('FCM Channel: Notification does not have toFcm method', [
                'notification' => get_class($notification),
            ]);
            return;
        }

        try {
            $payload = $notification->toFcm($notifiable);
            
            $title = $payload['notification']['title'] ?? 'Notification';
            $body = $payload['notification']['body'] ?? '';
            $options = $payload['options'] ?? [];
            
            if (!empty($payload['data'])) {
                $options['data'] = array_merge($options['data'] ?? [], $payload['data']);
            }

            FirebaseHelper::sendNotification($token, $title, $body, $options);

            // Bump validated timestamp — a successful send proves the token works,
            // so we can use this to keep the customer in "Reachable" state without
            // needing a separate ping from the weekly cron.
            try {
                if (\Schema::hasColumn('users', 'fcm_token_validated_at') && method_exists($notifiable, 'update')) {
                    $notifiable->update(['fcm_token_validated_at' => now()]);
                }
            } catch (\Throwable $e) {
                // Don't let timestamp update fail the actual notification flow
                Log::warning('FCM Channel: failed to bump validated timestamp', ['err' => $e->getMessage()]);
            }
            
        } catch (\Kreait\Firebase\Exception\Messaging\NotFound $e) {
            Log::warning('FCM Channel: Token no longer valid - Clearing token', [
                'user_id' => $notifiable->id,
                'token' => substr($token, 0, 20) . '...'
            ]);
            
            // Clear the invalid token from the user
            if (method_exists($notifiable, 'update')) {
                $notifiable->update(['fcm_token' => null]);
            }
        } catch (\Kreait\Firebase\Exception\MessagingException $e) {
            Log::error('FCM Channel: Messaging error', [
                'error' => $e->getMessage(),
                'user_id' => $notifiable->id,
            ]);
        } catch (\Throwable $e) {
            Log::error('FCM Channel: Critical failure', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'user_id' => $notifiable->id,
                'notification' => get_class($notification),
            ]);
        }
    }
}
