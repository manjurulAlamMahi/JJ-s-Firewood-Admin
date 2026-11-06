<?php

namespace App\Http\Controllers\API\Core;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use App\Mail\ScheduleCreatedMail;
use Illuminate\Support\Facades\Mail;
use App\Services\NotificationService;
use App\Services\RepeatDeliveryService;
use App\Notifications\CategoryNotification;
use App\Traits\ApiResponse;
use App\Models\Order;
use App\Models\User;
use Carbon\Carbon;

class ScheduleController extends Controller
{
    use ApiResponse;

    public function __construct(private RepeatDeliveryService $repeatDeliveries)
    {
    }

    /**
     * GET /v2/schedules - the customer's repeat deliveries. Paused ones are
     * included so the app can offer Resume; cancelled ones are not.
     */
    public function index(Request $request)
    {
        try {
            $schedules = Schedule::repeatDeliveries()
                ->with(['product.subcategory', 'shippingAddress', 'order.orderItems', 'order.address'])
                ->where('user_id', $request->user()->id)
                ->whereIn('status', ['active', 'paused'])
                ->orderBy('next_delivery_date')
                ->get()
                ->map(fn (Schedule $schedule) => $this->repeatDeliveryResource($schedule));

            return $this->success($schedules, 'Repeat deliveries retrieved successfully.');
        } catch (\Exception $e) {
            \Log::error('Repeat deliveries failed to load: ' . $e->getMessage());
            return $this->error('Failed to retrieve repeat deliveries.', 500);
        }
    }

    /**
     * GET /schedules - frozen response used by the shipped app's Scheduling screen.
     */
    public function legacyIndex(Request $request)
    {
        try {
            $schedules = Schedule::with(['order.orderItems'])
                ->where('user_id', $request->user()->id)
                ->get()
                ->map(function($schedule) {
                    return [
                        'id' => $schedule->id,
                        'user_id' => $schedule->user_id,
                        'order_id' => $schedule->order_id,
                        'preferred_date' => Carbon::parse($schedule->preferred_date)->format('d/m/Y'),
                        'repeat' => $schedule->repeat,
                        'status' => $schedule->status,
                        'order_number' => $schedule->order->order_number ?? null,
                        'products' => $schedule->order->orderItems->map(function($item) {
                            return [
                                'product_name' => $item->product_name,
                                'quantity' => $item->quantity,
                                'total_price' => $item->subtotal,
                            ];
                        }),
                    ];
                });

            return $this->success($schedules, 'Schedules retrieved successfully.');
        } catch (\Exception $e) {
            return $this->error('Failed to retrieve schedules.', 500);
        }
    }


