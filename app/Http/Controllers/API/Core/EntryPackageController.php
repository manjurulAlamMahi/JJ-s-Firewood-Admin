<?php

namespace App\Http\Controllers\API\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\EntryPackage;
use App\Models\Entry;
use App\Models\Payment;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Traits\ApiResponse;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\PointHistory;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Models\Giveaway;
use App\Models\PaymentMethod;


class EntryPackageController extends Controller
{

    use ApiResponse;

    protected $squareService;

    public function __construct(\App\Services\SquarePaymentService $squareService)
    {
        $this->squareService = $squareService;
    }

    public function index()
    {
        try {
            // Entry packages should be fetched from /giveaways endpoint
            // which includes giveaway-specific custom prices.
            // This endpoint no longer returns package data for the buy flow.
            return $this->success([], 'Use /giveaways endpoint to get entry packages with giveaway-specific pricing.');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve entry packages.', 500);
        }
    }

    public function show($id)
    {
        try {
            // Entry packages should be fetched from /giveaways endpoint
            // which includes giveaway-specific custom prices.
            return $this->success([], 'Use /giveaways endpoint to get entry packages with giveaway-specific pricing.');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve entry package.', 500);
        }
    }



    public function myEntries(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                return $this->error('Unauthenticated user.', 401);
            }

            $entries = Entry::where('user_id', $user->id)
                ->where(function ($q) {
                    $q->where('drawn', false)
                        ->orWhere('purchase_type', 'reward');
                })
                ->get();

            $totalEntries = $entries->sum('entry_count');

            $purchaseTypes = ['paid', 'reward'];

            $entriesGrouped = collect($purchaseTypes)->map(function ($type) use ($entries) {
                $group = $entries->filter(function ($entry) use ($type) {
                    return strtolower($entry->purchase_type) === $type;
                });

                return [
                    'purchase_type' => $type,
                    'total' => $group->sum('entry_count'),
                    'entries' => $group->map(function ($entry) {
                        return [
                            'giveaway_id' => $entry->giveaway_id,
                            'entry_count' => $entry->entry_count,
                            'amount_paid' => $entry->amount_paid,
                            'drawn' => $entry->drawn,
                            'created_at' => $entry->created_at,
                        ];
                    })->values(),
                ];
            });

