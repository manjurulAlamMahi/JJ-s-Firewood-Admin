<?php

namespace App\Console\Commands;

use App\Mail\DeliveryDigestMail;
use App\Services\AutoDeliveryService;
use App\Services\NeedsActionReportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * orders:auto-deliver
 *
 * Runs the daily auto-delivery sweep (via AutoDeliveryService) and then emails
 * a digest to the admin containing:
 *   1. Orders the sweep auto-marked delivered
 *   2. Outstanding orders that still need admin interaction (mirrors the
 *      Order Centre "Needs Action" tab), each with its delivery date and the
 *      reason it needs attention (awaiting payment, needs scheduling, overdue,
 *      needs date confirmation).
 *
 * This command was referenced in the scheduler (Kernel.php, dailyAt 18:00) but
 * never actually existed — so the scheduled sweep had never run. This file
 * creates it. The sweep logic itself lives in AutoDeliveryService, unchanged.
 *
 * Behaviour:
 *   - If auto-delivery is disabled in system_settings, the sweep is a no-op,
 *     but the digest is STILL sent if there are outstanding orders to report.
 *   - If there is nothing to report at all (nothing delivered AND nothing
 *     outstanding), no email is sent. Pass --always-email to override.
 *   - --dry-run runs the sweep in preview mode (no DB changes) and prints a
 *     summary without sending email.
 *
 * Usage:
 *   php artisan orders:auto-deliver
 *   php artisan orders:auto-deliver --dry-run
 *   php artisan orders:auto-deliver --always-email
 */
class AutoDeliverOrders extends Command
{
    protected $signature = 'orders:auto-deliver
        {--dry-run : Preview what would be delivered without changing anything or emailing}
        {--always-email : Send the digest even if there is nothing to report}';

    protected $description = 'Run the daily auto-delivery sweep and email a digest (delivered + outstanding orders)';

    public function handle(AutoDeliveryService $svc, NeedsActionReportService $needs): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $stats = ['delivered' => 0, 'skipped' => 0, 'errors' => 0, 'orders' => []];

        if (AutoDeliveryService::isEnabled()) {
            $stats = $svc->runSweep($dryRun);
            $this->info(sprintf(
                '%s: delivered=%d skipped=%d errors=%d',
                $dryRun ? 'Dry run' : 'Sweep complete',
                $stats['delivered'],
                $stats['skipped'],
                $stats['errors']
            ));
        } else {
            $this->comment('Auto-delivery disabled in settings — sweep skipped. Will still report outstanding orders.');
        }

        $deliveredOrders = array_values(array_filter(
            $stats['orders'],
            fn ($o) => ($o['action'] ?? null) === 'delivered'
        ));

        $outstanding = $needs->getOutstanding();

        if ($dryRun) {
            $this->comment(sprintf(
                'Dry run — would deliver %d, %d outstanding order(s) need action. No email sent.',
                $stats['delivered'],
                count($outstanding)
            ));
            return self::SUCCESS;
        }

        $nothingToReport = empty($deliveredOrders) && empty($outstanding);
        if ($nothingToReport && !$this->option('always-email')) {
            $this->comment('Nothing delivered and nothing outstanding — no email sent.');
            return self::SUCCESS;
        }

        $recipient = $this->resolveRecipient();
        if (!$recipient) {
            $this->warn('No digest recipient configured (DELIVERY_DIGEST_EMAIL / MAIL_FROM_ADDRESS). Email skipped.');
            Log::warning('orders:auto-deliver: no digest recipient configured');
            return self::SUCCESS;
        }

        try {
            Mail::to($recipient)->send(new DeliveryDigestMail($deliveredOrders, $stats, $outstanding));
            $this->info(sprintf(
                'Digest emailed to %s — %d delivered, %d outstanding.',
                $recipient,
                $stats['delivered'],
                count($outstanding)
            ));
            Log::info('orders:auto-deliver: digest emailed', [
                'recipient'   => $recipient,
                'delivered'   => $stats['delivered'],
                'outstanding' => count($outstanding),
            ]);
        } catch (\Throwable $e) {
            $this->error('Sweep/report ran but digest email failed: ' . $e->getMessage());
            Log::error('orders:auto-deliver: digest email failed', ['error' => $e->getMessage()]);
        }

        return self::SUCCESS;
    }

    protected function resolveRecipient(): ?string
    {
        return env('DELIVERY_DIGEST_EMAIL')
            ?: config('mail.from.address')
            ?: null;
    }
}
