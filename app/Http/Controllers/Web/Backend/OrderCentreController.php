<?php

namespace App\Http\Controllers\Web\Backend;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderTag;
use App\Models\OrderNote;
use App\Models\OrderAuditLog;
use App\Models\WoocommerceSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Yajra\DataTables\Facades\DataTables;

/**
 * Order Centre — comprehensive order management.
 *
 * Standalone from the legacy Core\OrderController. The legacy /order/*
 * system continues to work unchanged. This controller handles /order-centre/*.
 *
 * Phase 1: index page + live stats
 * Phase 2: All Orders DataTable, drawer payload, notes/tags/audit
 * Phase 3: bulk operations + CSV export
 * Phase 4: Needs Action tab, Refunds Queue tab, manual order edit (CURRENT)
 * Phase 5: Analytics dashboard (CURRENT)
 * Phase 6: WooCommerce settings (CURRENT)
 */
class OrderCentreController extends Controller
{
    // ============================================================
    // PAGE / STATS  (Phase 1)
    // ============================================================

    public function index()
    {
        $stats = $this->buildStats();
        $tags  = OrderTag::orderBy('name')->get();

        return view('backend.order-centre.index', compact('stats', 'tags'));
    }

    public function getStats()
    {
        return response()->json($this->buildStats());
    }

    protected function buildStats(): array
    {
        $today = Carbon::today();

        $todayQ = Order::whereDate('created_at', $today);
        $todayPaidQ = Order::whereDate('created_at', $today)->where('payment_status', 'paid');

        // Needs Action count must match the criteria in getNeedsActionData().
        // Keep these in sync if you change one, change the other.
        $hasConfirmCol = \Schema::hasColumn('orders', 'delivery_date_confirmed_at');
        $hasDismissCol = \Schema::hasColumn('orders', 'needs_action_dismissed_at');
        $needsActionQ = Order::where(function ($q) use ($today, $hasConfirmCol) {
            $q->where('order_status', 'pending')
              ->orWhere(function ($q2) use ($today) {
                  $q2->whereNotIn('order_status', ['delivered', 'cancelled'])
                     ->whereNotNull('preferred_delivery_date')
                     ->whereDate('preferred_delivery_date', '<', $today);
              })
              ->orWhere(function ($q3) {
                  $q3->where('payment_status', 'paid')
                     ->whereNull('preferred_delivery_date')
                     ->whereNotIn('order_status', ['delivered', 'cancelled']);
              });
            if ($hasConfirmCol) {
                $q->orWhere(function ($q4) {
                    $q4->where('payment_status', 'paid')
                       ->whereNull('delivery_date_confirmed_at')
                       ->whereNotIn('order_status', ['delivered', 'cancelled']);
                });
            }
        });
        if ($hasDismissCol) {
            $needsActionQ->whereNull('needs_action_dismissed_at');
        }

        $overdueQ = Order::whereNotIn('order_status', ['delivered', 'cancelled'])
            ->whereNotNull('preferred_delivery_date')
            ->whereDate('preferred_delivery_date', '<', $today);

        $stats = [
            'today_orders'  => $todayQ->count(),
            'today_revenue' => (float) $todayPaidQ->sum('total'),
            'needs_action'  => $needsActionQ->count(),
            'overdue'       => $overdueQ->count(),
        ];

        // WC counters from the new wc_orders table
        if (\Schema::hasTable('wc_orders')) {
            $stats['website_orders_today'] = \App\Models\WcOrder::whereDate('wc_date_created', $today)->count();
        } else {
            $stats['website_orders_today'] = 0;
        }
        $stats['app_orders_today'] = $stats['today_orders'];

        return $stats;
    }

    // ============================================================
    // ALL ORDERS — DataTables endpoint (Phase 2)
    // ============================================================