            return $this->success([
                'total_entries' => $totalEntries,
                'entries_by_type' => $entriesGrouped,
            ], 'User entries retrieved successfully.');
        } catch (\Exception $e) {
            \Log::error('myEntries error: ' . $e->getMessage());
            return $this->error('Failed to retrieve user entries.', 500);
        }
    }




    public function buyWithPoints(Request $request)
    {
        try {
            $user = $request->user();

            $pointsPerEntry = 500; 
            $availablePoints = $user->loyalty_points;

            if ($availablePoints < $pointsPerEntry) {
                return $this->error('Not enough points to buy entries. You need at least 500 points.', 400);
            }

            $affordableEntries = floor($availablePoints / $pointsPerEntry);

            $packages = EntryPackage::where('status', 1)
                ->orderBy('entry_quantity', 'desc')
                ->get();

            if ($packages->isEmpty()) {
                return $this->error('No entry packages available at the moment.', 400);
            }

            $selectedPackage = null;
            foreach ($packages as $package) {
                if ($package->entry_quantity <= $affordableEntries) {
                    $selectedPackage = $package;
                    break;
                }
            }

            if (!$selectedPackage) {
                $selectedPackage = $packages->last(); 
                
                if ($selectedPackage->entry_quantity > $affordableEntries) {
                    return $this->error(
                        "Not enough points. You need at least " . 
                        ($selectedPackage->entry_quantity * $pointsPerEntry) . 
                        " points to buy the smallest package ({$selectedPackage->entry_quantity} entries).",
                        400
                    );
                }
            }

            $pointsNeeded = $selectedPackage->entry_quantity * $pointsPerEntry;
            $remainingPoints = $availablePoints - $pointsNeeded;

            DB::beginTransaction();

            $user->decrement('loyalty_points', $pointsNeeded);

            PointHistory::create([
                'user_id' => $user->id,
                'type' => 'paid',
                'points' => -$pointsNeeded,
                'description' => "Used {$pointsNeeded} points to buy {$selectedPackage->entry_quantity} entries (Package: {$selectedPackage->name})",
                'point_usage_for' => 'entry_purchase',
            ]);

            $entry = Entry::create([
                'user_id' => $user->id,
                'entry_count' => $selectedPackage->entry_quantity,
                'purchase_type' => 'paid',
                'amount_paid' => 0, 
            ]);

            DB::commit();

            return $this->success([
                'entries_added' => $selectedPackage->entry_quantity,
                'package_used' => [
                    'id' => $selectedPackage->id,
                    'name' => $selectedPackage->name,
                    'entry_quantity' => $selectedPackage->entry_quantity,
                ],
                'points_used' => $pointsNeeded,
                'remaining_points' => $remainingPoints,
                'entry_id' => $entry->id,
            ], 'Entries purchased successfully using points.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('buyWithPoints error: ' . $e->getMessage());
            return $this->error($e->getMessage(), 500);
        }
    }



    public function buyWithMoney(Request $request, $id)
    {
        try {
            $sessionId = $request->input('session_id');
            $giveawayId = $request->input('giveaway_id');
            
            $user = $request->user();
            if (!$user && $sessionId) {
                $sessionData = \Illuminate\Support\Facades\Cache::get($sessionId);
                if ($sessionData && isset($sessionData['user_id'])) {
                    $user = User::find($sessionData['user_id']);
                }
            }
            
            if (!$user) {
                return $this->error('User not found or authentication required.', 401);
            }

            $package = EntryPackage::where('status', 1)->findOrFail($id);

            // Giveaways should be used everywhere now.
            // If giveaway_id is missing, fallback to the next active giveaway that includes this package.
            if (!$giveawayId) {
                $fallbackGiveaway = Giveaway::active()
                    ->whereNull('winner_id')
                    ->whereHas('entryPackages', function($q) use ($id) {
                        $q->where('entry_packages.id', $id);
                    })
                    ->orderBy('draw_date', 'asc')
                    ->first();
                
                if ($fallbackGiveaway) {
                    $giveawayId = $fallbackGiveaway->id;
                    Log::info('EntryPackageController: Using fallback giveaway matching package', [
                        'giveaway_id' => $giveawayId,
                        'package_id' => $id
                    ]);
                }
            }

            $price = $package->price;
            if ($giveawayId) {
                $giveaway = Giveaway::find($giveawayId);
                if ($giveaway) {
                    $customPrice = $giveaway->getEntryPackagePrice($id);
                    if ($customPrice !== null && $customPrice > 0) {
                        $price = $customPrice;
                        Log::info('EntryPackageController: Using custom price from giveaway', [
                            'giveaway_id' => $giveawayId,
                            'package_id' => $id,
                            'custom_price' => $price,
                            'default_price' => $package->price,
                        ]);
                    }
                }
            }

            $sessionId = $sessionId ?? ('pending_entry_pkg_' . uniqid() . '_' . $user->id);

            if ($request->filled('source_id')) {
                Log::info('EntryPackageController: Direct payment attempt', [
                    'user_id' => $user->id,
                    'package_id' => $id,
                    'giveaway_id' => $giveawayId,  
                    'price' => $price,  
                    'source_id' => 'present'
                ]);

                if (!PaymentMethod::where('slug', 'square')->where('is_active', true)->exists()) {
                    return $this->error('Card payments are currently disabled.', 422);
                }

                $result = $this->squareService->processPayment(
                    $request->source_id,
                    $price,
                    null,
                    "{$user->name} - Entry Package Purchase: {$package->name}",
                    $sessionId,
                    $user->email,
                    $user->name,
                    $request->input('verification_token')
                );

                if ($result['success']) {
                    $completionService = new \App\Services\EntryPackageCompletionService();
                    
                    $purchaseData = [
                        'type' => 'entry_package',
                        'user_id' => $user->id,
                        'package_id' => $package->id,
                        'giveaway_id' => $giveawayId,  
                        'entry_quantity' => $package->entry_quantity,
                        'price' => $price,  
                    ];
                    \Illuminate\Support\Facades\Cache::put($sessionId, $purchaseData, now()->addMinutes(30));

                    $extraData = [
                        'receipt_url' => $result['receipt_url'] ?? null,
                    ];

                    $entry = $completionService->completePurchase(
                        $sessionId,
                        $result['provider_order_id'] ?? null,
                        $result['payment_id'],
                        $result['status'],
                        $result['amount'],
                        $result['currency'],
                        $extraData
                    );

                    $successUrl = route('entry_package.success.public', ['entryId' => $entry->id]) . '?session_id=' . $sessionId;

                    return $this->success([
                        'entry' => $entry,
                        'message' => 'Purchase completed successfully.',
                        'success_url' => $successUrl,
                        'session_id' => $sessionId
                    ], 'Purchase completed successfully.');
                } else {
                    return $this->error($result['message'] ?? 'Payment failed.', 422);
                }
            }

            $purchaseData = [
                'type' => 'entry_package',
                'user_id' => $user->id,
                'user_email' => $user->email,
                'package_id' => $package->id,
                'package_name' => $package->name,
                'giveaway_id' => $giveawayId,  
                'entry_quantity' => $package->entry_quantity,
                'price' => $price,  
                'total' => $price,  
            ];

            // Include giveaway-specific data so checkout page shows giveaway entry package info
            if ($giveawayId && isset($giveaway) && $giveaway) {
                $purchaseData['giveaway_name'] = $giveaway->prize_name;
                $purchaseData['giveaway_prize_type'] = $giveaway->prize_type;
                $purchaseData['giveaway_prize_value'] = $giveaway->prize_value;
                $purchaseData['giveaway_image'] = $giveaway->image;
            }

            \Illuminate\Support\Facades\Cache::put($sessionId, $purchaseData, now()->addHours(24));
            \Illuminate\Support\Facades\Cache::put($sessionId . '_type', 'entry_package', now()->addHours(24));

            $checkoutUrl = route('checkout.show', ['session_id' => $sessionId]);

            Log::info('EntryPackageController: Returning local checkout link', [
                'session_id' => $sessionId,
                'user_id' => $user->id,
                'giveaway_id' => $giveawayId, 
                'price' => $price,  
                'checkout_url' => $checkoutUrl
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Redirect to custom checkout to complete purchase.',
                'payment_link' => $checkoutUrl,
                'session_id' => $sessionId,
                'code' => 200
            ], 200);

        } catch (\Exception $e) {
            \Log::error('Entry Package Buy Error: ' . $e->getMessage());
            return $this->error($e->getMessage(), 500);
        }
    }

    public function getPaymentSuccessPublic($entryId)
    {
        try {
            $sessionId = request()->query('session_id');
            
            $entry = Entry::with(['user'])->find($entryId);

            if (!$entry) {
                return $this->error('Entry not found', 404);
            }

            if ($sessionId) {
                $purchaseData = Cache::get($sessionId);
                if (!$purchaseData) {
                    Log::warning('Entry package payment success accessed with expired/invalid session', [
                        'entry_id' => $entryId,
                        'session_id' => $sessionId
                    ]);
                    return $this->error('Session expired or invalid', 401);
                }

                if ($entry->user_id !== $purchaseData['user_id']) {
                    Log::warning('Entry package payment success accessed with mismatched user', [
                        'entry_id' => $entryId,
                        'session_user_id' => $purchaseData['user_id'],
                        'entry_user_id' => $entry->user_id
                    ]);
                    return $this->error('Unauthorized access', 403);
                }
            } else {
                if ($entry->purchase_type === 'paid') {
                    $payment = Payment::where('user_id', $entry->user_id)
                        ->where('status', 'completed')
                        ->orderByDesc('created_at')
                        ->first();

                    if (!$payment) {
                        return $this->error('No completed payment found for this entry', 404);
                    }
                }
            }

            $payment = null;
            if ($entry->purchase_type === 'paid') {
                $payment = Payment::where('user_id', $entry->user_id)
                    ->where('status', 'completed')
                    ->orderByDesc('created_at')
                    ->first();
            }

            return $this->success([
                'success' => true,
                'message' => $entry->purchase_type === 'paid' 
                    ? 'Payment successful! Your entries have been added.'
                    : 'Entries added successfully.',
                'entry' => [
                    'id' => $entry->id,
                    'entry_count' => $entry->entry_count,
                    'amount_paid' => $entry->amount_paid,
                    'purchase_type' => $entry->purchase_type,
                    'created_at' => $entry->created_at,
                ],
                'payment' => ($entry->purchase_type === 'paid' && $payment) ? [
                    'id' => $payment->id,
                    'payment_id' => $payment->payment_id,
                    'amount' => $payment->amount,
                    'currency' => $payment->currency,
                    'status' => $payment->status,
                    'created_at' => $payment->created_at,
                ] : null,
            ], 'Entry package ' . ($entry->purchase_type === 'paid' ? 'purchase' : 'reward') . ' completed successfully.');

        } catch (\Exception $e) {
            Log::error('Entry Package Success Endpoint Error: ' . $e->getMessage());
            return $this->error($e->getMessage(), 500);
        }
    }
}