<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\ReachabilityService;
use Illuminate\Console\Command;

/**
 * Validate FCM tokens by sending a silent ping to each user.
 *
 * Scheduled weekly. Catches uninstalls/expired tokens without bothering
 * the customer (silent data-only messages don't display).
 *
 * Usage:
 *   php artisan tokens:validate                # Default: ping all users with stale tokens
 *   php artisan tokens:validate --all          # Ping every user with a token
 *   php artisan tokens:validate --dry-run      # Show what would happen, don't ping
 *   php artisan tokens:validate --limit=100    # Cap the number pinged (rate limiting)
 *   php artisan tokens:validate --user=42      # Just one user
 */
class ValidateFcmTokens extends Command
{
    protected $signature = 'tokens:validate
        {--all : Ping every user with a token, not just stale ones}
        {--dry-run : List what would be pinged but do not actually send}
        {--limit= : Maximum users to ping in this run}
        {--user= : Only validate one specific user ID}';

    protected $description = 'Send silent FCM pings to validate users\' tokens and update reachability status';

    public function handle(ReachabilityService $reachability): int
    {
        $isDryRun = (bool) $this->option('dry-run');
        $pingAll  = (bool) $this->option('all');
        $limit    = $this->option('limit') ? (int) $this->option('limit') : null;
        $userId   = $this->option('user') ? (int) $this->option('user') : null;

        $query = User::query()->whereNotNull('fcm_token');

        if ($userId) {
            $query->where('id', $userId);
        } elseif (!$pingAll) {
            // Default: only ping users whose token hasn't been validated recently,
            // or has never been validated at all.
            $cutoff = now()->subDays(ReachabilityService::COOLING_THRESHOLD_DAYS);
            $query->where(function ($q) use ($cutoff) {
                $q->whereNull('fcm_token_validated_at')
                  ->orWhere('fcm_token_validated_at', '<', $cutoff);
            });
        }

        if ($limit) {
            $query->limit($limit);
        }

        $count = $query->count();
        $this->info("Found {$count} users with tokens to validate.");

        if ($count === 0) {
            return self::SUCCESS;
        }

        if ($isDryRun) {
            $this->warn('DRY RUN — listing first 25, no pings sent:');
            $query->limit(25)->get(['id', 'name', 'email', 'fcm_token_validated_at'])
                ->each(fn ($u) => $this->line(
                    sprintf('  #%d %s — last validated: %s',
                        $u->id,
                        $u->name ?? '(no name)',
                        $u->fcm_token_validated_at?->diffForHumans() ?? 'never'
                    )
                ));
            return self::SUCCESS;
        }

        $start = microtime(true);
        $stats = ['total' => 0, 'reachable' => 0, 'unreachable' => 0, 'error' => 0];

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $query->chunkById(100, function ($users) use ($reachability, &$stats, $bar) {
            foreach ($users as $user) {
                $stats['total']++;
                $result = $reachability->ping($user);
                $stats[$result['status']] = ($stats[$result['status']] ?? 0) + 1;
                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $elapsed = round(microtime(true) - $start, 1);
        $this->info("Done in {$elapsed}s.");
        $this->table(
            ['Result', 'Count'],
            collect($stats)->map(fn ($v, $k) => [ucfirst($k), $v])->values()->all()
        );

        return self::SUCCESS;
    }
}
