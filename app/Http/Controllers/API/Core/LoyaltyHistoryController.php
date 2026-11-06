<?php

namespace App\Http\Controllers\API\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\ReferralUsage;
use App\Models\PointHistory;
use App\Traits\ApiResponse;

class LoyaltyHistoryController extends Controller
{
    use ApiResponse;

   
    public function index()
    {
        try {
            $user = auth()->user();

            $pointHistory = PointHistory::where('user_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function($history) {
                    $label = match($history->type) {
                        'purchase' => 'Purchase',
                        'refund' => 'Refund',
                        'reward' => 'Reward',
                        'usage' => 'Used',
                        'referral_reward' => 'Referral Bonus',
                        default => ucfirst($history->type)
                    };

                    return [
                        'id' => $history->id,
                        'type' => $history->type,
                        'label' => $label,
                        'points' => $history->points,
                        'description' => $history->description,
                        'order_id' => $history->order_id,
                        'created_at' => $history->created_at,
                        'date_formatted' => $history->created_at->format('M d, Y'),
                    ];
                });

            $purchaseLoyaltyPoints = PointHistory::where('user_id', $user->id)
                ->whereIn('type', ['purchase', 'cancellation', 'refund'])
                ->whereNotNull('order_id')
                ->with('order:id,order_number')
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function($history) {
                    return [
                        'id' => $history->id,
                        'order_number' => $history->order?->order_number ?? 'N/A',
                        'loyalty_points' => $history->points, 
                        'created_at' => $history->created_at,
                    ];
                });

            $referralLoyaltyPoints = ReferralUsage::where('referrer_user_id', $user->id)
                ->select('id', 'referred_user_id', 'referral_code', 'referral_bonus', 'used_at')
                ->get();

            $pointsUsedForEntries = PointHistory::where('user_id', $user->id)
                ->where('point_usage_for', 'entry_purchase')
                ->select('id', 'points', 'description', 'created_at')
                ->get();

            return $this->success([
                'total_loyalty_points' => $user->loyalty_points,
                'referral_code' => $user->referral_code,
                
                
                
                'purchase_loyalty_points' => $purchaseLoyaltyPoints,
                'referral_loyalty_points' => $referralLoyaltyPoints,
                'points_used_for_entries' => $pointsUsedForEntries,
            ], 'Loyalty info fetched successfully.', 200);
        } catch (\Exception $e) {
            return $this->error('Failed to load loyalty info: ' . $e->getMessage(), 500);
        }
    }
}