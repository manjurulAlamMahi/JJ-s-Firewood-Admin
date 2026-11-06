<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Payment;
use App\Models\OrderItem;
use App\Models\Cart;
use App\Models\User;
use App\Models\ReferralUsage;
use App\Models\PointHistory;
use App\Models\CouponUsage;
use App\Models\Entry;
use App\Models\OrderItemBundleExtra;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use App\Mail\OrderPlacedMail;
use Exception;

class OrderCompletionService
{
    /**
     * Complete order creation from cached session data
     *
     * @param string $sessionId
     * @param string $squareOrderId
     * @param string $paymentId
     * @param string $status
     * @param float $amount
     * @param string $currency
     * @return Order
     * @throws Exception
     */
    public function completeOrder($sessionId, $squareOrderId, $paymentId, $status, $amount, $currency = 'AUD', $extraData = [])
    {
        Log::info('OrderCompletionService: Starting completion', [
            'session_id' => $sessionId,
            'payment_id' => $paymentId
        ]);

        $orderData = Cache::get($sessionId);

        if (!$orderData) {
            throw new Exception("Order data not found in cache for session: {$sessionId}");
        }

        if ($squareOrderId) {
            $existingOrder = Order::where('square_order_id', $squareOrderId)->first();
            if ($existingOrder) {
                Log::info('OrderCompletionService: Order already exists', ['order_id' => $existingOrder->id]);
                return $existingOrder;
            }
        }

        DB::beginTransaction();

        try {
            $order = Order::create([
                'user_id' => $orderData['user_id'],
                'shipping_address_id' => $orderData['shipping_address_id'],
                'coupon_id' => $orderData['coupon_id'],
                'subtotal' => $orderData['subtotal'],
                'delivery_fee' => $orderData['delivery_fee'],
                'discount' => $orderData['discount'],
                'tax' => $orderData['tax'],
                'total' => $orderData['total'],
                'payment_method' => $orderData['payment_method'],
                'payment_status' => $paymentStatus = in_array($orderData['payment_method'], ['cash_on_delivery', 'direct_bank_transfer', 'bank_transfer']) ? 'pending' : 'paid',
                'preferred_delivery_date' => $orderData['preferred_delivery_date'],
                'order_status' => $paymentStatus === 'paid' ? 'processing' : 'pending',
                'notes' => $orderData['notes'],
                'loyalty_points' => $orderData['loyalty_points'],
                'delivery_type_id' => $orderData['delivery_type_id'],
                'transaction_id' => $paymentId,
                'square_order_id' => $squareOrderId,
                'payment_link' => Cache::get($sessionId . '_payment_link'),
            ]);

            // Auto-tag every app-placed order with the "Order Centre" tag.
            // Tags are pure provenance labels — they don't drive workflow state
            // (delivery_date_confirmed_at does that). Wrapped in try/catch so
            // a missing tags table never breaks order creation.
            try {
                if (class_exists('\App\Services\OrderTagService')) {
                    app(\App\Services\OrderTagService::class)->attachToOrder($order, 'order-centre');
                }
            } catch (\Throwable $e) { /* non-critical — order still completes */ }

            foreach ($orderData['cart_items'] as $item) {
                $orderItem = OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'giveaway_id' => $item['giveaway_id'] ?? null,
                    'bundle_id' => $item['bundle_id'] ?? null,
                    'product_name' => $item['product_name'],
                    'quantity' => $item['quantity'],
                    'price' => $item['price'],
                    'subtotal' => $item['subtotal'],
                ]);

                foreach ($item['bundle_extras'] ?? [] as $extra) {
                    OrderItemBundleExtra::create([
                        'order_item_id' => $orderItem->id,
                        'product_id' => $extra['product_id'],
                        'product_name' => $extra['product_name'],
                        'quantity' => $extra['quantity'],
                        'price' => $extra['price'],
                    ]);
                }

                // Update Prize status if this item is a giveaway claim
                if (isset($item['giveaway_id']) && $item['giveaway_id']) {
                    \App\Models\Prize::where('user_id', $orderData['user_id'])
                        ->where('giveaway_id', $item['giveaway_id'])
                        ->update(['status' => 'claimed']);
                }
            }

