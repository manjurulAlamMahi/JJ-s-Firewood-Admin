<?php

namespace App\Listeners;

use App\Events\OrderCancelled;
use App\Mail\OrderCancelledMail;
use App\Models\NotificationSetting;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use App\Services\NotificationService;
use App\Notifications\CategoryNotification;

class SendOrderCancelledNotification
{
    public function __construct()
    {
        //
    }

    public function handle(OrderCancelled $event)
    {
        $order = $event->order;

        // Retract loyalty points and entries when order is cancelled (always on — business logic)
        try {
            app(\App\Services\RewardRetractionService::class)->retractRewards($order, 'cancellation');
        } catch (\Exception $e) {
            \Log::error("Failed to retract rewards for Order #{$order->order_number}: " . $e->getMessage());
        }

        // In-app DB notifications (always on)
        NotificationService::error(
            $order->user,
            'Order Cancelled',
            "Your order #{$order->order_number} has been cancelled."
        );

        $admins = User::where('is_admin', 1)->get();
        foreach ($admins as $admin) {
            NotificationService::error(
                $admin,
                'Order Cancelled',
                "Order #{$order->order_number} by user #{$order->user_id} has been cancelled."
            );
        }

        $adminNotifications = $admins->reject(fn($a) => $a->id === $order->user_id || $a->email === $order->user->email);

        // Email — gated
        if (NotificationSetting::isEnabled('email_order_cancelled')) {
            $mail = Mail::to($order->user->email);
            if (NotificationSetting::isEnabled('admin_bcc_enabled')) {
                $bcc = NotificationSetting::get('admin_bcc_address');
                if ($bcc) $mail->bcc($bcc);
            }
            $mail->send(new OrderCancelledMail($order));
        }
        
        // Push to user — gated
        if (NotificationSetting::isEnabled('push_order_cancelled')) {
            $order->user->notify(new CategoryNotification('transactional', [
                'subject' => 'Order Cancelled',
                'body' => "Your order #{$order->order_number} has been cancelled.",
                'title' => 'Order Cancelled',
                'screen' => 'order_details',
                'data' => [
                    'order_id' => (string) $order->id,
                    'order_number' => $order->order_number,
                    'type' => 'order_cancelled'
                ]
            ]));

            // Push to admins (same toggle)
            foreach ($adminNotifications as $admin) {
                $admin->notify(new CategoryNotification('transactional', [
                    'subject' => 'Order Cancelled',
                    'body' => "Order #{$order->order_number} has been cancelled by user {$order->user->name}.",
                    'title' => 'Order Cancelled',
                    'screen' => 'order_details',
                    'data' => [
                        'order_id' => (string) $order->id,
                        'order_number' => $order->order_number,
                        'type' => 'admin_order_cancelled'
                    ]
                ]));
            }
        }
    }
}
