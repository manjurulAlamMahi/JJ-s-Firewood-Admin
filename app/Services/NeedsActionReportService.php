<?php

namespace App\Services;

use App\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Produces the list of orders that need admin interaction — the same set the
 * Order Centre "Needs Action" tab shows — for inclusion in the daily digest
 * email.
 *
 * The query and the per-order reason logic are deliberately kept in lock-step
 * with OrderCentreController::getNeedsActionData() so the email matches what
 * the admin sees on screen. If that controller's criteria change, mirror the
 * change here.
 */
class NeedsActionReportService
{
    /**
     * Return outstanding orders needing admin attention.
     *
     * @return array<int,array{
     *     order_number:string,
     *     customer:string,
     *     suburb:string,
     *     total:string,
     *     delivery_date:string,
     *     reasons:string
     * }>
     */
    public function getOutstanding(): array
    {
        $today = Carbon::today();
        $hasConfirmCol  = Schema::hasColumn('orders', 'delivery_date_confirmed_at');
        $hasDismissCol  = Schema::hasColumn('orders', 'needs_action_dismissed_at');

        $query = Order::with(['user', 'address'])
            ->select('orders.*')
            ->where(function ($q) use ($hasConfirmCol) {
                $q->where('order_status', 'pending')                               // never confirmed
                  ->orWhere(function ($q2) {                                        // overdue
                      $q2->whereNotIn('order_status', ['delivered', 'cancelled'])
                         ->whereNotNull('preferred_delivery_date')
                         ->whereDate('preferred_delivery_date', '<', Carbon::today());
                  })
                  ->orWhere(function ($q3) {                                        // paid, no date scheduled
                      $q3->where('payment_status', 'paid')
                         ->whereNull('preferred_delivery_date')
                         ->whereNotIn('order_status', ['delivered', 'cancelled']);
                  });

                if ($hasConfirmCol) {                                              // paid, date not confirmed
                    $q->orWhere(function ($q4) {
                        $q4->where('payment_status', 'paid')
                           ->whereNull('delivery_date_confirmed_at')
                           ->whereNotIn('order_status', ['delivered', 'cancelled']);
                    });
                }
            });

        // Exclude orders the admin already dismissed from Needs Action.
        if ($hasDismissCol) {
            $query->whereNull('needs_action_dismissed_at');
        }

        $query->orderByRaw("CASE
                WHEN order_status='pending' THEN 1
                WHEN preferred_delivery_date IS NOT NULL AND preferred_delivery_date < CURDATE() THEN 2
                ELSE 3 END")
              ->orderBy('created_at', 'asc');

        $orders = $query->get();

        return $orders->map(function (Order $o) use ($today, $hasConfirmCol) {
            return [
                'order_number'  => $o->order_number ?? ('#' . $o->id),
                'customer'      => ($o->user?->name) ?: ($o->address?->full_name ?: 'Guest'),
                'suburb'        => $o->address?->suburb ?? 'N/A',
                'total'         => number_format((float) ($o->total ?? 0), 2),
                'delivery_date' => $o->preferred_delivery_date?->format('d M Y') ?? '—',
                'reasons'       => implode(', ', $this->reasonsFor($o, $today, $hasConfirmCol)),
            ];
        })->all();
    }

    /**
     * Per-order reason labels — mirrors getNeedsActionData()'s reason column.
     */
    protected function reasonsFor(Order $o, Carbon $today, bool $hasConfirmCol): array
    {
        $reasons = [];

        if ($o->order_status === 'pending') {
            $reasons[] = 'Awaiting payment/confirmation';
        }

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

        if ($o->payment_status === 'paid'
            && !is_null($o->preferred_delivery_date)
            && $hasConfirmCol
            && is_null($o->delivery_date_confirmed_at)
            && !in_array($o->order_status, ['delivered', 'cancelled'])) {
            $reasons[] = 'Needs date confirmation';
        }

        return $reasons ?: ['Review'];
    }
}
