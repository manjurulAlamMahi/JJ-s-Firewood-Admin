<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Entry;
use App\Models\PointHistory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class RewardRetractionService
{
    /**
     * Retract rewards (loyalty points and entries) for an order.
     * 
     * @param Order $order
     * @param string $reason e.g., 'refund', 'cancellation'
     * @return void
     */
    public function retractRewards(Order $order, string $reason = 'cancellation')
    {
        DB::beginTransaction();
        
        try {
            $this->retractLoyaltyPoints($order, $reason);
            $this->retractEntries($order, $reason);
            
            DB::commit();
            
            Log::info("RewardRetractionService: Successfully retracted points and entries ({$reason})", [
                'order_id' => $order->id,
                'user_id' => $order->user_id
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("RewardRetractionService: Failed to retract points/entries ({$reason})", [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Retract loyalty points earned from this order
     */
    protected function retractLoyaltyPoints(Order $order, string $reason)
    {
        if ($order->loyalty_points <= 0) {
            Log::info('No loyalty points to retract', ['order_id' => $order->id]);
            return;
        }
        
        $user = $order->user;
        
        if (!$user) {
            Log::warning('User not found for order', ['order_id' => $order->id]);
            return;
        }

        // Check if points were already retracted for this order (to avoid double deduction)
        $alreadyRetracted = PointHistory::where('order_id', $order->id)
            ->where('user_id', $user->id)
            ->where('points', '<', 0)
            ->exists();

        if ($alreadyRetracted) {
            Log::info('Loyalty points already retracted for this order', ['order_id' => $order->id]);
            return;
        }
        
        // Deduct the points
        $pointsToDeduct = $order->loyalty_points;
        
        // Make sure user doesn't go negative if that's a requirement, 
        // but usually we just deduct what was given even if it goes negative if they spent it.
        // However, the original code capped it at 0. Let's stick to that for safety unless told otherwise.
        if ($user->loyalty_points < $pointsToDeduct) {
            Log::warning('User has fewer points than order granted', [
                'user_id' => $user->id,
                'current_points' => $user->loyalty_points,
                'points_to_deduct' => $pointsToDeduct
            ]);
            $pointsToDeduct = $user->loyalty_points; 
        }
        
        if ($pointsToDeduct > 0) {
            $user->decrement('loyalty_points', $pointsToDeduct);
            
            // Log the retraction
            PointHistory::create([
                'user_id' => $user->id,
                'type' => $reason,
                'points' => -$pointsToDeduct,
                'description' => ucfirst($reason) . ": Points retracted from order #{$order->order_number}",
                'order_id' => $order->id,
            ]);
            
            Log::info('Loyalty points retracted', [
                'user_id' => $user->id,
                'order_id' => $order->id,
                'points_retracted' => $pointsToDeduct
            ]);
        }
    }

    /**
     * Retract entries granted by this order
     */
    protected function retractEntries(Order $order, string $reason)
    {
        // 1. Retract Permanent Reward Entries (for orders >= £200)
        $this->retractPermanentRewardEntries($order, $reason);

        // 2. Retract Temporary/Package Entries (if any were directly linked to order)
        // Usually permanent entries are the ones we track specifically here.
        // If there are other entry types linked to order_id, they should also be retracted.
        
        $entries = Entry::where('order_id', $order->id)
            ->where('user_id', $order->user_id)
            ->get();

        foreach ($entries as $entry) {
            Log::info('Retracting entry', [
                'entry_id' => $entry->id,
                'purchase_type' => $entry->purchase_type,
                'order_id' => $order->id
            ]);
            $entry->delete();

            // Track in PointHistory even if points are 0 for entries tracking
            PointHistory::create([
                'user_id' => $order->user_id,
                'type' => $reason . '_entry',
                'points' => 0,
                'description' => ucfirst($reason) . ": Entry retracted from order #{$order->order_number}",
                'order_id' => $order->id,
            ]);
        }
    }

    /**
     * Specifically handles the permanent reward entries logic
     */
    protected function retractPermanentRewardEntries(Order $order, string $reason)
    {
        // This is mostly covered by the general retractEntries loop above if order_id is set.
        // But the original code had fallback logic. I'll include a simplified version if order_id is missing.
        
        if (!Schema::hasColumn('entries', 'order_id')) {
             // FALLBACK: Time-based matching (only if order_id doesn't exist in schema)
             $entry = Entry::where('user_id', $order->user_id)
                ->whereNull('giveaway_id')
                ->where('purchase_type', 'reward')
                ->where('entry_count', 1)
                ->where('created_at', '>=', $order->created_at->subMinutes(5))
                ->where('created_at', '<=', $order->created_at->addMinutes(5))
                ->first();

            if ($entry) {
                $entry->delete();
                Log::warning('Retracted entry using time-based fallback', ['order_id' => $order->id]);
            }
        }
    }
}