    public function getAllOrdersData(Request $request)
    {
        try {
            $query = Order::with(['user', 'address', 'payment', 'orderItems'])
                ->select('orders.*');

            $this->applyOrderFilters($query, $request);

            $query->orderBy('created_at', 'desc');

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('customer', function ($o) {
                    return [
                        'id'     => $o->user?->id,
                        'name'   => $o->user?->name ?? 'Guest',
                        'email'  => $o->user?->email ?? '',
                        'phone'  => $o->user?->phone ?? '',
                        'avatar' => $o->user?->avatar_url ?? asset('uploads/avatars/default.png'),
                    ];
                })
                ->addColumn('suburb',           fn($o) => $o->address?->suburb ?? 'N/A')
                ->addColumn('total_formatted',  fn($o) => number_format((float) ($o->total ?? 0), 2))
                ->addColumn('created_at_human', fn($o) => $o->created_at?->diffForHumans() ?? '')
                ->addColumn('items_count',      fn($o) => $o->orderItems?->count() ?? 0)
                ->addColumn('tags', function ($o) {
                    // Convert Collection to plain array for cleaner JSON serialization
                    return DB::table('order_tag')
                        ->join('order_tags', 'order_tags.id', '=', 'order_tag.tag_id')
                        ->where('order_tag.order_id', $o->id)
                        ->select('order_tags.id', 'order_tags.name', 'order_tags.colour')
                        ->get()
                        ->map(fn($t) => (array) $t)
                        ->values()
                        ->toArray();
                })
                ->make(true);
        } catch (\Throwable $e) {
            \Log::error('OrderCentre getAllOrdersData failed: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'draw'            => (int) $request->input('draw', 0),
                'recordsTotal'    => 0,
                'recordsFiltered' => 0,
                'data'            => [],
                'error'           => 'Server error: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ============================================================
    // ORDER DRAWER PAYLOAD (Phase 2)
    // ============================================================

    public function getOrderDetails($id)
    {
        $order = Order::with([
            'user', 'address', 'orderItems.product.category', 'orderItems.product.subcategory', 'payment', 'coupon', 'deliveryType',
        ])->find($id);

        if (!$order) return response()->json(['message' => 'Order not found'], 404);

        $latestPayment = $order->payment->sortByDesc('created_at')->first();

        return response()->json([
            'id'                       => $order->id,
            'order_number'             => $order->order_number,
            'created_at'               => $order->created_at?->format('d M Y, h:i A'),
            'created_at_human'         => $order->created_at?->diffForHumans(),
            'order_status'             => $order->order_status,
            'payment_status'           => $order->payment_status,
            'payment_method'           => $order->payment_method,
            'preferred_delivery_date'  => $order->preferred_delivery_date?->toDateString(),
            'delivery_date_confirmed_at' => \Schema::hasColumn('orders', 'delivery_date_confirmed_at')
                ? $order->delivery_date_confirmed_at?->format('d M Y, h:i A')
                : null,
            'auto_delivered_at' => \Schema::hasColumn('orders', 'auto_delivered_at')
                ? $order->auto_delivered_at?->format('d M Y, h:i A')
                : null,
            'subtotal'                 => (float) $order->subtotal,
            'delivery_fee'             => (float) $order->delivery_fee,
            'discount'                 => (float) $order->discount,
            'tax'                      => (float) $order->tax,
            'total'                    => (float) $order->total,
            'notes'                    => $order->notes,

            // User block — falls back to shipping_addresses.full_name for
            // Apple-ID signups where Apple doesn't share the user's real name.
            // The customer always types their real name + phone at checkout
            // into the shipping address.
            'user' => $order->user ? [
                'id'         => $order->user->id,
                'name'       => $order->user->name
                                ?: ($order->address?->full_name ?: 'Guest'),
                'email'      => $order->user->email,
                'phone'      => $order->user->phone
                                ?: ($order->address?->phone ?: null),
                'avatar_url' => $order->user->avatar_url,
            ] : null,

            'address' => $order->address ? [
                'address'   => $order->address->address ?? '',
                'suburb'    => $order->address->suburb ?? '',
                'city'      => $order->address->city ?? '',
                'state'     => $order->address->state ?? '',
                'zip'       => $order->address->zip_code ?? '',
                'phone'     => $order->address->phone ?? '',
                'full_name' => $order->address->full_name ?? '',  // billing name on the order
                'email'     => $order->address->email ?? '',       // order-time email
                'label'     => $order->address->label ?? '',       // e.g. "Home"
            ] : null,

            // Delivery method/service — e.g. "Standard Delivery (3-5 days)".
            // Eager-loaded via `deliveryType` on the Order model. Falls back to null
            // for legacy orders without a delivery_type_id.
            'delivery_type' => $order->deliveryType ? [
                'id'   => $order->deliveryType->id,
                'name' => $order->deliveryType->name,
                'fee'  => (float) ($order->deliveryType->fee ?? 0),
                'days' => $order->deliveryType->days,
            ] : null,

            'items' => $order->orderItems->map(function ($it) {
                // Enrich each line item with product details so the drawer can
                // distinguish between products with similar names (e.g. different
                // sizes of the same firewood).
                $p = $it->product;
                return [
                    'id'             => $it->id,
                    'product_id'     => $it->product_id,
                    'product_name'   => $it->product_name ?? ($p?->name ?? 'Item'),
                    'short_title'    => $p?->short_title,        // distinguishing subtitle (e.g. "Jarrah Bush Mix")
                    'category'       => $p?->category?->name,    // e.g. "FIREWOOD"
                    'subcategory'    => $p?->subcategory?->name,
                    'image'          => $p?->image,              // thumbnail URL/path
                    'size_info'      => $p?->size_info,          // e.g. "16 Scoops / 4T"
                    'burn_quantity'  => $p?->burn_quantity_info,
                    'quantity'       => (float) $it->quantity,
                    'price'          => (float) $it->price,
                    'subtotal'       => (float) ($it->subtotal ?? ($it->quantity * $it->price)),
                ];
            })->values(),

            'latest_payment' => $latestPayment ? [
                'id'              => $latestPayment->id,
                'payment_id'      => $latestPayment->payment_id,
                'amount'          => (float) $latestPayment->amount,
                'status'          => $latestPayment->status,
                'refund_amount'   => (float) ($latestPayment->refund_amount ?? 0),
                'refund_status'   => $latestPayment->refund_status,
                'refund_reason'   => $latestPayment->refund_reason,
                'remaining_amount'=> (float) ($latestPayment->remaining_amount ?? 0),
                'refunded_at'     => $latestPayment->refunded_at?->format('d M Y, h:i A'),
                'receipt_url'     => $latestPayment->receipt_url,
                'payment_method'  => $latestPayment->payment_method,
            ] : null,

            'tags' => DB::table('order_tag')
                ->join('order_tags', 'order_tags.id', '=', 'order_tag.tag_id')
                ->where('order_tag.order_id', $order->id)
                ->select('order_tags.id', 'order_tags.name', 'order_tags.colour')
                ->get(),

            'notes_count'      => OrderNote::where('order_id', $order->id)->count(),
            'audit_log_count'  => OrderAuditLog::where('order_id', $order->id)->count(),
        ]);
    }

    // ============================================================
    // NOTES (Phase 2)
    // ============================================================

    public function getNotes($id)
    {
        $notes = OrderNote::with('user:id,name,avatar')
            ->where('order_id', $id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($n) {
                return [
                    'id'              => $n->id,
                    'body'            => $n->body,
                    'author'          => $n->user?->name ?? 'System',
                    'avatar'          => $n->user?->avatar_url ?? asset('uploads/avatars/default.png'),
                    'created_at'      => $n->created_at?->diffForHumans(),
                    'created_at_full' => $n->created_at?->format('d M Y, h:i A'),
                ];
            });

        return response()->json(['data' => $notes]);
    }

    public function addNote(Request $request, $id)
    {
        $request->validate(['body' => 'required|string|max:5000']);

        $order = Order::find($id);
        if (!$order) return response()->json(['message' => 'Order not found'], 404);

        $note = OrderNote::create([
            'order_id' => $id,
            'user_id'  => auth()->id(),
            'body'     => trim($request->body),
        ]);

        OrderAuditLog::record(
            orderId: $id,
            action: 'note_added',
            description: "Note added: " . str()->limit($note->body, 80),
            metadata: ['note_id' => $note->id],
        );

        return response()->json([
            'message' => 'Note added',
            'note' => [
                'id'              => $note->id,
                'body'            => $note->body,
                'author'          => auth()->user()->name,
                'avatar'          => auth()->user()->avatar_url ?? asset('uploads/avatars/default.png'),
                'created_at'      => 'just now',
                'created_at_full' => $note->created_at?->format('d M Y, h:i A'),
            ],
        ]);
    }

    public function deleteNote($orderId, $noteId)
    {
        $note = OrderNote::where('order_id', $orderId)->where('id', $noteId)->first();
        if (!$note) return response()->json(['message' => 'Note not found'], 404);

        $note->delete();

        OrderAuditLog::record(
            orderId: $orderId,
            action: 'note_deleted',
            description: "Note deleted",
            metadata: ['deleted_note_id' => $noteId],
        );

        return response()->json(['message' => 'Note deleted']);
    }

    // ============================================================
    // AUDIT LOG (Phase 2)
    // ============================================================

    public function getAuditLog($id)
    {
        $logs = OrderAuditLog::with('user:id,name,avatar')
            ->where('order_id', $id)
            ->orderBy('created_at', 'desc')
            ->limit(200)
            ->get()
            ->map(function ($log) {
                return [
                    'id'              => $log->id,
                    'action'          => $log->action,
                    'description'     => $log->description,
                    'metadata'        => $log->metadata,
                    'author'          => $log->user?->name ?? 'System',
                    'avatar'          => $log->user?->avatar_url ?? asset('uploads/avatars/default.png'),
                    'created_at'      => $log->created_at?->diffForHumans(),
                    'created_at_full' => $log->created_at?->format('d M Y, h:i A'),
                ];
            });

        return response()->json(['data' => $logs]);
    }

    // ============================================================
    // TAGS (Phase 2)
    // ============================================================

    public function listTags()
    {
        $tags = OrderTag::orderBy('name')
            ->withCount(['orders'])  // requires `orders` BelongsToMany on OrderTag (Phase 1)
            ->get(['id', 'name', 'slug', 'colour']);

        return response()->json([
            'data' => $tags->map(function ($t) {
                return [
                    'id'           => $t->id,
                    'name'         => $t->name,
                    'slug'         => $t->slug,
                    'colour'       => $t->colour,
                    'orders_count' => $t->orders_count ?? 0,
                ];
            }),
        ]);
    }

    public function createTag(Request $request)
    {
        $request->validate([
            'name'   => 'required|string|max:64|unique:order_tags,name',
            'colour' => 'nullable|string|max:16',
        ]);

        $tag = OrderTag::create([
            'name'   => $request->name,
            'colour' => $request->colour ?? '#6B7280',
        ]);

        return response()->json([
            'message' => 'Tag created',
            'tag'     => $tag,
        ]);
    }

    public function updateTag(Request $request, $id)
    {
        $tag = OrderTag::find($id);
        if (!$tag) return response()->json(['message' => 'Tag not found'], 404);

        $request->validate([
            'name'   => 'required|string|max:64|unique:order_tags,name,' . $tag->id,
            'colour' => 'nullable|string|max:16',
        ]);

        $tag->update([
            'name'   => $request->name,
            'colour' => $request->colour ?? $tag->colour,
        ]);

        return response()->json(['message' => 'Tag updated', 'tag' => $tag]);
    }

    public function deleteTag($id)
    {
        $tag = OrderTag::find($id);
        if (!$tag) return response()->json(['message' => 'Tag not found'], 404);

        // Detach from all orders, then delete
        \DB::table('order_tag')->where('tag_id', $id)->delete();
        $name = $tag->name;
        $tag->delete();

        return response()->json(['message' => "Tag '{$name}' deleted"]);
    }

    public function attachTag(Request $request, $id)
    {
        $request->validate(['tag_id' => 'required|exists:order_tags,id']);

        $order = Order::find($id);
        if (!$order) return response()->json(['message' => 'Order not found'], 404);

        DB::table('order_tag')->insertOrIgnore([
            'order_id'   => $id,
            'tag_id'     => $request->tag_id,
            'created_at' => now(),
        ]);

        $tag = OrderTag::find($request->tag_id);

        OrderAuditLog::record(
            orderId: $id,
            action: 'tag_attached',
            description: "Tag '{$tag->name}' attached",
            metadata: ['tag_id' => $tag->id, 'tag_name' => $tag->name],
        );

        return response()->json(['message' => 'Tag attached', 'tag' => $tag]);
    }

    public function detachTag($id, $tagId)
    {
        $order = Order::find($id);
        if (!$order) return response()->json(['message' => 'Order not found'], 404);

        $tag = OrderTag::find($tagId);
        DB::table('order_tag')
            ->where('order_id', $id)
            ->where('tag_id', $tagId)
            ->delete();

        if ($tag) {
            OrderAuditLog::record(
                orderId: $id,
                action: 'tag_detached',
                description: "Tag '{$tag->name}' removed",
                metadata: ['tag_id' => $tag->id, 'tag_name' => $tag->name],
            );
        }

        return response()->json(['message' => 'Tag detached']);
    }

    // ============================================================
    // BULK OPERATIONS (Phase 3)
    // ============================================================

    /**
     * Bulk update order_status across many orders.
     * Audit logging happens via OrderObserver automatically.
     * Fires OrderCancelled / OrderDelivered events to match legacy behaviour.
     */
    public function bulkUpdateStatus(Request $request)
    {
        $request->validate([
            'ids'             => 'required|array|min:1|max:1000',
            'ids.*'           => 'integer',
            'order_status'    => 'required|in:pending,confirmed,processing,shipped,delivered,cancelled',
            'also_mark_paid'  => 'nullable|boolean',
        ]);

        $newStatus       = $request->order_status;
        $alsoMarkPaid    = $request->boolean('also_mark_paid');
        $updated         = 0;
        $skipped         = 0;
        $errors          = 0;
        $autoPaidCount   = 0;

        Order::whereIn('id', $request->ids)->chunkById(50, function ($orders) use ($newStatus, $alsoMarkPaid, &$updated, &$skipped, &$errors, &$autoPaidCount) {
            foreach ($orders as $order) {
                try {
                    if ($order->order_status === $newStatus) {
                        $skipped++;
                        continue;
                    }
                    $order->order_status = $newStatus;

                    // DELIVERY=PAYMENT: when marking delivered with also_mark_paid,
                    // flip payment_status to 'paid' if it was pending. Done in the
                    // same save so audit logs / observers see one consistent state.
                    $paidJustFlipped = false;
                    if ($newStatus === 'delivered' && $alsoMarkPaid && $order->payment_status === 'pending') {
                        $order->payment_status = 'paid';
                        $paidJustFlipped = true;
                        $autoPaidCount++;
                    }

                    $order->save();

                    // Award rewards (loyalty + entry) AFTER save so the order
                    // record is up-to-date when the award routine reads it.
                    if ($paidJustFlipped) {
                        try { $this->awardRewards($order); }
                        catch (\Throwable $e) { \Log::error("Auto-pay award failed for order {$order->id}: " . $e->getMessage()); }
                    }

                    // Match legacy event firing where classes exist
                    if ($newStatus === 'cancelled' && class_exists('\\App\\Events\\OrderCancelled')) {
                        try { event(new \App\Events\OrderCancelled($order)); } catch (\Throwable $e) {}
                    }
                    if ($newStatus === 'delivered' && class_exists('\\App\\Events\\OrderDelivered')) {
                        try { event(new \App\Events\OrderDelivered($order)); } catch (\Throwable $e) {}
                    }

                    // Safety net: if order was ALREADY paid (not the just-flipped case)
                    // and we're marking delivered, ensure rewards exist. Idempotent.
                    if ($newStatus === 'delivered' && !$paidJustFlipped && $order->payment_status === 'paid') {
                        try { $this->awardRewards($order); }
                        catch (\Throwable $e) { \Log::error("Bulk delivery award check failed for order {$order->id}: " . $e->getMessage()); }
                    }

                    $updated++;
                } catch (\Throwable $e) {
                    $errors++;
                    \Log::error("Bulk status update failed for order {$order->id}: " . $e->getMessage());
                }
            }
        });

        $msg = $this->buildBulkResultMessage('Updated', $updated, $skipped, $errors);
        if ($autoPaidCount > 0) $msg .= ", {$autoPaidCount} also marked as paid";

        return response()->json([
            'message'         => $msg,
            'updated'         => $updated,
            'skipped'         => $skipped,
            'errors'          => $errors,
            'auto_paid_count' => $autoPaidCount,
        ]);
    }

    /**
     * Bulk update payment_status. Auto-promotes pending → processing on 'paid' (matches legacy).
     */
    public function bulkUpdatePayment(Request $request)
    {
        $request->validate([
            'ids'            => 'required|array|min:1|max:1000',
            'ids.*'          => 'integer',
            'payment_status' => 'required|in:pending,paid,failed,refunded,partially_refunded',
        ]);

        $new = $request->payment_status;
        $updated = 0;
        $skipped = 0;
        $autoPromoted = 0;
        $errors = 0;

        Order::whereIn('id', $request->ids)->chunkById(50, function ($orders) use ($new, &$updated, &$skipped, &$autoPromoted, &$errors) {
            foreach ($orders as $order) {
                try {
                    if ($order->payment_status === $new) {
                        $skipped++;
                        continue;
                    }
                    $order->payment_status = $new;

                    // Match legacy auto-promotion on payment received
                    if ($new === 'paid' && $order->order_status === 'pending') {
                        $order->order_status = 'processing';
                        $autoPromoted++;
                    }

                    $order->save();

                    // ENTRY/LOYALTY FIX: when payment becomes 'paid', award
                    // loyalty + permanent entry. Idempotent.
                    if ($new === 'paid') {
                        try { $this->awardRewards($order); }
                        catch (\Throwable $e) { \Log::error("Bulk award failed for order {$order->id}: " . $e->getMessage()); }
                    }

                    $updated++;
                } catch (\Throwable $e) {
                    $errors++;
                    \Log::error("Bulk payment update failed for order {$order->id}: " . $e->getMessage());
                }
            }
        });

        $msg = $this->buildBulkResultMessage('Updated', $updated, $skipped, $errors);
        if ($autoPromoted > 0) $msg .= ", {$autoPromoted} auto-promoted to 'processing'";

        return response()->json([
            'message'       => $msg,
            'updated'       => $updated,
            'skipped'       => $skipped,
            'auto_promoted' => $autoPromoted,
            'errors'        => $errors,
        ]);
    }

    /**
     * Bulk reschedule preferred_delivery_date.
     */
    public function bulkUpdateDeliveryDate(Request $request)
    {
        $request->validate([
            'ids'                     => 'required|array|min:1|max:1000',
            'ids.*'                   => 'integer',
            'preferred_delivery_date' => 'required|date',
        ]);

        $date = $request->preferred_delivery_date;
        $updated = 0;
        $errors = 0;
        $hasConfirmCol = \Schema::hasColumn('orders', 'delivery_date_confirmed_at');

        Order::whereIn('id', $request->ids)->chunkById(50, function ($orders) use ($date, $hasConfirmCol, &$updated, &$errors) {
            foreach ($orders as $order) {
                try {
                    $order->preferred_delivery_date = $date;
                    // Bulk reschedule = admin action = date is confirmed.
                    if ($hasConfirmCol) {
                        $order->delivery_date_confirmed_at = now();
                    }
                    $order->save();
                    $updated++;
                } catch (\Throwable $e) {
                    $errors++;
                    \Log::error("Bulk delivery date update failed for order {$order->id}: " . $e->getMessage());
                }
            }
        });

        return response()->json([
            'message' => "Rescheduled {$updated} order(s) to " . date('d M Y', strtotime($date)) . ($errors > 0 ? ", {$errors} failed" : ""),
            'updated' => $updated,
            'errors'  => $errors,
        ]);
    }

    /**
     * Bulk send a custom email notification to the selected orders' customers.
     * Supports placeholders: {customer_name}, {order_number}, {order_total}, {delivery_date}
     */
    public function bulkSendNotification(Request $request)
    {
        $request->validate([
            'ids'     => 'required|array|min:1|max:500',
            'ids.*'   => 'integer',
            'subject' => 'required|string|max:200',
            'body'    => 'required|string|max:5000',
        ]);

        $sent = 0;
        $skipped = 0;
        $errors = 0;

        Order::with('user')->whereIn('id', $request->ids)->chunkById(50, function ($orders) use ($request, &$sent, &$skipped, &$errors) {
            foreach ($orders as $order) {
                if (!$order->user || empty($order->user->email)) {
                    $skipped++;
                    continue;
                }

                try {
                    $personalised = str_replace(
                        ['{customer_name}', '{order_number}', '{order_total}', '{delivery_date}'],
                        [
                            $order->user->name ?? 'Customer',
                            $order->order_number,
                            '$' . number_format((float) $order->total, 2),
                            $order->preferred_delivery_date?->format('d M Y') ?? 'TBC',
                        ],
                        $request->body
                    );

                    $html = '<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;color:#1f2937;">'
                          . '<div style="background:linear-gradient(135deg,#667eea,#764ba2);padding:24px;border-radius:8px 8px 0 0;color:#fff;">'
                          . '<h2 style="margin:0;color:#fff;">JJ\'s Firewood Perth</h2></div>'
                          . '<div style="background:#fff;padding:24px;border:1px solid #e5e7eb;border-top:none;border-radius:0 0 8px 8px;">'
                          . nl2br(e($personalised))
                          . '<hr style="margin-top:24px;border:none;border-top:1px solid #e5e7eb;">'
                          . '<div style="font-size:12px;color:#6b7280;margin-top:16px;">Order #' . e($order->order_number) . ' &middot; jjsfirewoodperth.com.au</div>'
                          . '</div></div>';

                    \Illuminate\Support\Facades\Mail::html($html, function ($msg) use ($order, $request) {
                        $msg->to($order->user->email, $order->user->name);
                        $msg->subject($request->subject);
                    });

                    OrderAuditLog::record(
                        orderId: $order->id,
                        action: 'bulk_notification_sent',
                        description: "Bulk email sent: " . str()->limit($request->subject, 80),
                        metadata: ['subject' => $request->subject],
                    );

                    $sent++;
                } catch (\Throwable $e) {
                    $errors++;
                    \Log::error("Bulk notification failed for order {$order->id}: " . $e->getMessage());
                }
            }
        });

        $msg = "Sent {$sent} notification(s)";
        if ($skipped > 0) $msg .= ", {$skipped} skipped (no email)";
        if ($errors > 0)  $msg .= ", {$errors} failed";

        return response()->json([
            'message' => $msg,
            'sent'    => $sent,
            'skipped' => $skipped,
            'errors'  => $errors,
        ]);
    }

    // ============================================================
    // CSV EXPORT (Phase 3)
    // Streams CSV using current All-Orders filters.
    // ============================================================

    public function exportCsv(Request $request)
    {
        $query = Order::with(['user', 'address', 'orderItems'])->select('orders.*');
        $this->applyOrderFilters($query, $request);
        $query->orderBy('created_at', 'desc');

        $filename = 'orders-' . date('Y-m-d-His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            // BOM for Excel UTF-8 detection
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, [
                'Order #', 'Customer Name', 'Email', 'Phone',
                'Address', 'Suburb',
                'Subtotal', 'Delivery Fee', 'Discount', 'Total',
                'Payment Status', 'Payment Method',
                'Order Status', 'Delivery Date',
                'Items', 'Created At',
            ]);

            $query->chunkById(200, function ($orders) use ($out) {
                foreach ($orders as $o) {
                    $itemsStr = $o->orderItems
                        ->map(fn($it) => ($it->product_name ?? 'Item') . ' x ' . (float) $it->quantity)
                        ->join('; ');

                    fputcsv($out, [
                        $o->order_number,
                        $o->user?->name ?? 'Guest',
                        $o->user?->email ?? '',
                        $o->user?->phone ?? '',
                        $o->address?->address ?? '',
                        $o->address?->suburb ?? '',
                        number_format((float) $o->subtotal, 2, '.', ''),
                        number_format((float) $o->delivery_fee, 2, '.', ''),
                        number_format((float) $o->discount, 2, '.', ''),
                        number_format((float) $o->total, 2, '.', ''),
                        $o->payment_status,
                        $o->payment_method,
                        $o->order_status,
                        $o->preferred_delivery_date?->format('Y-m-d') ?? '',
                        $itemsStr,
                        $o->created_at?->format('Y-m-d H:i:s'),
                    ]);
                }
            });
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    // ============================================================
    // SHARED FILTER HELPER (used by getAllOrdersData & exportCsv)
    // ============================================================

    protected function applyOrderFilters($query, Request $request): void
    {
        if ($request->filled('search_text')) {
            $s = $request->search_text;
            $query->where(function ($q) use ($s) {
                $q->where('order_number', 'like', "%{$s}%")
                  ->orWhere('id', 'like', "%{$s}%")
                  ->orWhereHas('user', function ($u) use ($s) {
                      $u->where('name', 'like', "%{$s}%")
                        ->orWhere('email', 'like', "%{$s}%")
                        ->orWhere('phone', 'like', "%{$s}%");
                  });
            });
        }

        if ($request->filled('date_from')) $query->whereDate('created_at', '>=', $request->date_from);
        if ($request->filled('date_to'))   $query->whereDate('created_at', '<=', $request->date_to);

        if ($request->filled('order_status')) {
            $statuses = array_filter(explode(',', $request->order_status));
            if (!empty($statuses)) $query->whereIn('order_status', $statuses);
        }

        if ($request->filled('payment_status')) {
            $payStatuses = array_filter(explode(',', $request->payment_status));
            if (!empty($payStatuses)) $query->whereIn('payment_status', $payStatuses);
        }

        if ($request->filled('value_min')) $query->where('total', '>=', $request->value_min);
        if ($request->filled('value_max')) $query->where('total', '<=', $request->value_max);

        if ($request->filled('tags')) {
            $tagIds = array_filter(explode(',', $request->tags));
            if (!empty($tagIds)) {
                $query->whereExists(function ($q) use ($tagIds) {
                    $q->select(DB::raw(1))
                      ->from('order_tag')
                      ->whereColumn('order_tag.order_id', 'orders.id')
                      ->whereIn('order_tag.tag_id', $tagIds);
                });
            }
        }

        if ($request->filled('suburb')) {
            $suburb = $request->suburb;
            $query->whereHas('address', function ($a) use ($suburb) {
                $a->where('suburb', 'like', "%{$suburb}%");
            });
        }

        if ($request->filled('tab')) {
            switch ($request->tab) {
                case 'needs_action':
                    $query->where(function ($q) {
                        $q->where('order_status', 'pending')
                          ->orWhere(function ($q2) {
                              $q2->whereNotIn('order_status', ['delivered', 'cancelled'])
                                 ->whereNotNull('preferred_delivery_date')
                                 ->whereDate('preferred_delivery_date', '<', Carbon::today());
                          });
                    });
                    break;
            }
        }
    }

    protected function buildBulkResultMessage(string $verb, int $updated, int $skipped, int $errors): string
    {
        $parts = ["{$verb} {$updated} order(s)"];
        if ($skipped > 0) $parts[] = "{$skipped} unchanged";
        if ($errors > 0)  $parts[] = "{$errors} failed";
        return implode(', ', $parts);
    }

    // ============================================================
    // MANUAL ORDER EDIT (Phase 4) — full-form edit inside drawer
    // Mirrors the legacy /order/{id}/update with the same validation
    // and event firing, but as an AJAX endpoint for the drawer.
    // ============================================================

    public function updateOrderDetails(Request $request, $id)
    {
        $order = Order::find($id);
        if (!$order) return response()->json(['message' => 'Order not found'], 404);

        $validated = $request->validate([
            'subtotal'                => 'nullable|numeric|min:0',
            'delivery_fee'            => 'nullable|numeric|min:0',
            'discount'                => 'nullable|numeric|min:0',
            'tax'                     => 'nullable|numeric|min:0',
            'total'                   => 'nullable|numeric|min:0',
            'payment_method'          => 'nullable|string|max:50',
            'payment_status'          => 'nullable|in:pending,paid,failed,refunded,partially_refunded',
            'order_status'            => 'nullable|in:pending,confirmed,processing,shipped,delivered,cancelled',
            'preferred_delivery_date' => 'nullable|date',
            'notes'                   => 'nullable|string|max:5000',
        ]);

        try {
            $oldPaymentStatus = $order->payment_status;
            $oldStatus       = $order->order_status;
            $oldDate         = $order->preferred_delivery_date?->toDateString();

            $order->update($validated);

            $newPaymentStatus = $order->payment_status;
            $newStatus        = $order->order_status;
            $newDate          = $order->preferred_delivery_date?->toDateString();

            // Mirror legacy event firing
            if ($newStatus !== $oldStatus) {
                if ($newStatus === 'delivered' && class_exists('\\App\\Events\\OrderDelivered')) {
                    try { event(new \App\Events\OrderDelivered($order)); } catch (\Throwable $e) {}
                } elseif ($newStatus === 'cancelled' && class_exists('\\App\\Events\\OrderCancelled')) {
                    try { event(new \App\Events\OrderCancelled($order)); } catch (\Throwable $e) {}
                }
            }
            if ($oldDate !== $newDate && class_exists('\\App\\Events\\OrderDeliveryDateUpdated')) {
                try { event(new \App\Events\OrderDeliveryDateUpdated($order, $oldDate, $newDate)); } catch (\Throwable $e) {}
            }
            if (in_array($newPaymentStatus, ['refunded', 'partially_refunded'], true)
                && $newPaymentStatus !== $oldPaymentStatus
                && class_exists('\\App\\Events\\PaymentRefunded')) {
                $payment = $order->payment()->latest()->first();
                if ($payment) {
                    try { event(new \App\Events\PaymentRefunded($payment)); } catch (\Throwable $e) {}
                }
            }

            // ENTRY/LOYALTY FIX: when payment_status transitions to 'paid'
            // via the edit form, award rewards. Idempotent.
            if ($newPaymentStatus === 'paid' && $oldPaymentStatus !== 'paid') {
                try { $this->awardRewards($order); }
                catch (\Throwable $e) { \Log::error("Update award failed for order {$order->id}: " . $e->getMessage()); }
            }
            // Safety net: paid order transitioning to delivered → award check.
            if ($newStatus === 'delivered' && $newStatus !== $oldStatus && $order->payment_status === 'paid') {
                try { $this->awardRewards($order); }
                catch (\Throwable $e) { \Log::error("Update delivery award check failed for order {$order->id}: " . $e->getMessage()); }
            }

            return response()->json([
                'message' => 'Order updated successfully',
                'order_id' => $order->id,
            ]);
        } catch (\Throwable $e) {
            \Log::error("OrderCentre updateOrderDetails failed for order {$id}: " . $e->getMessage());
            return response()->json([
                'message' => 'Update failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ============================================================
    // NEEDS ACTION TAB (Phase 4)
    // Server-side DataTable filtered to: pending, overdue, or
    // payment-pending orders that need staff attention.
    // ============================================================

    /**
     * Dismiss an order from Needs Action without resolving it.
     * The order stays in admin; it just disappears from the default
     * Needs Action view. Any change to payment_status / order_status /
     * preferred_delivery_date / delivery_date_confirmed_at automatically
     * un-dismisses (see OrderObserver::updating()).
     *
     * Supports both single id (POST .../{id}/dismiss) and bulk
     * (POST .../bulk/dismiss with ids[]).
     */
    // ============================================================
    // AUTO-DELIVERY (end-of-day automation)
    // Admin endpoints for toggling, previewing, and triggering the
    // daily auto-delivery sweep.
    // ============================================================

    /**
     * Toggle the auto_deliver_enabled flag in system_settings.
     * When ON, the scheduled daily job will actually mark orders delivered.
     * When OFF (default), the daily job is a no-op.
     */
    public function toggleAutoDeliver(Request $request)
    {
        if (!\Schema::hasTable('system_settings')) {
            return response()->json(['ok' => false, 'message' => 'system_settings table missing.'], 503);
        }
        if (!\Schema::hasColumn('system_settings', 'auto_deliver_enabled')) {
            return response()->json(['ok' => false, 'message' => 'auto_deliver_enabled column missing. Run migrations.'], 503);
        }

        $newValue = $request->boolean('enabled');

        $row = \DB::table('system_settings')->orderBy('id')->first();
        if ($row) {
            \DB::table('system_settings')
                ->where('id', $row->id)
                ->update(['auto_deliver_enabled' => $newValue, 'updated_at' => now()]);
        } else {
            \DB::table('system_settings')->insert([
                'auto_deliver_enabled' => $newValue,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        try { \Cache::forget('system_settings'); } catch (\Throwable $e) {}

        return response()->json([
            'ok'      => true,
            'enabled' => $newValue,
            'message' => $newValue
                ? 'Auto-delivery enabled. The 6pm sweep will mark verified-payment orders as delivered.'
                : 'Auto-delivery disabled. The 6pm sweep is now a no-op.',
        ]);
    }

    /**
     * Dry-run preview: shows what TODAY's auto-delivery would do.
     * No DB changes. Returns the candidate list with action+reason.
     */
    public function previewAutoDeliver(\App\Services\AutoDeliveryService $svc)
    {
        $stats = $svc->runSweep(true);  // dry-run
        return response()->json([
            'ok'    => true,
            'stats' => $stats,
        ]);
    }

    /**
     * Manually trigger the auto-delivery sweep RIGHT NOW (ignoring the
     * 6pm schedule). Useful for catching up after a missed run or for
     * admin to trigger early when deliveries are done.
     */
    public function runAutoDeliver(\App\Services\AutoDeliveryService $svc)
    {
        $stats = $svc->runSweep(false);
        return response()->json([
            'ok'    => true,
            'stats' => $stats,
            'message' => sprintf(
                'Auto-delivery complete: %d delivered, %d skipped (needs admin), %d errors.',
                $stats['delivered'],
                $stats['skipped'],
                $stats['errors']
            ),
        ]);
    }

    public function dismissOrder(Request $request, $id = null)
    {
        if (!\Schema::hasColumn('orders', 'needs_action_dismissed_at')) {
            return response()->json([
                'ok' => false,
                'message' => 'Dismiss column missing. Run migrations.',
            ], 503);
        }

        $ids = $id ? [(int) $id] : (array) $request->input('ids', []);
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (empty($ids)) {
            return response()->json(['ok' => false, 'message' => 'No order id(s) provided.'], 422);
        }

        $now = now();
        $count = \DB::table('orders')
            ->whereIn('id', $ids)
            ->whereNull('needs_action_dismissed_at')
            ->update(['needs_action_dismissed_at' => $now, 'updated_at' => $now]);

        return response()->json([
            'ok'      => true,
            'message' => "Dismissed {$count} order(s) from Needs Action.",
            'count'   => $count,
        ]);
    }

    /**
     * Restore a previously-dismissed order to Needs Action.
     * Sets needs_action_dismissed_at = NULL.
     */
    public function restoreOrder(Request $request, $id = null)
    {
        if (!\Schema::hasColumn('orders', 'needs_action_dismissed_at')) {
            return response()->json([
                'ok' => false,
                'message' => 'Dismiss column missing. Run migrations.',
            ], 503);
        }

        $ids = $id ? [(int) $id] : (array) $request->input('ids', []);
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (empty($ids)) {
            return response()->json(['ok' => false, 'message' => 'No order id(s) provided.'], 422);
        }

        $count = \DB::table('orders')
            ->whereIn('id', $ids)
            ->whereNotNull('needs_action_dismissed_at')
            ->update(['needs_action_dismissed_at' => null, 'updated_at' => now()]);

        return response()->json([
            'ok'      => true,
            'message' => "Restored {$count} order(s) to Needs Action.",
            'count'   => $count,
        ]);
    }

    public function getNeedsActionData(Request $request)
    {
        try {
            $today = Carbon::today();
            $hasConfirmCol = \Schema::hasColumn('orders', 'delivery_date_confirmed_at');
            $hasDismissCol = \Schema::hasColumn('orders', 'needs_action_dismissed_at');
            $includeDismissed = $request->boolean('include_dismissed', false);

            $query = Order::with(['user', 'address', 'payment', 'orderItems'])
                ->select('orders.*')
                ->where(function ($q) use ($today, $hasConfirmCol) {
                    $q->where('order_status', 'pending')                              // never confirmed
                      ->orWhere(function ($q2) use ($today) {                         // overdue
                          $q2->whereNotIn('order_status', ['delivered', 'cancelled'])
                             ->whereNotNull('preferred_delivery_date')
                             ->whereDate('preferred_delivery_date', '<', $today);
                      })
                      ->orWhere(function ($q3) {                                      // paid but no delivery date scheduled
                          $q3->where('payment_status', 'paid')
                             ->whereNull('preferred_delivery_date')
                             ->whereNotIn('order_status', ['delivered', 'cancelled']);
                      });
                    // NEW: paid + delivery date not yet admin-confirmed.
                    if ($hasConfirmCol) {
                        $q->orWhere(function ($q4) {
                            $q4->where('payment_status', 'paid')
                               ->whereNull('delivery_date_confirmed_at')
                               ->whereNotIn('order_status', ['delivered', 'cancelled']);
                        });
                    }
                });

            // Dismiss filter: by default hide dismissed orders. Admin can
            // toggle "Show dismissed" to see them with a badge + restore button.
            if ($hasDismissCol && !$includeDismissed) {
                $query->whereNull('needs_action_dismissed_at');
            }

            // Allow standard filters (search, value, etc.) on top
            $this->applyOrderFilters($query, $request);

            // Stable ordering: oldest pending first (most urgent)
            $query->orderByRaw("CASE
                WHEN order_status='pending' THEN 1
                WHEN preferred_delivery_date IS NOT NULL AND preferred_delivery_date < CURDATE() THEN 2
                ELSE 3 END")
                  ->orderBy('created_at', 'asc');

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('reason', function ($o) use ($today) {
                    $reasons = [];
                    if ($o->order_status === 'pending') $reasons[] = 'Awaiting confirmation';
                    if ($o->preferred_delivery_date
                        && $o->preferred_delivery_date->lt($today)
                        && !in_array($o->order_status, ['delivered', 'cancelled'])) {
                        $days = (int) $today->diffInDays($o->preferred_delivery_date, false);
                        $reasons[] = 'Overdue ' . abs($days) . 'd';
                    }
                    if ($o->payment_status === 'paid'
                        && is_null($o->preferred_delivery_date)
                        && !in_array($o->order_status, ['delivered', 'cancelled'])) {
                        $reasons[] = 'Needs scheduling';
                    }
                    // NEW: customer picked a date but admin hasn't confirmed it
                    if ($o->payment_status === 'paid'
                        && !is_null($o->preferred_delivery_date)
                        && \Schema::hasColumn('orders', 'delivery_date_confirmed_at')
                        && is_null($o->delivery_date_confirmed_at)
                        && !in_array($o->order_status, ['delivered', 'cancelled'])) {
                        $reasons[] = 'Needs date confirmation';
                    }
                    return $reasons ?: ['Review'];
                })
                ->addColumn('customer', function ($o) {
                    return [
                        'id'     => $o->user?->id,
                        // Apple-ID fallback: user.name may be empty, in which
                        // case use the name typed at checkout in shipping_addresses
                        'name'   => ($o->user?->name) ?: ($o->address?->full_name ?: 'Guest'),
                        'email'  => $o->user?->email ?? '',
                        'phone'  => ($o->user?->phone) ?: ($o->address?->phone ?: ''),
                        'avatar' => $o->user?->avatar_url ?? asset('uploads/avatars/default.png'),
                    ];
                })
                ->addColumn('suburb',           fn($o) => $o->address?->suburb ?? 'N/A')
                ->addColumn('total_formatted',  fn($o) => number_format((float) ($o->total ?? 0), 2))
                ->addColumn('delivery_date',    fn($o) => $o->preferred_delivery_date?->format('d M Y') ?? '—')
                ->addColumn('is_dismissed', function ($o) {
                    if (\Schema::hasColumn('orders', 'needs_action_dismissed_at')) {
                        return !is_null($o->needs_action_dismissed_at);
                    }
                    return false;
                })
                ->addColumn('created_at_human', fn($o) => $o->created_at?->diffForHumans() ?? '')
                ->make(true);
        } catch (\Throwable $e) {
            \Log::error('OrderCentre getNeedsActionData failed: ' . $e->getMessage());
            return response()->json([
                'draw' => (int) $request->input('draw', 0),
                'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [],
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // ============================================================
    // REFUNDS QUEUE (Phase 4)
    // Surfaces orders eligible for refund consideration:
    //   - cancelled orders that were paid
    //   - delivered orders with refund-related payment status
    //   - any order with payment_status partially_refunded (still has remaining amount)
    // Excludes anything previously dismissed by staff.
    // ============================================================

    public function getRefundsQueueData(Request $request)
    {
        try {
            $query = Order::with(['user', 'address', 'payment'])
                ->select('orders.*')
                ->where(function ($q) {
                    $q->where(function ($q1) {
                        $q1->where('order_status', 'cancelled')
                           ->where('payment_status', 'paid');
                    })->orWhere('payment_status', 'partially_refunded')
                      ->orWhere('payment_status', 'refunded');
                });

            // Hide dismissed unless explicitly requested
            if (Schema::hasColumn('orders', 'is_dismissed_from_refund_queue')) {
                if (!$request->boolean('include_dismissed')) {
                    $query->where('is_dismissed_from_refund_queue', false);
                }
            }

            $query->orderBy('updated_at', 'desc');

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('customer', function ($o) {
                    return [
                        'id'     => $o->user?->id,
                        'name'   => $o->user?->name ?? 'Guest',
                        'email'  => $o->user?->email ?? '',
                        'avatar' => $o->user?->avatar_url ?? asset('uploads/avatars/default.png'),
                    ];
                })
                ->addColumn('total_formatted',    fn($o) => number_format((float) ($o->total ?? 0), 2))
                ->addColumn('refunded_amount',    function ($o) {
                    $latest = $o->payment->sortByDesc('created_at')->first();
                    return number_format((float) ($latest?->refund_amount ?? 0), 2);
                })
                ->addColumn('remaining_amount', function ($o) {
                    $latest = $o->payment->sortByDesc('created_at')->first();
                    $refunded = (float) ($latest?->refund_amount ?? 0);
                    return number_format(max(0, (float) $o->total - $refunded), 2);
                })
                ->addColumn('refund_status', function ($o) {
                    $latest = $o->payment->sortByDesc('created_at')->first();
                    return $latest?->refund_status ?? 'none';
                })
                ->addColumn('payment_method',    fn($o) => $o->payment_method)
                ->addColumn('cancelled_at',      fn($o) => $o->cancellation_requested_at?->format('d M Y') ?? $o->updated_at?->format('d M Y'))
                ->addColumn('is_dismissed',      fn($o) => (bool) ($o->is_dismissed_from_refund_queue ?? false))
                ->make(true);
        } catch (\Throwable $e) {
            \Log::error('OrderCentre getRefundsQueueData failed: ' . $e->getMessage());
            return response()->json([
                'draw' => (int) $request->input('draw', 0),
                'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [],
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function dismissFromRefundQueue($id)
    {
        $order = Order::find($id);
        if (!$order) return response()->json(['message' => 'Order not found'], 404);

        if (Schema::hasColumn('orders', 'is_dismissed_from_refund_queue')) {
            $order->is_dismissed_from_refund_queue = true;
            $order->save();
        }

        OrderAuditLog::record(
            orderId: $id,
            action: 'refund_queue_dismissed',
            description: 'Dismissed from refunds queue',
        );

        return response()->json(['message' => 'Dismissed from queue']);
    }

    public function restoreToRefundQueue($id)
    {
        $order = Order::find($id);
        if (!$order) return response()->json(['message' => 'Order not found'], 404);

        if (Schema::hasColumn('orders', 'is_dismissed_from_refund_queue')) {
            $order->is_dismissed_from_refund_queue = false;
            $order->save();
        }

        OrderAuditLog::record(
            orderId: $id,
            action: 'refund_queue_restored',
            description: 'Restored to refunds queue',
        );

        return response()->json(['message' => 'Restored to queue']);
    }

    // ============================================================
    // ANALYTICS (Phase 5)
    // Single endpoint returning all 4 KPIs for a given date range.
    // ============================================================

    public function getAnalytics(Request $request)
    {
        $days  = max(1, min(365, (int) $request->input('days', 30)));
        $start = Carbon::today()->subDays($days - 1);
        $end   = Carbon::today()->endOfDay();

        // 1) Revenue trend (per day)
        $revenueByDay = Order::where('payment_status', 'paid')
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('DATE(created_at) as d, SUM(total) as t, COUNT(*) as n')
            ->groupBy('d')
            ->orderBy('d')
            ->get()
            ->keyBy('d');

        $labels = [];
        $revenueSeries = [];
        $countSeries = [];
        $cursor = $start->copy();
        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();
            $labels[] = $cursor->format('d M');
            $revenueSeries[] = (float) ($revenueByDay[$key]->t ?? 0);
            $countSeries[]   = (int) ($revenueByDay[$key]->n ?? 0);
            $cursor->addDay();
        }

        // 2) Top 10 customers (by paid revenue in window)
        $topCustomers = Order::with('user:id,name,email')
            ->where('payment_status', 'paid')
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('user_id, SUM(total) as revenue, COUNT(*) as orders')
            ->groupBy('user_id')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get()
            ->map(fn($r) => [
                'name'    => $r->user?->name ?? 'Guest #' . $r->user_id,
                'email'   => $r->user?->email ?? '',
                'revenue' => (float) $r->revenue,
                'orders'  => (int) $r->orders,
            ]);

        // 3) Top 10 products (by quantity in window)
        $topProducts = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereBetween('orders.created_at', [$start, $end])
            ->where('orders.payment_status', 'paid')
            ->selectRaw('order_items.product_name, SUM(order_items.quantity) as qty, SUM(order_items.subtotal) as revenue')
            ->groupBy('order_items.product_name')
            ->orderByDesc('qty')
            ->limit(10)
            ->get()
            ->map(fn($r) => [
                'name'    => $r->product_name,
                'qty'     => (float) $r->qty,
                'revenue' => (float) $r->revenue,
            ]);

        // 4) Delivery performance (within window, scheduled deliveries only)
        $today = Carbon::today();
        $delivered = Order::whereBetween('created_at', [$start, $end])
            ->where('order_status', 'delivered')->count();
        $overdue = Order::whereBetween('created_at', [$start, $end])
            ->whereNotIn('order_status', ['delivered', 'cancelled'])
            ->whereNotNull('preferred_delivery_date')
            ->whereDate('preferred_delivery_date', '<', $today)
            ->count();
        $upcoming = Order::whereBetween('created_at', [$start, $end])
            ->whereNotIn('order_status', ['delivered', 'cancelled'])
            ->whereNotNull('preferred_delivery_date')
            ->whereDate('preferred_delivery_date', '>=', $today)
            ->count();
        $cancelled = Order::whereBetween('created_at', [$start, $end])
            ->where('order_status', 'cancelled')->count();
        $awaitingSchedule = Order::whereBetween('created_at', [$start, $end])
            ->whereNotIn('order_status', ['delivered', 'cancelled'])
            ->whereNull('preferred_delivery_date')->count();

        // KPI summary
        $totalRevenue = (float) Order::where('payment_status', 'paid')
            ->whereBetween('created_at', [$start, $end])->sum('total');
        $totalOrders = Order::whereBetween('created_at', [$start, $end])->count();
        $avgOrderValue = $totalOrders > 0 ? $totalRevenue / max(1, Order::where('payment_status', 'paid')->whereBetween('created_at', [$start, $end])->count()) : 0;
        $newCustomers = DB::table('users')
            ->whereBetween('created_at', [$start, $end])
            ->where('role', 'customer')->count();

        return response()->json([
            'period_days'   => $days,
            'period_start'  => $start->toDateString(),
            'period_end'    => $end->toDateString(),
            'kpi'           => [
                'total_revenue'   => round($totalRevenue, 2),
                'total_orders'    => $totalOrders,
                'avg_order_value' => round($avgOrderValue, 2),
                'new_customers'   => $newCustomers,
            ],
            'revenue_trend' => [
                'labels'   => $labels,
                'revenue'  => $revenueSeries,
                'order_count' => $countSeries,
            ],
            'top_customers' => $topCustomers,
            'top_products'  => $topProducts,
            'delivery_performance' => [
                'delivered'         => $delivered,
                'upcoming'          => $upcoming,
                'overdue'           => $overdue,
                'awaiting_schedule' => $awaitingSchedule,
                'cancelled'         => $cancelled,
            ],
        ]);
    }

    // ============================================================
    // WOOCOMMERCE SETTINGS (Phase 6)
    // ============================================================

    // ============================================================
    // NOTIFICATION SETTINGS — emails + push toggles for app orders
    // ============================================================

    public function getNotificationSettings()
    {
        $singleton = \App\Models\NotificationSetting::singleton();
        $current = array_merge(\App\Models\NotificationSetting::DEFAULTS, $singleton->settings ?? []);
        return response()->json([
            'settings' => $current,
            'updated_at' => $singleton->updated_at?->format('d M Y, h:i A'),
        ]);
    }

    public function saveNotificationSettings(Request $request)
    {
        $validated = $request->validate([
            'email_order_placed'                => 'boolean',
            'email_order_delivered'             => 'boolean',
            'email_order_cancelled'             => 'boolean',
            'email_order_delivery_date_updated' => 'boolean',
            'email_payment_refunded'            => 'boolean',
            'admin_bcc_enabled'                 => 'boolean',
            'admin_bcc_address'                 => 'nullable|email|max:255',
            'push_order_placed'                 => 'boolean',
            'push_order_delivered'              => 'boolean',
            'push_order_cancelled'              => 'boolean',
            'push_order_delivery_date_updated'  => 'boolean',
            'push_payment_refunded'             => 'boolean',
            // Setmore delivery emails (phone / WooCommerce bookings)
            'setmore_email_master'              => 'boolean',
            'setmore_email_booked'              => 'boolean',
            'setmore_email_rescheduled'         => 'boolean',
            'setmore_email_cancelled'           => 'boolean',
            'setmore_email_bcc_admin'           => 'boolean',
            'setmore_email_mailer'              => 'nullable|in:brevo,gmail',
        ]);

        // Explicitly cast each boolean — Laravel's `boolean` validation rule
        // accepts 0/1 strings but doesn't always cast to actual booleans, so
        // we get explicit values into the JSON column.
        $payload = [];
        foreach ($validated as $key => $value) {
            if (in_array($key, ['admin_bcc_address', 'setmore_email_mailer'], true)) {
                $payload[$key] = $value;
            } else {
                $payload[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            }
        }

        \App\Models\NotificationSetting::setMany($payload);

        return response()->json([
            'success' => true,
            'message' => 'Notification settings saved.',
        ]);
    }

    /**
     * Send a sample delivery email (booked/rescheduled/cancelled) to an address
     * on demand, so the admin can preview what customers receive.
     */
    public function sendDeliveryTestEmail(Request $request)
    {
        $validated = $request->validate([
            'type'  => 'required|in:booked,rescheduled,cancelled',
            'email' => 'required|email',
        ]);

        $result = (new \App\Services\DeliveryEmailService())
            ->sendTest($validated['type'], $validated['email']);

        return response()->json([
            'success' => $result['sent'],
            'message' => $result['message'],
        ], $result['sent'] ? 200 : 422);
    }

    public function getWooCommerceSettings()
    {
        $settings = WoocommerceSetting::singleton();

        return response()->json([
            'site_url'        => $settings->site_url,
            'consumer_key'    => $settings->consumer_key,
            // Don't echo secrets back fully. Show masked version.
            'consumer_secret' => $settings->consumer_secret ? str_repeat('•', 8) . substr($settings->consumer_secret, -4) : '',
            'webhook_secret'  => $settings->webhook_secret ? str_repeat('•', 8) . substr($settings->webhook_secret, -4) : '',
            'has_secret'      => !empty($settings->consumer_secret),
            'has_webhook'     => !empty($settings->webhook_secret),
            'sync_enabled'    => $settings->sync_enabled,
            'last_sync_at'    => $settings->last_sync_at?->format('d M Y, h:i A'),
            'is_configured'   => $settings->isConfigured(),
        ]);
    }

    public function saveWooCommerceSettings(Request $request)
    {
        $validated = $request->validate([
            'site_url'        => 'nullable|url|max:255',
            'consumer_key'    => 'nullable|string|max:255',
            'consumer_secret' => 'nullable|string|max:255',
            'webhook_secret'  => 'nullable|string|max:255',
            'sync_enabled'    => 'boolean',
        ]);

        $settings = WoocommerceSetting::singleton();

        // Only overwrite secrets if a new (non-masked) value was provided
        $payload = ['site_url' => $validated['site_url'] ?? null,
                    'consumer_key' => $validated['consumer_key'] ?? null,
                    'sync_enabled' => $request->boolean('sync_enabled')];

        if (!empty($validated['consumer_secret']) && !str_starts_with($validated['consumer_secret'], '•')) {
            $payload['consumer_secret'] = $validated['consumer_secret'];
        }
        if (!empty($validated['webhook_secret']) && !str_starts_with($validated['webhook_secret'], '•')) {
            $payload['webhook_secret'] = $validated['webhook_secret'];
        }

        $settings->update($payload);

        return response()->json([
            'message' => 'WooCommerce settings saved',
            'is_configured' => $settings->fresh()->isConfigured(),
        ]);
    }

    public function testWooCommerceConnection()
    {
        $settings = WoocommerceSetting::singleton();

        if (!$settings->isConfigured()) {
            return response()->json([
                'success' => false,
                'message' => 'Settings incomplete. Save URL, key and secret first.',
            ], 422);
        }

        try {
            $url = rtrim($settings->site_url, '/') . '/wp-json/wc/v3/system_status';
            $response = Http::withBasicAuth($settings->consumer_key, $settings->consumer_secret)
                ->timeout(10)
                ->get($url);

            if ($response->successful()) {
                $settings->update([
                    'last_sync_result' => [
                        'tested_at' => now()->toIso8601String(),
                        'status'    => 'ok',
                    ],
                ]);
                return response()->json([
                    'success' => true,
                    'message' => 'Connection successful! WooCommerce API is reachable.',
                    'http_status' => $response->status(),
                ]);
            }

            return response()->json([
                'success' => false,
                'message' => 'Connection failed (HTTP ' . $response->status() . '). Check URL and credentials.',
                'http_status' => $response->status(),
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection error: ' . $e->getMessage(),
            ], 200);
        }
    }


    // ============================================================
    // PHASE 10: WOOCOMMERCE — fully separate wc_* tables
    //
    // All WC orders, items, customers, notes, audit logs, tags and refunds
    // live in their own dedicated tables (see migration 2026_05_08_000004).
    // The native `orders` table is no longer touched by WC at all.
    // ============================================================

    /**
     * DataTable endpoint for the Website Orders tab — reads from wc_orders.
     */
    public function getWebsiteOrdersData(Request $request)
    {
        try {
            // Defensive: if Phase 10 migration hasn't run yet, return empty
            // gracefully instead of throwing a SQL error to the DataTable.
            if (!\Schema::hasTable('wc_orders')) {
                return response()->json([
                    'draw' => (int) $request->input('draw', 0),
                    'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [],
                    'message' => 'WooCommerce tables not yet created. Run: php artisan migrate',
                ]);
            }

            $query = \App\Models\WcOrder::with(['customer', 'tags'])
                ->select('wc_orders.*');

            // Search across order#, WC id, customer name/email/phone
            if ($request->filled('search_text')) {
                $s = $request->search_text;
                $query->where(function ($q) use ($s) {
                    $q->where('woocommerce_number', 'like', "%{$s}%")
                      ->orWhere('woocommerce_id', 'like', "%{$s}%")
                      ->orWhere('customer_email', 'like', "%{$s}%")
                      ->orWhere('customer_phone', 'like', "%{$s}%")
                      ->orWhere('customer_first_name', 'like', "%{$s}%")
                      ->orWhere('customer_last_name', 'like', "%{$s}%");
                });
            }

            if ($request->filled('date_from')) $query->whereDate('wc_date_created', '>=', $request->date_from);
            if ($request->filled('date_to'))   $query->whereDate('wc_date_created', '<=', $request->date_to);
            if ($request->filled('value_min')) $query->where('total', '>=', $request->value_min);
            if ($request->filled('value_max')) $query->where('total', '<=', $request->value_max);
            if ($request->filled('suburb'))    $query->where('suburb', 'like', '%' . $request->suburb . '%');

            // WC has its own status set — filter by it
            if ($request->filled('wc_status')) {
                $statuses = array_filter(explode(',', $request->wc_status));
                if (!empty($statuses)) $query->whereIn('wc_status', $statuses);
            }
            if ($request->filled('payment_status')) {
                $payStatuses = array_filter(explode(',', $request->payment_status));
                if (!empty($payStatuses)) $query->whereIn('payment_status', $payStatuses);
            }

            $query->orderByDesc('wc_date_created');

            $settings = WoocommerceSetting::singleton();
            $siteUrl  = $settings->site_url ? rtrim($settings->site_url, '/') : null;

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('customer', function ($o) {
                    return [
                        'id'    => $o->wc_customer_id,
                        'name'  => trim(($o->customer_first_name ?? '') . ' ' . ($o->customer_last_name ?? '')) ?: 'Guest',
                        'email' => $o->customer_email,
                        'phone' => $o->customer_phone,
                    ];
                })
                ->addColumn('total_formatted',    fn($o) => number_format((float) ($o->total ?? 0), 2))
                ->addColumn('refund_formatted',   fn($o) => number_format((float) ($o->refund_total ?? 0), 2))
                ->addColumn('delivery_date',      fn($o) => $o->preferred_delivery_date?->format('d M Y') ?? '—')
                ->addColumn('created_human',      fn($o) => $o->wc_date_created?->diffForHumans() ?? '')
                ->addColumn('wc_link', function ($o) use ($siteUrl) {
                    if (!$siteUrl || !$o->woocommerce_id) return null;
                    return $siteUrl . '/wp-admin/post.php?post=' . $o->woocommerce_id . '&action=edit';
                })
                ->addColumn('tags', function ($o) {
                    return $o->tags ? $o->tags->map(fn($t) => (array) $t)->toArray() : [];
                })
                ->make(true);
        } catch (\Throwable $e) {
            \Log::error('OrderCentre getWebsiteOrdersData failed: ' . $e->getMessage());
            return response()->json([
                'draw' => (int) $request->input('draw', 0),
                'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [],
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Manual sync trigger from the UI's Sync Now button.
     */
    public function syncWooCommerce(Request $request)
    {
        try {
            $service = new \App\Services\WooCommerceService();

            if (!$service->isConfigured()) {
                return response()->json([
                    'success' => false,
                    'message' => 'WooCommerce not configured. Save credentials in Settings first.',
                ], 422);
            }

            $settings = WoocommerceSetting::singleton();
            $full     = $request->boolean('full');
            $max      = max(1, min(2000, (int) $request->input('max', 200)));
            $since    = (!$full && $settings->last_sync_at) ? $settings->last_sync_at : null;

            $result = $service->pullOrders($since, $max);

            // Sync rewards for all newly pulled/updated orders
            \App\Services\WooCommerceSyncService::syncAllPendingRewards();

            $totalThisRun = (int) $result['imported'] + (int) $result['updated'];
            $hitCap = $totalThisRun >= $max;

            $msg = "Synced {$result['imported']} new + {$result['updated']} updated";
            if ($result['errors'] > 0) $msg .= ", {$result['errors']} errors";
            if ($hitCap) $msg .= ' (more may remain — continuing…)';

            return response()->json([
                'success'      => true,
                'message'      => $msg,
                'result'       => $result,
                'hit_cap'      => $hitCap,
                'total_run'    => $totalThisRun,
                'max'          => $max,
                'last_sync_at' => $settings->fresh()->last_sync_at?->format('d M Y, h:i A'),
            ]);
        } catch (\Throwable $e) {
            \Log::error('OrderCentre syncWooCommerce failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Sync failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reset & pull latest N orders.
     * Wipes all local WC data and pulls only the N most recent orders.
     * Sets the sync watermark to "now" so future incremental syncs only catch
     * orders created/modified after this point.
     */
    public function resetAndPullLatestWcOrders(Request $request)
    {
        try {
            $service = new \App\Services\WooCommerceService();

            if (!$service->isConfigured()) {
                return response()->json([
                    'success' => false,
                    'message' => 'WooCommerce not configured. Save credentials in Settings first.',
                ], 422);
            }

            $count = max(1, min(500, (int) $request->input('count', 100)));

            $result = $service->resetAndPullLatest($count);

            $msg = "Wiped local data and pulled the latest {$result['imported']} orders";
            if ($result['errors'] > 0) $msg .= " ({$result['errors']} errors)";
            $msg .= '. Future syncs will only pick up orders modified after now.';

            return response()->json([
                'success'      => true,
                'message'      => $msg,
                'result'       => $result,
                'last_sync_at' => WoocommerceSetting::singleton()->fresh()->last_sync_at?->format('d M Y, h:i A'),
            ]);
        } catch (\Throwable $e) {
            \Log::error('OrderCentre resetAndPullLatest failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Reset failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Drawer payload for a single WC order.
     */
    public function getWcOrderDetails($id)
    {
        $order = \App\Models\WcOrder::with(['customer', 'items', 'tags', 'refunds'])->find($id);
        if (!$order) return response()->json(['message' => 'WC order not found'], 404);

        $settings = WoocommerceSetting::singleton();
        $siteUrl  = $settings->site_url ? rtrim($settings->site_url, '/') : null;

        return response()->json([
            'id'                       => $order->id,
            'woocommerce_id'           => $order->woocommerce_id,
            'woocommerce_number'       => $order->woocommerce_number,
            'wc_status'                => $order->wc_status,
            'payment_status'           => $order->payment_status,
            'currency'                 => $order->currency,
            'subtotal'                 => (float) $order->subtotal,
            'shipping_total'           => (float) $order->shipping_total,
            'tax_total'                => (float) $order->tax_total,
            'discount_total'           => (float) $order->discount_total,
            'total'                    => (float) $order->total,
            'refund_total'             => (float) $order->refund_total,
            'refundable_remaining'     => $order->refundableAmount(),
            'payment_method'           => $order->payment_method,
            'payment_method_title'     => $order->payment_method_title,
            'transaction_id'           => $order->transaction_id,
            'customer'                 => [
                'id'         => $order->wc_customer_id,
                'first_name' => $order->customer_first_name,
                'last_name'  => $order->customer_last_name,
                'email'      => $order->customer_email,
                'phone'      => $order->customer_phone,
                'is_guest'   => (bool) ($order->customer?->is_guest ?? true),
            ],
            'billing_address'          => $order->billing_address,
            'shipping_address'         => $order->shipping_address,
            'suburb'                   => $order->suburb,
            'state'                    => $order->state,
            'postcode'                 => $order->postcode,
            'customer_note'            => $order->customer_note,
            'preferred_delivery_date'  => $order->preferred_delivery_date?->toDateString(),
            'wc_date_created'          => $order->wc_date_created?->format('d M Y, h:i A'),
            'wc_date_modified'         => $order->wc_date_modified?->format('d M Y, h:i A'),
            'last_synced_at'           => $order->last_synced_at?->diffForHumans(),
            'wp_admin_link'            => ($siteUrl && $order->woocommerce_id)
                                            ? $siteUrl . '/wp-admin/post.php?post=' . $order->woocommerce_id . '&action=edit'
                                            : null,
            'sync_enabled'             => (bool) $settings->sync_enabled,
            'items'                    => $order->items->map(fn($i) => [
                'id'        => $i->id,
                'name'      => $i->name,
                'sku'       => $i->sku,
                'quantity'  => (float) $i->quantity,
                'price'     => (float) $i->price,
                'subtotal'  => (float) $i->subtotal,
                'total'     => (float) $i->total,
            ]),
            'tags'                     => $order->tags->map(fn($t) => (array) $t),
            'refunds'                  => $order->refunds->map(fn($r) => [
                'id'           => $r->id,
                'amount'       => (float) $r->amount,
                'reason'       => $r->reason,
                'status'       => $r->status,
                'wc_refund_id' => $r->woocommerce_refund_id,
                'refunded_at'  => $r->refunded_at?->format('d M Y, h:i A'),
            ]),
            'notes_count'              => \App\Models\WcOrderNote::where('wc_order_id', $order->id)->count(),
        ]);
    }

    /**
     * Manual edit of an existing WC order. We allow the user to edit the
     * fields the WC REST API supports: status, customer note, preferred
     * delivery date (local-only), billing address, shipping address.
     * If sync_enabled, all changes get pushed back to WC.
     */
    public function updateWcOrderDetails(Request $request, $id)
    {
        $order = \App\Models\WcOrder::find($id);
        if (!$order) return response()->json(['message' => 'WC order not found'], 404);

        $validated = $request->validate([
            'wc_status'                => 'nullable|in:pending,on-hold,processing,completed,cancelled,refunded,failed',
            'customer_note'            => 'nullable|string|max:5000',
            'preferred_delivery_date'  => 'nullable|date',

            // Billing address (also includes contact email/phone — WC stores these on billing)
            'billing'                  => 'nullable|array',
            'billing.first_name'       => 'nullable|string|max:100',
            'billing.last_name'        => 'nullable|string|max:100',
            'billing.email'            => 'nullable|email|max:255',
            'billing.phone'            => 'nullable|string|max:50',
            'billing.address_1'        => 'nullable|string|max:255',
            'billing.address_2'        => 'nullable|string|max:255',
            'billing.city'             => 'nullable|string|max:100',
            'billing.state'            => 'nullable|string|max:50',
            'billing.postcode'         => 'nullable|string|max:20',
            'billing.country'          => 'nullable|string|max:5',

            // Shipping address (no email/phone)
            'shipping'                 => 'nullable|array',
            'shipping.first_name'      => 'nullable|string|max:100',
            'shipping.last_name'       => 'nullable|string|max:100',
            'shipping.address_1'       => 'nullable|string|max:255',
            'shipping.address_2'       => 'nullable|string|max:255',
            'shipping.city'            => 'nullable|string|max:100',
            'shipping.state'           => 'nullable|string|max:50',
            'shipping.postcode'        => 'nullable|string|max:20',
            'shipping.country'         => 'nullable|string|max:5',
        ]);

        $oldStatus = $order->wc_status;

        // Status / note / delivery date
        if (isset($validated['wc_status'])) {
            $order->wc_status = $validated['wc_status'];
            $order->payment_status = (new \App\Services\WooCommerceService())->mapWcStatusToPayment($validated['wc_status']);
        }
        if (array_key_exists('customer_note', $validated)) {
            $order->customer_note = $validated['customer_note'];
        }
        if (array_key_exists('preferred_delivery_date', $validated)) {
            $order->preferred_delivery_date = $validated['preferred_delivery_date'];
        }

        // Billing address — merge with existing snapshot so we don't lose other fields
        if (!empty($validated['billing'])) {
            $existingBilling = is_array($order->billing_address) ? $order->billing_address : [];
            $newBilling = array_merge($existingBilling, array_filter($validated['billing'], fn($v) => $v !== null));
            $order->billing_address = $newBilling;

            // Mirror to denormalized snapshot columns for fast filtering
            $order->customer_first_name = $newBilling['first_name'] ?? $order->customer_first_name;
            $order->customer_last_name  = $newBilling['last_name']  ?? $order->customer_last_name;
            $order->customer_email      = $newBilling['email']      ?? $order->customer_email;
            $order->customer_phone      = $newBilling['phone']      ?? $order->customer_phone;
        }

        // Shipping address
        if (!empty($validated['shipping'])) {
            $existingShipping = is_array($order->shipping_address) ? $order->shipping_address : [];
            $newShipping = array_merge($existingShipping, array_filter($validated['shipping'], fn($v) => $v !== null));
            $order->shipping_address = $newShipping;

            // Mirror suburb/state/postcode for fast filtering
            $order->suburb   = $newShipping['city']     ?? $order->suburb;
            $order->state    = $newShipping['state']    ?? $order->state;
            $order->postcode = $newShipping['postcode'] ?? $order->postcode;
        }

        $order->save();

        \App\Models\WcOrderAuditLog::record(
            $order->id, 'wc_local_edit',
            'Order edited locally: ' . implode(', ', array_keys(array_filter($validated))),
            ['changed' => array_keys(array_filter($validated))]
        );

        // Push back to WC if write mode is on AND something pushable changed
        $pushed = false;
        $settings = WoocommerceSetting::singleton();
        $hasAddressChange = !empty($validated['billing']) || !empty($validated['shipping']);
        $hasPushableChange = ($oldStatus !== $order->wc_status) || $hasAddressChange || array_key_exists('customer_note', $validated);

        if ($settings->sync_enabled && $hasPushableChange) {
            try {
                (new \App\Services\WooCommerceService())->pushOrderUpdate($order);
                $pushed = true;
            } catch (\Throwable $e) {
                \Log::warning("WC push after local edit failed: " . $e->getMessage());
                return response()->json([
                    'message'       => 'Saved locally, but push to WC failed: ' . $e->getMessage(),
                    'pushed_to_wc'  => false,
                ], 200);
            }
        }

        return response()->json([
            'message'        => 'Order updated' . ($pushed ? ' and pushed to WooCommerce' : ' (local only — write mode is off)'),
            'pushed_to_wc'   => $pushed,
        ]);
    }

    /**
     * Push current WC order's status back to WC (write mode only).
     * Wraps the service call.
     */
    public function pushOrderToWooCommerce($id)
    {
        $order = \App\Models\WcOrder::find($id);
        if (!$order) return response()->json(['success' => false, 'message' => 'WC order not found'], 404);
        try {
            $result = (new \App\Services\WooCommerceService())->pushOrderUpdate($order);
            return response()->json([
                'success' => true,
                'message' => "Pushed to WooCommerce as '{$result['pushed_status']}'",
                'result'  => $result,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // ----- WC Notes ----------------------------------------------------

    public function getWcNotes($id)
    {
        return response()->json([
            'data' => \App\Models\WcOrderNote::where('wc_order_id', $id)
                ->with('user:id,name,avatar')
                ->orderByDesc('created_at')
                ->get()
                ->map(fn($n) => [
                    'id'         => $n->id,
                    'body'       => $n->body,
                    'created_at' => $n->created_at?->diffForHumans(),
                    'author'     => $n->user?->name ?? 'System',
                    'avatar'     => $n->user?->avatar_url ?? asset('uploads/avatars/default.png'),
                ]),
        ]);
    }

    public function addWcNote(Request $request, $id)
    {
        $order = \App\Models\WcOrder::find($id);
        if (!$order) return response()->json(['message' => 'WC order not found'], 404);

        $validated = $request->validate(['body' => 'required|string|max:5000']);
        $note = \App\Models\WcOrderNote::create([
            'wc_order_id' => $id,
            'user_id'     => auth()->id(),
            'body'        => $validated['body'],
        ]);
        \App\Models\WcOrderAuditLog::record($id, 'wc_note_added', 'Staff note added');
        return response()->json(['message' => 'Note added', 'id' => $note->id]);
    }

    public function deleteWcNote($orderId, $noteId)
    {
        $note = \App\Models\WcOrderNote::where('wc_order_id', $orderId)->find($noteId);
        if (!$note) return response()->json(['message' => 'Note not found'], 404);
        $note->delete();
        \App\Models\WcOrderAuditLog::record($orderId, 'wc_note_deleted', 'Staff note deleted');
        return response()->json(['message' => 'Note deleted']);
    }

    // ----- WC Audit log ------------------------------------------------

    public function getWcAuditLog($id)
    {
        return response()->json([
            'data' => \App\Models\WcOrderAuditLog::where('wc_order_id', $id)
                ->with('user:id,name')
                ->orderByDesc('created_at')
                ->limit(200)
                ->get()
                ->map(fn($a) => [
                    'id'          => $a->id,
                    'action'      => $a->action,
                    'description' => $a->description,
                    'metadata'    => $a->metadata,
                    'author'      => $a->user?->name ?? 'System',
                    'created_at'  => $a->created_at?->format('d M Y, h:i A'),
                ]),
        ]);
    }

    // ----- WC Tags -----------------------------------------------------

    public function attachWcTag(Request $request, $id)
    {
        $order = \App\Models\WcOrder::find($id);
        if (!$order) return response()->json(['message' => 'WC order not found'], 404);

        $request->validate(['tag_id' => 'required|exists:order_tags,id']);
        \DB::table('wc_order_tag')->insertOrIgnore([
            'wc_order_id' => $id,
            'tag_id'      => $request->tag_id,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);

        $tag = OrderTag::find($request->tag_id);
        \App\Models\WcOrderAuditLog::record($id, 'wc_tag_attached', "Tag '{$tag->name}' attached", ['tag_id' => $tag->id]);
        return response()->json(['message' => 'Tag attached', 'tag' => $tag]);
    }

    public function detachWcTag($id, $tagId)
    {
        $order = \App\Models\WcOrder::find($id);
        if (!$order) return response()->json(['message' => 'WC order not found'], 404);

        $tag = OrderTag::find($tagId);
        \DB::table('wc_order_tag')
            ->where('wc_order_id', $id)
            ->where('tag_id', $tagId)
            ->delete();

        if ($tag) {
            \App\Models\WcOrderAuditLog::record($id, 'wc_tag_detached', "Tag '{$tag->name}' removed", ['tag_id' => $tag->id]);
        }
        return response()->json(['message' => 'Tag detached']);
    }

    // ----- WC Refunds --------------------------------------------------

    public function processWcRefund(Request $request, $id)
    {
        $order = \App\Models\WcOrder::find($id);
        if (!$order) return response()->json(['success' => false, 'message' => 'WC order not found'], 404);

        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'reason' => 'nullable|string|max:500',
        ]);

        try {
            $refund = (new \App\Services\WooCommerceService())->processRefund(
                $order,
                (float) $validated['amount'],
                $validated['reason'] ?? null
            );
            return response()->json([
                'success' => true,
                'message' => "Refund of \${$refund->amount} processed",
                'refund'  => [
                    'id'           => $refund->id,
                    'wc_refund_id' => $refund->woocommerce_refund_id,
                    'amount'       => (float) $refund->amount,
                    'status'       => $refund->status,
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        }
    }

    // ----- WC Refund queue dismiss/restore -----------------------------

    public function dismissWcFromRefundQueue($id)
    {
        $order = \App\Models\WcOrder::find($id);
        if (!$order) return response()->json(['message' => 'Order not found'], 404);
        $order->update(['is_dismissed_from_refund_queue' => true]);
        \App\Models\WcOrderAuditLog::record($id, 'wc_refund_queue_dismissed', 'Dismissed from refunds queue');
        return response()->json(['message' => 'Dismissed from queue']);
    }

    public function restoreWcToRefundQueue($id)
    {
        $order = \App\Models\WcOrder::find($id);
        if (!$order) return response()->json(['message' => 'Order not found'], 404);
        $order->update(['is_dismissed_from_refund_queue' => false]);
        \App\Models\WcOrderAuditLog::record($id, 'wc_refund_queue_restored', 'Restored to refunds queue');
        return response()->json(['message' => 'Restored to queue']);
    }

    // ----- WC Customers ------------------------------------------------

    public function getWcCustomersData(Request $request)
    {
        try {
            if (!\Schema::hasTable('wc_customers')) {
                return response()->json([
                    'draw' => (int) $request->input('draw', 0),
                    'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [],
                    'message' => 'WooCommerce tables not yet created. Run: php artisan migrate',
                ]);
            }

            $query = \App\Models\WcCustomer::query()->select('wc_customers.*');

            if ($request->filled('search_text')) {
                $s = $request->search_text;
                $query->where(function ($q) use ($s) {
                    $q->where('email', 'like', "%{$s}%")
                      ->orWhere('first_name', 'like', "%{$s}%")
                      ->orWhere('last_name', 'like', "%{$s}%")
                      ->orWhere('phone', 'like', "%{$s}%");
                });
            }

            return DataTables::of($query)
                ->addIndexColumn()
                ->addColumn('full_name', fn($c) => trim(($c->first_name ?? '') . ' ' . ($c->last_name ?? '')) ?: 'Guest')
                ->addColumn('total_spent_formatted', fn($c) => '$' . number_format((float) ($c->total_spent ?? 0), 2))
                ->addColumn('last_order_human', fn($c) => $c->last_order_at?->diffForHumans() ?? '—')
                ->addColumn('is_guest_label', fn($c) => $c->is_guest ? 'Guest' : 'Registered')
                ->make(true);
        } catch (\Throwable $e) {
            \Log::error('OrderCentre getWcCustomersData failed: ' . $e->getMessage());
            return response()->json([
                'draw' => (int) $request->input('draw', 0),
                'recordsTotal' => 0, 'recordsFiltered' => 0, 'data' => [],
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getWcCustomerProfile($id)
    {
        $customer = \App\Models\WcCustomer::find($id);
        if (!$customer) return response()->json(['message' => 'WC customer not found'], 404);

        $orders = $customer->orders()
            ->orderByDesc('wc_date_created')
            ->limit(20)
            ->get(['id', 'woocommerce_number', 'woocommerce_id', 'wc_status', 'payment_status', 'total', 'preferred_delivery_date', 'wc_date_created', 'suburb'])
            ->map(fn($o) => [
                'id'             => $o->id,
                'woocommerce_id' => $o->woocommerce_id,
                'order_number'   => $o->woocommerce_number,
                'wc_status'      => $o->wc_status,
                'payment_status' => $o->payment_status,
                'total'          => (float) $o->total,
                'created_at'     => $o->wc_date_created?->format('d M Y'),
                'delivery_date'  => $o->preferred_delivery_date?->format('d M Y'),
                'suburb'         => $o->suburb,
            ]);

        return response()->json([
            'customer' => [
                'id'             => $customer->id,
                'woocommerce_id' => $customer->woocommerce_id,
                'name'           => $customer->fullName(),
                'email'          => $customer->email,
                'phone'          => $customer->phone,
                'is_guest'       => $customer->is_guest,
                'created_at'     => $customer->created_at?->format('d M Y'),
                'last_order_at'  => $customer->last_order_at?->format('d M Y'),
                'billing_data'   => $customer->billing_data,
                'shipping_data'  => $customer->shipping_data,
            ],
            'stats' => [
                'total_orders'  => $customer->total_orders,
                'total_spent'   => (float) $customer->total_spent,
                'avg_order'     => $customer->total_orders > 0 ? round((float) $customer->total_spent / $customer->total_orders, 2) : 0,
            ],
            'recent_orders' => $orders,
        ]);
    }

    // ============================================================
    // PHASE 9: TODAY'S DELIVERIES WIDGET
    // ============================================================

    /**
     * Returns orders scheduled for delivery today, grouped for quick action.
     * Used by the floating "Today's Deliveries" widget visible across all tabs.
     */
    public function getTodayDeliveries()
    {
        $today = Carbon::today();

        $orders = Order::with(['user:id,name,email,phone', 'address:id,address,suburb,city,zip_code'])
            ->whereDate('preferred_delivery_date', $today)
            ->whereNotIn('order_status', ['delivered', 'cancelled'])
            ->orderBy('id')
            ->get([
                'id', 'order_number', 'user_id', 'shipping_address_id',
                'order_status', 'payment_status', 'total', 'preferred_delivery_date',
            ]);

        // Grouping the run by suburb has to happen after loading: `address` is
        // an eager-loaded relation, not a joined table, so ordering by
        // "address.suburb" in SQL raised "Unknown column 'address.suburb'" and
        // returned a 500 to the widget. One day of deliveries is a small set,
        // so sorting the collection is cheap and avoids an ambiguous join.
        $orders = $orders
            ->sortBy(fn ($order) => [$order->address?->suburb ?? '', $order->id])
            ->values();

        $payload = $orders->map(function ($o) {
            $a = $o->address;
            return [
                'id'             => $o->id,
                'order_number'   => $o->order_number,
                'order_status'   => $o->order_status,
                'payment_status' => $o->payment_status,
                'total'          => (float) $o->total,
                'customer_name'  => $o->user?->name ?? 'Guest',
                'customer_phone' => $o->user?->phone ?? '',
                'address_line'   => $a ? trim(($a->address ?? '') . ', ' . ($a->suburb ?? '') . ' ' . ($a->zip_code ?? '')) : '',
                'suburb'         => $a?->suburb ?? '',
            ];
        });

        return response()->json([
            'date'   => $today->format('D, d M Y'),
            'count'  => $payload->count(),
            'orders' => $payload,
        ]);
    }

    /**
     * One-click "Mark as Delivered". Sets status=delivered and fires the
     * legacy OrderDelivered event so any downstream side effects happen.
     */
    public function markAsDelivered(Request $request, $id)
    {
        $order = Order::find($id);
        if (!$order) return response()->json(['message' => 'Order not found'], 404);

        if ($order->order_status === 'delivered') {
            return response()->json(['message' => 'Order is already marked as delivered']);
        }

        $alsoMarkPaid = $request->boolean('also_mark_paid');

        try {
            $oldStatus     = $order->order_status;
            $oldPayment    = $order->payment_status;
            $paidJustFlipped = false;

            $order->order_status = 'delivered';

            // DELIVERY=PAYMENT: when admin confirms the customer paid on
            // delivery, flip payment_status to 'paid' in the same save.
            if ($alsoMarkPaid && $order->payment_status === 'pending') {
                $order->payment_status = 'paid';
                $paidJustFlipped = true;
            }

            $order->save();

            // Award rewards if we just flipped to paid, OR if the order was
            // already paid (defensive — catches orders that slipped through
            // the old payment-flip bug).
            if ($paidJustFlipped || $order->payment_status === 'paid') {
                try { $this->awardRewards($order); }
                catch (\Throwable $e) { \Log::error("Mark-delivered award failed for order {$order->id}: " . $e->getMessage()); }
            }

            if (class_exists('\\App\\Events\\OrderDelivered')) {
                try { event(new \App\Events\OrderDelivered($order)); } catch (\Throwable $e) {}
            }

            $msg = "Order #{$order->order_number} marked as delivered";
            if ($paidJustFlipped) $msg .= " and paid";

            return response()->json([
                'message'         => $msg,
                'order_id'        => $order->id,
                'paid_flipped'    => $paidJustFlipped,
                'payment_status'  => $order->payment_status,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Failed: ' . $e->getMessage()], 500);
        }
    }

    // ============================================================
    // PHASE 9: CUSTOMER DRILL-THROUGH
    // ============================================================

    /**
     * Returns a customer profile payload for the drill-through modal:
     * basic info, lifetime stats, addresses, recent orders, entries.
     */
    public function getCustomerProfile($id)
    {
        $user = \App\Models\User::find($id);
        if (!$user) return response()->json(['message' => 'Customer not found'], 404);

        // Lifetime stats
        $orderCount = Order::where('user_id', $id)->count();
        $lifetimeSpend = (float) Order::where('user_id', $id)
            ->where('payment_status', 'paid')->sum('total');
        $lastOrderAt = Order::where('user_id', $id)->latest('created_at')->value('created_at');

        // Temporary entries (live count from entries table if present)
        $tempEntries = 0;
        if (\Schema::hasTable('entries')) {
            $tempEntries = (int) \DB::table('entries')
                ->where('user_id', $id)
                ->where('drawn', 0)
                ->whereIn('purchase_type', ['paid', 'free'])
                ->sum('entry_count');
        }

        // Addresses — include all contact bits so the modal can show full details
        $addresses = [];
        if (\Schema::hasTable('shipping_addresses')) {
            $addresses = \DB::table('shipping_addresses')
                ->where('user_id', $id)
                ->select(['id', 'label', 'full_name', 'phone', 'email', 'address', 'suburb', 'city', 'state', 'zip_code', 'is_default'])
                ->get()
                ->toArray();
        }

        // Recent orders (last 10)
        $recentOrders = Order::with(['address:id,suburb'])
            ->where('user_id', $id)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'order_number', 'order_status', 'payment_status', 'total', 'preferred_delivery_date', 'created_at', 'shipping_address_id'])
            ->map(function ($o) {
                return [
                    'id'             => $o->id,
                    'order_number'   => $o->order_number,
                    'order_status'   => $o->order_status,
                    'payment_status' => $o->payment_status,
                    'total'          => (float) $o->total,
                    'created_at'     => $o->created_at?->format('d M Y'),
                    'delivery_date'  => $o->preferred_delivery_date?->format('d M Y'),
                    'suburb'         => $o->address?->suburb ?? '',
                ];
            });

        return response()->json([
            'user' => [
                'id'                  => $user->id,
                'name'                => $user->name,
                'email'               => $user->email,
                'phone'               => $user->phone,
                'avatar'              => $user->avatar_url ?? asset('uploads/avatars/default.png'),
                'is_guest'            => (bool) ($user->is_guest ?? false),
                'role'                => $user->role ?? null,
                'member_since'        => $user->created_at?->format('d M Y'),
                'permanent_entries'   => (int) ($user->permanent_entries ?? 0),
                'temporary_entries'   => $tempEntries,
                'total_entries'       => (int) ($user->permanent_entries ?? 0) + $tempEntries,
                'loyalty_points'      => (int) ($user->loyalty_points ?? 0),
            ],
            'stats' => [
                'order_count'    => $orderCount,
                'lifetime_spend' => round($lifetimeSpend, 2),
                'last_order_at'  => $lastOrderAt ? Carbon::parse($lastOrderAt)->format('d M Y') : null,
                'avg_order_value' => $orderCount > 0 ? round($lifetimeSpend / max(1, $orderCount), 2) : 0,
            ],
            'addresses'     => $addresses,
            'recent_orders' => $recentOrders,
        ]);
    }

    /**
     * Award loyalty points and permanent giveaway entry for a paid order.
     * Mirrors the legacy private method so behaviour is identical between systems.
     * IDEMPOTENT — guards on existing PointHistory rows.
     */
    private function awardRewards(Order $order)
    {
        $hasRewards = \App\Models\PointHistory::where('order_id', $order->id)
            ->where('user_id', $order->user_id)
            ->where('type', 'purchase')
            ->exists();

        if (!$hasRewards) {
            if (!$order->loyalty_points || $order->loyalty_points <= 0) {
                $order->loyalty_points = round($order->total);
                $order->save();
            }
            app(\App\Services\OrderCompletionService::class)->processLoyaltyPoints($order);
            \Log::info('OrderCentre: rewards awarded for order #' . ($order->order_number ?? $order->id));
        }
    }
}
