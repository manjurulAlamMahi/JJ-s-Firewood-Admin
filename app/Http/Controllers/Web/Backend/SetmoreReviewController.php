<?php

namespace App\Http\Controllers\Web\Backend;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\SetmoreWebhookEvent;
use App\Services\SetmoreMatchingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/**
 * Admin review panel for Setmore webhook events.
 *
 * - index()  : list pending + recent reviewed
 * - apply()  : delegate to SetmoreMatchingService::applyEvent()
 * - reject() : delegate to SetmoreMatchingService::rejectEvent()
 *
 * No business logic here — the service is the single source of truth.
 */
class SetmoreReviewController extends Controller
{
    public function index(Request $request)
    {
        if (!Schema::hasTable('setmore_webhook_events')) {
            return view('backend.setmore-reviews.index', [
                'events'        => collect(),
                'pendingCount'  => 0,
                'tableMissing'  => true,
            ]);
        }

        $tab = $request->input('tab', 'pending');

        $query = SetmoreWebhookEvent::with(['matchedOrder.user', 'appliedOrder.user', 'reviewer'])
            ->orderBy('created_at', 'desc');

        if ($tab === 'pending') {
            $query->where('status', 'pending');
        } elseif ($tab === 'no_match') {
            $query->where('status', 'no_match');
        } elseif ($tab === 'history') {
            $query->whereIn('status', ['applied', 'rejected']);
        } elseif ($tab === 'cancellations') {
            // Show all cancellation events regardless of status:
            //   - applied  (successful auto-cancels)
            //   - pending  (delivered orders flagged for review)
            //   - no_match (cancel arrived but no order linked)
            //   - ignored  (duplicate cancellation fires)
            // Detection: _kind in raw_payload starts with 'cancellation'.
            $query->whereRaw(
                "JSON_UNQUOTE(JSON_EXTRACT(raw_payload, '$._kind')) LIKE 'cancellation%'"
            );
        }
        // else 'all' — show everything

        $events = $query->paginate(25)->withQueryString();

        // Pre-load all candidate orders for the visible page so the view can
        // render them without N+1 lookups
        $allCandidateIds = $events->flatMap(function ($e) {
            return is_array($e->candidate_order_ids) ? $e->candidate_order_ids : [];
        })->unique()->values()->all();

        $candidateOrders = collect();
        if ($allCandidateIds) {
            $candidateOrders = Order::with('user')
                ->whereIn('id', $allCandidateIds)
                ->get()
                ->keyBy('id');
        }

        // Count cancellation events that need a glance: 'pending' kind means
        // a delivered-order cancel that needs admin decision; 'no_match' kind
        // means a possible missed link. Together these are the "needs review"
        // bucket. Successful applies and duplicates are routine and don't bump
        // the badge — they're visible in the tab but don't demand attention.
        $cancellationActiveCount = 0;
        try {
            $cancellationActiveCount = SetmoreWebhookEvent::whereIn('status', ['pending', 'no_match'])
                ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(raw_payload, '$._kind')) LIKE 'cancellation%'")
                ->count();
        } catch (\Throwable $e) {
            // Defensive: if JSON functions aren't supported (older MySQL), don't crash
            $cancellationActiveCount = 0;
        }

