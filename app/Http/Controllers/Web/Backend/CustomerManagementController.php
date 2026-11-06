<?php

namespace App\Http\Controllers\Web\Backend;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Order;
use App\Models\Entry;
use App\Models\ShippingAddress;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\CustomersManagementExport;
use App\Helpers\FirebaseHelper;
use App\Services\NotificationService;
use App\Mail\ManualNotificationMail;
use Illuminate\Support\Facades\Mail;

class CustomerManagementController extends Controller
{

    public function index(Request $request)
    {
        $query = User::query()
            ->where('is_guest', 0)
            ->with('shippingAddresses')
            ->withCount(['orders'])
            // ============================================================
            // Three distinct entry aggregates for this listing:
            //
            // 1. permanent_entries_sum — reward-type entries (always active,
            //    never drawn). From $200+ orders or admin gifts.
            //
            // 2. active_temp_entries_sum — paid/free entries that haven't
            //    been drawn yet (i.e. live in the current week's draw).
            //
            // 3. lifetime_temp_entries_sum — every paid/free entry EVER,
            //    including drawn historical ones. This is the "lifetime
            //    purchase" view — useful for spotting loyal buyers.
            // ============================================================
            ->withSum(['entries as permanent_entries_sum' => function ($q) {
                $q->where('purchase_type', 'reward');
            }], 'entry_count')
            ->withSum(['entries as active_temp_entries_sum' => function ($q) {
                $q->where('purchase_type', '!=', 'reward')
                  ->where('drawn', false);
            }], 'entry_count')
            ->withSum(['entries as lifetime_temp_entries_sum' => function ($q) {
                $q->where('purchase_type', '!=', 'reward');
            }], 'entry_count')
            ->withSum('orders as total_spent', 'total');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->filled('status')) {
            // New: filter by reachability state (Reachable / Cooling / Stale / No App).
            // Backwards compat: keep 'active'/'inactive' for any saved links.
            $cooling = now()->subDays(\App\Services\ReachabilityService::COOLING_THRESHOLD_DAYS);
            $stale   = now()->subDays(\App\Services\ReachabilityService::STALE_THRESHOLD_DAYS);

            switch ($request->status) {
                case 'reachable':
                    // Has token AND validated recently
                    $query->whereNotNull('fcm_token')
                          ->where('fcm_token_validated_at', '>=', $cooling);
                    break;
                case 'cooling':
                    // Has token, but not recently validated (or never)
                    $query->whereNotNull('fcm_token')
                          ->where(function ($q) use ($cooling, $stale) {
                              $q->whereBetween('fcm_token_validated_at', [$stale, $cooling])
                                ->orWhere(function ($q2) use ($stale) {
                                    $q2->whereNull('fcm_token_validated_at')
                                       ->where('updated_at', '>=', $stale);
                                });
                          });
                    break;
                case 'stale':
                    // Has token, but very old or never validated and account is old
                    $query->whereNotNull('fcm_token')
                          ->where(function ($q) use ($stale) {
                              $q->where('fcm_token_validated_at', '<', $stale)
                                ->orWhere(function ($q2) use ($stale) {
                                    $q2->whereNull('fcm_token_validated_at')
                                       ->where('updated_at', '<', $stale);
                                });
                          });
                    break;
                case 'no_app':
                    $query->whereNull('fcm_token');
                    break;
                // Backwards compat
                case 'active':
                    $query->whereNotNull('fcm_token');
                    break;
                case 'inactive':
                    $query->whereNull('fcm_token');
                    break;
            }
        }

        if ($request->filled('city')) {
            $query->whereHas('shippingAddresses', function ($q) use ($request) {
                $q->where('city', $request->city);
            });
        }

        if ($request->filled('state')) {
            $query->whereHas('shippingAddresses', function ($q) use ($request) {
                $q->where('state', $request->state);
            });
        }

