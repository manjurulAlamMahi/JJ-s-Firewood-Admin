<?php

namespace App\Listeners;

use App\Events\OrderDeliveryDateUpdated;
use App\Mail\OrderDeliveryDateUpdatedMail;
use App\Models\NotificationSetting;
use App\Notifications\CategoryNotification;
use Illuminate\Support\Facades\Mail;

class SendOrderDeliveryDateUpdatedNotification
{
    /**
     * Handle the event.
     *
     * @param OrderDeliveryDateUpdated $event
     * @return void
     */
    public function handle(OrderDeliveryDateUpdated $event)
    {
        $order = $event->order;
        $user = $order->user;

        if (!$user) {
            return;
        }

        try {
            $isFirstBooking = $event->isFirstBooking ?? false;

            // Email — gated
            if (NotificationSetting::isEnabled('email_order_delivery_date_updated')) {
                $mail = Mail::to($user->email);
                if (NotificationSetting::isEnabled('admin_bcc_enabled')) {
                    $bcc = NotificationSetting::get('admin_bcc_address');
                    if ($bcc) $mail->bcc($bcc);
                }
                $mail->send(new OrderDeliveryDateUpdatedMail($order, $event->oldDate, $event->newDate, $isFirstBooking));
            }

            // Push to user — gated
            if (NotificationSetting::isEnabled('push_order_delivery_date_updated')) {
                $dateFmt = \Carbon\Carbon::parse($event->newDate)->format('d/m/Y');
                $pushTitle = $isFirstBooking ? 'Delivery Scheduled' : 'Delivery Date Updated';
                $pushBody  = $isFirstBooking
                    ? "Your delivery for order #{$order->order_number} has been scheduled for {$dateFmt}."
                    : "The delivery date for order #{$order->order_number} has been updated to {$dateFmt}.";

                $user->notify(new CategoryNotification('transactional', [
                    'subject' => $pushTitle,
                    'body' => $pushBody,
                    'title' => $pushTitle,
                    'data' => [
                        'order_id' => $order->id,
                        'type' => 'delivery_date_updated'
                    ]
                ]));
            }

        } catch (\Exception $e) {
            \Log::error("Failed to send delivery date update notification for Order #{$order->order_number}: " . $e->getMessage());
        }
    }
}
