<?php

namespace App\Http\Controllers\Webhooks;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Payment;
use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SquareWebhookController extends Controller
{
    /**
     * Handle Square webhook events
     */
    public function handle(Request $request)
    {
        if (!$this->signatureIsValid($request)) {
            Log::error('Square webhook rejected: invalid signature', ['ip' => $request->ip()]);
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        try {
            $event = $request->json('type');
            $data = $request->json('data.object');

            Log::info('Square webhook received', [
                'event' => $event,
                'timestamp' => now(),
            ]);

            match ($event) {
                'payment.created' => $this->handlePaymentCreated($data),
                'payment.updated' => $this->handlePaymentUpdated($data),
                'payment.completed' => $this->handlePaymentCompleted($data),
                'refund.created' => $this->handleRefundCreated($data),
                'refund.updated' => $this->handleRefundUpdated($data),
                default => Log::warning('Unknown Square webhook event', ['event' => $event]),
            };

            return response()->json(['success' => true], 200);
        } catch (\Exception $e) {
            Log::error('Square webhook error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Verify the Square webhook HMAC signature before trusting the payload.
     */
    private function signatureIsValid(Request $request): bool
    {
        $key = config('square.webhook_signature_key');
        if (empty($key)) {
            Log::error('Square webhook signature key not configured');
            return false;
        }

        $signature = $request->header('x-square-hmacsha256-signature');
        if (empty($signature)) {
            return false;
        }

        // Square signs (notification URL + raw request body) with the signature key.
        $url = config('square.webhook_url') ?: $request->fullUrl();
        $expected = base64_encode(hash_hmac('sha256', $url . $request->getContent(), $key, true));

        return hash_equals($expected, $signature);
    }

    /**
     * Handle payment.created event
     */
    private function handlePaymentCreated($data)
    {
        try {
            $paymentId = $data['id'] ?? null;

            if (!$paymentId) {
                Log::warning('Payment webhook missing id', ['data' => $data]);
                return;
            }

            Log::info('Payment created webhook', ['payment_id' => $paymentId]);
        } catch (\Exception $e) {
            Log::error('Handle payment created error', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle payment.updated event
     */
    private function handlePaymentUpdated($data)
    {
        try {
            $paymentId = $data['id'] ?? null;
            $status = $data['status'] ?? null;

            if (!$paymentId) {
                Log::warning('Payment webhook missing id', ['data' => $data]);
                return;
            }

            Log::info('Payment updated webhook', [
                'payment_id' => $paymentId,
                'status' => $status,
            ]);
        } catch (\Exception $e) {
            Log::error('Handle payment updated error', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle payment.completed event
     */
    private function handlePaymentCompleted($data)
    {
        DB::beginTransaction();

        try {
            $paymentId = $data['id'] ?? null;
            $status = $data['status'] ?? null;

            if (!$paymentId) {
                Log::warning('Payment webhook missing id', ['data' => $data]);
                DB::rollBack();
                return;
            }

            // Find payment
            $payment = Payment::where('payment_id', $paymentId)->first();

            if (!$payment) {
                Log::warning('Payment record not found for webhook', [
                    'payment_id' => $paymentId,
                ]);
                DB::rollBack();
                return;
            }

            // Update payment status if not already completed
            if ($payment->status !== 'completed' && $status === 'COMPLETED') {
                $payment->markAsCompleted([
                    'receipt_url' => $data['receipt_url'] ?? null,
                ]);

                // Update associated order
                if ($payment->order) {
                    $payment->order->payment_status = 'paid';
                    $payment->order->confirmed_at = now();
                    $payment->order->save();

                    Log::info('Order updated from webhook', [
                        'order_id' => $payment->order->id,
                        'payment_id' => $paymentId,
                    ]);
                }

                Log::info('Payment marked as completed from webhook', [
                    'payment_id' => $paymentId,
                ]);
            }

            $payment->webhook_received = true;
            $payment->save();

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Handle payment completed error', [
                'payment_id' => $paymentId ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Handle refund.created event
     */
    private function handleRefundCreated($data)
    {
        try {
            $refundId = $data['id'] ?? null;
            $paymentId = $data['payment_id'] ?? null;
            $amount = $data['amount_money']['amount'] ?? 0;

            if (!$refundId || !$paymentId) {
                Log::warning('Refund webhook missing required fields', ['data' => $data]);
                return;
            }

            Log::info('Refund created webhook', [
                'refund_id' => $refundId,
                'payment_id' => $paymentId,
                'amount' => $amount / 100,
            ]);
        } catch (\Exception $e) {
            Log::error('Handle refund created error', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle refund.updated event
     */
    private function handleRefundUpdated($data)
    {
        DB::beginTransaction();

        try {
            $refundId = $data['id'] ?? null;
            $paymentId = $data['payment_id'] ?? null;
            $status = $data['status'] ?? null;
            $amount = ($data['amount_money']['amount'] ?? 0) / 100;

            if (!$refundId || !$paymentId) {
                Log::warning('Refund webhook missing required fields', ['data' => $data]);
                DB::rollBack();
                return;
            }

            // Find payment
            $payment = Payment::where('payment_id', $paymentId)->first();

            if (!$payment) {
                Log::warning('Payment record not found for refund webhook', [
                    'payment_id' => $paymentId,
                ]);
                DB::rollBack();
                return;
            }

            // Update refund status
            if ($status === 'COMPLETED' && $payment->refund_status === 'none') {
                // Determine if full or partial refund
                $refundStatus = 'partial';
                if (abs($amount - $payment->amount) < 0.01) {
                    $refundStatus = 'full';
                }

                $payment->refund_id = $refundId;
                $payment->refund_amount = $amount;
                $payment->refund_status = $refundStatus;
                $payment->refunded_at = now();
                $payment->save();

                // Update order payment status
                if ($payment->order) {
                    $payment->order->update([
                        'payment_status' => $refundStatus === 'full' ? 'refunded' : 'partially_refunded'
                    ]);
                }

                // Fire PaymentRefunded event to handle emails, notifications, and reward retraction
                event(new \App\Events\PaymentRefunded($payment));

                Log::info('Payment marked as refunded from webhook', [
                    'payment_id' => $paymentId,
                    'refund_id' => $refundId,
                    'refund_status' => $refundStatus,
                ]);
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Handle refund updated error', [
                'refund_id' => $refundId ?? null,
                'payment_id' => $paymentId ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
