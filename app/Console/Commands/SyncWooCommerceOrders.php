<?php

namespace App\Console\Commands;

use App\Models\WoocommerceSetting;
use App\Services\WooCommerceService;
use Illuminate\Console\Command;

/**
 * Schedule this command in app/Console/Kernel.php for automatic syncing:
 *   $schedule->command('orders:sync-woocommerce')->everyFifteenMinutes();
 *
 * Or run it manually:
 *   php artisan orders:sync-woocommerce
 *   php artisan orders:sync-woocommerce --full
 *   php artisan orders:sync-woocommerce --max=200
 */
class SyncWooCommerceOrders extends Command
{
    protected $signature = 'orders:sync-woocommerce
                            {--full : Full sync (ignores last_sync_at, pulls everything up to --max)}
                            {--max=500 : Maximum orders to pull in one run}';

    protected $description = 'Sync orders from WooCommerce into the local Order Centre';

    public function handle(): int
    {
        $service = new WooCommerceService();

        if (!$service->isConfigured()) {
            $this->error('WooCommerce is not configured. Save credentials in the Order Centre Settings tab first.');
            return Command::FAILURE;
        }

        $settings = WoocommerceSetting::singleton();
        $full     = (bool) $this->option('full');
        $max      = (int) $this->option('max');

        $since = null;
        if (!$full && $settings->last_sync_at) {
            $since = $settings->last_sync_at;
            $this->info("Incremental sync: pulling orders modified since {$since->toDateTimeString()}");
        } else {
            $this->info("Full sync: pulling up to {$max} orders");
        }

        try {
            $result = $service->pullOrders($since, $max);

            $this->newLine();
            $this->info("Sync complete:");
            $this->line("  Imported: {$result['imported']}");
            $this->line("  Updated:  {$result['updated']}");
            $this->line("  Errors:   {$result['errors']}");

            if (!empty($result['failures'])) {
                $this->newLine();
                $this->warn('First few failures:');
                foreach ($result['failures'] as $f) {
                    $this->line("  WC#{$f['wc_id']}: {$f['error']}");
                }
            }

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Sync failed: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
