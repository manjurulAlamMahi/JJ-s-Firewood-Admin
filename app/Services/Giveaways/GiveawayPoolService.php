<?php

namespace App\Services\Giveaways;

use App\Models\Giveaway;
use App\Models\GiveawayPoolSetting;
use App\Models\GiveawayTemplate;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Weekly giveaway cycle. tick() runs every minute from the scheduler and is safe to run any number of times:
 *
 *  1. Draw every live giveaway whose draw time has passed (no entries → pushed to the next weekly slot).
 *  2. If nothing is live, fill the slot:
 *       a. a manually scheduled giveaway that starts before the next weekly draw wins the slot, otherwise
 *       b. the pool's pre-picked template is turned into a normal giveaway drawing at the next weekly slot.
 *     A giveaway you make live yourself pauses the pool until it's drawn.
 */
class GiveawayPoolService
{
    /** A new giveaway always gets at least this long before its draw (otherwise it rolls to the following week). */
    public const MIN_HOURS_BEFORE_DRAW = 12;

    /** Scheduler counts as healthy if it has ticked within this many minutes. */
    public const HEARTBEAT_STALE_MINUTES = 5;

    public function __construct(private GiveawayDrawService $draws)
    {
    }

    public function settings(): GiveawayPoolSetting
    {
        return GiveawayPoolSetting::current();
    }

    public function tick(): array
    {
        $out = ['drawn' => [], 'extended' => [], 'activated' => null, 'errors' => []];

        $settings = $this->settings();
        $settings->last_heartbeat_at = now();
        $settings->saveQuietly();

        foreach (Giveaway::readyToDraw()->orderBy('id')->get() as $giveaway) {
            try {
                $r = $this->draws->draw($giveaway, true);

                if ($r['status'] === 'drawn') {
                    $out['drawn'][] = "#{$giveaway->id} {$giveaway->prize_name} → {$r['winner']->name}";
                } elseif ($r['status'] === 'no_entries') {
                    $slot = $this->extendToNextSlot($r['giveaway']);
                    $out['extended'][] = "#{$giveaway->id} {$giveaway->prize_name} (no entries) → " . $slot->format('D j M g:ia');
                }
            } catch (\Throwable $e) {
                $out['errors'][] = "#{$giveaway->id}: " . $e->getMessage();
                Log::error('Auto-draw failed', ['giveaway_id' => $giveaway->id, 'error' => $e->getMessage()]);
            }
        }

        try {
            $activated = $this->ensureActive();
            if ($activated) {
                $out['activated'] = "#{$activated->id} {$activated->prize_name} (draws "
                    . $this->drawAt($activated)?->format('D j M g:ia') . ')';
            }
        } catch (\Throwable $e) {
            $out['errors'][] = 'ensureActive: ' . $e->getMessage();
            Log::error('Giveaway pool rollover failed', ['error' => $e->getMessage()]);
        }

        return $out;
    }

    /**
     * Make sure a giveaway is live. Returns the giveaway it switched on, or null.
     */
    public function ensureActive(): ?Giveaway
    {
        return $this->withLock(function () {
            if ($this->liveGiveaways()->isNotEmpty()) {
                return null;
            }

            $slot = $this->nextDrawAt();

            if ($scheduled = $this->scheduledForSlot($slot)) {
                return $this->activateScheduled($scheduled, $slot);
            }

            $settings = $this->settings();
            if (!$settings->enabled) {
                return null;
            }

            $template = $this->takeNextTemplate();
            if (!$template) {
                return null;
            }

            return $this->spawn($template, $slot);
        });
    }

    // ------------------------------------------------------------------
    // Queries
    // ------------------------------------------------------------------

    public function liveGiveaways(): Collection
    {
        return Giveaway::whereIn('status', [Giveaway::STATUS_ACTIVE, 'active'])
            ->whereNull('winner_id')
            ->orderBy('id')
            ->get();
    }

    public function scheduledGiveaways(): Collection
    {
        return Giveaway::where('status', Giveaway::STATUS_SCHEDULED)
            ->whereNull('winner_id')
            ->get()
            ->sortBy(fn (Giveaway $g) => $this->startAt($g)?->timestamp ?? 0)
            ->values();
    }

