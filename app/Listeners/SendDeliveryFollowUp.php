<?php

namespace App\Listeners;

use App\Events\OrderDelivered;
use App\Models\DeliveryFollowUp;
use App\Models\NotificationSetting;
use App\Notifications\CategoryNotification;
use App\Services\NotificationService;

/**
 * Creates the per-order delivery_follow_ups row and prompts the customer to
 * review, give feedback, or reorder. Runs alongside
 * SendOrderDeliveredNotification on the same OrderDelivered event.
 *
 * firstOrCreate() on the unique order_id index is the idempotency guard: if
 * OrderDelivered ever fires twice for the same order (retry, duplicate
 * status update, etc.) the second call finds the existing row and — because
 * it's no longer 'pending' — skips sending again.
 */
class SendDeliveryFollowUp
{
    public function handle(OrderDelivered $event)
    {
        $order = $event->order;

        try {
            $followUp = DeliveryFollowUp::firstOrCreate(
                ['order_id' => $order->id],
                ['user_id' => $order->user_id, 'status' => 'pending']
            );

            if ($followUp->status !== 'pending') {
                return;
            }

            $channels = ['database'];

            NotificationService::success(
                $order->user,
                "How was your order?",
                "Your order #{$order->order_number} was delivered. Leave a review, share feedback, or reorder in one tap."
            );

            if (NotificationSetting::isEnabled('push_delivery_followup') && $order->user) {
                $order->user->notify(new CategoryNotification('transactional', [
                    'subject' => 'How was your order?',
                    'body' => "Your order #{$order->order_number} was delivered. Leave a review, share feedback, or reorder in one tap.",
                    'title' => 'How was your order?',
                    'screen' => 'delivery_follow_up',
                    'data' => [
                        'order_id' => (string) $order->id,
                        'order_number' => $order->order_number,
                        'type' => 'delivery_follow_up',
                    ],
                ]));

                $channels[] = 'push';
            }

            $followUp->markSent($channels);
        } catch (\Exception $e) {
            \Log::error("SendDeliveryFollowUp Error for Order #{$order->order_number}: " . $e->getMessage());
        }
    }
}
