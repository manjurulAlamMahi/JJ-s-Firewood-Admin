<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Order;
use App\Events\PaymentCompleted;
use App\Events\PaymentFailed;
use App\Events\PaymentRefunded;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PaymentService
{
    
    public static function createPayment(Order $order, array $paymentData): Payment
    {
        return Payment::create([
            'payment_id' => $paymentData['payment_id'],
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'amount' => $order->total,
            'currency' => $paymentData['currency'] ?? 'USD',
            'status' => $paymentData['status'] ?? 'pending',
            'receipt_url' => $paymentData['receipt_url'] ?? null,
            'payment_method' => $paymentData['payment_method'] ?? 'square',
            'source_id' => $paymentData['source_id'] ?? null,
            'card_details' => $paymentData['card_details'] ?? null,
        ]);
    }

   
    public static function completePayment(Payment $payment, array $data = []): void
    {
        DB::beginTransaction();

        try {
            $payment->markAsCompleted($data);

            if ($payment->order) {
                $payment->order->payment_status = 'paid';
                $payment->order->transaction_id = $payment->payment_id;
                $payment->order->confirmed_at = now();
                $payment->order->save();
            }

            Log::info('Payment completed', [
                'payment_id' => $payment->id,
                'order_id' => $payment->order_id,
                'amount' => $payment->amount,
            ]);

            DB::commit();

            event(new PaymentCompleted($payment));
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Complete payment error', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }


    public static function failPayment(Payment $payment, string $errorCode, string $errorMessage): void
    {
        DB::beginTransaction();

        try {
            $payment->markAsFailed($errorCode, $errorMessage);

            Log::error('Payment failed', [
                'payment_id' => $payment->id,
                'order_id' => $payment->order_id,
                'error_code' => $errorCode,
                'error_message' => $errorMessage,
            ]);

            DB::commit();

            event(new PaymentFailed($payment));
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Fail payment error', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }


    public static function processRefund(Payment $payment, float $amount, ?string $reason = null): void
    {
        DB::beginTransaction();

        try {
            $currentRefundAmount = $payment->refund_amount ?? 0;
            $totalRefundedAmount = $currentRefundAmount + $amount;

            $refundStatus = 'partial';
            if (abs($totalRefundedAmount - $payment->amount) < 0.01) {
                $refundStatus = 'full';
            }

            $payment->refund_amount = $totalRefundedAmount;
            $payment->refund_status = $refundStatus;
            $payment->refund_reason = $reason;
            $payment->refunded_at = now();
            $payment->save();

            if ($refundStatus === 'full' && $payment->order) {
                $payment->order->payment_status = 'refunded';
                $payment->order->save();
            }

            Log::info('Payment refunded', [
                'payment_id' => $payment->id,
                'order_id' => $payment->order_id,
                'refund_amount' => $amount,
                'refund_status' => $refundStatus,
            ]);

            DB::commit();

            event(new PaymentRefunded($payment));
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Process refund error', [
                'payment_id' => $payment->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }


    public static function getUserPaymentSummary($userId): array
    {
        $payments = Payment::where('user_id', $userId)->get();

        return [
            'total_payments' => $payments->count(),
            'total_amount' => $payments->sum('amount'),
            'completed_count' => $payments->where('status', 'completed')->count(),
            'failed_count' => $payments->where('status', 'failed')->count(),
            'refunded_count' => $payments->where('refund_status', '!=', 'none')->count(),
            'total_refunded' => $payments->sum('refund_amount'),
        ];
    }


    public static function getPaymentStatistics(): array
    {
        $payments = Payment::all();

        return [
            'total_transactions' => $payments->count(),
            'total_amount' => $payments->sum('amount'),
            'completed_transactions' => $payments->where('status', 'completed')->count(),
            'failed_transactions' => $payments->where('status', 'failed')->count(),
            'total_refunded' => $payments->sum('refund_amount'),
            'refunded_transactions' => $payments->where('refund_status', '!=', 'none')->count(),
            'average_transaction' => $payments->count() > 0 ? $payments->sum('amount') / $payments->count() : 0,
        ];
    }
}