        // ============================================================
        // Column sorting (click-to-sort with asc/desc/none cycle).
        //
        // Default: most-recently-joined first.
        // Whitelisted columns to prevent SQL injection. Special handling for
        // computed columns (orders_count, total_spent, active_entries_sum) which
        // are aliased aggregates added by withCount/withSum above.
        // ============================================================
        $sortable = [
            'id'                          => 'users.id',
            'name'                        => 'users.name',
            'email'                       => 'users.email',
            'role'                        => 'users.role',
            'status'                      => 'STATUS_RANK',         // special — see below
            'orders_count'                => 'orders_count',
            'total_spent'                 => 'total_spent',
            'permanent_entries_sum'       => 'permanent_entries_sum',
            'active_temp_entries_sum'     => 'active_temp_entries_sum',
            'lifetime_temp_entries_sum'   => 'lifetime_temp_entries_sum',
            'joined'                      => 'users.created_at',
        ];

        $sort = $request->get('sort');
        $direction = strtolower($request->get('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        if ($sort && isset($sortable[$sort])) {
            $column = $sortable[$sort];

            // SPECIAL: status sort needs a computed rank — Reachable=1, Cooling=2,
            // Stale=3, No App=4. Mirrors the classify() logic in PHP but in SQL
            // so we can sort at the database level (otherwise we'd be sorting
            // 1000 users in PHP after the fact).
            if ($sort === 'status') {
                $cooling = now()->subDays(\App\Services\ReachabilityService::COOLING_THRESHOLD_DAYS)->toDateTimeString();
                $stale   = now()->subDays(\App\Services\ReachabilityService::STALE_THRESHOLD_DAYS)->toDateTimeString();
                $statusRank = "
                    CASE
                        WHEN users.fcm_token IS NULL OR users.fcm_token = '' THEN 4
                        WHEN users.fcm_token_validated_at >= ? THEN 1
                        WHEN users.fcm_token_validated_at >= ? THEN 2
                        WHEN users.fcm_token_validated_at IS NULL AND users.updated_at >= ? THEN 2
                        ELSE 3
                    END
                ";
                $query->orderByRaw($statusRank . ' ' . $direction, [$cooling, $stale, $stale])
                      ->orderBy('users.id', 'desc');
            } else {
                $isAggregate = in_array($sort, [
                    'orders_count', 'total_spent',
                    'permanent_entries_sum', 'active_temp_entries_sum', 'lifetime_temp_entries_sum',
                ], true);
                if ($isAggregate) {
                    $query->orderByRaw("({$column} IS NULL) ASC")
                          ->orderBy($column, $direction)
                          ->orderBy('users.id', 'desc');
                } else {
                    $query->orderBy($column, $direction)
                          ->orderBy('users.id', 'desc');
                }
            }
        } else {
            $query->latest('users.created_at');
        }

        $customers = $query->paginate(50)->appends($request->query());

        // Classify reachability state for each customer (uses local data only,
        // no Firebase calls — fast). Attached as a virtual attribute so the
        // view can render the new badge without controller-view coupling.
        $reachability = app(\App\Services\ReachabilityService::class);
        $customers->getCollection()->transform(function ($c) use ($reachability) {
            $c->reachability_state = $reachability->classify($c);
            return $c;
        });

        $cities = ShippingAddress::distinct()->pluck('city')->filter()->sort()->values();
        $states = ShippingAddress::distinct()->pluck('state')->filter()->sort()->values();

        return view('backend.customers.index', compact('customers', 'cities', 'states'));
    }

    public function create()
    {
        return view('backend.customers.create');
    }


    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:admin,customer,business',
            'is_admin' => 'boolean',

