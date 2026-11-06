<?php

namespace App\Services;

use App\Models\User;
use App\Models\WcCustomer;
use App\Models\WcOrder;
use App\Models\PointHistory;
use App\Models\Entry;
use App\Helpers\FirebaseHelper;
use Illuminate\Support\Facades\Log;

class WooCommerceSyncService
{
    /**
     * Link an app user to their WooCommerce customer profile
     * and retroactively grant loyalty points and entries.
     *
     * @param User $user
     * @return void
     */
    public static function syncCustomerToAppUser(User $user)
    {
        try {
            // Find WooCommerce customer by email
            $wcCustomer = WcCustomer::where('email', $user->email)->first();

            if (!$wcCustomer) {
                return; // No WooCommerce history found
            }

            // Link the accounts
            $user->woocommerce_customer_id = $wcCustomer->id;
            $user->save();

            // Find completed, unsynced orders
            $wcOrders = WcOrder::where('wc_customer_id', $wcCustomer->id)
                ->whereIn('payment_status', ['paid']) // WcOrder payment_status is 'paid' mapped from WC
                ->where('rewards_synced', false)
                ->get();

            if ($wcOrders->isEmpty()) {
                return;
            }

            $totalPointsEarned = 0;
            $totalEntriesEarned = 0;

            foreach ($wcOrders as $order) {
                $points = round((float) $order->total);

                if ($points > 0) {
                    $user->increment('loyalty_points', $points);
                    $totalPointsEarned += $points;

                    PointHistory::create([
                        'user_id' => $user->id,
                        'type' => 'purchase',
                        'points' => $points,
                        'description' => "Earned from website order #{$order->woocommerce_number}",
                    ]);
                }

                // Grant permanent entry if order total >= 200
                if ($order->total >= 200) {
                    Entry::create([
                        'user_id' => $user->id,
                        'purchase_type' => 'reward',
                        'amount_paid' => 0,
                        'entry_count' => 1,
                    ]);

                    PointHistory::create([
                        'user_id' => $user->id,
                        'type' => 'reward',
                        'points' => 0,
                        'description' => "Reward entry for website order ≥ £200",
                    ]);
                    
                    $totalEntriesEarned += 1;
                }

                $order->rewards_synced = true;
                $order->save();
            }

            // Send Push Notification if we awarded anything
            if ($totalPointsEarned > 0 || $totalEntriesEarned > 0) {
                $body = "You earned $totalPointsEarned points";
                if ($totalEntriesEarned > 0) {
                    $body .= " and $totalEntriesEarned entries";
                }
                $body .= " from your past website orders!";

                if ($user->fcm_token) {
                    FirebaseHelper::sendNotification(
                        $user->fcm_token,
                        'Welcome to the App! 🎉',
                        $body,
                        ['screen' => 'profile']
                    );
                }
            }

            Log::info("WooCommerceSyncService: Synced $totalPointsEarned points and $totalEntriesEarned entries for user {$user->id}");

        } catch (\Exception $e) {
            Log::error("WooCommerceSyncService Error for User {$user->id}: " . $e->getMessage());
        }
    }

    /**
     * Sync rewards for a WooCommerce customer during the API sync process.
     * Uses database transactions and row-level locking to prevent race conditions.
     *
     * @param WcCustomer $wcCustomer
     * @return void
     */
    public static function syncCustomerRewards(WcCustomer $wcCustomer)
    {
        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($wcCustomer) {
                // First, try to find the user by their linked ID
                $user = User::where('woocommerce_customer_id', $wcCustomer->id)->lockForUpdate()->first();
                
                // If not found, try to find an app user with the matching email and link them now!
                if (!$user && !empty($wcCustomer->email)) {
                    $user = User::where('email', $wcCustomer->email)->lockForUpdate()->first();
                    if ($user) {
                        $user->woocommerce_customer_id = $wcCustomer->id;
                        // We don't call save() here because it will be saved at the end of this function
                    }
                }

                if (!$user) {
                    return; // No matching app user exists for this WooCommerce customer
                }

                // Find completed, unsynced orders with lock for update
                $wcOrders = WcOrder::where('wc_customer_id', $wcCustomer->id)
                    ->whereIn('payment_status', ['paid'])
                    ->where('rewards_synced', false)
                    ->lockForUpdate()
                    ->get();

                if ($wcOrders->isEmpty()) {
                    return;
                }

                $totalPointsEarned = 0;
                $totalEntriesEarned = 0;

                foreach ($wcOrders as $order) {
                    $points = round((float) $order->total);

                    if ($points > 0) {
                        $user->loyalty_points += $points;
                        $totalPointsEarned += $points;

                        PointHistory::create([
                            'user_id' => $user->id,
                            'type' => 'purchase',
                            'points' => $points,
                            'description' => "Earned from website order #{$order->woocommerce_number}",
                        ]);
                    }

                    if ($order->total >= 200) {
                        Entry::create([
                            'user_id' => $user->id,
                            'purchase_type' => 'reward',
                            'amount_paid' => 0,
                            'entry_count' => 1,
                        ]);

                        PointHistory::create([
                            'user_id' => $user->id,
                            'type' => 'reward',
                            'points' => 0,
                            'description' => "Reward entry for website order ≥ £200",
                        ]);
                        
                        $totalEntriesEarned += 1;
                    }

                    $order->rewards_synced = true;
                    $order->save();
                }

                $user->save();

                if ($totalPointsEarned > 0 || $totalEntriesEarned > 0) {
                    $body = "You earned $totalPointsEarned points";
                    if ($totalEntriesEarned > 0) {
                        $body .= " and $totalEntriesEarned entries";
                    }
                    $body .= " from your recent website orders!";

                    if ($user->fcm_token) {
                        FirebaseHelper::sendNotification(
                            $user->fcm_token,
                            'New Rewards! 🎉',
                            $body,
                            ['screen' => 'profile']
                        );
                    }
                }

                Log::info("WooCommerceSyncService: Synced $totalPointsEarned points and $totalEntriesEarned entries for user {$user->id} via WooCommerce sync");
            });
        } catch (\Exception $e) {
            Log::error("WooCommerceSyncService syncCustomerRewards Error for WcCustomer {$wcCustomer->id}: " . $e->getMessage());
        }
    }

    /**
     * Batch process all pending rewards for all synced WooCommerce orders.
     */
    public static function syncAllPendingRewards()
    {
        try {
            // Get all distinct customer IDs that have unsynced, paid orders
            $customerIds = WcOrder::whereIn('payment_status', ['paid'])
                ->where('rewards_synced', false)
                ->whereNotNull('wc_customer_id')
                ->distinct()
                ->pluck('wc_customer_id');

            foreach ($customerIds as $customerId) {
                $wcCustomer = WcCustomer::find($customerId);
                if ($wcCustomer) {
                    self::syncCustomerRewards($wcCustomer);
                }
            }
        } catch (\Exception $e) {
            Log::error("WooCommerceSyncService syncAllPendingRewards Error: " . $e->getMessage());
        }
    }
}
