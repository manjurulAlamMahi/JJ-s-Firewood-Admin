<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderAuditLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Daily end-of-day auto-delivery automation.
 *
 * Marks orders as delivered if they meet ALL of:
 *   1. preferred_delivery_date is today (or earlier — catches missed days)
 *   2. delivery_date_confirmed_at IS NOT NULL (admin-confirmed via Setmore/manual)
 *   3. order_status NOT IN (delivered, cancelled)
 *   4. cancellation_requested_at IS NULL (customer didn't request cancellation)
 *   5. payment_status = 'paid'
 *   6. payment_method IN (square, paypal, card, *card*) — electronically verified
 *
 * Orders that DON'T meet the payment criteria (cash, EFT, bank transfer, or
 * unpaid) are left alone — they appear in Needs Action for admin to confirm.
 *
 * Configurable via system_settings.auto_deliver_enabled (defaults to false).
 * When disabled, the job is a no-op.
 */
class AutoDeliveryService
{
    /**
     * Payment methods we consider "verified" for auto-delivery.
     * Case-insensitive. Any method containing "card" also qualifies
     * (covers variants like "credit_card", "debit_card", "card_payment").
     */
    public const VERIFIED_METHODS = ['square', 'paypal', 'card'];

    /**
     * Run the auto-delivery sweep.
     *
     * @param  bool  $dryRun  if true, don't make any DB changes — just return
     *                        the list of orders that WOULD be delivered
     * @param  Carbon|null  $forDate  defaults to today (Perth time). Override for testing.
     * @return array  ['delivered' => N, 'skipped' => N, 'errors' => N, 'orders' => [...]]
     */
    public function runSweep(bool $dryRun = false, ?Carbon $forDate = null): array
    {
        $forDate = $forDate ?? Carbon::now('Australia/Perth')->startOfDay();
        $stats = ['delivered' => 0, 'skipped' => 0, 'errors' => 0, 'orders' => []];

        $candidates = $this->findCandidates($forDate);

        Log::info('AutoDeliveryService: sweep started', [
            'for_date'   => $forDate->toDateString(),
            'candidates' => $candidates->count(),
            'dry_run'    => $dryRun,
        ]);

        foreach ($candidates as $order) {
            try {
                $shouldAuto = $this->isVerifiedPayment($order);

                if (!$shouldAuto) {
                    // Stays in Needs Action for manual review (cash/EFT/unpaid)
                    $stats['skipped']++;
                    $stats['orders'][] = [
                        'id'             => $order->id,
                        'order_number'   => $order->order_number,
                        'customer'       => $order->user?->name ?: $order->address?->full_name ?: 'Guest',
                        'payment_method' => $order->payment_method,
                        'payment_status' => $order->payment_status,
                        'action'         => 'skipped',
                        'reason'         => $this->skipReason($order),
                    ];
                    continue;
                }

                if (!$dryRun) {
                    $this->markDelivered($order);
                }

                $stats['delivered']++;
                $stats['orders'][] = [
                    'id'             => $order->id,
                    'order_number'   => $order->order_number,
                    'customer'       => $order->user?->name ?: $order->address?->full_name ?: 'Guest',
                    'payment_method' => $order->payment_method,
                    'payment_status' => $order->payment_status,
                    'action'         => $dryRun ? 'would_deliver' : 'delivered',
                ];
            } catch (\Throwable $e) {
                $stats['errors']++;
                Log::error('AutoDeliveryService: failed to process order', [
                    'order_id' => $order->id,
                    'error'    => $e->getMessage(),
                ]);
                $stats['orders'][] = [
                    'id'           => $order->id,
                    'order_number' => $order->order_number ?? '?',
                    'action'       => 'error',
                    'reason'       => $e->getMessage(),
                ];
            }
        }

        Log::info('AutoDeliveryService: sweep completed', $stats);
        return $stats;
    }

    /**
     * Find orders that are eligible for evaluation today.
     * Includes BOTH verified and unverified — the loop decides per-order.
     * Returns paid AND unpaid orders so we can report on both.
     */
    public function findCandidates(?Carbon $forDate = null)
    {
        $forDate = $forDate ?? Carbon::now('Australia/Perth')->startOfDay();
        $hasConfirmCol = Schema::hasColumn('orders', 'delivery_date_confirmed_at');

        $q = Order::with(['user', 'address'])
            ->whereNotIn('order_status', ['delivered', 'cancelled'])
            ->whereNotNull('preferred_delivery_date')
            ->whereDate('preferred_delivery_date', '<=', $forDate)  // today + missed previous days
            ->whereNull('cancellation_requested_at');

        if ($hasConfirmCol) {
            // Only orders that admin has confirmed the date for
            $q->whereNotNull('delivery_date_confirmed_at');
        }

        return $q->orderBy('preferred_delivery_date', 'asc')->get();
    }

    /**
     * Is this order paid AND via a verified electronic method?
     */
    public function isVerifiedPayment(Order $order): bool
    {
        if ($order->payment_status !== 'paid') return false;

        $method = strtolower(trim((string) ($order->payment_method ?? '')));
        if ($method === '') return false;

        // Exact match against the verified list
        if (in_array($method, self::VERIFIED_METHODS, true)) return true;

        // Or any method that contains "card" (credit_card, debit_card, etc.)
        if (str_contains($method, 'card')) return true;

        return false;
    }

    /**
     * Mark an order as delivered + write audit + set auto_delivered_at flag.
     * Transactional to ensure all three happen together or none.
     */
    protected function markDelivered(Order $order): void
    {
        DB::transaction(function () use ($order) {
            $order->order_status = 'delivered';
            if (Schema::hasColumn('orders', 'auto_delivered_at')) {
                $order->auto_delivered_at = now();
            }
            $order->save();

            OrderAuditLog::record(
                $order->id,
                'auto_delivered',
                'Auto-marked delivered: scheduled date reached and payment verified via ' . ($order->payment_method ?? 'unknown'),
                [
                    'payment_method'          => $order->payment_method,
                    'payment_status'          => $order->payment_status,
                    'preferred_delivery_date' => $order->preferred_delivery_date?->toDateString(),
                    'job_run_at'              => now()->toIso8601String(),
                ],
                null  // userId null = system action
            );
        });
    }

    /**
     * Human-readable reason a candidate was skipped (for the dry-run preview).
     */
    protected function skipReason(Order $order): string
    {
        if ($order->payment_status !== 'paid') {
            return 'Payment is ' . ($order->payment_status ?: 'missing') . ' — needs admin verification';
        }
        $method = $order->payment_method ?: '(none)';
        return "Payment method '{$method}' requires manual verification (cash / bank transfer / etc.)";
    }

    /**
     * Check the system setting to see if auto-delivery is enabled.
     */
    public static function isEnabled(): bool
    {
        try {
            if (!Schema::hasTable('system_settings')) return false;
            if (!Schema::hasColumn('system_settings', 'auto_deliver_enabled')) return false;

            $value = DB::table('system_settings')->orderBy('id')->value('auto_deliver_enabled');
            return (bool) $value;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