            'address' => 'nullable|string',
            'suburb' => 'nullable|string',
            'city' => 'nullable|string',
            'state' => 'nullable|string',
            'zip_code' => 'nullable|string',
        ]);

        DB::beginTransaction();
        try {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'password' => Hash::make($validated['password']),
                'role' => $validated['role'],
                'is_admin' => $validated['role'] === 'admin' ? 1 : 0,
                'is_guest' => 0,
            ]);

            if ($request->filled('address')) {
                ShippingAddress::create([
                    'user_id' => $user->id,
                    'label' => 'Default',
                    'full_name' => $validated['name'],
                    'phone' => $validated['phone'] ?? '',
                    'email' => $validated['email'],
                    'address' => $validated['address'],
                    'suburb' => $validated['suburb'] ?? '',
                    'city' => $validated['city'] ?? '',
                    'state' => $validated['state'] ?? '',
                    'country' => 'Australia',
                    'zip_code' => $validated['zip_code'] ?? '',
                    'is_default' => 1,
                ]);
            }

            DB::commit();

            return redirect()->route('customer-management.index')
                ->with('success', 'Customer created successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()
                ->with('error', 'Failed to create customer: ' . $e->getMessage());
        }
    }


    public function show($id)
    {
        $customer = User::with(['shippingAddresses', 'orders.items.product'])
            ->withCount(['orders'])
            ->withSum('orders as total_spent', 'total')
            ->findOrFail($id);

        $stats = [
            'total_orders' => $customer->orders_count,
            'total_spent' => $customer->total_spent ?? 0,
            'total_entries' => Entry::where('user_id', $id)->sum('entry_count'),
            'last_order' => $customer->orders()->latest()->first(),
            'account_age_days' => $customer->created_at->diffInDays(now()),
            'is_active' => ($customer->fcm_token || $customer->device_token) && $customer->updated_at >= now()->subDays(30),
        ];

        $entries = Entry::where('user_id', $id)
            ->with('giveaway')
            ->orderBy('created_at', 'desc')
            ->get();

        // ============================================================
        // Breakdowns (matches how customers see entries on the app):
        //   - Permanent entries = reward type. Always active across all giveaways.
        //   - Active purchase entries = paid/free/discount and not yet drawn.
        //   - Used purchase entries = paid/free/discount that have been drawn.
        // Reward entries don't have a drawn concept — they're permanent.
        // ============================================================
        $permanentEntries = $entries
            ->where('purchase_type', 'reward')
            ->sum('entry_count');

        $activePurchaseEntries = $entries
            ->where('purchase_type', '!=', 'reward')
            ->where('drawn', false)
            ->sum('entry_count');

        $usedPurchaseEntries = $entries
            ->where('purchase_type', '!=', 'reward')
            ->where('drawn', true)
            ->sum('entry_count');

        // Active total = what the customer actually sees as their entry count today
        $activeEntries = $permanentEntries + $activePurchaseEntries;
        $totalEntries = $entries->sum('entry_count'); // legacy — all entries ever

        // Kept for legacy use elsewhere in the partial
        $rewardEntries = $permanentEntries;
        $purchaseEntries = $activePurchaseEntries; // now means ACTIVE purchase, not all

        $entryHistory = \App\Models\EntryHistory::where('user_id', $id)
            ->with('admin')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('backend.customers.show', compact(
            'customer', 'stats', 'entries',
            'totalEntries', 'activeEntries',
            'permanentEntries', 'activePurchaseEntries', 'usedPurchaseEntries',
            'rewardEntries', 'purchaseEntries',
            'entryHistory'
        ));
    }


    public function edit($id)
    {
        $customer = User::with('shippingAddresses')
            ->withCount('orders')
            ->findOrFail($id);

        $entries = Entry::where('user_id', $id)
            ->with('giveaway')
            ->orderBy('created_at', 'desc')
            ->get();

        $permanentEntries = $entries->where('purchase_type', 'reward')->sum('entry_count');
        $activePurchaseEntries = $entries->where('purchase_type', '!=', 'reward')->where('drawn', false)->sum('entry_count');
        $usedPurchaseEntries = $entries->where('purchase_type', '!=', 'reward')->where('drawn', true)->sum('entry_count');
        $activeEntries = $permanentEntries + $activePurchaseEntries;
        $totalEntries = $entries->sum('entry_count');
        $rewardEntries = $permanentEntries;
        $purchaseEntries = $activePurchaseEntries;

        $entryHistory = \App\Models\EntryHistory::where('user_id', $id)
            ->with('admin')
            ->orderBy('created_at', 'desc')
            ->get();

        return view('backend.customers.edit', compact(
            'customer', 'entries',
            'totalEntries', 'activeEntries',
            'permanentEntries', 'activePurchaseEntries', 'usedPurchaseEntries',
            'rewardEntries', 'purchaseEntries',
            'entryHistory'
        ));
    }


    public function update(Request $request, $id)
    {
        $customer = User::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $id,
            'phone' => 'nullable|string|max:20',
            'password' => 'nullable|string|min:8|confirmed',
            'role' => 'required|in:admin,customer,business',
        ]);

        DB::beginTransaction();
        try {
            $customer->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone' => $validated['phone'] ?? null,
                'role' => $validated['role'],
                'is_admin' => $validated['role'] === 'admin' ? 1 : 0,
            ]);

            if ($request->filled('password')) {
                $customer->update(['password' => Hash::make($validated['password'])]);
            }

            DB::commit();

            return redirect()->route('customer-management.show', $customer->id)
                ->with('success', 'Customer updated successfully!');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withInput()
                ->with('error', 'Failed to update customer: ' . $e->getMessage());
        }
    }


    public function destroy($id)
    {
        try {
            $customer = User::findOrFail($id);

            if ($customer->is_admin && $customer->id === auth()->id()) {
                return back()->with('error', 'Cannot delete your own admin account!');
            }

            $customer->delete();

            return redirect()->route('customer-management.index')
                ->with('success', 'Customer deleted successfully!');
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to delete customer: ' . $e->getMessage());
        }
    }


    public function bulkDelete(Request $request)
    {
        $request->validate([
            'customer_ids' => 'required|array',
            'customer_ids.*' => 'exists:users,id',
        ]);

        try {
            $count = User::whereIn('id', $request->customer_ids)
                ->where('id', '!=', auth()->id())
                ->delete();

            return back()->with('success', "{$count} customer(s) deleted successfully!");
        } catch (\Exception $e) {
            return back()->with('error', 'Failed to delete customers: ' . $e->getMessage());
        }
    }


    public function export(Request $request)
    {
        return Excel::download(new CustomersManagementExport($request->all()), 'customers_' . date('Y-m-d') . '.xlsx');
    }


    public function manageEntries(Request $request, $id)
    {
        $validated = $request->validate([
            'action' => 'required|in:add,remove',
            'quantity' => 'required|integer|min:1|max:1000',
            'reason' => 'nullable|string|max:255',
        ]);

        DB::beginTransaction();
        try {
            $customer = User::findOrFail($id);

            if ($validated['action'] === 'add') {
                $entry = Entry::where('user_id', $id)
                    ->where('giveaway_id', null)
                    ->where('purchase_type', 'reward')
                    ->first();

                if ($entry) {
                    $entry->increment('entry_count', $validated['quantity']);
                    $entry->refresh();
                } else {
                    $entry = Entry::create([
                        'user_id' => $id,
                        'giveaway_id' => null,
                        'purchase_type' => 'reward',
                        'entry_count' => $validated['quantity'],
                        'amount_paid' => 0,
                    ]);
                }

                $message = "Added {$validated['quantity']} reward entries for {$customer->name}";
            } else {
                $remainingToRemove = $validated['quantity'];

                $rewardEntries = Entry::where('user_id', $id)
                    ->where('purchase_type', 'reward')
                    ->orderBy('created_at', 'desc')
                    ->get();

                foreach ($rewardEntries as $entry) {
                    if ($remainingToRemove <= 0) break;

                    $canRemove = min($entry->entry_count, $remainingToRemove);
                    $newCount = $entry->entry_count - $canRemove;

                    if ($newCount <= 0) {
                        $entry->delete();
                    } else {
                        $entry->update(['entry_count' => $newCount]);
                    }

                    $remainingToRemove -= $canRemove;
                }

                if ($remainingToRemove > 0) {
                    $paidEntries = Entry::where('user_id', $id)
                        ->where('purchase_type', '!=', 'reward')
                        ->orderBy('created_at', 'asc')
                        ->get();

                    foreach ($paidEntries as $entry) {
                        if ($remainingToRemove <= 0) break;

                        $canRemove = min($entry->entry_count, $remainingToRemove);
                        $newCount = $entry->entry_count - $canRemove;

                        if ($newCount <= 0) {
                            $entry->delete();
                        } else {
                            $entry->update(['entry_count' => $newCount]);
                        }

                        $remainingToRemove -= $canRemove;
                    }
                }

                if ($remainingToRemove > 0) {
                    DB::rollBack();
                    return back()->with('error', "Customer only has " . ($validated['quantity'] - $remainingToRemove) . " entries. Cannot remove {$validated['quantity']}.");
                }

                $message = "Removed {$validated['quantity']} entries from {$customer->name}";
            }

            \App\Models\EntryHistory::create([
                'user_id' => $id,
                'giveaway_id' => null,
                'admin_user_id' => auth()->id(),
                'action' => $validated['action'],
                'quantity' => $validated['quantity'],
                'source' => 'admin',
                'reason' => $validated['reason'],
            ]);

            DB::commit();

            \Log::info($message, [
                'admin_id' => auth()->id(),
                'customer_id' => $id,
                'action' => $validated['action'],
                'quantity' => $validated['quantity'],
            ]);

            return back()->with('success', $message);
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Entry management failed', [
                'customer_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return back()->with('error', 'Failed to manage entries: ' . $e->getMessage());
        }
    }


    public function sendNotification(Request $request, $id)
    {
        $customer = User::findOrFail($id);

        $validated = $request->validate([
            'title' => 'nullable|string|max:100',
            'message' => 'required|string|max:500',
            'channel' => 'required|in:push,email,sms,all',
        ]);

        if (in_array($validated['channel'], ['push', 'email', 'all']) && empty($validated['title'])) {
            return back()->with('error', 'Title is required for push and email notifications.')->withInput();
        }

        if (empty($validated['title'])) {
            $validated['title'] = "JJ's Firewood Perth";
        }

        $results = [];
        $errors = [];

        $sendPush = in_array($validated['channel'], ['push', 'all']);
        $sendEmail = in_array($validated['channel'], ['email', 'all']);
        $sendSms = in_array($validated['channel'], ['sms', 'all']);

        \Log::info('Notification request', [
            'customer_id' => $id,
            'channel_requested' => $validated['channel'],
            'will_send_push' => $sendPush,
            'will_send_email' => $sendEmail,
            'will_send_sms' => $sendSms,
            'has_fcm' => !empty($customer->fcm_token),
            'has_email' => !empty($customer->email),
            'has_phone' => !empty($customer->phone),
        ]);

        try {
            NotificationService::send($customer, 'info', $validated['title'], $validated['message']);
        } catch (\Exception $e) {
            \Log::error('In-app notification failed', ['error' => $e->getMessage()]);
        }

        if ($sendPush && $customer->fcm_token) {
            try {
                $this->sendFirebasePush($customer, $validated);
                $results[] = 'Push sent';
                \Log::info('Push notification sent', ['customer_id' => $id]);
            } catch (\Exception $e) {
                $errors[] = 'Push: ' . $e->getMessage();
                \Log::error('Push notification failed', [
                    'customer_id' => $id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        if ($sendEmail && $customer->email) {
            try {
                $this->sendEmailNotification($customer, $validated);
                $results[] = 'Email sent';
                \Log::info('Email sent', ['customer_id' => $id, 'email' => $customer->email]);
            } catch (\Exception $e) {
                $errors[] = 'Email: ' . $e->getMessage();
                \Log::error('Email failed', [
                    'customer_id' => $id,
                    'email' => $customer->email,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        if ($sendSms && $customer->phone) {
            try {
                $this->sendBrevoSms($customer, $validated);
                $results[] = 'SMS sent';
                \Log::info('SMS sent', ['customer_id' => $id, 'phone' => $customer->phone]);
            } catch (\Exception $e) {
                $errors[] = 'SMS: ' . $e->getMessage();
                \Log::error('SMS failed', [
                    'customer_id' => $id,
                    'phone' => $customer->phone,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }

        if (count($results) > 0) {
            $message = '✓ ' . implode(', ', $results);
            if (count($errors) > 0) {
                $message .= ' | ⚠ ' . implode('; ', $errors);
            }
            return back()->with('success', $message);
        }

        return back()->with('error', '✗ All channels failed: ' . implode('; ', $errors));
    }


    private function sendFirebasePush($customer, $data)
    {
        if (empty($customer->fcm_token)) {
            throw new \Exception('Customer has no FCM token');
        }

        $result = FirebaseHelper::sendNotification(
            $customer->fcm_token,
            $data['title'],
            $data['message']
        );

        if ($result === null) {
            throw new \Exception('FirebaseHelper returned null - check logs for details');
        }

        return true;
    }


    private function sendEmailNotification($customer, $data)
    {
        Mail::to($customer->email)->send(
            new ManualNotificationMail($data['title'], $data['message'])
        );

        return true;
    }


    private function sendBrevoSms($customer, $data)
    {
        $brevoKey = config('services.brevo.api_key') ?: env('BREVO_API_KEY');
        if (!$brevoKey) {
            throw new \Exception('Brevo API key not configured');
        }

        $message = $data['message'];
        if (!empty($data['title']) && $data['title'] !== "JJ's Firewood Perth") {
            $message = $data['title'] . "\n\n" . $message;
        }

        $phone = $customer->phone;

        if (!str_starts_with($phone, '+')) {
            $phone = ltrim($phone, '0');
            $phone = '+61' . $phone;
        }

        $sender = config('services.brevo.sms_sender', 'JJSFIREWOOD');

        $smsData = [
            'sender' => $sender,
            'recipient' => $phone,
            'content' => $message,
            'type' => 'transactional',
        ];

        $ch = curl_init('https://api.brevo.com/v3/transactionalSMS/sms');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'api-key: ' . $brevoKey,
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($smsData));

        $result = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($code !== 201) {
            $error = json_decode($result, true);
            $errorMsg = $error['message'] ?? $result;
            throw new \Exception("HTTP {$code}: {$errorMsg}");
        }

        \Log::info('SMS sent successfully', ['phone' => $phone, 'customer' => $customer->name]);

        return true;
    }

    /**
     * Send a silent FCM ping to a customer to test whether their device still
     * receives push notifications. Updates fcm_token_validated_at on success,
     * clears the dead fcm_token on failure.
     */
    public function testReachability($id, \App\Services\ReachabilityService $reachability)
    {
        $customer = \App\Models\User::findOrFail($id);
        $result = $reachability->ping($customer);

        // After a ping, fetch fresh state so the UI can update the badge inline
        $customer->refresh();
        $newState = $reachability->classify($customer);

        return response()->json([
            'ok'              => in_array($result['status'], ['reachable', 'unreachable', 'no_token'], true),
            'status'          => $result['status'],
            'detail'          => $result['detail'],
            'badge_state'     => $newState,
            'badge_label'     => $this->reachabilityLabel($newState),
            'has_token'       => !empty($customer->fcm_token),
            'last_validated'  => $customer->fcm_token_validated_at?->diffForHumans(),
        ]);
    }

    /**
     * Return a human-friendly label for a reachability state. Used by the
     * JSON endpoint above so the frontend doesn't need to duplicate logic.
     */
    private function reachabilityLabel(string $state): string
    {
        return match ($state) {
            'reachable' => 'Reachable',
            'cooling'   => 'Cooling',
            'stale'     => 'Stale',
            'no_app'    => 'No App',
            default     => ucfirst($state),
        };
    }

    /**
     * Bulk reachability — Step 1: return the list of user IDs to ping.
     * Fast call, no pings, no timeouts. Frontend then pages through these
     * IDs in batches via bulkTestReachabilityBatch().
     */
    public function bulkTestReachabilityCandidates(Request $request)
    {
        $scope = $request->input('scope', 'all_with_token');

        $query = \App\Models\User::query()->whereNotNull('fcm_token');

        if ($scope === 'stale_only') {
            // Only users whose token hasn't been validated in COOLING_THRESHOLD_DAYS
            $cutoff = now()->subDays(\App\Services\ReachabilityService::COOLING_THRESHOLD_DAYS);
            $query->where(function ($q) use ($cutoff) {
                $q->whereNull('fcm_token_validated_at')
                  ->orWhere('fcm_token_validated_at', '<', $cutoff);
            });
        }
        // scope === 'all_with_token' → just whereNotNull above

        $ids = $query->pluck('id')->all();

        return response()->json([
            'ok'    => true,
            'ids'   => $ids,
            'total' => count($ids),
            'scope' => $scope,
        ]);
    }

    /**
     * Bulk reachability — Step 2: ping one batch of users.
     * Returns counts so the frontend can aggregate. Idempotent — same batch
     * can be retried if needed.
     */
    public function bulkTestReachabilityBatch(Request $request, \App\Services\ReachabilityService $reachability)
    {
        $request->validate([
            'ids'   => 'required|array|max:50',
            'ids.*' => 'integer',
        ]);

        // Cap per-batch execution. 50 users × ~1 second of Firebase per ping ≈ 50s.
        // Set a generous timeout in case Firebase is slow.
        set_time_limit(120);

        $ids = $request->input('ids');
        $stats = [
            'reachable'   => 0,
            'unreachable' => 0,
            'no_token'    => 0,
            'error'       => 0,
        ];

        $users = \App\Models\User::whereIn('id', $ids)->whereNotNull('fcm_token')->get();
        foreach ($users as $user) {
            $result = $reachability->ping($user);
            $stats[$result['status']] = ($stats[$result['status']] ?? 0) + 1;
        }

        return response()->json([
            'ok'        => true,
            'processed' => count($users),
            'stats'     => $stats,
        ]);
    }

    /**
     * Legacy single-call bulk endpoint — kept for any external callers.
     * Internally redirects to the new batched approach by fetching candidates
     * and looping. For UI use, prefer the new batched approach.
     */
    public function bulkTestReachability(Request $request, \App\Services\ReachabilityService $reachability)
    {
        $scope = $request->input('scope', 'stale');
        $request->merge(['scope' => $scope === 'stale' ? 'stale_only' : 'all_with_token']);
        $candidates = $this->bulkTestReachabilityCandidates($request);
        $candidatesData = json_decode($candidates->getContent(), true);
        $ids = $candidatesData['ids'] ?? [];

        $cap = 1000;
        if (count($ids) > $cap) {
            return response()->json([
                'ok'      => false,
                'message' => "Too many users to validate via this endpoint (" . count($ids) . " found, limit {$cap}). Use the batched UI or run: php artisan tokens:validate --all",
                'count'   => count($ids),
            ], 413);
        }

        $start = microtime(true);
        $stats = ['total' => 0, 'reachable' => 0, 'unreachable' => 0, 'no_token' => 0, 'error' => 0];
        set_time_limit(300);
        foreach (array_chunk($ids, 50) as $batch) {
            $users = \App\Models\User::whereIn('id', $batch)->whereNotNull('fcm_token')->get();
            foreach ($users as $user) {
                $stats['total']++;
                $result = $reachability->ping($user);
                $stats[$result['status']] = ($stats[$result['status']] ?? 0) + 1;
            }
        }
        $elapsed = round(microtime(true) - $start, 1);

        return response()->json([
            'ok'        => true,
            'stats'     => $stats,
            'elapsed_s' => $elapsed,
            'message'   => "Pinged {$stats['total']} users in {$elapsed}s.",
        ]);
    }
}
