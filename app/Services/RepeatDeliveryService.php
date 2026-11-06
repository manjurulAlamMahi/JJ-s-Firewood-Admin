<?php

namespace App\Services;

use App\Mail\RepeatDeliveryStatusMail;
use App\Mail\ScheduleCreatedMail;
use App\Models\Order;
use App\Models\Schedule;
use App\Models\User;
use App\Notifications\CategoryNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class RepeatDeliveryService
{
    /**
     * Creates the subscription linked to a newly placed order. The checkout
     * only accepts repeat delivery for a single-product cart, so the order's
     * one line is the product the subscription repeats.
     */
    public function createFromOrder(Order $order, array $details): Schedule
    {
        $item = $order->orderItems()->with('product')->firstOrFail();
        $tonnes = Schedule::tonnesFromProductName($item->product_name);

        return Schedule::create([
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'repeat' => true,
            'status' => 'active',
            'frequency' => $details['frequency'],
            'custom_days' => $details['frequency'] === 'custom' ? $details['custom_days'] : null,
            'preferred_date' => $details['first_delivery_date'],
            'next_delivery_date' => $details['first_delivery_date'],
            'preferred_time' => $details['preferred_time'] ?? 'Anytime (6:00am - 5:00pm)',
            'shipping_address_id' => $order->shipping_address_id,
            'product_id' => $item->product_id,
            'load_size' => $tonnes !== null ? $tonnes * (float) $item->quantity : (float) $item->quantity,
            // The product price of one delivery (the whole order line).
            'unit_price' => $item->subtotal,
        ]);
    }

    public function notifyActivated(Schedule $schedule): void
    {
        $schedule->loadMissing('order.items', 'user');
        $date = $schedule->next_delivery_date_formatted;

        $this->notifyCustomer(
            $schedule,
            'Repeat Delivery Activated',
            "Your repeat delivery is set up ({$schedule->frequency_label}). First delivery: {$date}.",
            'repeat_delivery_activated',
            new ScheduleCreatedMail($schedule)
        );

        $this->notifyAdmins($schedule, 'New Repeat Delivery', "set up a repeat delivery ({$schedule->frequency_label}), first delivery {$date}");
    }

    public function notifySkipped(Schedule $schedule, string $skippedDate): void
    {
        $date = $schedule->next_delivery_date_formatted;

        $this->notifyCustomer(
            $schedule,
            'Next Delivery Skipped',
            "Your {$skippedDate} delivery was skipped. Your repeat schedule will continue on {$date}.",
            'repeat_delivery_skipped'
        );

        $this->notifyAdmins($schedule, 'Repeat Delivery Skipped', "skipped the {$skippedDate} delivery, next delivery is now {$date}");
    }

    public function notifyPaused(Schedule $schedule): void
    {
        $this->notifyCustomer(
            $schedule,
            'Repeat Delivery Paused',
            'Your repeat delivery has been paused. You can resume it at any time from the app.',
            'repeat_delivery_paused'
        );

        $this->notifyAdmins($schedule, 'Repeat Delivery Paused', 'paused their repeat delivery');
    }

    public function notifyCancelled(Schedule $schedule): void
    {
        $this->notifyCustomer(
            $schedule,
            'Repeat Delivery Cancelled',
            'Your repeat delivery has been cancelled. No future deliveries will be scheduled.',
            'repeat_delivery_cancelled'
        );

        $reason = $schedule->cancel_reason ? " (reason: {$schedule->cancel_reason})" : '';
        $this->notifyAdmins($schedule, 'Repeat Delivery Cancelled', "cancelled their repeat delivery{$reason}");
    }

    /**
     * Notification failures are logged, never thrown: the customer's change
     * has already been saved by the time these run.
     */
    private function notifyCustomer(Schedule $schedule, string $title, string $body, string $type, $mailable = null): void
    {
        $user = $schedule->user;

        if (!$user) {
            return;
        }

        $this->attempt(fn () => NotificationService::success($user, $title, $body));

        $this->attempt(fn () => $user->notify(new CategoryNotification('transactional', [
            'subject' => $title,
            'body' => $body,
            'title' => $title,
            'data' => [
                'schedule_id' => (string) $schedule->id,
                'type' => $type,
            ],
        ])));

        $this->attempt(fn () => Mail::to($user->email)->send(
            $mailable ?? new RepeatDeliveryStatusMail($schedule, $title, $body)
        ));
    }

    private function notifyAdmins(Schedule $schedule, string $title, string $action): void
    {
        $customer = $schedule->user->name ?? 'A customer';

        foreach (User::where('is_admin', 1)->get() as $admin) {
            $this->attempt(fn () => NotificationService::info($admin, $title, "{$customer} {$action}."));
        }
    }

    private function attempt(callable $send): void
    {
        try {
            $send();
        } catch (\Throwable $e) {
            Log::error('Repeat delivery notification failed: ' . $e->getMessage());
        }
    }
}
