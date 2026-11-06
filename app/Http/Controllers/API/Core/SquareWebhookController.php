<?php

namespace App\Http\Controllers\API\Core;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\OrderItem;
use App\Models\Cart;
use App\Models\Coupon;
use App\Models\User;
use App\Models\ReferralUsage;
use App\Models\Product;
use App\Models\Entry;
use App\Models\PointHistory;
use App\Models\CouponUsage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SquareWebhookController extends Controller
{

    public function handle(Request $request)
    {
        $payload = $request->all();

        Log::info('Square webhook received', [
            'type' => $payload['type'] ?? 'unknown',
            'event_id' => $payload['event_id'] ?? null,
            'timestamp' => now()->toDateTimeString(),
        ]);

        $eventType = $payload['type'] ?? null;

        try {
            switch ($eventType) {
                case 'payment.created':
                    return $this->handlePaymentCreated($payload);

                case 'payment.updated':
                    return $this->handlePaymentUpdated($payload);

                case 'order.updated':
                    return $this->handleOrderUpdated($payload);

                case 'refund.created':
                    return $this->handleRefundCreated($payload);

                case 'refund.updated':
                    return $this->handleRefundUpdated($payload);

                default:
                    Log::info('Unhandled webhook event type', [
                        'event_type' => $eventType
                    ]);
                    return response()->json(['status' => 'ignored'], 200);
            }
        } catch (\Exception $e) {
            Log::error('Webhook processing error', [
                'event_type' => $eventType,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }


    private function handlePaymentCreated($payload)
    {
        $paymentData = $payload['data']['object']['payment'] ?? null;

        if (!$paymentData) {
            Log::warning('Payment data not found in webhook payload');
            return response()->json(['status' => 'no_data'], 200);
        }

        $orderId = $paymentData['order_id'] ?? null;
        $paymentId = $paymentData['id'] ?? null;
        $status = $paymentData['status'] ?? null;
        $amount = isset($paymentData['amount_money']['amount'])
            ? $paymentData['amount_money']['amount']
            : 0;
        $currency = $paymentData['amount_money']['currency'] ?? 'AUD';

        Log::info('Processing payment.created webhook', [
            'square_order_id' => $orderId,
            'payment_id' => $paymentId,
            'status' => $status,
            'amount' => $amount,
        ]);

        if (!$orderId) {
            Log::warning('Order ID not found in payment data');
            return response()->json(['status' => 'no_order_id'], 200);
        }

        if ($status === 'COMPLETED') {
            $sessionId = null;
            foreach (\Cache::getStore()->getKeys() as $key) {
                $cacheKey = str_replace(\Cache::getPrefix(), '', $key);
                if (strpos($cacheKey, '_square_order_id') !== false && \Cache::get($cacheKey) === $orderId) {
                    $sessionId = str_replace('_square_order_id', '', $cacheKey);
                    break;
                }
            }

            if (!$sessionId) {
                Log::warning('Session not found for square_order_id', [
                    'square_order_id' => $orderId
                ]);
                return response()->json(['status' => 'session_not_found'], 200);
            }

            $orderData = \Cache::get($sessionId);

            if (!$orderData) {
                Log::warning('Order data not found in cache', [
                    'session_id' => $sessionId
                ]);
                return response()->json(['status' => 'order_data_not_found'], 200);
            }

            Log::info('Creating order from cached data', [
                'session_id' => $sessionId,
                'user_id' => $orderData['user_id']
            ]);

            try {
                $orderService = new \App\Services\OrderCompletionService();
                $orderService->completeOrder(
                    $sessionId,
                    $orderId,
                    $paymentId,
                    $status,
                    $amount,
                    'AUD',
                    [
                        'receipt_url' => $paymentData['receipt_url'] ?? null,
                        'source_id' => $paymentData['source_type'] ?? null,
                    ]
                );
            } catch (\Exception $e) {
                Log::error('Failed to create order from webhook', [
                    'session_id' => $sessionId,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
            }
        }

        return response()->json(['status' => 'success'], 200);
    }


    private function handlePaymentUpdated($payload)
    {
        $paymentData = $payload['data']['object']['payment'] ?? null;

        if (!$paymentData) {
            return response()->json(['status' => 'no_data'], 200);
        }

        $paymentId = $paymentData['id'] ?? null;
        $status = $paymentData['status'] ?? null;

        Log::info('Processing payment.updated webhook', [
            'payment_id' => $paymentId,
            'status' => $status,
        ]);

        if ($paymentId) {
            $payment = Payment::where('payment_id', $paymentId)->first();

            if ($payment) {
                $oldStatus = $payment->status;
                $payment->status = strtolower($status);
                $payment->save();

                if ($payment->order) {
                    $newStatus = strtolower($status);
                    if ($newStatus === 'completed') $newStatus = 'paid';
                    $payment->order->payment_status = $newStatus;
                    if ($newStatus === 'paid') {
                        $payment->order->order_status = 'processing';

                        $hasRewards = \App\Models\PointHistory::where('order_id', $payment->order->id)
                            ->where('user_id', $payment->order->user_id)
                            ->exists();

                        if (!$hasRewards) {
                            (new \App\Services\OrderCompletionService())->processLoyaltyPoints($payment->order);
                        }
                    }
                    $payment->order->save();
                }

                Log::info('Payment status updated via webhook', [
                    'payment_id' => $paymentId,
                    'old_status' => $oldStatus,
                    'new_status' => $status,
                ]);
            } else {
                Log::warning('Payment not found for update', [
                    'payment_id' => $paymentId
                ]);
            }
        }

        return response()->json(['status' => 'success'], 200);
    }


    private function handleOrderUpdated($payload)
    {
        $orderData = $payload['data']['object']['order'] ?? null;

        if (!$orderData) {
            return response()->json(['status' => 'no_data'], 200);
        }

        Log::info('Processing order.updated webhook', [
            'square_order_id' => $orderData['id'] ?? null,
            'state' => $orderData['state'] ?? null,
        ]);


        return response()->json(['status' => 'success'], 200);
    }


    private function handleRefundCreated($payload)
    {
        $refundData = $payload['data']['object']['refund'] ?? null;

        if (!$refundData) {
            return response()->json(['status' => 'no_data'], 200);
        }

        $paymentId = $refundData['payment_id'] ?? null;
        $refundId = $refundData['id'] ?? null;
        $status = $refundData['status'] ?? null;
        $amount = isset($refundData['amount_money']['amount'])
            ? $refundData['amount_money']['amount'] / 100
            : 0;

        Log::info('Processing refund.created webhook', [
            'payment_id' => $paymentId,
            'refund_id' => $refundId,
            'status' => $status,
            'amount' => $amount,
        ]);

        if ($paymentId) {
            $payment = Payment::where('payment_id', $paymentId)->first();

            if ($payment) {
                $payment->refund_id = $refundId;
                $payment->refund_status = strtolower($status);
                $payment->save();

                Log::info('Refund status updated via webhook', [
                    'payment_id' => $paymentId,
                    'refund_id' => $refundId,
                ]);
            }
        }

        return response()->json(['status' => 'success'], 200);
    }


    private function handleRefundUpdated($payload)
    {
        $refundData = $payload['data']['object']['refund'] ?? null;

        if (!$refundData) {
            return response()->json(['status' => 'no_data'], 200);
        }

        $refundId = $refundData['id'] ?? null;
        $status = $refundData['status'] ?? null;

        Log::info('Processing refund.updated webhook', [
            'refund_id' => $refundId,
            'status' => $status,
        ]);

        if ($refundId) {
            $payment = Payment::where('refund_id', $refundId)->first();

            if ($payment) {
                $payment->refund_status = strtolower($status);
                $payment->save();

                Log::info('Refund status updated', [
                    'refund_id' => $refundId,
                    'status' => $status,
                ]);
            }
        }

        return response()->json(['status' => 'success'], 200);
    }


    private function verifySignature($payload, $signature)
    {
        $webhookSecret = config('square.webhook_signature_key');

        if (empty($webhookSecret)) {
            Log::warning('Webhook signature key not configured');
            return true;
        }

        $expectedSignature = base64_encode(
            hash_hmac('sha256', $payload, $webhookSecret, true)
        );

        if (!hash_equals($expectedSignature, $signature)) {
            Log::error('Invalid webhook signature');
            return false;
        }

        return true;
    }
}
