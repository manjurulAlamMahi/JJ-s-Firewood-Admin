<?php

namespace App\Services;

use App\Models\Entry;
use App\Models\Payment;
use App\Models\EntryPackage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Services\NotificationService;
use App\Notifications\CategoryNotification;
use Illuminate\Support\Facades\Mail;
use App\Mail\OrderPlacedMail;
use Exception;

class EntryPackageCompletionService
{
    /**
     * Complete entry package purchase from cached session data
     *
     * @param string $sessionId
     * @param string $squareOrderId
     * @param string $paymentId
     * @param string $status
     * @param float $amount
     * @param string $currency
     * @param array $extraData
     * @return Entry
     * @throws Exception
     */
    public function completePurchase($sessionId, $squareOrderId, $paymentId, $status, $amount, $currency = 'AUD', $extraData = [])
    {
        Log::info('EntryPackageCompletionService: Starting completion', [
            'session_id' => $sessionId,
            'payment_id' => $paymentId
        ]);

        $purchaseData = Cache::get($sessionId);

        if (!$purchaseData) {
            throw new Exception("Purchase data not found in cache for session: {$sessionId}");
        }


        $existingPayment = Payment::where('payment_id', $paymentId)->first();
        if ($existingPayment) {
             Log::info('EntryPackageCompletionService: Payment already recorded', ['payment_id' => $paymentId]);
            
        }

        DB::beginTransaction();

        try {
            $entry = Entry::create([
                'user_id' => $purchaseData['user_id'],
                'entry_count' => $purchaseData['entry_quantity'],
                'purchase_type' => 'paid',
                'amount_paid' => $purchaseData['price'],
                'giveaway_id' => $purchaseData['giveaway_id'] ?? null, 
            ]);

            $this->createPaymentRecord($entry, $purchaseData['user_id'], $paymentId, $amount, $currency, $extraData);

            DB::commit();
            
            // Notifications
            try {
                $user = User::findOrFail($purchaseData['user_id']);
                $giveaway = $entry->giveaway;
                $giveawayName = $giveaway ? $giveaway->prize_name : 'Giveaway';

                // 1. Database Notification
                NotificationService::success(
                    $user,
                    'Entries Received',
                    "You have received {$entry->entry_count} entries for {$giveawayName}."
                );

                // 2. Email Notification (Generic for now or use specific mailable if exists)
                // Using OrderPlacedMail as a placeholder if appropriate or just a log for now
                // Actually, let's just log and recommend a specific mailable to the user if they want a separate design.
                
                // 3. Push Notification to User
                $user->notify(new CategoryNotification('transactional', [
                    'subject' => 'Entries Added',
                    'body' => "You have received {$entry->entry_count} entries for {$giveawayName}.",
                    'title' => 'Entries Added',
                    'data' => [
                        'entry_id' => $entry->id,
                        'entry_count' => $entry->entry_count,
                        'type' => 'entries_added'
                    ]
                ]));

                // 4. Notifications to Admins
                $admins = User::where('is_admin', 1)->get();
                foreach ($admins as $admin) {
                    NotificationService::info(
                        $admin,
                        'New Package Purchase',
                        "User {$user->name} purchased {$entry->entry_count} entries for {$giveawayName}."
                    );

                    $admin->notify(new CategoryNotification('transactional', [
                        'subject' => 'New Entry Purchase',
                        'body' => "User {$user->name} purchased {$entry->entry_count} entries for {$giveawayName}.",
                        'title' => 'New Purchase',
                        'data' => [
                            'user_id' => $user->id,
                            'entry_id' => $entry->id,
                            'type' => 'admin_entries_purchased'
                        ]
                    ]));
                }

                Log::info('EntryPackageCompletionService: Notifications sent successfully', ['entry_id' => $entry->id]);

            } catch (Exception $e) {
                Log::error('EntryPackageCompletionService: Notification sending failed', [
                    'entry_id' => $entry->id,
                    'error' => $e->getMessage()
                ]);
            }

            // Keep session data for success endpoint validation - it will expire naturally
            // Only clear temporary/intermediate cache keys
            Cache::forget($sessionId . '_payment_link');
            Cache::forget($sessionId . '_square_order_id');
            Cache::forget($sessionId . '_type');

            // Re-cache session data to keep it valid for the success endpoint
            Cache::put($sessionId, $purchaseData, now()->addHours(1));

            Log::info('EntryPackageCompletionService: Purchase completed successfully', [
                'entry_id' => $entry->id,
                'session_id' => $sessionId
            ]);

            return $entry;

        } catch (Exception $e) {
            DB::rollBack();
            Log::error('EntryPackageCompletionService: Failed to complete purchase', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    protected function createPaymentRecord($entry, $userId, $paymentId, $amount, $currency, $extraData)
    {
        $paymentData = [
            'user_id' => $userId,
            'payment_id' => $paymentId,
            'amount' => $amount,
            'remaining_amount' => $amount,
            'currency' => $currency ?? 'AUD',
            'status' => 'completed',
        ];
        

        if (Schema::hasColumn('payments', 'payment_method')) {
            $paymentData['payment_method'] = 'square';
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
