<?php

namespace App\Http\Controllers\Web\Backend\Core;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Order;
use App\Models\Entry;
use App\Models\PointHistory;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\Facades\DataTables;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use App\Services\SquarePaymentService;


class OrderController extends Controller
{


    public function index()
    {
        $statusCounts = [
            'pending' => Order::where('order_status', 'pending')->count(),
            'confirmed' => Order::where('order_status', 'confirmed')->count(),
            'processing' => Order::where('order_status', 'processing')->count(),
            'shipped' => Order::where('order_status', 'shipped')->count(),
            'delivered' => Order::where('order_status', 'delivered')->count(),
            'cancelled' => Order::where('order_status', 'cancelled')->count(),
        ];

        $tabs = [
            'all' => Order::count(),
            'new_orders' => $statusCounts['pending'],
            'scheduled' => Order::whereNotNull('preferred_delivery_date')->where('preferred_delivery_date', '>', now())->count(),
            'out_for_delivery' => $statusCounts['shipped'],
            'overdue' => Order::where('order_status', '!=', 'delivered')->where('order_status', '!=', 'cancelled')->where('preferred_delivery_date', '<', now())->count(),
            'completed' => $statusCounts['delivered'],
            'cancelled' => $statusCounts['cancelled'],
        ];

        return view('backend.layouts.order.index', compact('statusCounts', 'tabs'));
    }


