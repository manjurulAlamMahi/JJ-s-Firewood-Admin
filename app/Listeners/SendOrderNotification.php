<?php

namespace App\Listeners;

use App\Events\OrderPlaced;
use App\Mail\OrderPlacedMail;
use App\Models\NotificationSetting;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Services\NotificationService;
use App\Notifications\CategoryNotification;

class SendOrderNotification
{
    public function __construct()
    {
        //
    }

    public function handle(OrderPlaced $event)
    {
        $order = $event->order;

        // In-app notifications (always on — these stay regardless of email/push toggles)
        NotificationService::success(
            $order->user,
            'Order Placed',
            "Your order #{$order->order_number} has been placed successfully."
        );

        $admins = User::where('is_admin', 1)->get();
        foreach ($admins as $admin) {
            NotificationService::success(
                $admin,
                'New Order',
                "Order #{$order->order_number} has been placed by user #{$order->user_id}."
            );
        }

        $adminNotifications = $admins->reject(fn($a) => $a->id === $order->user_id || $a->email === $order->user->email);

        // Email — gated by Settings → Email Notifications → "Order Placed"
        if (NotificationSetting::isEnabled('email_order_placed')) {
            $mail = Mail::to($order->user->email);
            if (NotificationSetting::isEnabled('admin_bcc_enabled')) {
                $bcc = NotificationSetting::get('admin_bcc_address');
                if ($bcc) $mail->bcc($bcc);
            }
            $mail->send(new OrderPlacedMail($order));
        }

        // Push to user — gated
        if (NotificationSetting::isEnabled('push_order_placed')) {
            $order->user->notify(new CategoryNotification('transactional', [
                'subject' => 'Order Placed',
                'body' => "Your order #{$order->order_number} has been placed successfully.",
                'title' => 'Order Placed',
                'screen' => 'order_details',
                'data' => [
                    'order_id' => (string) $order->id,
                    'order_number' => $order->order_number,
                    'type' => 'order_placed'
                ]
            ]));

            // Push to admins (same toggle)
            foreach ($adminNotifications as $admin) {
                $admin->notify(new CategoryNotification('transactional', [
                    'subject' => 'New Order Received',
                    'body' => "Order #{$order->order_number} has been placed by {$order->user->name}.",
                    'title' => 'New Order',
                    'screen' => 'order_details',
                    'data' => [
                        'order_id' => (string) $order->id,
                        'order_number' => $order->order_number,
                        'type' => 'new_order'
                    ]
                ]));
            }
        }
    }
}
