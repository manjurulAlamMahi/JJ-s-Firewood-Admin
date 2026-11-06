<?php

namespace App\Http\Controllers\Web\Backend;

use App\Http\Controllers\Controller;
use App\Models\Giveaway;
use App\Models\EntryPackage;
use App\Models\UserPermanentEntry;
use App\Models\Entry;
use App\Models\User;
use App\Models\GiveawayDrawLog;
use App\Models\EntryHistory;
use App\Models\GiveawayDrawReplay;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\WinnerMailable;
use App\Notifications\WinnerSelectedNotification;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Notification;
use App\Notifications\CategoryNotification;
use App\Traits\ApiResponse;
use App\Models\Prize;
use App\Models\Product;
use App\Models\Cart;
use Illuminate\Support\Facades\Validator;

class GiveawayCentreController extends Controller
{
    use ApiResponse;

    public function index()
    {
        $permanentEntriesCount = Entry::where('giveaway_id', null)
            ->where('purchase_type', 'reward')
            ->where('drawn', false)
            ->distinct('user_id')
            ->count('user_id');

        $stats = [
            'total_giveaways' => Giveaway::count(),
            'active_giveaways' => Giveaway::where('status', Giveaway::STATUS_ACTIVE)->count(),
            'scheduled_giveaways' => Giveaway::where('status', Giveaway::STATUS_SCHEDULED)->count(),
            'completed_giveaways' => Giveaway::where('status', Giveaway::STATUS_COMPLETED)->count(),
            'entry_packages' => EntryPackage::count(),
            'users_with_permanent_entries' => $permanentEntriesCount,
        ];

        $hasDrawnColumn = Schema::hasColumn('entries', 'drawn');
        $permanentEntriesData = collect([]);

        $poolStatus = null;
        try {
            $pool = app(\App\Services\Giveaways\GiveawayPoolService::class);
            $poolStatus = [
                'healthy' => $pool->isSchedulerHealthy(),
                'last_heartbeat_at' => $pool->settings()->last_heartbeat_at,
            ];
        } catch (\Throwable $e) {
            // pool tables not migrated yet
        }

        return view('backend.giveaway-centre.index', compact('stats', 'permanentEntriesData', 'poolStatus'));
    }