    public function getData(Request $request)
    {
        $orders = Order::with(['user', 'address', 'payment', 'orderItems'])
            ->select('orders.*')
            ->orderBy('created_at', 'desc');

        if ($request->has('payment_status') && !empty($request->payment_status)) {
            $orders->whereIn('payment_status', explode(',', $request->payment_status));
        }

        if ($request->filled('order_value_min')) {
            $orders->where('total', '>=', $request->order_value_min);
        }
        if ($request->filled('order_value_max')) {
            $orders->where('total', '<=', $request->order_value_max);
        }

        if ($request->filled('start_date')) {
            $orders->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $orders->whereDate('created_at', '<=', $request->end_date);
        }

        if ($request->filled('date_filter')) {
            $filter = $request->date_filter;
            if ($filter === 'today') {
                $orders->whereDate('created_at', today());
            } elseif ($filter === 'tomorrow') {
                $orders->whereDate('created_at', today()->addDay());
            } elseif ($filter === 'this_week') {
                $orders->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
            }
        }

        if ($request->filled('tab_status')) {
            $tab = $request->tab_status;
            if ($tab === 'new_orders') $orders->where('order_status', 'pending');
            elseif ($tab === 'out_for_delivery') $orders->where('order_status', 'shipped');
            elseif ($tab === 'completed') $orders->where('order_status', 'delivered');
            elseif ($tab === 'cancelled') $orders->where('order_status', 'cancelled');
            elseif ($tab === 'overdue') $orders->where('order_status', '!=', 'delivered')->where('order_status', '!=', 'cancelled')->where('preferred_delivery_date', '<', now());
            elseif ($tab === 'scheduled') $orders->whereNotNull('preferred_delivery_date')->where('preferred_delivery_date', '>', now());
        }

        return DataTables::of($orders)
            ->addIndexColumn()
            ->filter(function ($query) use ($request) {
                if ($request->has('search') && isset($request->search['value']) && !empty($request->search['value'])) {
                    $search = $request->search['value'];
                    $query->where(function ($q) use ($search) {
                        $q->where('order_number', 'like', "%{$search}%")
                            ->orWhere('id', 'like', "%{$search}%")
                            ->orWhereHas('user', function ($userQ) use ($search) {
                                $userQ->where('name', 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%")
                                    ->orWhere('phone', 'like', "%{$search}%");
                            });
                    });
                }
            })
            ->addColumn('customer', function ($o) {
                return [
                    'name' => $o->user?->name ?? 'Guest',
                    'email' => $o->user?->email,
                    'phone' => $o->user?->phone,
                    'avatar' => $o->user?->avatar_url ?? asset('uploads/avatars/default.png')
                ];
            })
            ->addColumn('suburb', fn($o) => $o->address?->suburb ?? 'N/A')
            ->addColumn('total_formatted', fn($o) => number_format($o->total, 2))
            ->addColumn('refunded_amount', function ($o) {
                $latestPayment = $o->payment?->sortByDesc('created_at')->first();
                return (float)($latestPayment?->refund_amount ?? 0);
            })
            ->addColumn('remaining_balance', function ($o) {
                $latestPayment = $o->payment?->sortByDesc('created_at')->first();
                $refunded = (float)($latestPayment?->refund_amount ?? 0);
                if ($latestPayment && isset($latestPayment->remaining_amount)) {
                    return (float)$latestPayment->remaining_amount;
                }
                return (float)$o->total - $refunded;
            })
            ->addColumn('created_at_human', fn($o) => $o->created_at->diffForHumans())
            ->addColumn('items_count', fn($o) => $o->orderItems->count())
            ->make(true);
    }


    public function updateStatus(Request $request, $id)
    {
        $request->validate([
            'order_status' => 'required|in:pending,confirmed,processing,shipped,delivered,cancelled'
        ]);

        $order = Order::find($id);
        if (!$order)
            return response()->json(['message' => 'Order not found'], 404);

        $old = $order->order_status;
        $new = $request->order_status;

        if ($old === $new)
            return response()->json(['message' => "Order is already '{$new}'"]);

        DB::transaction(function () use ($order, $new) {
            $order->order_status = $new;
            $order->save();

            if ($new === 'cancelled') {
                event(new \App\Events\OrderCancelled($order));
            } elseif ($new === 'delivered') {
                event(new \App\Events\OrderDelivered($order));
            }
        });

        return response()->json(['message' => "Order status updated from '{$old}' to '{$new}'"]);
    }


    public function updatePaymentStatus(Request $request, $id)
    {
        $request->validate([
            'payment_status' => 'required|in:pending,paid,failed'
        ]);

        $order = Order::find($id);
        if (!$order) return response()->json(['message' => 'Order not found'], 404);

        $old = $order->payment_status;
        $new = $request->payment_status;

        DB::transaction(function () use ($order, $new) {
            $order->payment_status = $new;
            if ($new === 'paid' && $order->order_status === 'pending') {
                $order->order_status = 'processing';
            }
            $order->save();

            if ($new === 'paid') {
                $this->awardRewards($order);

                if (!$order->payment()->exists()) {
                    \App\Models\Payment::create([
                        'order_id' => $order->id,
                        'user_id' => $order->user_id,
                        'amount' => $order->total,
                        'status' => 'completed',
                        'payment_method' => 'manual',
                        'refund_status' => 'none',
                        'currency' => 'AUD'
                    ]);
                }
            }
        });

        $msg = "Payment status updated from '{$old}' to '{$new}'";
        if ($new === 'paid' && $old === 'pending') $msg .= " and order status changed to confirmed";

        return response()->json(['message' => $msg]);
    }

    public function edit($id)
    {
        $order = Order::with('user')->find($id);
        if (!$order) abort(404, 'Order not found');
        $users = User::select('id', 'name', 'email')->get();

        return view('backend.layouts.order.edit', compact('order', 'users'));
    }

    public function update(Request $request, $id)
    {
        $order = Order::findOrFail($id);

        $validated = $request->validate([
            'user_id'                => 'nullable|exists:users,id',
            'subtotal'               => 'nullable|numeric|min:0',
            'delivery_fee'           => 'nullable|numeric|min:0',
            'discount'               => 'nullable|numeric|min:0',
            'tax'                    => 'nullable|numeric|min:0',
            'total'                  => 'nullable|numeric|min:0',
            'payment_method'         => 'nullable|string',
            'payment_status'         => 'nullable|string',
            'order_status'           => 'nullable|string',
            'preferred_delivery_date' => 'nullable|date',
            'notes'                  => 'nullable|string|max:1000',
        ]);

        try {
            $oldPaymentStatus = $order->payment_status;
            $oldStatus = $order->order_status;
            $oldDate = $order->preferred_delivery_date ? $order->preferred_delivery_date->toDateString() : null;

            $order->update($validated);

            $newDate = $order->preferred_delivery_date ? $order->preferred_delivery_date->toDateString() : null;
            $newPaymentStatus = $order->payment_status;
            $newStatus = $order->order_status;

            if ($newPaymentStatus === 'paid' && $oldPaymentStatus !== 'paid') {
                $this->awardRewards($order);
            }

            if ($newStatus !== $oldStatus) {
                if ($newStatus === 'delivered') {
                    event(new \App\Events\OrderDelivered($order));
                } elseif ($newStatus === 'cancelled') {
                    event(new \App\Events\OrderCancelled($order));
                }
            }

            if ($oldDate !== $newDate) {
                event(new \App\Events\OrderDeliveryDateUpdated($order, $oldDate, $newDate));
            }

            if (in_array($newPaymentStatus, ['refunded', 'partially_refunded']) && $newPaymentStatus !== $oldPaymentStatus) {
                $payment = $order->payment()->latest()->first();
                if ($payment) {
                    event(new \App\Events\PaymentRefunded($payment));
                }
            }

            return redirect()
                ->route('order.index')
                ->with('success', 'Order updated successfully!');
        } catch (\Exception $e) {
            Log::error('Order update failed: ' . $e->getMessage());
            return back()->with('error', 'Something went wrong. Please try again.');
        }
    }

    public function destroy($id)
    {
        $order = Order::find($id);
        if (!$order) return response()->json(['message' => 'Order not found'], 404);

        if ($order->order_status !== 'cancelled')
            return response()->json(['message' => 'Only cancelled orders can be deleted'], 400);

        $order->delete();
        return response()->json(['message' => "Order #{$order->order_number} deleted"]);
    }

    public function updateDeliveryDate(Request $request, $id)
    {
        $request->validate([
            'preferred_delivery_date' => 'required|date'
        ]);

        $order = Order::find($id);
        if (!$order) return response()->json(['message' => 'Order not found'], 404);

        $oldDate = $order->preferred_delivery_date ? $order->preferred_delivery_date->toDateString() : null;
        $newDate = $request->preferred_delivery_date;

        $order->preferred_delivery_date = $newDate;

        // Mark the delivery date as admin-confirmed — this is the signal that
        // drives Needs Action. Any admin manually setting a date counts as
        // confirmation, regardless of whether it came via Setmore or by hand.
        // (Safe-guarded so legacy databases without the column don't error.)
        if (\Schema::hasColumn('orders', 'delivery_date_confirmed_at')) {
            $order->delivery_date_confirmed_at = now();
        }

        $order->save();

        event(new \App\Events\OrderDeliveryDateUpdated($order, $oldDate, $newDate));

        return response()->json(['message' => "Delivery date updated to {$order->preferred_delivery_date}"]);
    }

    public function cancelledOrders()
    {
        return view('backend.layouts.order.cancelled');
    }


    public function getCancelledData()
    {
        $orders = Order::with(['user', 'payment'])
            ->where('order_status', 'cancelled')
            ->whereIn('payment_status', ['paid', 'partially_refunded', 'refunded'])
            ->select('orders.*')
            ->orderBy('created_at', 'desc');

        return DataTables::of($orders)
            ->addIndexColumn()
            ->addColumn('id', fn($o) => $o->id)
            ->addColumn('order_number', fn($o) => $o->order_number)
            ->addColumn('user_name', fn($o) => $o->user?->name ?? 'N/A')
            ->addColumn('user_email', fn($o) => $o->user?->email ?? 'N/A')
            ->addColumn('total', fn($o) => $o->total)
            ->addColumn('cancelled_at', fn($o) => $o->cancellation_requested_at?->format('d M Y, h:i A') ?? $o->updated_at->format('d M Y, h:i A'))
            ->addColumn('payment_id', function ($o) {
                $latestPayment = $o->payment?->sortByDesc('created_at')->first();
                return $latestPayment?->payment_id ?? 'N/A';
            })
            ->addColumn('refund_status', function ($o) {
                $latestPayment = $o->payment?->sortByDesc('created_at')->first();
                return $latestPayment?->refund_status ?? 'none';
            })
            ->addColumn('refunded_amount', function ($o) {
                $latestPayment = $o->payment?->sortByDesc('created_at')->first();
                return $latestPayment?->refund_amount ?? 0;
            })
            ->addColumn('remaining_balance', function ($o) {
                $latestPayment = $o->payment?->sortByDesc('created_at')->first();
                $refunded = (float)($latestPayment?->refund_amount ?? 0);

                if ($latestPayment && isset($latestPayment->remaining_amount)) {
                    return (float)$latestPayment->remaining_amount;
                }
                return (float)$o->total - $refunded;
            })
            ->addColumn('action', function ($o) {
                return '<button class="btn btn-sm btn-primary refund-btn"
                            data-order-id="' . $o->id . '"
                            data-total="' . $o->total . '">
                        Process Refund
                    </button>';
            })
            ->rawColumns(['action'])
            ->make(true);
    }



    public function processRefund(Request $request, $id)
    {
        $request->validate([
            'refund_amount' => 'required|numeric|min:0.01',
            'refund_type' => 'required|in:full,partial',
            'reason' => 'nullable|string|max:500'
        ]);

        $order = Order::with('payment')->findOrFail($id);

        $isStripe = strtolower($order->payment_method) === 'stripe';
        if (!$isStripe && $order->transaction_id && (str_starts_with($order->transaction_id, 'pi_') || str_starts_with($order->transaction_id, 'ch_'))) {
            $isStripe = true;
        }

        if ($isStripe) {
            return $this->stripeRefund($request, $id);
        }

        DB::beginTransaction();

        try {
            $order = Order::with('payment')->findOrFail($id);

            if ($order->order_status !== 'cancelled') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only cancelled orders can be refunded'
                ], 400);
            }