            if (isset($orderData['coupon_id']) && $orderData['coupon_id']) {
                $coupon = \App\Models\Coupon::find($orderData['coupon_id']);
                if ($coupon) {
                    \App\Models\CouponUsage::create([
                        'user_id' => $orderData['user_id'],
                        'coupon_id' => $coupon->id,
                        'order_id' => $order->id,
                        'discount_amount' => $orderData['discount'],
                        'order_total' => $orderData['total'],
                    ]);
                    $coupon->increment('used_count');
                }
            }

            if (isset($orderData['referral_info']) && $orderData['referral_info']) {
                $this->processReferral($orderData['referral_info'], $orderData['user_id']);
            }

            foreach ($order->orderItems as $item) {
                if ($item->product) {
                    $item->product->decrement('quantity', $item->quantity);
                }

                if ($item->bundle_id) {
                    $bundle = \App\Models\Bundle::find($item->bundle_id);
                    if ($bundle && $bundle->baseProduct) {
                        $bundle->baseProduct->decrement('quantity', $item->quantity);
                    }
                    foreach ($item->bundleExtras as $bundleExtra) {
                        if ($bundleExtra->product) {
                            $bundleExtra->product->decrement('quantity', $bundleExtra->quantity);
                        }
                    }
                }
            }

            Log::info('OrderCompletionService: Deleting cart for user', ['user_id' => $order->user_id]);
            $deletedCount = Cart::where('user_id', $order->user_id)->delete();
            Log::info('OrderCompletionService: Cart deleted', ['count' => $deletedCount]);

            if ($order->payment_status === 'paid') {
                $this->processLoyaltyPoints($order);
            }

            $this->createPaymentRecord($order, $paymentId, $amount, $currency, $extraData);

            $repeatSchedule = $this->createRepeatSchedule($order, $orderData);

            DB::commit();

            Cache::forget($sessionId);
            Cache::forget($sessionId . '_payment_link');
            Cache::forget($sessionId . '_square_order_id');
            Cache::forget($sessionId . '_frontend_url'); 

            Log::info('OrderCompletionService: Order created successfully', ['order_id' => $order->id]);

            event(new \App\Events\OrderPlaced($order));
            Log::info('OrderCompletionService: Event OrderPlaced dispatched', ['order_id' => $order->id]);

            if ($repeatSchedule) {
                app(RepeatDeliveryService::class)->notifyActivated($repeatSchedule);
            }