        return view('backend.setmore-reviews.index', [
            'events'                  => $events,
            'tab'                     => $tab,
            'pendingCount'            => SetmoreWebhookEvent::pending()->count(),
            'noMatchCount'            => SetmoreWebhookEvent::where('status', 'no_match')->count(),
            'ignoredCount'            => SetmoreWebhookEvent::where('status', 'ignored')->count(),
            'cancellationActiveCount' => $cancellationActiveCount,
            'candidateOrders'         => $candidateOrders,
            'autoApply'               => $this->readAutoApplySetting(),
            'autoCancel'              => $this->readAutoCancelSetting(),
        ]);
    }

    /**
     * Read the current setmore_auto_apply setting from system_settings.
     * Returns false defensively if column doesn't exist (pre-migration) or
     * the table is empty.
     */
    protected function readAutoApplySetting(): bool
    {
        try {
            if (!Schema::hasTable('system_settings')) return false;
            if (!Schema::hasColumn('system_settings', 'setmore_auto_apply')) return false;

            $value = \DB::table('system_settings')->orderBy('id')->value('setmore_auto_apply');
            return (bool) $value;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Read the current setmore_auto_cancel setting from system_settings.
     * Defaults to TRUE — auto-cancellation is the standard behaviour and
     * we only want it off when admin explicitly disables it.
     */
    protected function readAutoCancelSetting(): bool
    {
        try {
            if (!Schema::hasTable('system_settings')) return true;
            if (!Schema::hasColumn('system_settings', 'setmore_auto_cancel')) return true;

            $value = \DB::table('system_settings')->orderBy('id')->value('setmore_auto_cancel');
            return $value === null ? true : (bool) $value;
        } catch (\Throwable $e) {
            return true;
        }
    }

    /**
     * Toggle the auto-apply setting.
     *
     * Writes to the single row in system_settings (creates one if none
     * exists). Returns the new boolean state.
     */
    public function toggleAutoApply(Request $request)
    {
        if (!Schema::hasTable('system_settings')) {
            return response()->json(['ok' => false, 'message' => 'system_settings table missing.'], 503);
        }
        if (!Schema::hasColumn('system_settings', 'setmore_auto_apply')) {
            return response()->json(['ok' => false, 'message' => 'setmore_auto_apply column missing. Run migrations.'], 503);
        }

        $newValue = $request->boolean('enabled');

        // Use the first row (system_settings is a single-row table by convention)
        $row = \DB::table('system_settings')->orderBy('id')->first();
        if ($row) {
            \DB::table('system_settings')
                ->where('id', $row->id)
                ->update(['setmore_auto_apply' => $newValue, 'updated_at' => now()]);
        } else {
            \DB::table('system_settings')->insert([
                'setmore_auto_apply' => $newValue,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Bust the singleton cache that AppServiceProvider sets up so the
        // next read sees the new value
        try { \Cache::forget('system_settings'); } catch (\Throwable $e) {}

        return response()->json([
            'ok'      => true,
            'enabled' => $newValue,
            'message' => $newValue
                ? 'Auto-apply enabled. Single-candidate matches will be applied automatically.'
                : 'Auto-apply disabled. All events go to pending review.',
        ]);
    }

    /**
     * Toggle the auto-cancel setting.
     *
     * When enabled (default), Setmore "Appointment Deleted" webhooks
     * automatically cancel the matched order. When disabled, cancellation
     * events sit in 'pending' for admin to manually action via the
     * Setmore Reviews panel.
     */
    public function toggleAutoCancel(Request $request)
    {
        if (!Schema::hasTable('system_settings')) {
            return response()->json(['ok' => false, 'message' => 'system_settings table missing.'], 503);
        }
        if (!Schema::hasColumn('system_settings', 'setmore_auto_cancel')) {
            return response()->json(['ok' => false, 'message' => 'setmore_auto_cancel column missing. Run migrations.'], 503);
        }

        $newValue = $request->boolean('enabled');

        $row = \DB::table('system_settings')->orderBy('id')->first();
        if ($row) {
            \DB::table('system_settings')
                ->where('id', $row->id)
                ->update(['setmore_auto_cancel' => $newValue, 'updated_at' => now()]);
        } else {
            \DB::table('system_settings')->insert([
                'setmore_auto_cancel' => $newValue,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        try { \Cache::forget('system_settings'); } catch (\Throwable $e) {}

        return response()->json([
            'ok'      => true,
            'enabled' => $newValue,
            'message' => $newValue
                ? 'Auto-cancel enabled. Setmore deletions will cancel matched orders automatically.'
                : 'Auto-cancel disabled. Setmore deletions queue for admin review.',
        ]);
    }

    public function apply(Request $request, $eventId, SetmoreMatchingService $svc)
    {
        $event = SetmoreWebhookEvent::find($eventId);
        if (!$event) {
            return response()->json(['ok' => false, 'message' => 'Event not found'], 404);
        }

        // Admin can specify which candidate to apply to (for ambiguous events)
        $orderId = $request->input('order_id');
        $orderId = $orderId ? (int) $orderId : null;

        $result = $svc->applyEvent($event, $orderId, auth()->id());
        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    public function reject(Request $request, $eventId, SetmoreMatchingService $svc)
    {
        $event = SetmoreWebhookEvent::find($eventId);
        if (!$event) {
            return response()->json(['ok' => false, 'message' => 'Event not found'], 404);
        }

        $notes = $request->input('notes');
        $result = $svc->rejectEvent($event, auth()->id(), $notes);
        return response()->json($result, $result['ok'] ? 200 : 422);
    }

    /**
     * Activity log of server-sent delivery emails (phone / WC bookings).
     */
    public function deliveryEmailLog(Request $request)
    {
        if (!Schema::hasTable('setmore_delivery_emails')) {
            return view('backend.setmore-reviews.delivery-log', [
                'logs'         => collect(),
                'tableMissing' => true,
            ]);
        }

        $logs = \App\Models\SetmoreDeliveryEmail::orderBy('created_at', 'desc')
            ->paginate(50)
            ->withQueryString();

        return view('backend.setmore-reviews.delivery-log', [
            'logs'         => $logs,
            'tableMissing' => false,
        ]);
    }
}