            $payment = $order->payment->sortByDesc('created_at')->first();

            if (!$payment) {
                $payment = \App\Models\Payment::create([
                    'order_id' => $order->id,
                    'user_id' => $order->user_id,
                    'amount' => $order->total,
                    'remaining_amount' => $order->total,
                    'status' => 'completed',
                    'payment_method' => 'manual',
                    'refund_status' => 'none',
                    'currency' => 'AUD'
                ]);
            }

            if ($payment->isFullyRefunded()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This order is already fully refunded'
                ], 400);
            }

            $refundableAmount = (float)$order->total - (float)($payment->refund_amount ?? 0);
            if ($request->refund_amount > ($refundableAmount + 0.01)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Refund amount cannot exceed remaining balance of $' . number_format($refundableAmount, 2)
                ], 400);
            }

            $result = ['success' => true, 'refund_id' => 'manual_' . time()];

            if ($payment->payment_id && in_array($payment->payment_method, ['square', 'card'])) {
                $squareService = app(SquarePaymentService::class);
                $result = $squareService->refundPayment(
                    $payment->payment_id,
                    $request->refund_amount,
                    $payment->currency ?? 'AUD',
                    $request->reason ?? 'Order cancelled - Admin approved refund'
                );
            }

            if ($result['success']) {
                $refundAmount = $payment->refund_amount ?? 0;
                $totalRefunded = $refundAmount + $request->refund_amount;

                $refundStatus = 'partial';
                if (abs($totalRefunded - (float)$order->total) < 0.01) {
                    $refundStatus = 'full';
                }

                $payment->update([
                    'refund_id' => $result['refund_id'],
                    'refund_amount' => $totalRefunded,
                    'remaining_amount' => (float)$order->total - (float)$totalRefunded,
                    'refund_status' => $refundStatus,
                    'refund_reason' => $request->reason,
                    'refunded_at' => now()
                ]);

                $order->update([
                    'payment_status' => $refundStatus === 'full' ? 'refunded' : 'partially_refunded',
                    'refund_processed_at' => now(),
                    'refund_processed_by' => auth()->id()
                ]);

                event(new \App\Events\PaymentRefunded($payment));

                DB::commit();

                Log::info('Admin processed refund', [
                    'admin_id' => auth()->id(),
                    'order_id' => $order->id,
                    'refund_amount' => $request->refund_amount,
                    'refund_type' => $refundStatus,
                    'refund_id' => $result['refund_id']
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Refund processed successfully',
                    'refund' => [
                        'refund_id' => $result['refund_id'],
                        'amount' => $request->refund_amount,
                        'type' => $refundStatus,
                        'status' => $result['refund']['status'] ?? 'PENDING'
                    ]
                ]);
            }

            DB::rollBack();

            Log::error('Admin refund failed', [
                'admin_id' => auth()->id(),
                'order_id' => $order->id,
                'error' => $result['message'] ?? 'Unknown error'
            ]);

            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Refund failed'
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Admin refund exception', [
                'admin_id' => auth()->id(),
                'order_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Refund error: ' . $e->getMessage()
            ], 500);
        }
    }


    public function stripeRefund(Request $request, $id)
    {
        $request->validate([
            'refund_amount' => 'required|numeric|min:0.01',
            'reason' => 'nullable|string|max:500'
        ]);

        DB::beginTransaction();

        try {
            $order = Order::with('payment')->findOrFail($id);

            $paymentIntentId = $order->transaction_id;

            if (!$paymentIntentId) {
                return response()->json([
                    'success' => false,
                    'message' => 'No payment_intent_id found for this order.',
                ], 400);
            }

            \Stripe\Stripe::setApiKey(env('STRIPE_SECRET_KEY'));

            $amountCents = (int)($request->refund_amount * 100);

            $refund = \Stripe\Refund::create([
                'payment_intent' => $paymentIntentId,
                'amount' => $amountCents,
                'metadata' => [
                    'reason' => $request->reason ?? 'Admin requested refund'
                ]
            ]);

            $payment = $order->payment->sortByDesc('created_at')->first();

            if ($payment) {
                $refundAmount = $payment->refund_amount ?? 0;
                $totalRefunded = $refundAmount + $request->refund_amount;

                $refundStatus = 'partial';
                if (abs($totalRefunded - $payment->amount) < 0.01) {
                    $refundStatus = 'full';
                }

                $payment->update([
                    'refund_id' => $refund->id,
                    'refund_amount' => $totalRefunded,
                    'remaining_amount' => (float)$payment->amount - (float)$totalRefunded,
                    'refund_status' => $refundStatus,
                    'refund_reason' => $request->reason,
                    'refunded_at' => now()
                ]);

                event(new \App\Events\PaymentRefunded($payment));

                $order->update([
                    'payment_status' => $refundStatus === 'full' ? 'refunded' : 'partially_refunded',
                    'refund_processed_at' => now(),
                    'refund_processed_by' => auth()->id()
                ]);
            }

            DB::commit();

            Log::info('Admin processed Stripe refund', [
                'admin_id' => auth()->id(),
                'order_id' => $order->id,
                'refund_amount' => $request->refund_amount,
                'refund_id' => $refund->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Order refunded successfully!',
                'refund' => $refund,
            ]);
        } catch (\Stripe\Exception\ApiErrorException $e) {
            DB::rollBack();

            Log::error('Stripe refund API error', [
                'order_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Stripe refund failed: ' . $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Stripe refund exception', [
                'order_id' => $id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Something went wrong: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function show($id)
    {
        $order = Order::with(['user', 'address', 'orderItems.product', 'payment', 'coupon', 'deliveryType'])->findOrFail($id);

        if (request()->ajax() || request()->wantsJson()) {
            return response()->json($order);
        }

        return view('backend.layouts.order.show', compact('order'));
    }


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
            Log::info('Rewards awarded manually for order #' . $order->order_number);
        }
    }
}