    /** First scheduled giveaway that starts before the given weekly draw. */
    public function scheduledForSlot(Carbon $slot): ?Giveaway
    {
        return $this->scheduledGiveaways()->first(function (Giveaway $g) use ($slot) {
            $start = $this->startAt($g);
            return !$start || $start->lt($slot);
        });
    }

    public function tz(): string
    {
        // Giveaway dates are Perth wall-clock times, whatever APP_TIMEZONE is set to.
        return 'Australia/Perth';
    }

    /** Next weekly draw slot (e.g. Friday 8pm) at least MIN_HOURS_BEFORE_DRAW from $after. */
    public function nextDrawAt(?Carbon $after = null): Carbon
    {
        $settings = $this->settings();
        $tz = $this->tz();
        $earliest = ($after ?? now())->copy()->timezone($tz)->addHours(self::MIN_HOURS_BEFORE_DRAW);

        [$h, $m] = array_map('intval', array_pad(explode(':', (string) $settings->draw_time), 2, 0));
        $candidate = $earliest->copy()->setTime($h, $m, 0);

        for ($i = 0; $i < 15; $i++) {
            if ($candidate->dayOfWeek === (int) $settings->draw_day && $candidate->gt($earliest)) {
                return $candidate;
            }
            $candidate->addDay();
        }

        return $candidate; // unreachable in practice
    }

    public function startAt(Giveaway $g): ?Carbon
    {
        if (!$g->start_date) {
            return null;
        }
        return Carbon::parse($g->start_date->format('Y-m-d') . ' ' . ($g->start_time ?: '00:00:00'), $this->tz());
    }

    public function drawAt(Giveaway $g): ?Carbon
    {
        if ($g->scheduled_at) {
            return Carbon::parse($g->scheduled_at->format('Y-m-d H:i:s'), $this->tz());
        }
        if (!$g->draw_date) {
            return null;
        }
        return Carbon::parse($g->draw_date->format('Y-m-d') . ' ' . ($g->draw_time ?: '00:00:00'), $this->tz());
    }

    public function isSchedulerHealthy(): bool
    {
        $beat = $this->settings()->last_heartbeat_at;
        return $beat && $beat->gt(now()->subMinutes(self::HEARTBEAT_STALE_MINUTES));
    }

    // ------------------------------------------------------------------
    // Template rotation
    // ------------------------------------------------------------------

    /** Pick a template according to the rotation mode, avoiding $exclude ids where possible. */
    public function pick(array $exclude = []): ?GiveawayTemplate
    {
        $templates = GiveawayTemplate::active()->get();
        if ($templates->isEmpty()) {
            return null;
        }

        $mode = $this->settings()->rotation_mode;
        $exclude = array_filter($exclude);

        if ($mode !== 'random' && $exclude) {
            $filtered = $templates->reject(fn ($t) => in_array($t->id, $exclude));
            if ($filtered->isNotEmpty()) {
                $templates = $filtered;
            }
        }

        if ($mode === 'shuffle') {
            $min = $templates->min('times_used');
            $templates = $templates->where('times_used', $min);
        }

        $templates = $templates->values();
        return $templates[random_int(0, $templates->count() - 1)];
    }

    /** The pre-picked "up next" template; picks one if none is set (or it was disabled/deleted). */
    public function upNextTemplate(): ?GiveawayTemplate
    {
        $settings = $this->settings();
        $next = $settings->next_template_id
            ? GiveawayTemplate::active()->find($settings->next_template_id)
            : null;

        if (!$next) {
            $next = $this->pick([$this->lastPoolTemplateId()]);
            $settings->next_template_id = $next?->id;
            $settings->save();
        }

        return $next;
    }

    public function reroll(): ?GiveawayTemplate
    {
        $settings = $this->settings();
        $next = $this->pick([$settings->next_template_id, $this->lastPoolTemplateId()]);
        $settings->next_template_id = $next?->id;
        $settings->save();
        return $next;
    }

    public function setNext(GiveawayTemplate $template): void
    {
        $settings = $this->settings();
        $settings->next_template_id = $template->id;
        $settings->save();
    }

    private function takeNextTemplate(): ?GiveawayTemplate
    {
        return $this->upNextTemplate();
    }

    private function lastPoolTemplateId(): ?int
    {
        return Giveaway::whereNotNull('template_id')->orderByDesc('id')->value('template_id');
    }

    // ------------------------------------------------------------------
    // State changes
    // ------------------------------------------------------------------