   public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|exists:orders,id',
            'preferred_date' => 'nullable|date|after_or_equal:today',
            'repeat' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $order = Order::with('user', 'orderItems')->find($request->order_id);

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }


        $schedule = Schedule::create([
            'user_id'        => $order->user_id,
            'order_id'       => $order->id,
        'preferred_date'  => Carbon::createFromFormat('d-m-Y', $request->preferred_date)
                                ->toDateString(),
            'repeat'         => $request->repeat ?? false,
            'status'         => 'active',
        ]);

        $schedule->load('order.orderItems', 'order.user');

        try {
            \Mail::to($order->user->email)
                ->send(new ScheduleCreatedMail($schedule));

            $admins = \App\Models\User::where('is_admin', 1)->pluck('email');

            foreach ($admins as $adminEmail) {
                \Mail::to($adminEmail)
                    ->send(new ScheduleCreatedMail($schedule));
            }

            NotificationService::success(
                $order->user,
                'Order Scheduled',
                "Your order #{$order->order_number} has been scheduled for " . Carbon::parse($schedule->preferred_date)->format('d/m/Y') . "."
            );

            $adminUsers = \App\Models\User::where('is_admin', 1)->get();
            foreach ($adminUsers as $admin) {
                NotificationService::info(
                    $admin,
                    'New Schedule',
                    "Order #{$order->order_number} has been scheduled by {$order->user->name}."
                );
            }

            $order->user->notify(new CategoryNotification('transactional', [
                'subject' => 'Order Scheduled',
                'body' => "Your order #{$order->order_number} has been scheduled for " . Carbon::parse($schedule->preferred_date)->format('d/m/Y') . ".",
                'title' => 'Order Scheduled',
                'data' => [
                    'order_id' => $order->id,
                    'schedule_id' => $schedule->id,
                    'type' => 'order_scheduled'
                ]
            ]));

            foreach ($adminUsers as $admin) {
                $admin->notify(new CategoryNotification('transactional', [
                    'subject' => 'New Order Schedule',
                    'body' => "Order #{$order->order_number} has been scheduled by {$order->user->name}.",
                    'title' => 'Order Scheduled',
                    'data' => [
                        'order_id' => $order->id,
                        'schedule_id' => $schedule->id,
                        'type' => 'admin_order_scheduled'
                    ]
                ]));
            }

        } catch (\Exception $e) {
            \Log::error("Schedule email/notification failed: " . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Schedule created successfully',
            'schedule' => $schedule
        ]);
    }


    /**
     * PUT /v2/schedules/{id} - edit frequency, next delivery date and preferred time.
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'frequency' => 'sometimes|required|string|in:' . implode(',', Schedule::FREQUENCIES),
            'custom_days' => 'nullable|integer|min:1|max:' . Schedule::MAX_CUSTOM_DAYS . '|required_if:frequency,custom',
            'next_delivery_date' => 'sometimes|required|date|after_or_equal:today',
            'preferred_time' => 'sometimes|required|string|max:100',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors(), $validator->errors()->first());
        }

        $changedOrderDate = null;

        $schedule = DB::transaction(function () use ($request, $id, &$changedOrderDate) {
            $schedule = $this->findRepeatDelivery($request, $id);

            if ($schedule->status === 'cancelled') {
                return null;
            }

            $previousDate = $schedule->next_delivery_date->copy();
            $schedule->fill($request->only(['frequency', 'preferred_time', 'next_delivery_date']));

            if ($schedule->frequency === 'custom') {
                $schedule->custom_days = $request->input('custom_days', $schedule->custom_days);
            } else {
                $schedule->custom_days = null;
            }

            if ($schedule->frequency === 'custom' && !$schedule->custom_days) {
                abort($this->error('Please choose how many days between deliveries.', 422));
            }

            $schedule->save();

            if (!$schedule->next_delivery_date->isSameDay($previousDate)) {
                $changedOrderDate = $this->moveUndeliveredOrder($schedule, $previousDate) ? $previousDate : null;
            }

            return $schedule;
        });

        if (!$schedule) {
            return $this->error('This repeat delivery has been cancelled.', 422);
        }

        if ($changedOrderDate) {
            event(new \App\Events\OrderDeliveryDateUpdated(
                $schedule->order,
                $changedOrderDate->toDateString(),
                $schedule->next_delivery_date->toDateString()
            ));
        }

        return $this->success([
            'id' => $schedule->id,
            'status' => $schedule->status,
            'frequency' => $schedule->frequency,
            'next_delivery_date' => $schedule->next_delivery_date->toDateString(),
            'preferred_time' => $schedule->preferred_time,
        ], 'Repeat delivery updated successfully.');
    }

    /**
     * POST /schedules/update/{id} - frozen behaviour used by the shipped app.
     */
    public function legacyUpdate(Request $request, $id)
    {
        $schedule = Schedule::where('user_id', $request->user()->id)->findOrFail($id);


        if ($request->has('repeat')) {
            $request->merge([
                'repeat' => filter_var($request->repeat, FILTER_VALIDATE_BOOLEAN)
            ]);
        }

        $validator = Validator::make($request->all(), [
            'preferred_date' => 'date|after_or_equal:today',
            'repeat' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $order = $schedule->order;
        $oldDate = $schedule->preferred_date?->toDateString();
        $newDate = $request->preferred_date;

        $schedule->update($request->only(['preferred_date', 'repeat']));

        if ($request->has('preferred_date') && $order) {
            $order->preferred_delivery_date = $newDate;
            $order->save();

            event(new \App\Events\OrderDeliveryDateUpdated($order, $oldDate, $newDate));
        }

        return response()->json([
            'success' => true,
            'schedule' => $schedule
        ]);
    }

    /**
     * POST /v2/schedules/{id}/skip - skips only the upcoming delivery.
     */
    public function skip(Request $request, $id)
    {
        $previousDate = null;

        $schedule = DB::transaction(function () use ($request, $id, &$previousDate) {
            $schedule = $this->findRepeatDelivery($request, $id);

            if ($schedule->status !== 'active') {
                return null;
            }

            $previousDate = $schedule->next_delivery_date->copy();
            $schedule->next_delivery_date = $schedule->nextOccurrenceAfter($previousDate);
            $schedule->save();

            $this->moveUndeliveredOrder($schedule, $previousDate);

            return $schedule;
        });

        if (!$schedule) {
            return $this->error('Only an active repeat delivery can be skipped.', 422);
        }

        $this->repeatDeliveries->notifySkipped($schedule, $previousDate->format('D j M Y'));

        return $this->success([
            'id' => $schedule->id,
            'previous_delivery_date' => $previousDate->toDateString(),
            'next_delivery_date' => $schedule->next_delivery_date->toDateString(),
            'next_delivery_date_formatted' => $schedule->next_delivery_date_formatted,
        ], "Next delivery skipped successfully. Your repeat schedule will continue on {$schedule->next_delivery_date_formatted}.");
    }

    /**
     * POST /v2/schedules/{id}/pause
     */
    public function pause(Request $request, $id)
    {
        $schedule = DB::transaction(function () use ($request, $id) {
            $schedule = $this->findRepeatDelivery($request, $id);

            if ($schedule->status !== 'active') {
                return null;
            }

            $schedule->update(['status' => 'paused']);

            return $schedule;
        });

        if (!$schedule) {
            return $this->error('Only an active repeat delivery can be paused.', 422);
        }

        $this->repeatDeliveries->notifyPaused($schedule);

        return $this->success([
            'id' => $schedule->id,
            'status' => $schedule->status,
        ], 'Repeat delivery has been paused.');
    }

    /**
     * POST /v2/schedules/{id}/resume - a date that passed while paused rolls
     * forward to the next occurrence on the same cycle.
     */
    public function resume(Request $request, $id)
    {
        $schedule = DB::transaction(function () use ($request, $id) {
            $schedule = $this->findRepeatDelivery($request, $id);

            if ($schedule->status !== 'paused') {
                return null;
            }

            $schedule->status = 'active';

            if ($schedule->next_delivery_date->lt(today())) {
                $schedule->next_delivery_date = $schedule->nextOccurrenceAfter($schedule->next_delivery_date);
            }

            $schedule->save();

            return $schedule;
        });

        if (!$schedule) {
            return $this->error('Only a paused repeat delivery can be resumed.', 422);
        }

        return $this->success([
            'id' => $schedule->id,
            'status' => $schedule->status,
            'next_delivery_date' => $schedule->next_delivery_date->toDateString(),
            'next_delivery_date_formatted' => $schedule->next_delivery_date_formatted,
        ], 'Repeat delivery has been resumed.');
    }

    /**
     * POST /v2/schedules/{id}/cancel
     */
    public function cancel(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator->errors(), $validator->errors()->first());
        }

        $schedule = DB::transaction(function () use ($request, $id) {
            $schedule = $this->findRepeatDelivery($request, $id);

            if ($schedule->status === 'cancelled') {
                return null;
            }

            $schedule->update([
                'status' => 'cancelled',
                'cancel_reason' => $request->input('reason'),
                'cancelled_at' => now(),
            ]);

            return $schedule;
        });

        if (!$schedule) {
            return $this->error('This repeat delivery has already been cancelled.', 422);
        }

        $this->repeatDeliveries->notifyCancelled($schedule);

        return $this->success([
            'id' => $schedule->id,
            'status' => $schedule->status,
            'cancelled_at' => $schedule->cancelled_at->toIso8601ZuluString(),
        ], 'Repeat delivery has been cancelled. No future deliveries will be scheduled.');
    }

    public function destroy(Request $request, $id)
    {
        $schedule = Schedule::where('user_id', $request->user()->id)->findOrFail($id);
        $schedule->delete();

        return response()->json([
            'success' => true,
            'message' => 'Schedule deleted successfully'
        ]);
    }

    private function findRepeatDelivery(Request $request, $id): Schedule
    {
        return Schedule::repeatDeliveries()
            ->where('user_id', $request->user()->id)
            ->lockForUpdate()
            ->findOrFail($id);
    }

    /**
     * The first delivery is a real order, so when its date moves the order has
     * to move too or it would still go out on the old date.
     */
    private function moveUndeliveredOrder(Schedule $schedule, Carbon $previousDate): bool
    {
        $order = $schedule->order;

        if (
            !$order
            || !$order->preferred_delivery_date?->isSameDay($previousDate)
            || in_array($order->order_status, ['delivered', 'completed', 'cancelled'], true)
        ) {
            return false;
        }

        $order->preferred_delivery_date = $schedule->next_delivery_date;
        $order->save();

        return true;
    }

    private function repeatDeliveryResource(Schedule $schedule): array
    {
        $product = $schedule->product;
        $item = $schedule->order?->orderItems->first();
        $productName = $product->name ?? $item?->product_name;
        $address = $schedule->shippingAddress ?? $schedule->order?->address;

        return [
            'id' => $schedule->id,
            'status' => $schedule->status,
            'product' => [
                'id' => $schedule->product_id,
                'name' => $product?->subcategory?->name ?? $productName,
                'load_size' => $this->loadSizeLabel($schedule, $productName),
                'price' => number_format((float) $schedule->unit_price, 2, '.', ''),
                'image_url' => $product?->image ?? image_asset(),
            ],
            'frequency' => $schedule->frequency,
            'custom_days' => $schedule->custom_days,
            'frequency_label' => $schedule->frequency_label,
            'next_delivery_date' => $schedule->next_delivery_date->toDateString(),
            'next_delivery_date_formatted' => $schedule->next_delivery_date_formatted,
            'preferred_time' => $schedule->preferred_time,
            'delivery_address' => $address ? [
                'id' => $address->id,
                'full_address' => $this->fullAddress($address),
            ] : null,
            'payment_method' => $this->paymentMethodLabel($schedule->order?->payment_method),
            'created_at' => $schedule->created_at?->toIso8601ZuluString(),
        ];
    }

    private function loadSizeLabel(Schedule $schedule, ?string $productName): ?string
    {
        if (Schedule::tonnesFromProductName($productName) === null) {
            return $productName;
        }

        return rtrim(rtrim(number_format((float) $schedule->load_size, 2, '.', ''), '0'), '.') . ' Tonne';
    }

    private function fullAddress($address): string
    {
        $locality = trim(implode(' ', array_filter([
            $address->suburb ?: $address->city,
            $address->state,
            $address->zip_code,
        ])));

        return implode(', ', array_filter([$address->address, $locality]));
    }

    private function paymentMethodLabel(?string $method): ?string
    {
        return match ($method) {
            null => null,
            'square' => 'Card',
            'paypal' => 'PayPal',
            'bank_transfer' => 'Bank Transfer',
            'cash_on_delivery' => 'Cash on Delivery',
            default => ucwords(str_replace('_', ' ', $method)),
        };
    }
}
