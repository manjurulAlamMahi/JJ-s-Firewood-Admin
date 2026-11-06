<?php

namespace App\Console\Commands;

use App\Services\Giveaways\GiveawayPoolService;
use Illuminate\Console\Command;

/**
 * Runs every minute (routes/console.php). Draws any giveaway that's due and keeps the
 * weekly slot filled from scheduled giveaways or the giveaway pool.
 */
class DrawGiveawayWinners extends Command
{
    protected $signature = 'giveaways:draw-winners';

    protected $description = 'Draw due giveaways and start the next one (scheduled or from the giveaway pool)';

    public function handle(GiveawayPoolService $pool): int
    {
        $r = $pool->tick();

        foreach ($r['drawn'] as $line) {
            $this->info("Drawn: {$line}");
        }
        foreach ($r['extended'] as $line) {
            $this->warn("Extended: {$line}");
        }
        if ($r['activated']) {
            $this->info("Now live: {$r['activated']}");
        }
        foreach ($r['errors'] as $line) {
            $this->error($line);
        }
        if (!$r['drawn'] && !$r['extended'] && !$r['activated'] && !$r['errors']) {
            $this->line('Nothing due. Next weekly draw slot: ' . $pool->nextDrawAt()->format('D j M Y g:ia'));
        }

        return self::SUCCESS;
    }
}