    private function spawn(GiveawayTemplate $template, Carbon $slot): Giveaway
    {
        $tz = $this->tz();
        $now = now()->timezone($tz);

        $giveaway = DB::transaction(function () use ($template, $slot, $now) {
            $g = Giveaway::create([
                'prize_name' => $template->prize_name,
                'description' => $template->description,
                'prize_type' => $template->prize_type ?: 'others',
                'prize_value' => $template->prize_value ?? 0,
                'image' => $template->getRawOriginal('image'),
                'status' => Giveaway::STATUS_ACTIVE,
                'start_date' => $now->toDateString(),
                'start_time' => $now->format('H:i:s'),
                'end_date' => $slot->toDateString(),
                'draw_date' => $slot->toDateString(),
                'draw_time' => $slot->format('H:i:s'),
                'scheduled_at' => $slot->format('Y-m-d H:i:s'),
                'max_entries_per_user' => $template->max_entries_per_user,
                'required_entries' => $template->required_entries ?? 1,
                'auto_draw' => true,
                'allow_temp_entries' => true,
                'animation_style' => $template->animation_style ?: 'lottery_balls',
                'position' => (Giveaway::max('position') ?? 0) + 1,
                'winner_id' => null,
                'template_id' => $template->id,
                'source' => 'pool',
            ]);

            $packages = [];
            foreach ($template->entryPackages as $pkg) {
                $packages[$pkg->id] = ['custom_price' => $pkg->pivot->custom_price];
            }
            if ($packages) {
                $g->entryPackages()->attach($packages);
            }

            $template->increment('times_used');
            $template->last_used_at = now();
            $template->save();

            return $g;
        });

        // Pre-pick next week's prize so it shows as "Up next" in the admin.
        $settings = $this->settings();
        $settings->next_template_id = $this->pick([$template->id])?->id;
        $settings->save();

        Log::info('Giveaway pool spawned giveaway', [
            'giveaway_id' => $giveaway->id,
            'template_id' => $template->id,
            'draws_at' => $slot->toDateTimeString(),
        ]);

        Giveaway::broadcastNewGiveawayNotification($giveaway);

        return $giveaway;
    }

    private function activateScheduled(Giveaway $g, Carbon $slot): Giveaway
    {
        $now = now()->timezone($this->tz());

        $start = $this->startAt($g);
        if (!$start || $start->gt($now)) {
            // Pulled forward to fill this week's slot.
            $g->start_date = $now->toDateString();
            $g->start_time = $now->format('H:i:s');
        }

        $drawAt = $this->drawAt($g);
        if (!$drawAt || $drawAt->lte($now->copy()->addHour())) {
            $this->applyDrawAt($g, $slot);
        }

        $g->status = Giveaway::STATUS_ACTIVE;
        $g->auto_draw = true;
        $g->save();

        Log::info('Scheduled giveaway activated', ['giveaway_id' => $g->id, 'draws_at' => $this->drawAt($g)?->toDateTimeString()]);

        Giveaway::broadcastNewGiveawayNotification($g);

        return $g;
    }

    private function extendToNextSlot(Giveaway $g): Carbon
    {
        $slot = $this->nextDrawAt();
        $this->applyDrawAt($g, $slot);
        $g->save();

        Log::warning('Giveaway had no entries — moved to next weekly draw', [
            'giveaway_id' => $g->id,
            'draws_at' => $slot->toDateTimeString(),
        ]);

        return $slot;
    }

    private function applyDrawAt(Giveaway $g, Carbon $slot): void
    {
        $g->draw_date = $slot->toDateString();
        $g->draw_time = $slot->format('H:i:s');
        $g->end_date = $slot->toDateString();
        $g->scheduled_at = $slot->format('Y-m-d H:i:s');
    }

    /** Prevents the cron and an admin action from both spawning a giveaway at the same moment. */
    private function withLock(callable $fn)
    {
        $lock = null;
        try {
            $lock = Cache::lock('giveaway-pool-ensure-active', 60);
            $got = $lock->get();
        } catch (\Throwable $e) {
            $lock = null;
            $got = true; // cache store without lock support — carry on unlocked
        }

        if (!$got) {
            return null;
        }

        try {
            return $fn();
        } finally {
            if ($lock) {
                try {
                    $lock->release();
                } catch (\Throwable $e) {
                }
            }
        }
    }
}