    public function getGiveawaysData(Request $request)
    {
        $query = Giveaway::with(['winner', 'entries']);

        if ($request->filled('status') && $request->status !== 'all') {
            // Filter values are words; the DB stores active as '1' and completed as '0'.
            $map = ['active' => ['1', 'active'], 'completed' => ['0', 'completed']];
            $query->whereIn('status', $map[$request->status] ?? [$request->status]);
        }

        if ($request->type === 'upcoming') {
            $query->whereNull('winner_id');
        } elseif ($request->type === 'drawn') {
            $query->whereNotNull('winner_id');
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('prize_name', 'like', '%' . $request->search . '%')
                    ->orWhere('description', 'like', '%' . $request->search . '%')
                    ->orWhere('prize_type', 'like', '%' . $request->search . '%');
            });
        }

        $giveaways = $query->orderByRaw('
                CASE 
                    WHEN winner_id IS NOT NULL THEN winner_announced_at
                    ELSE draw_date 
                END DESC
            ')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($giveaway) {
                $usersWithPermanentEntries = Entry::where('giveaway_id', null)
                    ->where('purchase_type', 'reward')
                    ->where('drawn', false)
                    ->where('entry_count', '>', 0)
                    ->count();

                $totalPermanentEntriesAvailable = Entry::where('giveaway_id', null)
                    ->where('purchase_type', 'reward')
                    ->where('drawn', false)
                    ->sum('entry_count');

                $temporaryEntries = Entry::where('giveaway_id', $giveaway->id)
                    ->whereIn('purchase_type', ['discount', 'paid', 'firewood'])
                    ->where('drawn', false)
                    ->sum('entry_count');

                $totalInGiveaway = $giveaway->totalEntries();

                return [
                    'id' => $giveaway->id,
                    'prize_name' => $giveaway->prize_name,
                    'prize_type' => $giveaway->prize_type,
                    'prize_value' => number_format($giveaway->prize_value, 2),
                    'image' => $giveaway->getAttributes()['image'] ?? null,
                    'draw_date' => $giveaway->draw_date ? $giveaway->draw_date->format('Y-m-d') : 'N/A',
                    'draw_time' => $giveaway->draw_time ?? 'N/A',
                    'status' => $giveaway->status,
                    'winner_id' => $giveaway->winner_id,
                    'winner_name' => $giveaway->winner ? $giveaway->winner->name : null,
                    'permanent_entries' => $totalPermanentEntriesAvailable,
                    'temporary_entries' => $temporaryEntries,
                    'total_entries' => $totalPermanentEntriesAvailable + $temporaryEntries,
                    'total_participants' => $giveaway->totalParticipants(),
                ];
            });

        return response()->json(['data' => $giveaways]);
    }

    public function getEntryPackagesData()
    {
        $packages = EntryPackage::orderBy('position', 'asc')
            ->orderBy('entry_quantity', 'asc')
            ->get()
            ->map(function ($package) {
                return [
                    'id' => $package->id,
                    'name' => $package->name,
                    'description' => $package->description,
                    'entry_quantity' => $package->entry_quantity,
                    'price' => number_format($package->price, 2),
                    'price_raw' => $package->price,
                    'status' => $package->status,
                    'is_featured' => $package->is_featured,
                ];
            });

        return response()->json(['data' => $packages]);
    }

    public function getPermanentEntriesData(Request $request)
    {
        try {
            $query = User::query()->where('is_guest', 0);

            if ($request->filled('search')) {
                $query->where(function ($q) use ($request) {
                    $q->where('name', 'like', '%' . $request->search . '%')
                        ->orWhere('email', 'like', '%' . $request->search . '%');
                });
            }

            $hasDrawnColumn = Schema::hasColumn('entries', 'drawn');

            $users = $query->get()->map(function ($user) use ($hasDrawnColumn) {
                $permanentEntry = Entry::where('user_id', $user->id)
                    ->where('giveaway_id', null)
                    ->where('purchase_type', 'reward')
                    ->first();

                if ($hasDrawnColumn) {
                    $temporaryEntries = Entry::where('user_id', $user->id)
                        ->whereNotNull('giveaway_id')
                        ->where(function ($q) {
                            $q->where('drawn', false)->orWhereNull('drawn');
                        })
                        ->sum('entry_count');

                    $usedCount = Entry::where('user_id', $user->id)
                        ->whereNotNull('giveaway_id')
                        ->where('drawn', true)
                        ->distinct('giveaway_id')
                        ->count('giveaway_id');
                } else {
                    $temporaryEntries = Entry::where('user_id', $user->id)
                        ->whereNotNull('giveaway_id')
                        ->sum('entry_count');
                    $usedCount = 0;
                }

                $permanentCount = $permanentEntry ? $permanentEntry->entry_count : 0;
                $totalEntries = $permanentCount + $temporaryEntries;

                return [
                    'user_id' => $user->id,
                    'user_name' => $user->name,
                    'user_email' => $user->email,
                    'permanent_entries' => $permanentCount,
                    'temporary_entries' => $temporaryEntries,
                    'total_entries' => $totalEntries,
                    'total_earned' => $permanentCount,
                    'total_used' => $usedCount,
                    'last_updated_at' => $permanentEntry && $permanentEntry->updated_at
                        ? $permanentEntry->updated_at->format('Y-m-d H:i')
                        : 'Never',
                ];
            });

            if ($request->filled('has_entries') && $request->has_entries == '1') {
                $users = $users->filter(function ($user) {
                    return $user['total_entries'] > 0;
                });
            }

            return response()->json(['data' => $users->values()]);
        } catch (\Exception $e) {
            Log::error('getPermanentEntriesData failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json(['data' => [], 'error' => $e->getMessage()], 500);
        }
    }

    public function updateOrCreatePermanentEntries(Request $request, $userId)
    {
        $request->validate([
            'action' => 'required|in:set,add,deduct',
            'amount' => 'required|integer|min:0',
            'reason' => 'nullable|string|max:255'
        ]);

        DB::beginTransaction();
        try {
            $user = User::findOrFail($userId);

            $entry = Entry::where('user_id', $userId)
                ->where('giveaway_id', null)
                ->where('purchase_type', 'reward')
                ->first();

            $oldCount = $entry ? $entry->entry_count : 0;

            switch ($request->action) {
                case 'set':
                    if ($entry) {
                        $entry->update(['entry_count' => $request->amount]);
                    } else {
                        $entryData = [
                            'user_id' => $userId,
                            'giveaway_id' => null,
                            'purchase_type' => 'reward',
                            'entry_count' => $request->amount,
                            'amount_paid' => 0,
                        ];
                        if (Schema::hasColumn('entries', 'drawn')) {
                            $entryData['drawn'] = false;
                        }
                        $entry = Entry::create($entryData);
                    }
                    $message = "Set permanent entries to {$request->amount}";
                    $historyAction = 'add';
                    break;

                case 'add':
                    if ($entry) {
                        $entry->increment('entry_count', $request->amount);
                        $entry->refresh();
                    } else {
                        $entryData = [
                            'user_id' => $userId,
                            'giveaway_id' => null,
                            'purchase_type' => 'reward',
                            'entry_count' => $request->amount,
                            'amount_paid' => 0,
                        ];
                        if (Schema::hasColumn('entries', 'drawn')) {
                            $entryData['drawn'] = false;
                        }
                        $entry = Entry::create($entryData);
                    }
                    $message = "Added {$request->amount} permanent entries";
                    $historyAction = 'add';
                    break;

                case 'deduct':
                    if (!$entry || $entry->entry_count < $request->amount) {
                        $available = $entry ? $entry->entry_count : 0;
                        DB::rollBack();
                        return response()->json([
                            'success' => false,
                            'message' => "Cannot deduct {$request->amount} entries. User only has {$available} permanent entries."
                        ], 400);
                    }

                    $newCount = $entry->entry_count - $request->amount;
                    if ($newCount <= 0) {
                        $entry->delete();
                    } else {
                        $entry->update(['entry_count' => $newCount]);
                    }
                    $message = "Deducted {$request->amount} permanent entries";
                    $historyAction = 'remove';
                    break;
            }

            EntryHistory::create([
                'user_id' => $userId,
                'giveaway_id' => null,
                'admin_user_id' => auth()->id(),
                'action' => $historyAction,
                'quantity' => $request->amount,
                'source' => 'admin',
                'reason' => $request->reason ?? 'Giveaway Centre - ' . ucfirst($request->action),
            ]);

            DB::commit();

            $entry = Entry::where('user_id', $userId)
                ->where('giveaway_id', null)
                ->where('purchase_type', 'reward')
                ->first();

            $newCount = $entry ? $entry->entry_count : 0;

            Log::info("PERMANENT ENTRIES UPDATE", [
                'admin_id' => auth()->id(),
                'user_id' => $userId,
                'user_name' => $user->name,
                'action' => $request->action,
                'amount' => $request->amount,
                'reason' => $request->reason ?? 'None',
                'old_count' => $oldCount,
                'new_count' => $newCount,
                'table' => 'entries (giveaway_id=NULL, purchase_type=reward)'
            ]);

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'permanent_entries' => $newCount,
                    'total_earned' => $newCount,
                    'total_used' => 0,
                    'last_updated_at' => now()->format('Y-m-d H:i')
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to update permanent entries', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $userId,
                'action' => $request->action,
                'amount' => $request->amount
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update entries: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getUserEntryHistory($userId)
    {
        try {
            $user = User::findOrFail($userId);
            $historyItems = [];

            $entries = Entry::where('user_id', $userId)
                ->with('giveaway')
                ->orderBy('created_at', 'desc')
                ->get();

            foreach ($entries as $entry) {
                $giveawayName = 'Global Reward Entries';
                if ($entry->giveaway_id && $entry->giveaway) {
                    $giveawayName = $entry->giveaway->prize_name;
                }

                $type = 'Unknown';
                if ($entry->purchase_type === 'reward') {
                    $type = 'Admin Reward';
                } elseif ($entry->purchase_type === 'discount') {
                    $type = 'Purchased Package';
                } elseif ($entry->purchase_type === 'firewood') {
                    $type = 'Firewood Purchase';
                } elseif ($entry->purchase_type === 'paid') {
                    $type = 'Paid Entry';
                }

                $status = 'Active';
                if (isset($entry->drawn) && $entry->drawn) {
                    $status = 'Used';
                } elseif ($entry->giveaway_id === null) {
                    $status = 'Permanent';
                }

                $historyItems[] = [
                    'giveaway_name' => $giveawayName,
                    'entry_count' => $entry->entry_count,
                    'type' => $type,
                    'status' => $status,
                    'reason' => '-',
                    'admin' => '-',
                    'created_at' => $entry->created_at->format('Y-m-d H:i'),
                    'timestamp' => $entry->created_at->timestamp,
                ];
            }

            $adminHistory = EntryHistory::where('user_id', $userId)
                ->with(['admin', 'giveaway'])
                ->orderBy('created_at', 'desc')
                ->get();

            foreach ($adminHistory as $history) {
                $giveawayName = $history->giveaway_id
                    ? ($history->giveaway ? $history->giveaway->prize_name : 'Giveaway #' . $history->giveaway_id)
                    : 'Global Entries';

                $action = $history->action === 'add' ? 'Added' : 'Removed';
                $type = $history->action === 'add' ? 'Admin Added' : 'Admin Removed';

                $historyItems[] = [
                    'giveaway_name' => $giveawayName,
                    'entry_count' => ($history->action === 'add' ? '+' : '-') . $history->quantity,
                    'type' => $type,
                    'status' => 'Admin Action',
                    'reason' => $history->reason ?? '-',
                    'admin' => $history->admin ? $history->admin->name : 'System',
                    'created_at' => $history->created_at->format('Y-m-d H:i'),
                    'timestamp' => $history->created_at->timestamp,
                ];
            }

            usort($historyItems, function ($a, $b) {
                return $b['timestamp'] - $a['timestamp'];
            });

            foreach ($historyItems as &$item) {
                unset($item['timestamp']);
            }

            return response()->json([
                'success' => true,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'entries' => $historyItems
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get user entry history', [
                'error' => $e->getMessage(),
                'user_id' => $userId
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function create()
    {
        $entryPackages = EntryPackage::where('status', 1)
            ->orderBy('entry_quantity', 'asc')
            ->get();

        return view('backend.giveaway-centre.create', compact('entryPackages'));
    }

    public function store(Request $request)
    {
        Log::info('GIVEAWAY STORE STARTED', [
            'all_input' => $request->all(),
            'has_image' => $request->hasFile('image'),
        ]);

        // Drafts don't need real dates yet - fill placeholders so the draft can be saved.
        if ($request->input('action') === 'draft' || $request->input('status') === 'draft') {
            $request->merge([
                'status'      => 'draft',
                'prize_value' => $request->input('prize_value') !== null && $request->input('prize_value') !== '' ? $request->input('prize_value') : 0,
                'start_date' => $request->input('start_date') ?: now('Australia/Perth')->toDateString(),
                'start_time' => $request->input('start_time') ?: '00:00',
                'draw_date'  => $request->input('draw_date') ?: now('Australia/Perth')->addDays(7)->toDateString(),
                'draw_time'  => $request->input('draw_time') ?: '20:00',
            ]);
        }

        try {
            $validated = $request->validate([
                'prize_name' => 'required|string|max:255',
                'description' => ($request->input('status') === 'draft') ? 'nullable|string' : 'required|string',
                'prize_value' => 'required|numeric|min:0',
                'status' => 'required|in:draft,scheduled,active,cancelled',
                'start_date' => 'required|date',
                'start_time' => 'required',
                'draw_date' => 'required|date|after_or_equal:start_date',
                'draw_time' => 'required',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:10240',
                'prize_type' => 'required|in:bundle,discount,accessory,others',
                'max_entries_per_user' => 'nullable|integer|min:1',
                'auto_draw' => 'nullable',
                'entry_packages' => 'nullable|array',
                'package_custom_price' => 'nullable|array',
                'animation_style' => 'required|in:lottery_balls,raffle_tickets',
            ], [
                'image.uploaded' => 'The image failed to upload - it is probably larger than the server allows. Try a smaller image (under 10MB).',
                'image.mimes'    => 'The image must be a JPG, PNG or WEBP file.',
                'image.image'    => 'The image must be a JPG, PNG or WEBP file.',
                'image.max'      => 'The image must be under 10MB.',
            ]);

            Log::info('VALIDATION PASSED', ['validated' => $validated]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('VALIDATION FAILED', [
                'errors' => $e->errors(),
                'input' => $request->all()
            ]);

            return back()
                ->withErrors($e->errors())
                ->withInput()
                ->with('error', 'Please check the form for errors');
        }

        DB::beginTransaction();
        try {
            $imagePath = null;
            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();

                if (!file_exists(public_path('giveaway_images'))) {
                    mkdir(public_path('giveaway_images'), 0777, true);
                }

                $image->move(public_path('giveaway_images'), $imageName);
                $imagePath = 'giveaway_images/' . $imageName;

                Log::info('IMAGE UPLOADED', ['path' => $imagePath]);
            }

            $status = $request->status;
            if ($request->action === 'draft') {
                $status = Giveaway::STATUS_DRAFT;
            }

            if ($status === 'active') {
                $status = Giveaway::STATUS_ACTIVE;
            } elseif ($status === 'completed') {
                $status = Giveaway::STATUS_COMPLETED;
            }

            $endDate = $request->draw_date;
            $scheduledAt = $request->draw_date . ' ' . $request->draw_time;
            $maxPosition = Giveaway::max('position') ?? 0;

            $giveawayData = [
                'prize_name' => $request->prize_name,
                'description' => $request->description ?? '',
                'prize_value' => $request->prize_value,
                'prize_type' => $request->prize_type ?? 'others',
                'image' => $imagePath,
                'status' => $status,
                'start_date' => $request->start_date,
                'end_date' => $endDate,
                'draw_date' => $request->draw_date,
                'draw_time' => $request->draw_time,
                'max_entries_per_user' => $request->max_entries_per_user,
                'auto_draw' => $request->has('auto_draw') ? 1 : 0,
                'allow_temp_entries' => 1,
                'position' => $maxPosition + 1,
                'winner_id' => null,
                'animation_style' => $request->animation_style ?? 'lottery_balls',
            ];

            if (Schema::hasColumn('giveaways', 'scheduled_at')) {
                $giveawayData['scheduled_at'] = $scheduledAt;
            }

            if (Schema::hasColumn('giveaways', 'winner_drawn_at')) {
                $giveawayData['winner_drawn_at'] = null;
            }

            Log::info('CREATING GIVEAWAY', ['data' => $giveawayData]);

            $giveaway = Giveaway::create($giveawayData);

            Log::info('GIVEAWAY CREATED', [
                'giveaway_id' => $giveaway->id,
                'prize_name' => $giveaway->prize_name
            ]);

            if ($request->has('entry_packages') && is_array($request->entry_packages)) {
                $packageData = [];

                foreach ($request->entry_packages as $packageId) {
                    $package = EntryPackage::find($packageId);
                    if ($package) {
                        $customPrice = null;

                        if (
                            isset($request->package_custom_price[$packageId]) &&
                            !empty($request->package_custom_price[$packageId]) &&
                            $request->package_custom_price[$packageId] != $package->price
                        ) {
                            $customPrice = $request->package_custom_price[$packageId];
                        }

                        $packageData[$packageId] = [
                            'custom_price' => $customPrice,
                            'created_at' => now(),
                            'updated_at' => now()
                        ];
                    }
                }

                if (!empty($packageData)) {
                    $giveaway->entryPackages()->attach($packageData);
                    Log::info('PACKAGES ATTACHED', ['count' => count($packageData)]);
                }
            }

            DB::commit();

            Log::info('GIVEAWAY SAVED SUCCESSFULLY', [
                'giveaway_id' => $giveaway->id,
                'status' => $giveaway->status
            ]);

            // Notify all non-admin users if a new giveaway is created as active
            if ($giveaway->status === Giveaway::STATUS_ACTIVE) {
                try {
                    $users = User::whereNotNull('fcm_token')->where('is_admin', 0)->get();
                    foreach ($users as $user) {
                        $user->notify(new CategoryNotification('updates', [
                            'subject' => 'New Giveaway!',
                            'body'    => "🎁 {$giveaway->prize_name} — Enter now for a chance to win!",
                            'title'   => 'New Giveaway Available!',
                            'screen'  => 'giveaway_details',
                            'data'    => ['giveaway_id' => (string) $giveaway->id, 'type' => 'new_giveaway']
                        ]));
                    }
                    Log::info('Push notifications sent for new active giveaway', ['giveaway_id' => $giveaway->id]);
                } catch (\Exception $e) {
                    Log::error('Failed to send push notifications for new giveaway', [
                        'giveaway_id' => $giveaway->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            $message = ($request->status === 'draft' || $request->action === 'draft')
                ? 'Giveaway saved as draft successfully!'
                : 'Giveaway created successfully!';

            return redirect()->route('giveaway-centre.index')
                ->with('success', $message);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('GIVEAWAY CREATION FAILED', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return back()
                ->withInput()
                ->with('error', 'Failed to create giveaway: ' . $e->getMessage());
        }
    }

    public function edit($id)
    {
        $giveaway = Giveaway::with('entryPackages')->findOrFail($id);

        $entryPackages = EntryPackage::where('status', 1)
            ->orderBy('entry_quantity', 'asc')
            ->get();

        return view('backend.giveaway-centre.edit', compact('giveaway', 'entryPackages'));
    }

    public function update(Request $request, $id)
    {
        Log::info('GIVEAWAY UPDATE STARTED', [
            'giveaway_id' => $id,
            'all_input' => $request->all(),
            'has_image' => $request->hasFile('image'),
        ]);

        $isDraft = $request->input('status') === 'draft';
        if ($isDraft) {
            // Drafts don't need real dates yet - fill placeholders so the draft can be saved.
            $request->merge([
                'prize_value' => $request->input('prize_value') !== null && $request->input('prize_value') !== '' ? $request->input('prize_value') : 0,
                'start_date'  => $request->input('start_date') ?: now('Australia/Perth')->toDateString(),
                'start_time'  => $request->input('start_time') ?: '00:00',
                'draw_date'   => $request->input('draw_date') ?: now('Australia/Perth')->addDays(7)->toDateString(),
                'draw_time'   => $request->input('draw_time') ?: '20:00',
            ]);
        }

        try {
            $validated = $request->validate([
                'prize_name' => 'required|string|max:255',
                'description' => $isDraft ? 'nullable|string' : 'required|string',
                'prize_value' => 'required|numeric|min:0',
                'status' => 'required|in:draft,scheduled,active,cancelled',
                'start_date' => 'required|date',
                'start_time' => 'required',
                'draw_date' => 'required|date|after_or_equal:start_date',
                'draw_time' => 'required',
                'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:10240',
                'max_entries_per_user' => 'nullable|integer|min:1',
                'auto_draw' => 'nullable',
                'entry_packages' => 'nullable|array',
                'package_custom_price' => 'nullable|array',
                'prize_type' => 'required|in:bundle,discount,accessory,others',
                'animation_style' => 'nullable|in:lottery_balls,raffle_tickets',
            ]);

            Log::info('VALIDATION PASSED');
        } catch (\Illuminate\Validation\ValidationException $e) {
            Log::error('VALIDATION FAILED', [
                'errors' => $e->errors(),
                'input' => $request->all()
            ]);

            return back()
                ->withErrors($e->errors())
                ->withInput()
                ->with('error', 'Please check the form for errors');
        }

        DB::beginTransaction();
        try {
            $giveaway = Giveaway::findOrFail($id);
            $oldStatus = $giveaway->status;

            if ($request->hasFile('image')) {
                $rawImage = $giveaway->getRawOriginal('image');
                if ($rawImage) {
                    $oldImagePath = public_path($rawImage);
                    if (file_exists($oldImagePath)) {
                        unlink($oldImagePath);
                    }
                }

                $image = $request->file('image');
                $imageName = time() . '_' . uniqid() . '.' . $image->getClientOriginalExtension();

                if (!file_exists(public_path('giveaway_images'))) {
                    mkdir(public_path('giveaway_images'), 0777, true);
                }

                $image->move(public_path('giveaway_images'), $imageName);
                $giveaway->image = 'giveaway_images/' . $imageName;

                Log::info('IMAGE UPDATED', ['path' => $giveaway->image]);
            }

            $status = $request->status;

            if ($status === 'active') {
                $status = Giveaway::STATUS_ACTIVE;
            } elseif ($status === 'completed') {
                $status = Giveaway::STATUS_COMPLETED;
            }

            $giveaway->prize_name = $request->prize_name;
            $giveaway->description = $request->description ?? '';
            $giveaway->prize_value = $request->prize_value;
            $giveaway->prize_type = $request->prize_type ?? $giveaway->prize_type;
            $giveaway->status = $status;
            $giveaway->start_date = $request->start_date;
            $giveaway->start_time = $request->start_time ?: '00:00';
            $giveaway->end_date = $request->draw_date;
            $giveaway->draw_date = $request->draw_date;
            $giveaway->draw_time = $request->draw_time;
            $giveaway->max_entries_per_user = $request->max_entries_per_user;
            $giveaway->auto_draw = $request->has('auto_draw') ? 1 : 0;

            if ($request->has('animation_style')) {
                $giveaway->animation_style = $request->animation_style;
            }

            if (Schema::hasColumn('giveaways', 'scheduled_at')) {
                $giveaway->scheduled_at = $request->draw_date . ' ' . $request->draw_time;
            }

            $giveaway->save();

            Log::info('GIVEAWAY UPDATED', ['giveaway_id' => $giveaway->id]);

            $giveaway->entryPackages()->detach();

            if ($request->has('entry_packages') && is_array($request->entry_packages)) {
                $packageData = [];

                foreach ($request->entry_packages as $packageId) {
                    $package = EntryPackage::find($packageId);
                    if ($package) {
                        $customPrice = null;

                        if (
                            isset($request->package_custom_price[$packageId]) &&
                            !empty($request->package_custom_price[$packageId]) &&
                            $request->package_custom_price[$packageId] != $package->price
                        ) {
                            $customPrice = $request->package_custom_price[$packageId];
                        }

                        $packageData[$packageId] = [
                            'custom_price' => $customPrice,
                            'created_at' => now(),
                            'updated_at' => now()
                        ];
                    }
                }

                if (!empty($packageData)) {
                    $giveaway->entryPackages()->attach($packageData);
                    Log::info('PACKAGES UPDATED', ['count' => count($packageData)]);
                }
            }

            DB::commit();

            Log::info('GIVEAWAY UPDATE SUCCESSFUL', [
                'giveaway_id' => $giveaway->id,
                'status' => $giveaway->status
            ]);

            // Notify all non-admin users if a giveaway is newly activated
            if ($oldStatus !== Giveaway::STATUS_ACTIVE && $giveaway->status === Giveaway::STATUS_ACTIVE) {
                try {
                    $users = User::whereNotNull('fcm_token')->where('is_admin', 0)->get();
                    foreach ($users as $user) {
                        $user->notify(new CategoryNotification('updates', [
                            'subject' => 'New Giveaway Active!',
                            'body'    => "🎁 {$giveaway->prize_name} — Enter now for a chance to win!",
                            'title'   => 'New Giveaway Available!',
                            'screen'  => 'giveaway_details',
                            'data'    => ['giveaway_id' => (string) $giveaway->id, 'type' => 'new_giveaway']
                        ]));
                    }
                    Log::info('Push notifications sent for newly activated giveaway', ['giveaway_id' => $giveaway->id]);
                } catch (\Exception $e) {
                    Log::error('Failed to send push notifications for newly activated giveaway', [
                        'giveaway_id' => $giveaway->id,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            return redirect()->route('giveaway-centre.index')
                ->with('success', 'Giveaway updated successfully!');
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('GIVEAWAY UPDATE FAILED', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return back()
                ->withInput()
                ->with('error', 'Failed to update giveaway: ' . $e->getMessage());
        }
    }

    public function draw($id)
    {
        try {
            $giveaway = Giveaway::findOrFail($id);

            $result = app(\App\Services\Giveaways\GiveawayDrawService::class)
                ->draw($giveaway, false, auth()->user());

            if ($result['status'] === 'already_drawn') {
                return response()->json(['success' => false, 'message' => 'Winner already drawn for this giveaway'], 400);
            }
            if ($result['status'] === 'not_active') {
                return response()->json(['success' => false, 'message' => 'Only a live (active) giveaway can be drawn. Set it to Active first.'], 400);
            }
            if ($result['status'] === 'no_entries') {
                return response()->json(['success' => false, 'message' => 'No entries found for this giveaway'], 400);
            }
            if ($result['status'] !== 'drawn') {
                return response()->json(['success' => false, 'message' => 'Giveaway not found'], 404);
            }

            // Start the next giveaway (scheduled or from the pool) straight away.
            \App\Models\Giveaway::activateNextScheduled();

            $winner = $result['winner'];
            $replay = $result['replay'];

            return response()->json([
                'success' => true,
                'message' => 'Winner drawn successfully!',
                'winner' => [
                    'id' => $winner->id,
                    'name' => $winner->name,
                    'email' => $winner->email,
                ],
                'replay_id' => $replay->id ?? null,
                'draw_id' => $replay->draw_id ?? null,
                'animation_url' => route('giveaway-centre.draw-live', $giveaway->id),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to draw winner', [
                'giveaway_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to draw winner: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function generateAnimationData($giveaway, $entries, $winnerId)
    {
        $winner = User::find($winnerId);

        return [
            'version' => '1.0',
            'duration' => 7000,
            'style' => $giveaway->animation_style ?? 'lottery_balls',
            'giveaway' => [
                'id' => $giveaway->id,
                'prize_name' => $giveaway->prize_name,
                'prize_value' => $giveaway->prize_value,
                'image' => $giveaway->image
            ],
            'winner' => [
                'id' => $winner->id,
                'name' => $winner->name,
                'avatar' => $winner->avatar ?? null
            ],
            'stats' => [
                'total_entries' => $entries->sum('entry_count'),
                'total_participants' => $entries->unique('user_id')->count(),
                'draw_date' => $giveaway->draw_date->format('d/m/Y'),
                'draw_time' => $giveaway->draw_time
            ],
            'timeline' => [
                ['time' => 0, 'action' => 'start'],
                ['time' => 3000, 'action' => 'slow_down'],
                ['time' => 6500, 'action' => 'reveal_winner'],
                ['time' => 7000, 'action' => 'complete']
            ]
        ];
    }

    public function duplicate(Request $request, $id)
    {
        $request->validate([
            'start_date' => 'required|date',
            'draw_date' => 'required|date|after_or_equal:start_date',
            'draw_time' => 'required'
        ]);

        DB::beginTransaction();
        try {
            $original = Giveaway::with('entryPackages')->findOrFail($id);

            $newStartDate = \Carbon\Carbon::parse($request->start_date);
            $newDrawDate = \Carbon\Carbon::parse($request->draw_date);
            $newDrawTime = $request->draw_time;
            $newEndDate = $newDrawDate->copy();

            $maxPosition = Giveaway::max('position') ?? 0;

            $columns = Schema::getColumnListing('giveaways');

            $duplicateData = [];

            $baseName = preg_replace('/\s\((Copy|\d+)\)$/', '', $original->prize_name);

            $counter = 1;
            $newName = $baseName . ' (' . $counter . ')';

            while (Giveaway::where('prize_name', $newName)->exists()) {
                $counter++;
                $newName = $baseName . ' (' . $counter . ')';
            }

            $duplicateData['prize_name'] = $newName;
            $duplicateData['description'] = $original->description;
            $duplicateData['prize_value'] = $original->prize_value;
            $duplicateData['status'] = $original->status === '0' ? 'draft' : $original->status;
            $duplicateData['start_date'] = $newStartDate;
            $duplicateData['draw_date'] = $newDrawDate;
            $duplicateData['draw_time'] = $newDrawTime;

            if (in_array('prize_type', $columns)) {
                $duplicateData['prize_type'] = $original->prize_type ?? 'others';
            }

            if (in_array('image', $columns)) {
                $duplicateData['image'] = $original->getAttributes()['image'] ?? null;
            }

            if (in_array('end_date', $columns)) {
                $duplicateData['end_date'] = $newEndDate;
            }

            if (in_array('max_entries_per_user', $columns)) {
                $duplicateData['max_entries_per_user'] = $original->max_entries_per_user;
            }

            if (in_array('auto_draw', $columns)) {
                $duplicateData['auto_draw'] = $original->auto_draw ? 1 : 0;
            }

            if (in_array('allow_temp_entries', $columns)) {
                $duplicateData['allow_temp_entries'] = $original->allow_temp_entries ? 1 : 0;
            }

            if (in_array('position', $columns)) {
                $duplicateData['position'] = $maxPosition + 1;
            }

            if (in_array('winner_id', $columns)) {
                $duplicateData['winner_id'] = null;
            }

            if (in_array('required_entries', $columns)) {
                $duplicateData['required_entries'] = $original->required_entries ?? 1;
            }

            if (in_array('scheduled_at', $columns)) {
                $duplicateData['scheduled_at'] = $newDrawDate->format('Y-m-d') . ' ' . $newDrawTime;
            }

            if (in_array('winner_drawn_at', $columns)) {
                $duplicateData['winner_drawn_at'] = null;
            }

            if (in_array('winner_announced_at', $columns)) {
                $duplicateData['winner_announced_at'] = null;
            }

            if (in_array('animation_style', $columns)) {
                $duplicateData['animation_style'] = $original->animation_style ?? 'lottery_balls';
            }

            Log::info('DUPLICATE DATA PREPARED', ['data' => $duplicateData]);

            $duplicate = Giveaway::create($duplicateData);

            if ($original->entryPackages->isNotEmpty()) {
                $packageData = [];
                foreach ($original->entryPackages as $package) {
                    $packageData[$package->id] = [
                        'custom_price' => $package->pivot->custom_price,
                        'created_at' => now(),
                        'updated_at' => now()
                    ];
                }
                $duplicate->entryPackages()->attach($packageData);
            }

            DB::commit();

            Log::info('GIVEAWAY DUPLICATED', [
                'original_id' => $original->id,
                'duplicate_id' => $duplicate->id,
                'new_start_date' => $newStartDate->format('Y-m-d'),
                'new_draw_date' => $newDrawDate->format('Y-m-d'),
                'new_draw_time' => $newDrawTime
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Giveaway duplicated successfully',
                'duplicate_id' => $duplicate->id,
                'new_dates' => [
                    'start_date' => $newStartDate->format('Y-m-d'),
                    'draw_date' => $newDrawDate->format('Y-m-d'),
                    'draw_time' => $newDrawTime
                ]
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('GIVEAWAY DUPLICATION FAILED', [
                'original_id' => $id,
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to duplicate giveaway: ' . $e->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $giveaway = Giveaway::findOrFail($id);

            if ($giveaway->winner_id) {
                return response()->json([
                    'success' => false,
                    'message' => 'This giveaway has a winner, so it is kept as a record. Cancel it instead of deleting it.',
                ], 400);
            }
            if (in_array((string) $giveaway->status, ['1', 'active'], true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'This giveaway is live. Set it to cancelled first, then delete it.',
                ], 400);
            }

            Entry::where('giveaway_id', $giveaway->id)->delete();

            $giveaway->entryPackages()->detach();

            $giveaway->delete();

            return response()->json([
                'success' => true,
                'message' => 'Giveaway deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete giveaway', [
                'giveaway_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete giveaway: ' . $e->getMessage()
            ], 500);
        }
    }

    public function drawLive($id)
    {
        $giveaway = Giveaway::with('winner')->findOrFail($id);
        $replay = GiveawayDrawReplay::where('giveaway_id', $id)
            ->latest('drawn_at')
            ->first();

        if (!$replay) {
            return view('backend.giveaway-centre.draw-live-pending', compact('giveaway'));
        }

        return view('backend.giveaway-centre.draw-live', [
            'giveaway' => $giveaway,
            'replay' => $replay,
            'isAdmin' => true
        ]);
    }

    public function publicLatestDraw()
    {
        $replay = GiveawayDrawReplay::with(['giveaway', 'winner'])
            ->latest('drawn_at')
            ->first();

        if (!$replay) {
            return response()->json([
                'success' => false,
                'message' => 'No draws available yet'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'draw_id' => $replay->draw_id,
            'giveaway' => [
                'id' => $replay->giveaway->id,
                'prize_name' => $replay->giveaway->prize_name,
                'prize_value' => $replay->giveaway->prize_value,
                'image' => $replay->giveaway->image
            ],
            'winner' => [
                'name' => $replay->winner->name,
                'avatar' => $replay->winner->avatar ?? null
            ],
            'animation_data' => $replay->animation_data,
            'is_live' => $replay->isLive(),
            'drawn_at' => $replay->drawn_at->format('d/m/Y H:i'),
            'watch_url' => url('/public/draw/latest')
        ]);
    }

    public function publicDrawById($id)
    {
        $replay = GiveawayDrawReplay::with(['giveaway', 'winner'])
            ->where('giveaway_id', $id)
            ->orWhere('id', $id)
            ->latest('drawn_at')
            ->first();

        if (!$replay) {
            return response()->json([
                'success' => false,
                'message' => 'Draw not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'draw_id' => $replay->draw_id,
            'giveaway' => [
                'id' => $replay->giveaway->id,
                'prize_name' => $replay->giveaway->prize_name,
                'prize_value' => $replay->giveaway->prize_value,
                'image' => $replay->giveaway->image
            ],
            'winner' => [
                'name' => $replay->winner->name,
                'avatar' => $replay->winner->avatar ?? null
            ],
            'animation_data' => $replay->animation_data,
            'is_live' => $replay->isLive(),
            'drawn_at' => $replay->drawn_at->format('d/m/Y H:i')
        ]);
    }

    public function publicLatestDrawId()
    {
        $replay = GiveawayDrawReplay::latest('drawn_at')->first();

        if (!$replay) {
            return response()->json([
                'success' => false,
                'draw_id' => null
            ]);
        }

        return response()->json([
            'success' => true,
            'draw_id' => $replay->draw_id,
            'giveaway_id' => $replay->giveaway_id,
            'drawn_at' => $replay->drawn_at->toIso8601String()
        ]);
    }

    public function publicViewLatest()
    {
        $replay = GiveawayDrawReplay::with(['giveaway', 'winner'])
            ->latest('drawn_at')
            ->first();

        if (!$replay) {
            return view('public.draw-no-draws');
        }

        return view('public.draw-viewer', [
            'replay' => $replay,
            'isAdmin' => false
        ]);
    }

    public function publicViewDraw($id)
    {
        $replay = GiveawayDrawReplay::with(['giveaway', 'winner'])
            ->where('giveaway_id', $id)
            ->orWhere('id', $id)
            ->latest('drawn_at')
            ->first();

        if (!$replay) {
            return view('public.draw-not-found');
        }

        return view('public.draw-viewer', [
            'replay' => $replay,
            'isAdmin' => false
        ]);
    }

    public function show($id)
    {
        try {
            $giveaway = Giveaway::with(['entryPackages', 'winner'])->findOrFail($id);
            return view('backend.giveaway-centre.show', compact('giveaway'));
        } catch (\Exception $e) {
            return redirect()->route('giveaway-centre.index')->with('error', 'Giveaway not found.');
        }
    }

    public function updateStatus(Request $request, $id)
    {
        try {
            $giveaway = Giveaway::findOrFail($id);
            $giveaway->status = $request->status;
            $giveaway->save();

            return response()->json([
                'success' => true,
                'message' => 'Status updated successfully!',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }


    public function apiDashboardStats()
    {
        try {
            $permanentEntriesCount = Entry::where('giveaway_id', null)
                ->where('purchase_type', 'reward')
                ->where('drawn', false)
                ->distinct('user_id')
                ->count('user_id');

            $stats = [
                'total_giveaways' => Giveaway::count(),
                'active_giveaways' => Giveaway::where('status', Giveaway::STATUS_ACTIVE)->count(),
                'scheduled_giveaways' => Giveaway::where('status', Giveaway::STATUS_SCHEDULED)->count(),
                'completed_giveaways' => Giveaway::where('status', Giveaway::STATUS_COMPLETED)->count(),
                'entry_packages' => EntryPackage::count(),
                'users_with_permanent_entries' => $permanentEntriesCount,
            ];

            return $this->success($stats, 'Dashboard stats retrieved successfully.');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve stats: ' . $e->getMessage(), 500);
        }
    }


    public function apiNextGiveaway()
    {
        try {
            $giveaway = Giveaway::with(['activeEntryPackages' => function ($q) {
                $q->orderBy('entry_quantity', 'asc');
            }])
                ->where('status', Giveaway::STATUS_ACTIVE)
                ->whereNull('winner_id')
                ->orderBy('draw_date', 'asc')
                ->orderBy('draw_time', 'asc')
                ->first();

            if (!$giveaway) {
                return $this->success(null, 'No active giveaway found.');
            }

            $packages = $giveaway->activeEntryPackages->map(function ($package) {
                if ($package->pivot && $package->pivot->custom_price !== null) {
                    $package->price = (float)$package->pivot->custom_price;
                }
                return $package;
            });

            $giveaway->setRelation('entry_packages', $packages);
            $giveaway->unsetRelation('activeEntryPackages');

            return $this->success($this->appGiveawayPayload($giveaway), 'Giveaway retrieved successfully.');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve giveaway.', 500);
        }
    }


    public function apiShow($id)
    {
        try {
            $giveaway = Giveaway::with(['activeEntryPackages' => function ($q) {
                $q->orderBy('entry_quantity', 'asc');
            }])
                ->where('status', Giveaway::STATUS_ACTIVE)
                ->findOrFail($id);

            $packages = $giveaway->activeEntryPackages->map(function ($package) {
                if ($package->pivot && $package->pivot->custom_price !== null) {
                    $package->price = (float)$package->pivot->custom_price;
                }
                return $package;
            });

            $giveaway->setRelation('entry_packages', $packages);
            $giveaway->unsetRelation('activeEntryPackages');

            return $this->success($this->appGiveawayPayload($giveaway), 'Giveaway retrieved successfully.');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve giveaway or it is not active.', 404);
        }
    }


    /**
     * Giveaway payload for the mobile app with start/end/draw as plain UTC strings.
     * Assigning these back onto the model's date-cast attributes made Laravel serialise
     * them as midnight Perth -> previous day in UTC, so the app showed draws a day early.
     */
    private function appGiveawayPayload(Giveaway $giveaway): array
    {
        // Same shape the old (UTC) server sent: dates as ISO midnight UTC
        // ("2026-10-16T00:00:00.000000Z") plus the UTC time in draw_time/start_time.
        // The app reads them in exactly this format.
        $iso = fn ($c) => $c->copy()->utc()->startOfDay()->format('Y-m-d\TH:i:s.u\Z');
        $data = $giveaway->toArray();

        if ($giveaway->start_date) {
            $utc = $giveaway->start_date_utc;
            $data['start_date'] = $iso($utc);
            $data['start_time'] = $utc->toTimeString();
        }
        if ($giveaway->end_date) {
            $data['end_date'] = $iso($giveaway->end_date_utc);
        }
        if ($giveaway->draw_date) {
            $utc = $giveaway->draw_date_utc;
            $data['draw_date'] = $iso($utc);
            $data['draw_time'] = $utc->toTimeString();
        }

        return $data;
    }

    public function apiRecentWinners()
    {
        try {
            $winners = Giveaway::with(['winner.orders.address'])
                ->whereNotNull('winner_id')
                ->whereNotNull('winner_announced_at')
                ->orderBy('winner_announced_at', 'desc')
                ->limit(4)
                ->get()
                ->map(function ($giveaway) {
                    $drawDate = $giveaway->draw_date_utc;
                    
                    $winnerName = '-';
                    if ($giveaway->winner) {
                        $winnerName = $giveaway->winner->name ?? '-';
                        $latestOrder = $giveaway->winner->orders()->latest()->first();
                        if ($latestOrder && $latestOrder->address) {
                            $winnerName = $latestOrder->address->full_name ?? $winnerName;
                        }
                    }

                    return [
                        'winner_name' => $winnerName,
                        'prize_type' => $giveaway->prize_type,
                        'week' => $drawDate ? $drawDate->format('W') : '-',
                        'year' => $drawDate ? $drawDate->format('Y') : '-',
                        'date' => $drawDate ? $drawDate->format('Y-m-d') : '-',
                    ];
                });

            return $this->success($winners, 'Recent Winners retrieved successfully.');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve recent winners.', 500);
        }
    }


    public function apiMyPrizes(Request $request)
    {
        try {
            $user = $request->user();
            if (!$user) return $this->error('User not authenticated.', 401);

            $prizes = Prize::with(['giveaway', 'user'])
                ->where('user_id', $user->id)
                ->where('status', '!=', 'claimed')
                ->orderBy('won_date', 'desc')
                ->get()
                ->filter(function ($prize) use ($user) {
                    if (!$prize->giveaway_id) return true;

                    $alreadyClaimed = \App\Models\OrderItem::where('giveaway_id', $prize->giveaway_id)
                        ->whereHas('order', function ($query) use ($user) {
                            $query->where('user_id', $user->id)
                                ->whereIn('order_status', ['processing', 'completed', 'shipped', 'delivered']);
                        })
                        ->exists();

                    $inCart = \App\Models\Cart::where('user_id', $user->id)
                        ->where('giveaway_id', $prize->giveaway_id)
                        ->exists();

                    return !$alreadyClaimed && !$inCart;
                })
                ->values()
                ->map(function ($prize) {
                    return [
                        'prize_id' => $prize->id,
                        'giveaway_id' => $prize->giveaway_id,
                        'prize_name' => $prize->giveaway?->prize_name ?? '-',
                        'prize_type' => $prize->prize_type ?? '-',
                        'won_date' => $prize->won_date_utc ? $prize->won_date_utc->format('Y-m-d') : '-',
                        'description' => $prize->giveaway?->description ?? '-',
                    ];
                });

            return $this->success($prizes, 'Your prizes retrieved successfully.');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve your prizes.', 500);
        }
    }


    public function apiClaimPrize(Request $request, $giveawayId)
    {
        try {
            $user = $request->user();
            $giveaway = Giveaway::with('winner')->find($giveawayId);

            if (!$giveaway || $giveaway->winner_id !== $user->id) {
                $prize = Prize::where('id', $giveawayId)->where('user_id', $user->id)->first();
                if ($prize && $prize->giveaway) $giveaway = $prize->giveaway;
            }

            if (!$giveaway) return $this->error('Giveaway not found!', 404);
            if ($giveaway->winner_id !== $user->id) return $this->error('You are not the winner!', 403);
            if ($giveaway->draw_date->addDays(30) < now()) return $this->error('Claim period expired!', 400);

            $prize = Prize::where('giveaway_id', $giveaway->id)->where('user_id', $user->id)->first();
            if ($prize && $prize->status === 'claimed') return $this->error('Already claimed!', 400);

            $alreadyOrdered = \App\Models\OrderItem::where('giveaway_id', $giveaway->id)
                ->whereHas('order', function ($query) use ($user) {
                    $query->where('user_id', $user->id)
                        ->whereIn('order_status', ['processing', 'completed', 'shipped', 'delivered']);
                })->exists();

            if ($alreadyOrdered) {
                if ($prize) $prize->update(['status' => 'claimed']);
                return $this->error('Already ordered!', 400);
            }

            if (Cart::where('user_id', $user->id)->where('giveaway_id', $giveaway->id)->exists()) {
                return $this->error('Already in cart!', 400);
            }

            $cartItem = Cart::updateOrCreate(
                ['user_id' => $user->id, 'giveaway_id' => $giveaway->id],
                ['quantity' => 1, 'price' => $giveaway->prize_value, 'subtotal' => 0, 'product_id' => null]
            );

            return $this->success(['cart_item' => $cartItem], 'Prize claimed! Discount applied.');
        } catch (\Exception $e) {
            return $this->error('Failed to claim prize: ' . $e->getMessage(), 500);
        }
    }


    public function apiGetEntryPackages()
    {
        try {
            $packages = EntryPackage::where('status', 1)->orderBy('entry_quantity', 'asc')->get();
            return $this->success($packages, 'Entry packages retrieved successfully.');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve entry packages.', 500);
        }
    }


    public function getUpcoming(Request $request)
    {
        $request->merge(['type' => 'upcoming']);
        return $this->getGiveawaysData($request);
    }

    public function getDrawn(Request $request)
    {
        $request->merge(['type' => 'drawn']);
        return $this->getGiveawaysData($request);
    }

    public function drawWinner($id)
    {
        return $this->draw($id);
    }

    public function apiGetPermanentEntries(Request $request)
    {
        try {
            $query = User::where('is_guest', false);
            if ($request->filled('search')) {
                $query->where(function ($q) use ($request) {
                    $q->where('name', 'like', '%' . $request->search . '%')
                        ->orWhere('email', 'like', '%' . $request->search . '%');
                });
            }
            $users = $query->paginate($request->per_page ?? 10);
            $users->getCollection()->transform(function ($user) {
                $permanentEntries = Entry::where('user_id', $user->id)->where('giveaway_id', null)->where('purchase_type', 'reward')->sum('entry_count');
                $temporaryEntries = Entry::where('user_id', $user->id)->whereNotNull('giveaway_id')->where('drawn', false)->sum('entry_count');
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'permanent_entries' => (int)$permanentEntries,
                    'temporary_entries' => (int)$temporaryEntries,
                    'total_entries' => (int)$permanentEntries + (int)$temporaryEntries,
                    'history_count' => EntryHistory::where('user_id', $user->id)->count()
                ];
            });
            return $this->success($users, 'Permanent entries retrieved successfully.');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve permanent entries.', 500);
        }
    }


    public function apiUpdatePermanentEntries(Request $request, $userId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'action' => 'required|in:add,set,deduct',
                'quantity' => 'required|integer|min:0',
                'reason' => 'nullable|string|max:255'
            ]);

            if ($validator->fails()) return $this->error('Validation Error', 422, $validator->errors());

            DB::beginTransaction();
            $user = User::findOrFail($userId);
            $action = $request->action;
            $quantity = (int)$request->quantity;

            $currentEntry = Entry::where('user_id', $userId)->where('giveaway_id', null)->where('purchase_type', 'reward')->first();
            $oldQuantity = $currentEntry ? $currentEntry->entry_count : 0;
            $newQuantity = match ($action) {
                'set' => $quantity,
                'add' => $oldQuantity + $quantity,
                'deduct' => max(0, $oldQuantity - $quantity),
            };

            Entry::updateOrCreate(
                ['user_id' => $userId, 'giveaway_id' => null, 'purchase_type' => 'reward'],
                ['entry_count' => $newQuantity]
            );

            EntryHistory::create([
                'user_id' => $userId,
                'admin_id' => auth()->id(),
                'action' => ($action === 'deduct' ? 'deduct' : 'add'),
                'quantity' => $action === 'set' ? abs($newQuantity - $oldQuantity) : $quantity,
                'reason' => $request->reason ?? 'Admin action via API (' . $action . ')',
            ]);

            DB::commit();
            return $this->success(['new_total' => $newQuantity], 'Permanent entries updated successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->error('Failed to update entries.', 500);
        }
    }


    public function apiGetUserHistory($userId)
    {
        try {
            $user = User::findOrFail($userId);
            $historyItems = [];

            $entries = Entry::where('user_id', $userId)->with('giveaway')->orderBy('created_at', 'desc')->get();
            foreach ($entries as $entry) {
                $giveawayName = ($entry->giveaway_id && $entry->giveaway) ? $entry->giveaway->prize_name : 'Global Reward Entries';
                $type = match ($entry->purchase_type) {
                    'reward' => 'Admin Reward',
                    'discount' => 'Discount',
                    'firewood' => 'Firewood Purchase',
                    'paid' => 'Paid Entry',
                    default => 'Unknown'
                };
                $status = (isset($entry->drawn) && $entry->drawn) ? 'Used' : ($entry->giveaway_id === null ? 'Permanent' : 'Active');

                $historyItems[] = [
                    'giveaway_name' => $giveawayName,
                    'entry_count' => (int)$entry->entry_count,
                    'type' => $type,
                    'status' => $status,
                    'reason' => '-',
                    'admin' => '-',
                    'created_at' => $entry->created_at->format('Y-m-d H:i'),
                    'timestamp' => $entry->created_at->timestamp,
                ];
            }

            $adminHistory = EntryHistory::where('user_id', $userId)->with(['admin', 'giveaway'])->orderBy('created_at', 'desc')->get();
            foreach ($adminHistory as $history) {
                $giveawayName = $history->giveaway_id ? ($history->giveaway ? $history->giveaway->prize_name : 'Giveaway #' . $history->giveaway_id) : 'Global Entries';
                $historyItems[] = [
                    'giveaway_name' => $giveawayName,
                    'entry_count' => ($history->action === 'add' ? '+' : '-') . $history->quantity,
                    'type' => $history->action === 'add' ? 'Admin Added' : 'Admin Removed',
                    'status' => 'Admin Action',
                    'reason' => $history->reason ?? '-',
                    'admin' => $history->admin ? $history->admin->name : 'System',
                    'created_at' => $history->created_at->format('Y-m-d H:i'),
                    'timestamp' => $history->created_at->timestamp,
                ];
            }

            usort($historyItems, fn($a, $b) => $b['timestamp'] - $a['timestamp']);
            foreach ($historyItems as &$item) unset($item['timestamp']);

            return $this->success(['user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email], 'entries' => $historyItems], 'History retrieved successfully.');
        } catch (\Exception $e) {
            return $this->error('Failed to get history.', 500);
        }
    }
}
