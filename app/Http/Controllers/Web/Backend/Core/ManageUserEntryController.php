<?php

namespace App\Http\Controllers\Web\Backend\Core;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Entry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ManageUserEntryController extends Controller
{
    /**
     * Render the management page.
     */
    public function index()
    {
        return view('backend.layouts.manage_user_entry.index');
    }

    /**
     * Return all users with their permanent + temporary + total entry counts.
     * Returns plain JSON (not server-side DataTables) — the front-end handles
     * search, sort, and pagination on the client for fluid UX.
     */
    public function getData(Request $request)
    {
        try {
            $query = User::query()
                ->where('is_guest', 0)
                ->where('is_admin', 0)
                ->select('id', 'name', 'email');

            if ($request->filled('search')) {
                $term = $request->input('search');
                $query->where(function ($q) use ($term) {
                    $q->where('name', 'like', "%{$term}%")
                      ->orWhere('email', 'like', "%{$term}%");
                });
            }

            $hasDrawnColumn = Schema::hasColumn('entries', 'drawn');

            // Pre-compute aggregates in two grouped queries for performance
            // instead of N+1.
            $userIds = $query->pluck('id');

            // Permanent entries (giveaway_id is null AND purchase_type='reward')
            $permanentRows = Entry::whereIn('user_id', $userIds)
                ->whereNull('giveaway_id')
                ->where('purchase_type', 'reward')
                ->get(['user_id', 'entry_count', 'updated_at'])
                ->keyBy('user_id');

            // Temporary entries — only undrawn ones still count toward the user's
            // active balance. (Drawn rows represent consumed entries.)
            $temporaryQuery = Entry::whereIn('user_id', $userIds)
                ->whereNotNull('giveaway_id');

            if ($hasDrawnColumn) {
                $temporaryQuery->where(function ($q) {
                    $q->where('drawn', false)->orWhereNull('drawn');
                });
            }

            $temporarySums = $temporaryQuery
                ->select('user_id', DB::raw('SUM(entry_count) as total'))
                ->groupBy('user_id')
                ->pluck('total', 'user_id');

            // Build the response collection
            $users = $query->get()->map(function ($user) use ($permanentRows, $temporarySums) {
                $permEntry = $permanentRows->get($user->id);
                $perm = $permEntry ? (int) $permEntry->entry_count : 0;
                $temp = (int) ($temporarySums[$user->id] ?? 0);

                return [
                    'user_id'            => $user->id,
                    'user_name'          => $user->name,
                    'user_email'         => $user->email,
                    'permanent_entries'  => $perm,
                    'temporary_entries'  => $temp,
                    'total_entries'      => $perm + $temp,
                    'last_updated_at'    => $permEntry && $permEntry->updated_at
                        ? $permEntry->updated_at->format('M d, Y H:i')
                        : 'Never',
                ];
            });

            // Optional "only users with entries" filter
            if ($request->filled('has_entries') && $request->input('has_entries') === '1') {
                $users = $users->filter(fn ($u) => $u['total_entries'] > 0)->values();
            }

            return response()->json(['data' => $users->values()]);
        } catch (\Throwable $e) {
            Log::error('ManageUserEntryController@getData failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'data'    => [],
                'message' => 'Failed to fetch user entries',
            ], 500);
        }
    }

    /**
     * Update permanent entries for a user.
     * Supports actions: set | add | deduct.
     */
    public function update(Request $request, $userId)
    {
        $validated = $request->validate([
            'action'   => 'required|in:set,add,deduct',
            'amount'   => 'required|integer|min:0',
            'reason'   => 'nullable|string|max:500',
        ]);

        // For add/deduct, amount must be at least 1
        if (in_array($validated['action'], ['add', 'deduct']) && $validated['amount'] < 1) {
            return response()->json([
                'success' => false,
                'message' => 'Amount must be at least 1 for add/deduct actions',
            ], 422);
        }

        DB::beginTransaction();
        try {
            $user = User::findOrFail($userId);

            $entry = Entry::where('user_id', $userId)
                ->whereNull('giveaway_id')
                ->where('purchase_type', 'reward')
                ->lockForUpdate()
                ->first();

            $oldCount = $entry ? (int) $entry->entry_count : 0;
            $hasDrawnColumn = Schema::hasColumn('entries', 'drawn');

            $message = '';

            switch ($validated['action']) {
                case 'set':
                    $newCount = (int) $validated['amount'];
                    if ($entry) {
                        if ($newCount === 0) {
                            $entry->delete();
                        } else {
                            $entry->update(['entry_count' => $newCount]);
                        }
                    } elseif ($newCount > 0) {
                        $entry = $this->createPermanentEntry($userId, $newCount, $hasDrawnColumn);
                    }
                    $message = "Set permanent entries to {$newCount} for {$user->name}";
                    break;

                case 'add':
                    if ($entry) {
                        $entry->increment('entry_count', $validated['amount']);
                        $entry->refresh();
                    } else {
                        $entry = $this->createPermanentEntry($userId, $validated['amount'], $hasDrawnColumn);
                    }
                    $message = "Added {$validated['amount']} permanent entries for {$user->name}";
                    break;

                case 'deduct':
                    if (!$entry || $entry->entry_count < $validated['amount']) {
                        DB::rollBack();
                        $available = $entry ? $entry->entry_count : 0;
                        return response()->json([
                            'success' => false,
                            'message' => "Cannot deduct {$validated['amount']} entries. {$user->name} only has {$available} permanent entries.",
                        ], 422);
                    }

                    $newCount = $entry->entry_count - $validated['amount'];
                    if ($newCount === 0) {
                        $entry->delete();
                    } else {
                        $entry->update(['entry_count' => $newCount]);
                    }
                    $message = "Deducted {$validated['amount']} permanent entries from {$user->name}";
                    break;
            }

            DB::commit();

            Log::info('Permanent entries updated', [
                'user_id'   => $userId,
                'admin_id'  => optional($request->user())->id,
                'action'    => $validated['action'],
                'amount'    => $validated['amount'],
                'old_count' => $oldCount,
                'new_count' => $entry ? $entry->entry_count : 0,
                'reason'    => $validated['reason'] ?? null,
            ]);

            return response()->json([
                'success'           => true,
                'message'           => $message,
                'permanent_entries' => $entry ? (int) $entry->entry_count : 0,
            ]);
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('ManageUserEntryController@update failed', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update entries: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get a single user's permanent + temporary breakdown (used by the modal).
     */
    public function getUserEntries($userId)
    {
        try {
            $user = User::findOrFail($userId);
            $hasDrawnColumn = Schema::hasColumn('entries', 'drawn');

            $perm = (int) Entry::where('user_id', $userId)
                ->whereNull('giveaway_id')
                ->where('purchase_type', 'reward')
                ->sum('entry_count');

            $tempQuery = Entry::where('user_id', $userId)->whereNotNull('giveaway_id');
            if ($hasDrawnColumn) {
                $tempQuery->where(function ($q) {
                    $q->where('drawn', false)->orWhereNull('drawn');
                });
            }
            $temp = (int) $tempQuery->sum('entry_count');

            return response()->json([
                'success'           => true,
                'user_id'           => $user->id,
                'user_name'         => $user->name,
                'user_email'        => $user->email,
                'permanent_entries' => $perm,
                'temporary_entries' => $temp,
                'total_entries'     => $perm + $temp,
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch entries',
            ], 500);
        }
    }

    /**
     * Helper to create a permanent entry row, handling the optional drawn column.
     */
    private function createPermanentEntry(int $userId, int $count, bool $hasDrawnColumn): Entry
    {
        $data = [
            'user_id'       => $userId,
            'giveaway_id'   => null,
            'purchase_type' => 'reward',
            'entry_count'   => $count,
            'amount_paid'   => 0,
        ];
        if ($hasDrawnColumn) {
            $data['drawn'] = false;
        }
        return Entry::create($data);
    }
}