            return $order;

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('OrderCompletionService: Failed to create order', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Payment has usually been taken by now, so a schedule failure must not
     * roll back the customer's order. The savepoint undoes only the schedule.
     */
    protected function createRepeatSchedule($order, $orderData)
    {
        if (empty($orderData['repeat_details'])) {
            return null;
        }

        try {
            return DB::transaction(fn () => app(RepeatDeliveryService::class)
                ->createFromOrder($order, $orderData['repeat_details']));
        } catch (\Throwable $e) {
            Log::error('OrderCompletionService: Failed to create repeat schedule', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected function processReferral($referralInfo, $userId)
    {
        $referralData = [
            'referrer_user_id' => $referralInfo['referrer_id'],
            'referred_user_id' => $userId,
            'referral_code' => $referralInfo['referral_code'],
            'bonus_given' => true,
            'referral_bonus' => 100,
            'used_at' => now(),
        ];

        // Only add discount_amount if it exists in the referral info
        if (isset($referralInfo['discount_amount'])) {
            $referralData['discount_amount'] = $referralInfo['discount_amount'];
        }

        ReferralUsage::create($referralData);

        if ($referralInfo['referrer_id']) {
            $referrer = User::find($referralInfo['referrer_id']);
            if ($referrer) {
                $referrer->increment('loyalty_points', 100);
                
                PointHistory::create([
                    'user_id' => $referrer->id,
                    'type' => 'referral_reward',
                    'points' => 100,
                    'description' => "Referral reward from user #{$userId}",
                ]);

                $user = User::find($userId);
                if ($user && !$user->referrer_id) {
                    $user->referrer_id = $referrer->id;
                    $user->save();
                }
            }
        }
    }

    /**
     * ==========================================
     * UPDATED METHOD - order_id TRACKING ADDED
     * ==========================================
     * 
     * Process loyalty points and permanent entries for completed order
     * 
     * CHANGES:
     * - Line 213: Added 'order_id' => $order->id to PointHistory (purchase)
     * - Line 220: Added 'order_id' => $order->id to Entry (permanent entry)
     * - Line 228: Added 'order_id' => $order->id to PointHistory (reward entry)
     * 
     * This enables:
     * - Accurate tracking of which order granted which points/entries
     * - Precise retraction on refund (no time-based guessing)
     * - Complete audit trail for all transactions
     */
    public function processLoyaltyPoints($order)
    {
        $user = $order->user;
        if ($user && $order->loyalty_points > 0) {
            $user->increment('loyalty_points', $order->loyalty_points);

            // UPDATED: Added order_id tracking
            PointHistory::create([
                'user_id' => $user->id,
                'type' => 'purchase',
                'points' => $order->loyalty_points,
                'description' => "Earned from order #{$order->id}",
                'order_id' => $order->id, // ADDED: Track which order gave these points
            ]);

            // Grant permanent entry if order total >= £200
            if ($order->total >= 200) {
                // UPDATED: Added order_id tracking
                Entry::create([
                    'user_id' => $user->id,
                    'order_id' => $order->id, // ADDED: Track which order granted this entry
                    'purchase_type' => 'reward',
                    'amount_paid' => 0,
                    'entry_count' => 1,
                ]);

                // UPDATED: Added order_id tracking
                PointHistory::create([
                    'user_id' => $user->id,
                    'type' => 'reward',
                    'points' => 0,
                    'description' => "Reward entry for order ≥ £200",
                    'order_id' => $order->id, // ADDED: Track which order gave this entry
                ]);
            }

        }
        
        // Note: Referral code is now generated when order is delivered (payment_status=paid AND order_status=delivered)
    }

    

    protected function createPaymentRecord($order, $paymentId, $amount, $currency, $extraData)
    {
        // Determine Status
        $status = 'completed'; // Default
        if (in_array($order->payment_method, ['cash_on_delivery', 'bank_transfer', 'direct_bank_transfer'])) {
            $status = 'pending';
        }

        $paymentData = [
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'payment_id' => $paymentId,
            'amount' => $amount,
            'remaining_amount' => $amount,
            'currency' => $currency ?? 'AUD',
            'status' => $status,
        ];
        
        if (Schema::hasColumn('payments', 'payment_method')) {
            // Determine Payment Method
            $method = 'square'; // Default
            
            if ($order->payment_method === 'cash_on_delivery') {
                $method = 'cod';
            } elseif (in_array($order->payment_method, ['bank_transfer', 'direct_bank_transfer'])) {
                $method = 'bank_transfer';
            } elseif (isset($extraData['is_free_order']) && $extraData['is_free_order']) {
                $method = 'free';
            }

            $paymentData['payment_method'] = $method;
        }

        if (Schema::hasColumn('payments', 'receipt_url') && isset($extraData['receipt_url'])) {
            $paymentData['receipt_url'] = $extraData['receipt_url'];
        }
        if (Schema::hasColumn('payments', 'source_id') && isset($extraData['source_id'])) {
            $paymentData['source_id'] = $extraData['source_id'];
        }

        Payment::create($paymentData);
    }

}