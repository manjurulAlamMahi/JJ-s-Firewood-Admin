<?php

namespace App\Console\Commands;

use App\Models\Order;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

/**
 * One-time backfill for the new delivery_date_confirmed_at column.
 *
 * After deploy, every pre-existing paid order would technically meet the new
 * "needs admin confirmation" criterion, which would flood Needs Action with
 * months of legacy orders.
 *
 * This command marks orders created more than 3 days ago as already-confirmed
 * (delivery_date_confirmed_at = now()) so they fall out of Needs Action
 * immediately. Anything from the last 3 days stays in Needs Action so the
 * admin can catch up on recent missed orders.
 *
 * Safe to re-run: only touches rows where delivery_date_confirmed_at IS NULL.
 *
 * Usage:
 *   php artisan oc:backfill-delivery-confirmed           # dry-run
 *   php artisan oc:backfill-delivery-confirmed --apply   # actually update
 *   php artisan oc:backfill-delivery-confirmed --apply --days=7   # custom window
 */
class BackfillDeliveryConfirmedAt extends Command
{
    protected $signature = 'oc:backfill-delivery-confirmed
                            {--apply : Actually persist changes (otherwise dry-run)}
                            {--days=3 : Treat orders OLDER than this many days as already confirmed}';

    protected $description = 'Backfill delivery_date_confirmed_at for legacy orders so Needs Action does not flood';

    public function handle(): int
    {
        if (!Schema::hasTable('orders')) {
            $this->error('orders table not found.');
            return self::FAILURE;
        }
        if (!Schema::hasColumn('orders', 'delivery_date_confirmed_at')) {
            $this->error('delivery_date_confirmed_at column not found — run migrations first.');
            return self::FAILURE;
        }

        $days = (int) $this->option('days');
        if ($days < 0) {
            $this->error('--days must be 0 or positive.');
            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);
        $apply = (bool) $this->option('apply');

        $query = Order::whereNull('delivery_date_confirmed_at')
            ->where('created_at', '<', $cutoff);
        $total = $query->count();

        $this->info("Found {$total} order(s) created before {$cutoff->toDateTimeString()} without a confirmed timestamp.");
        if ($total === 0) {
            return self::SUCCESS;
        }
        if (!$apply) {
            $this->warn('Dry-run — pass --apply to actually update.');
            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($total);
        $bar->start();
        $updated = 0;

        $query->chunkById(200, function ($orders) use (&$updated, $bar) {
            foreach ($orders as $order) {
                $order->delivery_date_confirmed_at = now();
                $order->save();
                $updated++;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);
        $this->info("Backfilled {$updated} order(s).");
        return self::SUCCESS;
    }
}
