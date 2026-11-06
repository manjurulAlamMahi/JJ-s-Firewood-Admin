<?php

namespace App\Http\Controllers\API\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Traits\ApiResponse;
use App\Models\Order;
use App\Models\Payment;
use App\Services\SquarePaymentService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Cache;
use App\Models\PaymentMethod;
use Exception;

class PaymentController extends Controller
{
    use ApiResponse;

    protected $squareService;

    public function __construct(SquarePaymentService $squareService)
    {
        $this->squareService = $squareService;
    }


    public function pay(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:orders,id|max:255',
            'source_id' => 'required|string|max:255',
            'verification_token' => 'nullable|string|max:1024',
            'idempotency_key' => 'nullable|string|max:255',
        ]);

        DB::beginTransaction();

        try {
            $user = $request->user();
            $order = Order::findOrFail($request->order_id);

            if (!PaymentMethod::where('slug', 'square')->where('is_active', true)->exists()) {
                return $this->error('Card payments are currently disabled.', 422);
            }

            if ($order->user_id !== $user->id) {
                return $this->error('Unauthorized access to this order', 403);
            }

            if ($order->payment_status === 'paid') {
                return $this->error('Order already paid', 400);
            }

            if (!$order->isPending()) {
                return $this->error('Order cannot be paid in current state', 400);
            }

            if ($order->orderItems()->count() === 0) {
                return $this->error('Order has no items', 400);
            }

            $idempotencyKey = $request->idempotency_key ?? \Illuminate\Support\Str::uuid()->toString();

            $result = $this->squareService->processPayment(
                $request->source_id,
                $order->total,
                null,
                "{$user->name} - Order #{$order->order_number}",
                $order->order_number,
                $user->email,
                $user->name,
                $request->input('verification_token')
            );

            if ($result['success']) {
                $paymentData = [
                    'payment_id' => $result['payment_id'],
                    'amount' => $result['amount'],
                    'currency' => $result['currency'],
                    'status' => $result['status'] === 'COMPLETED' ? 'completed' : 'pending',
                    'receipt_url' => $result['receipt_url'] ?? null,
                    'user_id' => $user->id,
                    'order_id' => $order->id,
                ];

                if (Schema::hasColumn('payments', 'payment_method')) {
                    $paymentData['payment_method'] = 'square';
                }
                if (Schema::hasColumn('payments', 'source_id')) {
                    $paymentData['source_id'] = $request->source_id;
                }

                $payment = Payment::create($paymentData);

                $order->payment_status = 'paid';
                $order->order_status = 'processing';
                $order->transaction_id = $payment->payment_id;
                $order->payment_method = 'square';
                $order->idempotency_key = $idempotencyKey;
                $order->save();

                (new \App\Services\OrderCompletionService())->processLoyaltyPoints($order);

                DB::commit();

                Log::info('Payment successful', [
                    'user_id' => $user->id,
                    'order_id' => $order->id,
                    'payment_id' => $payment->payment_id,
                    'amount' => $payment->amount,
                ]);

                return $this->success([
                    'order' => $order->fresh(),
                    'payment' => $payment,
                    'message' => 'Payment successful!'
                ], 'Payment successful!');
            } else {
                DB::rollBack();

                $failedPaymentData = [
                    'order_id' => $order->id,
                    'user_id' => $user->id,
                    'amount' => $order->total,
                    'currency' => 'AUD',
                    'status' => 'failed',
                ];

                if (Schema::hasColumn('payments', 'payment_method')) {
                    $failedPaymentData['payment_method'] = 'square';
                }
                if (Schema::hasColumn('payments', 'source_id')) {
                    $failedPaymentData['source_id'] = $request->source_id;
                }
                if (Schema::hasColumn('payments', 'error_message')) {
                    $failedPaymentData['error_message'] = $result['message'] ?? 'Payment failed';
                }
                if (Schema::hasColumn('payments', 'error_code')) {
                    $failedPaymentData['error_code'] = $result['error_code'] ?? 'PAYMENT_FAILED';
                }

                $payment = Payment::create($failedPaymentData);

                Log::error('Payment failed', [
                    'user_id' => $user->id,
                    'order_id' => $order->id,
                    'error' => $result['message'],
                    'payment_id' => $payment->id,
                ]);

                return $this->error(
                    $result['message'] ?? 'Payment failed',
                    422,
                    $result['errors'] ?? null
                );
            }
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Payment exception', [
                'user_id' => $request->user()->id ?? null,
                'order_id' => $request->order_id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error('Payment error: ' . $e->getMessage(), 500);
        }
    }


    public function getPayment($paymentId)
    {
        try {
            $user = request()->user();

            $payment = Payment::where('id', $paymentId)
                ->where('user_id', $user->id)
                ->with(['order', 'user'])
                ->first();

            if (!$payment) {
                $payment = Payment::where('payment_id', $paymentId)
                    ->where('user_id', $user->id)
                    ->with(['order', 'user'])
                    ->first();
            }

            if (!$payment) {
                return $this->error('Payment not found', 404);
            }

            return $this->success([
                'payment' => $payment,
                'refundable_amount' => $payment->getRefundableAmount(),
                'can_refund' => $payment->canBeRefunded(),
            ], 'Payment details retrieved');
        } catch (Exception $e) {
            Log::warning('Payment not found', [
                'user_id' => request()->user()->id,
                'payment_id' => $paymentId,
            ]);
            return $this->error('Payment not found', 404);
        }
    }


    public function listPayments(Request $request)
    {
        try {
            $user = $request->user();
            $perPage = $request->query('per_page', 15);

            $payments = Payment::where('user_id', $user->id)
                ->with(['order'])
                ->orderBy('created_at', 'desc')
                ->paginate($perPage);

            return $this->success([
                'payments' => $payments,
            ], 'Payments retrieved');
        } catch (Exception $e) {
            Log::error('List payments exception', [
                'user_id' => $request->user()->id,
                'error' => $e->getMessage(),
            ]);
            return $this->error('Failed to retrieve payments', 500);
        }
    }

    


    public function getPaymentStatus($paymentId)
    {
        try {
            $user = request()->user();

            $payment = Payment::where('id', $paymentId)
                ->where('user_id', $user->id)
                ->first();

            if (!$payment) {
                $payment = Payment::where('payment_id', $paymentId)
                    ->where('user_id', $user->id)
                    ->first();
            }

            if (!$payment) {
                return $this->error('Payment not found', 404);
            }

            return $this->success([
                'payment_id' => $payment->payment_id,
                'status' => $payment->status,
                'refund_status' => $payment->refund_status,
                'amount' => $payment->amount,
                'refund_amount' => $payment->refund_amount,
                'created_at' => $payment->created_at,
                'refunded_at' => $payment->refunded_at,
            ], 'Payment status retrieved');
        } catch (Exception $e) {
            return $this->error('Payment not found', 404);
        }
    }


    public function verifyPayment($paymentId)
    {
        try {
            $user = request()->user();

            $payment = Payment::where('id', $paymentId)
                ->where('user_id', $user->id)
                ->first();

            if (!$payment) {
                $payment = Payment::where('payment_id', $paymentId)
                    ->where('user_id', $user->id)
                    ->first();
            }

            if (!$payment) {
                return $this->error('Payment not found', 404);
            }

            $result = $this->squareService->getPayment($paymentId);

            if ($result['success']) {
                $squarePayment = $result['payment'];

                if ($payment->status !== 'completed' && $squarePayment['status'] === 'COMPLETED') {
                    $payment->markAsCompleted([
                        'receipt_url' => $squarePayment['receipt_url'] ?? null,
                    ]);

                    if ($payment->order) {
                        $payment->order->payment_status = 'paid';
                        $payment->order->save();
                    }
                }

                return $this->success([
                    'local_payment' => $payment,
                    'square_payment' => $squarePayment,
                ], 'Payment verified');
            }

            return $this->error('Failed to verify payment with Square', 500);
        } catch (Exception $e) {
            Log::error('Verify payment exception', [
                'user_id' => request()->user()->id,
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);
            return $this->error('Payment verification failed', 500);
        }
    }


    public function cancelPayment($paymentId)
    {
        DB::beginTransaction();

        try {
            $user = request()->user();

            $payment = Payment::where('id', $paymentId)
                ->where('user_id', $user->id)
                ->first();

            if (!$payment) {
                $payment = Payment::where('payment_id', $paymentId)
                    ->where('user_id', $user->id)
                    ->first();
            }

            if (!$payment) {
                return $this->error('Payment not found', 404);
            }

            if ($payment->status !== 'pending') {
                return $this->error('Only pending payments can be cancelled', 400);
            }

            $result = $this->squareService->cancelPayment($payment->payment_id);

            if ($result['success']) {
                $payment->status = 'cancelled';
                $payment->save();

                if ($payment->order && $payment->order->payment_status === 'pending') {
                    $payment->order->payment_status = 'pending';
                    $payment->order->transaction_id = null;
                    $payment->order->save();
                }

                DB::commit();

                Log::info('Payment cancelled', [
                    'user_id' => $user->id,
                    'payment_id' => $paymentId,
                ]);

                return $this->success([
                    'payment' => $payment->fresh(),
                ], 'Payment cancelled successfully');
            }

            DB::rollBack();

            return $this->error(
                $result['message'] ?? 'Failed to cancel payment',
                422
            );
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Cancel payment exception', [
                'user_id' => request()->user()->id,
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);

            return $this->error('Cancel payment error: ' . $e->getMessage(), 500);
        }
    }


    public function getOrderPayment($orderId)
    {
        try {
            $user = request()->user();

            $order = Order::findOrFail($orderId);

            if ($order->user_id !== $user->id) {
                return $this->error('Unauthorized access', 403);
            }

            $payment = Payment::where('order_id', $orderId)
                ->with(['order'])
                ->first();

            if (!$payment) {
                return $this->success([
                    'payment' => null,
                    'message' => 'No payment found for this order'
                ]);
            }

            return $this->success([
                'payment' => $payment,
                'can_refund' => $payment->canBeRefunded(),
                'refundable_amount' => $payment->getRefundableAmount(),
            ], 'Order payment details retrieved');
        } catch (Exception $e) {
            return $this->error('Order not found', 404);
        }
    }


    public function refundByOrder(Request $request, $orderId)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'nullable|string|max:255',
        ]);

        DB::beginTransaction();

        try {
            $user = $request->user();

            $order = Order::findOrFail($orderId);

            if ($order->user_id !== $user->id) {
                return $this->error('Unauthorized access to this order', 403);
            }

            $payment = Payment::where('order_id', $orderId)
                ->where('user_id', $user->id)
                ->first();

            if (!$payment) {
                return $this->error('No payment found for this order', 404);
            }

            if (!$payment->isCompleted()) {
                return $this->error("Payment status is '{$payment->status}'. Only completed payments can be refunded.", 400);
            }

            if ($payment->isFullyRefunded()) {
                return $this->error('This payment has already been fully refunded', 400);
            }

            if ($payment->getRefundableAmount() <= 0) {
                return $this->error('No refundable amount available for this payment', 400);
            }

            if ($request->amount > $payment->getRefundableAmount()) {
                return $this->error(
                    'Refund amount cannot exceed ' . number_format($payment->getRefundableAmount(), 2),
                    400
                );
            }

            if (in_array($payment->payment_method, ['square', 'card'])) {
                $result = $this->squareService->refundPayment(
                    $payment->payment_id,
                    $request->amount,
                    $payment->currency ?? null,
                    $request->reason ?? 'Customer requested refund'
                );
            } else {
                 $result = ['success' => true, 'refund_id' => 'manual_' . time()];
            }

            if ($result['success']) {
                $refundAmount = $payment->refund_amount ?? 0;
                $totalRefunded = $refundAmount + $request->amount;

                $refundStatus = 'partial';
                if (abs($totalRefunded - $payment->amount) < 0.01) {
                    $refundStatus = 'full';
                }

                $payment->refund_id = $result['refund_id'];
                $payment->refund_amount = $totalRefunded;
                $payment->refund_status = $refundStatus;
                $payment->refund_reason = $request->reason;
                $payment->refunded_at = now();
                $payment->save();


                DB::commit();

                Log::info('Refund successful', [
                    'user_id' => $user->id,
                    'order_id' => $orderId,
                    'payment_id' => $payment->payment_id,
                    'refund_amount' => $request->amount,
                    'refund_id' => $result['refund_id'],
                ]);

                return $this->success([
                    'payment' => $payment->fresh(),
                    'message' => 'Refund processed successfully'
                ], 'Refund successful!');
            }

            DB::rollBack();

            Log::error('Refund failed', [
                'user_id' => $user->id,
                'order_id' => $orderId,
                'amount' => $request->amount,
                'error' => $result['message'] ?? 'Unknown error',
            ]);

            return $this->error(
                $result['message'] ?? 'Refund failed',
                422,
                $result['errors'] ?? null
            );
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Refund by order exception', [
                'user_id' => $request->user()->id,
                'order_id' => $orderId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error('Refund error: ' . $e->getMessage(), 500);
        }
    }

    public function refund(Request $request, $paymentId)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'nullable|string|max:255',
        ]);

        DB::beginTransaction();

        try {
            $user = $request->user();

            $payment = Payment::where('id', $paymentId)
                ->where('user_id', $user->id)
                ->first();

            if (!$payment) {
                $payment = Payment::where('payment_id', $paymentId)
                    ->where('user_id', $user->id)
                    ->first();
            }

            if (!$payment) {
                return $this->error('Payment not found', 404);
            }

            if (!$payment->isCompleted()) {
                return $this->error("Payment status is '{$payment->status}'. Only completed payments can be refunded.", 400);
            }

            if ($payment->isFullyRefunded()) {
                return $this->error('This payment has already been fully refunded', 400);
            }

            if ($payment->getRefundableAmount() <= 0) {
                return $this->error('No refundable amount available for this payment', 400);
            }

            if ($request->amount > $payment->getRefundableAmount()) {
                return $this->error(
                    'Refund amount cannot exceed ' . number_format($payment->getRefundableAmount(), 2),
                    400
                );
            }

             if (in_array($payment->payment_method, ['square', 'card'])) {
                $result = $this->squareService->refundPayment(
                    $payment->payment_id,
                    $request->amount,
                    $payment->currency ?? null,
                    $request->reason ?? 'Customer requested refund'
                );
             } else {
                 $result = ['success' => true, 'refund_id' => 'manual_' . time()];
             }

            if ($result['success']) {
                $refundAmount = $payment->refund_amount ?? 0;
                $totalRefunded = $refundAmount + $request->amount;

                $refundStatus = 'partial';
                if (abs($totalRefunded - $payment->amount) < 0.01) {
                    $refundStatus = 'full';
                }

                $payment->refund_id = $result['refund_id'];
                $payment->refund_amount = $totalRefunded;
                $payment->refund_status = $refundStatus;
                $payment->refund_reason = $request->reason;
                $payment->refunded_at = now();
                $payment->save();

                DB::commit();

                Log::info('Refund successful', [
                    'user_id' => $user->id,
                    'payment_id' => $payment->payment_id,
                    'refund_amount' => $request->amount,
                    'refund_id' => $result['refund_id'],
                ]);

                return $this->success([
                    'payment' => $payment->fresh(),
                    'message' => 'Refund processed successfully'
                ], 'Refund successful!');
            }

            DB::rollBack();

            return $this->error(
                $result['message'] ?? 'Refund failed',
                422,
                $result['errors'] ?? null
            );
        } catch (Exception $e) {
            DB::rollBack();

            Log::error('Refund exception', [
                'user_id' => $request->user()->id,
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return $this->error('Refund error: ' . $e->getMessage(), 500);
        }
    }


    public function checkSquareConfig()
    {
        try {
            $accessToken = config('square.access_token');
            $locationId = config('square.location_id');
            $environment = config('square.environment');
            $testMode = env('SQUARE_TEST_MODE', false);

            $config = [
                'access_token_set' => !empty($accessToken),
                'access_token_first_chars' => $accessToken ? substr($accessToken, 0, 5) . '***' : 'NOT SET',
                'location_id' => $locationId,
                'environment' => $environment,
                'test_mode' => $testMode,
            ];

            $result = $this->squareService->verifyConnection();

            return $this->success([
                'config' => $config,
                'verification' => $result,
            ], 'Square configuration retrieved');
        } catch (Exception $e) {
            return $this->error('Error checking Square config: ' . $e->getMessage(), 500);
        }
    }

    public function handleCallback(Request $request)
    {
        $sessionId = $request->input('session_id');
        $frontendRedirectUrl = $request->input('redirect_url');
        $squareTransactionId = $request->input('transactionId');

        Log::info('Payment Callback Received', [
            'session_id' => $sessionId,
            'frontend_url' => $frontendRedirectUrl,
            'square_tx_id' => $squareTransactionId
        ]);

        if (!$sessionId) {
            return response()->json(['error' => 'Missing session_id'], 400);
        }

        try {
            if (!Cache::has($sessionId)) {
                 Log::warning('Session not found in callback', ['session_id' => $sessionId]);
                 if ($frontendRedirectUrl) {
                     return redirect($frontendRedirectUrl . '&status=error&message=Session_expired_or_already_processed');
                 }
                 return response()->json(['error' => 'Session expired or not found'], 404);
            }

            $squareOrderId = Cache::get($sessionId . '_square_order_id');
            
            $paymentStatus = 'unknown';
            $amount = 0;
            $currency = 'AUD';
            $paymentId = $squareTransactionId;
            $verified = false;

            if ($squareTransactionId) {
                $paymentResult = $this->squareService->getPayment($squareTransactionId);
                if ($paymentResult['success']) {
                    $paymentStatus = $paymentResult['payment']['status'];
                    $amount = $paymentResult['payment']['amount'];
                    $currency = $paymentResult['payment']['currency'];
                    $verified = true;
                }
            } else if ($squareOrderId) {
                 Log::info('Checking Order status as fallback', ['square_order_id' => $squareOrderId]);
                 $orderResult = $this->squareService->getOrder($squareOrderId);
                 
                 if ($orderResult['success']) {
                     $orderState = $orderResult['order']['state'];
                     $tenders = $orderResult['order']['tenders'] ?? [];
                     
                     Log::info('Order status check', ['state' => $orderState, 'tenders_count' => count($tenders)]);
                     
                     if ($orderState === 'COMPLETED' || $orderState === 'OPEN') {
                         foreach ($tenders as $tender) {
                             $tenderType = $tender->getType();
                             Log::info('Checking tender', ['type' => $tenderType, 'id' => $tender->getId()]);

                             if (in_array($tenderType, ['CARD', 'card', 'THIRD_PARTY_CARD', 'OTHER', 'WALLET', 'SQUARE_GIFT_CARD'])) {
                                 
                                 $paymentId = $tender->getId();
                                 $amountMoney = $tender->getAmountMoney();
                                 $amount = $amountMoney ? ($amountMoney->getAmount() / 100) : 0;
                                 $currency = $amountMoney ? $amountMoney->getCurrency() : 'AUD';
                                 
                                 $paymentStatus = 'COMPLETED'; 
                                 $verified = true;
                                 break;
                             }
                         }
                     }
                 }
            } else {
                 Log::warning('No transactionId in callback', ['session_id' => $sessionId]);
            }

            $extraData = [];
            if ($verified && isset($paymentResult['payment'])) {
                $extraData = [
                    'receipt_url' => $paymentResult['payment']['receipt_url'] ?? null,
                    'source_id' => $paymentResult['payment']['source_type'] ?? null,
                ];
            }

            if ($verified && ($paymentStatus === 'COMPLETED' || $paymentStatus === 'APPROVED')) {
                
                 $sessionType = Cache::get($sessionId . '_type');

                 if ($sessionType === 'entry_package') {
                     $entryService = new \App\Services\EntryPackageCompletionService();
                     $entry = $entryService->completePurchase(
                        $sessionId,
                        $squareOrderId,
                        $paymentId,
                        $paymentStatus, 
                        $amount,
                        $currency,
                        $extraData
                     );

                     Log::info('Entry Package purchase completed via callback', ['entry_id' => $entry->id]);

                     if ($frontendRedirectUrl) {
                         $redirect = $frontendRedirectUrl . (strpos($frontendRedirectUrl, '?') === false ? '?' : '&') . 'status=success&session_id=' . $sessionId . '&entry_id=' . $entry->id;
                         return redirect($redirect);
                     }

                     return response()->json([
                         'success' => true,
                         'message' => 'Payment verified and entry purchase completed',
                         'entry' => $entry,
                         'code' => 200
                     ]);
                 } else {
                     $orderService = new \App\Services\OrderCompletionService();
                     $order = $orderService->completeOrder(
                        $sessionId,
                        $squareOrderId,
                        $paymentId,
                        $paymentStatus,
                        $amount,
                        $currency,
                        $extraData
                     );

                     Log::info('Order completed via callback', ['order_id' => $order->id]);
                     
                     if ($frontendRedirectUrl) {
                         $redirect = $frontendRedirectUrl . (strpos($frontendRedirectUrl, '?') === false ? '?' : '&') . 'status=success&session_id=' . $sessionId . '&order_id=' . $order->id;
                         return redirect($redirect);
                     }

                    
                     $successUrl = route('payment.success.public', ['orderId' => $order->id]) . '?session_id=' . $sessionId;
                     
                     return response()->json([
                        'success' => true,
                        'message' => 'Payment verified and order completed',
                        'order' => $order,
                        'success_url' => $successUrl,
                        'code' => 200
                     ]);
                 }

            } else {
                 Log::warning('Payment verification failed or not completed', ['status' => $paymentStatus]);
                 if ($frontendRedirectUrl) {
                     return redirect($frontendRedirectUrl . '&status=failed&reason=Payment_not_completed');
                 }
                 return response()->json(['error' => 'Payment verification failed'], 400);
            }

        } catch (\Exception $e) {
            Log::error('Callback processing failed', ['error' => $e->getMessage()]);
            if ($frontendRedirectUrl) {
                return redirect($frontendRedirectUrl . '&status=error&message=' . urlencode('Processing error'));
            }
            return response()->json(['error' => 'Server Error'], 500);
        }
    }

    /**
     * Get payment success information
     * Used by frontend app to display success message and order details
     *
     * @param string $orderId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPaymentSuccess($orderId)
    {
        try {
            $user = request()->user();
            
            $order = Order::with(['user', 'address', 'orderItems.product', 'deliveryType'])
                ->where('id', $orderId)
                ->where('user_id', $user->id)
                ->first();

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found'
                ], 404);
            }

            $payment = Payment::where('order_id', $order->id)
                ->where('status', 'completed')
                ->first();

            return response()->json([
                'success' => true,
                'data' => [
                    'success' => true,
                    'message' => 'Payment successful! Your order has been placed.',
                    'order' => $order,
                    'payment' => $payment,
                    'items_count' => $order->orderItems->count(),
                    'code' => 200
                ]
            ], 200);
        } catch (Exception $e) {
            Log::error('Get payment success exception', [
                'order_id' => $orderId,
                'user_id' => request()->user()->id,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payment success data'
            ], 500);
        }
    }

    /**
     * Get payment success without authentication
     * Uses session_id for validation - accessible without admin dashboard login
     * This endpoint allows payment callbacks to work regardless of auth status
     *
     * @param string $orderId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPaymentSuccessPublic($orderId)
    {
        try {
            $sessionId = request()->query('session_id');
            
            if (!$sessionId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Missing session_id - invalid payment flow'
                ], 400);
            }

            $orderData = Cache::get($sessionId);
            if (!$orderData) {
                Log::warning('Payment success accessed with expired/invalid session', [
                    'order_id' => $orderId,
                    'session_id' => $sessionId
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Session expired or invalid'
                ], 401);
            }

            $order = Order::with(['user', 'address', 'orderItems.product', 'deliveryType'])
                ->find($orderId);

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found'
                ], 404);
            }

            if ($order->user_id !== $orderData['user_id']) {
                Log::warning('Payment success accessed with mismatched user', [
                    'order_id' => $orderId,
                    'session_user_id' => $orderData['user_id'],
                    'order_user_id' => $order->user_id
                ]);
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access to this order'
                ], 403);
            }

            $payment = Payment::where('order_id', $order->id)
                ->where('status', 'completed')
                ->first();

            Log::info('Payment success retrieved without authentication', [
                'order_id' => $orderId,
                'user_id' => $order->user_id,
                'session_id' => $sessionId
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'success' => true,
                    'message' => 'Payment successful! Your order has been placed.',
                    'order' => $order,
                    'payment' => $payment,
                    'items_count' => $order->orderItems->count(),
                    'code' => 200
                ]
            ], 200);
        } catch (Exception $e) {
            Log::error('Get payment success public exception', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve payment success data'
            ], 500);
        }
    }
}

