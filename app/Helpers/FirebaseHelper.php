<?php

namespace App\Helpers;

use App\Services\FirebaseService;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;

class FirebaseHelper
{
    /**
     * 🔔 Send FCM notification with optional deep linking
     *
     * @param string $fcmToken Device FCM token
     * @param string $title Notification title
     * @param string $body Notification body
     * @param array $options Optional parameters:
     *   - 'link' (string): Deep link or URL to open when notification is tapped
     *   - 'screen' (string): App screen to navigate to (e.g., 'orders', 'products')
     *   - 'data' (array): Additional data payload (e.g., ['order_id' => 123])
     *   - 'image' (string): Image URL to display in notification
     *   - 'badge' (int): Badge count for iOS
     *
     * @return \Kreait\Firebase\Messaging\SendReport|null
     *
     * @example
     * // Basic notification
     * FirebaseHelper::sendNotification($token, 'Hello', 'Welcome back!');
     *
     * // With deep link to order
     * FirebaseHelper::sendNotification($token, 'Order Shipped', 'Your order is on the way', [
     *     'screen' => 'order_details',
     *     'data' => ['order_id' => 123]
     * ]);
     *
     * // With web URL
     * FirebaseHelper::sendNotification($token, 'Sale!', '50% off firewood', [
     *     'link' => 'https://jjsfirewoodperth.com.au/shop'
     * ]);
     *
     * // With image
     * FirebaseHelper::sendNotification($token, 'New Product', 'Check out our jarrah', [
     *     'image' => 'https://example.com/jarrah.jpg',
     *     'screen' => 'products',
     *     'data' => ['product_id' => 456]
     * ]);
     */
    public static function sendNotification(
        string $fcmToken,
        string $title,
        string $body,
        array $options = []
    ) {
        Log::info("🚀 Sending FCM notification | token=$fcmToken | title=$title");

        try {
            $messaging = FirebaseService::messaging();

            if (!$messaging) {
                Log::error("❌ Firebase messaging service not initialized");
                return null;
            }

            $notification = Notification::create($title, $body);

            if (!empty($options['image'])) {
                $notification = $notification->withImageUrl($options['image']);
            }

            $messageBuilder = CloudMessage::withTarget('token', $fcmToken)
                ->withNotification($notification);

            $dataPayload = [];

            if (!empty($options['link'])) {
                $dataPayload['click_action'] = $options['link'];
                $dataPayload['link'] = $options['link'];
            } elseif (!empty($options['screen'])) {
                $dataPayload['screen'] = $options['screen'];
                $dataPayload['click_action'] = 'FLUTTER_NOTIFICATION_CLICK';
            }

            if (!empty($options['data']) && is_array($options['data'])) {
                foreach ($options['data'] as $key => $value) {
                    $dataPayload[$key] = is_string($value) ? $value : (string) $value;
                }
            }

            if (!empty($dataPayload)) {
                $messageBuilder = $messageBuilder->withData($dataPayload);
            }

            $androidConfig = [
                // Without this, Android falls back to FCM's default priority, which
                // can get throttled/delayed by battery optimization on some devices.
                'priority' => 'high',
                'notification' => [
                    'sound' => 'default',
                    'notification_priority' => 'PRIORITY_HIGH',
                    'visibility' => 'public',
                ],
            ];

            if (!empty($options['channel_id'])) {
                // Must match a channel ID actually created on the device (e.g. via
                // flutter_local_notifications / AndroidManifest default_notification_channel_id).
                // An unrecognized channel_id can cause Android to mute/drop the alert.
                $androidConfig['notification']['channel_id'] = $options['channel_id'];
            }

            if (!empty($options['link'])) {
                $androidConfig['notification']['click_action'] = $options['link'];
            }

            $apnsConfig = [
                // apns-priority=10 is required for alert pushes to be delivered
                // immediately instead of being coalesced by Apple.
                'headers' => [
                    'apns-priority' => '10',
                    'apns-push-type' => 'alert',
                ],
                'payload' => [
                    'aps' => [
                        'sound' => 'default',
                    ],
                ],
            ];

            if (isset($options['badge'])) {
                $apnsConfig['payload']['aps']['badge'] = (int) $options['badge'];
            }

            if (!empty($androidConfig)) {
                $messageBuilder = $messageBuilder->withAndroidConfig($androidConfig);
            }
            if (!empty($apnsConfig)) {
                $messageBuilder = $messageBuilder->withApnsConfig($apnsConfig);
            }

            $message = $messageBuilder;
            $response = $messaging->send($message);

            Log::info("📬 FCM Notification sent", [
                'token' => substr($fcmToken, 0, 20) . '...',
                'title' => $title,
                'has_link' => !empty($options['link']),
                'has_screen' => !empty($options['screen']),
                'has_data' => !empty($options['data']),
            ]);

            return $response;
        } catch (\Throwable $e) {
            Log::error("❌ FCM send error: " . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
            return null;
        }
    }

    /**
     * 📱 Send notification with order details
     *
     * @param string $fcmToken
     * @param int $orderId
     * @param string $status
     * @return \Kreait\Firebase\Messaging\SendReport|null
     */
    public static function sendOrderNotification(string $fcmToken, int $orderId, string $status)
    {
        $titles = [
            'confirmed' => 'Order Confirmed',
            'processing' => 'Order Processing',
            'shipped' => 'Order Shipped',
            'delivered' => 'Order Delivered',
            'cancelled' => 'Order Cancelled',
        ];

        $bodies = [
            'confirmed' => "Your order #$orderId has been confirmed",
            'processing' => "We're preparing your order #$orderId",
            'shipped' => "Order #$orderId is on its way!",
            'delivered' => "Order #$orderId has been delivered",
            'cancelled' => "Order #$orderId has been cancelled",
        ];

        return self::sendNotification(
            $fcmToken,
            $titles[$status] ?? 'Order Update',
            $bodies[$status] ?? "Order #$orderId has been updated",
            [
                'screen' => 'order_details',
                'data' => [
                    'order_id' => (string) $orderId,
                    'status' => $status,
                ]
            ]
        );
    }

    /**
     * 🎁 Send promotional notification with web link
     *
     * @param string $fcmToken
     * @param string $title
     * @param string $message
     * @param string $url
     * @return \Kreait\Firebase\Messaging\SendReport|null
     */
    public static function sendPromoNotification(string $fcmToken, string $title, string $message, string $url)
    {
        return self::sendNotification(
            $fcmToken,
            $title,
            $message,
            [
                'link' => $url,
                'data' => [
                    'type' => 'promo',
                    'url' => $url,
                ]
            ]
        );
    }
}
