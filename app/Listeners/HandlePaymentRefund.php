<?php

namespace App\Listeners;

use App\Events\PaymentRefunded;
use App\Models\Order;
use App\Models\Entry;
use App\Models\PointHistory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Services\RewardRetractionService;

class HandlePaymentRefund
{
    /**
     * Handle the PaymentRefunded event
     * 
     * This listener automatically retracts loyalty points and permanent entries
     * when an order is fully refunded.
     */
    public function handle(PaymentRefunded $event)
    {
        Log::info('HandlePaymentRefund listener triggered', [
            'payment_id' => $event->payment->id,
            'refund_status' => $event->payment->refund_status
        ]);
        $payment = $event->payment;
        $order = $payment->order;
        
        if (!$order) {
            Log::warning('PaymentRefunded: No order found', ['payment_id' => $payment->id]);
            return;
        }

        $user = $order->user;
        if (!$user) {
            return;
        }

        try {
            // 1. Send Refund Email to User with BCC to Admins
            \Mail::to($user->email)
                ->bcc('admin@Jjsfirewoodperth.com.au')
                ->send(new \App\Mail\OrderRefundedMail($order, $payment, $payment->refund_amount, $payment->refund_status));

            // 2. Send Push Notification to User
            $refundTypeLabel = $payment->refund_status === 'full' ? 'Full' : 'Partial';
            $user->notify(new \App\Notifications\CategoryNotification('transactional', [
                'subject' => 'Refund Processed',
                'body' => "A {$refundTypeLabel} refund of $" . number_format($payment->refund_amount, 2) . " for order #{$order->order_number} has been processed.",
                'title' => 'Refund Processed',
                'data' => [
                    'order_id' => $order->id,
                    'type' => 'order_refunded'
                ]
            ]));

        } catch (\Exception $e) {
            Log::error("Failed to send refund notification for Order #{$order->order_number}: " . $e->getMessage());
        }

        // RETRACT REWARDS on FULL refund.
        //
        // For PARTIAL refunds we don't retract — the customer still received
        // most of the goods, and the entry threshold ($200) may still be met
        // by the remaining order value. If the admin wants to retract on a
        // partial refund, they should also flip order_status to 'cancelled'
        // (which fires OrderCancelled → retraction).
        //
        // Idempotent inside RewardRetractionService — guards on existing
        // negative PointHistory rows so re-firing is safe.
        if ($payment->refund_status === 'full') {
            try {
                app(RewardRetractionService::class)->retractRewards($order, 'refund');
                Log::info("HandlePaymentRefund: retracted rewards on full refund", [
                    'order_id' => $order->id,
                    'payment_id' => $payment->id,
                ]);
            } catch (\Exception $e) {
                Log::error("Failed to retract rewards on full refund for Order #{$order->order_number}: " . $e->getMessage());
            }
        }
    }
}