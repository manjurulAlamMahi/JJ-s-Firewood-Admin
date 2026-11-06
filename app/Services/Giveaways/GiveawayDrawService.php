<?php

namespace App\Services\Giveaways;

use App\Mail\WinnerMailable;
use App\Models\Entry;
use App\Models\Giveaway;
use App\Models\GiveawayDrawLog;
use App\Models\GiveawayDrawReplay;
use App\Models\Prize;
use App\Models\User;
use App\Notifications\CategoryNotification;
use App\Notifications\WinnerSelectedNotification;
use App\Services\NotificationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

/**
 * One draw routine shared by the scheduled auto-draw and the admin "Draw" button.
 *
 * Returns ['status' => 'drawn' | 'no_entries' | 'already_drawn' | 'missing', ...].
 * Entry rules are unchanged from the previous system:
 *   - eligible: undrawn entries for this giveaway + undrawn entries with no giveaway (paid weekly + permanent/reward)
 *   - consumed after the draw: this giveaway's entries + paid entries with no giveaway (reward entries carry over)
 */
class GiveawayDrawService
{
    public function draw(Giveaway $giveaway, bool $automatic, ?User $drawnBy = null): array
    {
        $result = DB::transaction(function () use ($giveaway, $automatic, $drawnBy) {
            // Row lock so the cron and an admin clicking "Draw" can never both draw the same giveaway.
            $g = Giveaway::whereKey($giveaway->id)->lockForUpdate()->first();

            if (!$g) {
                return ['status' => 'missing'];
            }
            if ($g->winner_id) {
                return ['status' => 'already_drawn', 'giveaway' => $g];
            }
            if (!in_array((string) $g->status, ['1', 'active'], true)) {
                return ['status' => 'not_active', 'giveaway' => $g];
            }

            $entries = $this->eligibleEntries($g);
            $totalEntries = (int) $entries->sum('entry_count');

            if ($totalEntries <= 0) {
                return ['status' => 'no_entries', 'giveaway' => $g];
            }

            $winnerId = $this->pickWeighted($entries, $totalEntries);
            $participants = $entries->pluck('user_id')->unique()->count();
            $now = now();

            $g->winner_id = $winnerId;
            $g->status = Giveaway::STATUS_COMPLETED;
            $g->winner_announced_at = $now;
            if (Schema::hasColumn('giveaways', 'winner_drawn_at')) {
                $g->winner_drawn_at = $now;
            }
            $g->save();

            $consumed = Entry::where('drawn', false)
                ->where(function ($q) use ($g) {
                    $q->where('giveaway_id', $g->id)
                      ->orWhere(function ($sq) {
                          $sq->whereNull('giveaway_id')->where('purchase_type', 'paid');
                      });
                })
                ->where('purchase_type', '!=', 'reward')
                ->update(['drawn' => true, 'drawn_at' => $now]);

            // Without a Prize row the winner sees "No prizes won yet" in the app.
            Prize::firstOrCreate(
                ['user_id' => $winnerId, 'giveaway_id' => $g->id],
                ['prize_value' => $g->prize_value ?? 0, 'won_date' => $now, 'status' => 'pending']
            );

            $notes = $automatic
                ? 'Automatically drawn by the weekly scheduler'
                : 'Drawn manually by admin ' . ($drawnBy->name ?? 'unknown');

            $log = GiveawayDrawLog::createLog($g->id, $winnerId, $totalEntries, $participants, $automatic, $notes);

            $replay = $this->createReplay($g, $winnerId, $totalEntries, $participants, $drawnBy);

            Log::info('Giveaway drawn', [
                'giveaway_id' => $g->id,
                'winner_id' => $winnerId,
                'total_entries' => $totalEntries,
                'participants' => $participants,
                'entries_consumed' => $consumed,
                'automatic' => $automatic,
            ]);

            return [
                'status' => 'drawn',
                'giveaway' => $g,
                'winner' => User::find($winnerId),
                'total_entries' => $totalEntries,
                'participants' => $participants,
                'log' => $log,
                'replay' => $replay,
            ];
        });

        // Notifications go out only after the draw is committed.
        if ($result['status'] === 'drawn' && $result['winner']) {
            $sent = $this->notify($result['giveaway'], $result['winner']);
            if ($sent && $result['log']) {
                $result['log']->notification_sent = true;
                $result['log']->save();
            }
        }

        return $result;
    }

    public function eligibleEntries(Giveaway $g): Collection
    {
        return Entry::where('drawn', false)
            ->where(function ($q) use ($g) {
                $q->where('giveaway_id', $g->id)->orWhereNull('giveaway_id');
            })
            ->where('entry_count', '>', 0)
            ->whereHas('user') // skip entries whose customer record wasn't recovered
            ->get(['id', 'user_id', 'entry_count']);
    }

    /**
     * Cryptographically secure weighted pick: each entry counts entry_count times.
     */
    private function pickWeighted(Collection $entries, int $total): int
    {
        $ticket = random_int(1, $total);
        foreach ($entries as $entry) {
            $ticket -= (int) $entry->entry_count;
            if ($ticket <= 0) {
                return (int) $entry->user_id;
            }
        }
        return (int) $entries->last()->user_id;
    }

    private function createReplay(Giveaway $g, int $winnerId, int $totalEntries, int $participants, ?User $drawnBy)
    {
        if (!Schema::hasTable('giveaway_draw_replays')) {
            return null;
        }

        try {
            $winner = User::find($winnerId);

            return GiveawayDrawReplay::updateOrCreate(
                ['draw_id' => GiveawayDrawReplay::generateDrawId($g->id)],
                [
                    'giveaway_id' => $g->id,
                    'animation_style' => $g->animation_style ?? 'lottery_balls',
                    'winner_id' => $winnerId,
                    'animation_data' => [
                        'version' => '1.0',
                        'duration' => 7000,
                        'style' => $g->animation_style ?? 'lottery_balls',
                        'giveaway' => [
                            'id' => $g->id,
                            'prize_name' => $g->prize_name,
                            'prize_value' => $g->prize_value,
                            'image' => $g->image,
                        ],
                        'winner' => [
                            'id' => $winnerId,
                            'name' => $winner->name ?? '',
                            'avatar' => $winner->avatar ?? null,
                        ],
                        'stats' => [
                            'total_entries' => $totalEntries,
                            'total_participants' => $participants,
                            'draw_date' => optional($g->draw_date)->format('d/m/Y'),
                            'draw_time' => $g->draw_time,
                        ],
                        'timeline' => [
                            ['time' => 0, 'action' => 'start'],
                            ['time' => 3000, 'action' => 'slow_down'],
                            ['time' => 6500, 'action' => 'reveal_winner'],
                            ['time' => 7000, 'action' => 'complete'],
                        ],
                    ],
                    'total_entries' => $totalEntries,
                    'total_participants' => $participants,
                    'drawn_at' => now(),
                    'drawn_by' => $drawnBy->id ?? null,
                ]
            );
        } catch (\Throwable $e) {
            Log::warning('Could not save draw replay', ['giveaway_id' => $g->id, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Winner email + in-app + push, admin email + in-app + push, broadcast to all app users.
     * Each channel is isolated so one failure doesn't stop the rest.
     */
    private function notify(Giveaway $giveaway, User $winner): bool
    {
        $ok = true;
        $attempt = function (string $what, callable $fn) use (&$ok, $giveaway) {
            try {
                $fn();
            } catch (\Throwable $e) {
                $ok = false;
                Log::error("Giveaway notification failed ({$what})", [
                    'giveaway_id' => $giveaway->id,
                    'error' => $e->getMessage(),
                ]);
            }
        };

        $wonText = "You have won the giveaway: {$giveaway->prize_name}! Check your prizes to claim it.";

        $attempt('winner email', fn () => Mail::to($winner->email)->send(new WinnerMailable($giveaway, $winner)));
        $attempt('winner in-app', fn () => NotificationService::success($winner, 'Congratulations! 🎁', $wonText));
        $attempt('winner push', fn () => $winner->notify(new CategoryNotification('transactional', [
            'subject' => 'Congratulations! You Won!',
            'body' => $wonText,
            'title' => 'Giveaway Won!',
            'data' => ['giveaway_id' => $giveaway->id, 'type' => 'giveaway_won'],
        ])));

        $admins = User::where('is_admin', 1)->get();
        foreach ($admins as $admin) {
            $adminText = "User {$winner->name} has won the giveaway: {$giveaway->prize_name}.";
            $attempt('admin email', fn () => Mail::to($admin->email)->send(new WinnerMailable($giveaway, $winner)));
            $attempt('admin in-app', fn () => NotificationService::info($admin, 'Giveaway Won', $adminText));
            $attempt('admin push', fn () => $admin->notify(new CategoryNotification('transactional', [
                'subject' => 'Giveaway Winner Drawn',
                'body' => $adminText,
                'title' => 'Giveaway Won',
                'data' => ['giveaway_id' => $giveaway->id, 'winner_id' => $winner->id, 'type' => 'admin_giveaway_won'],
            ])));
        }

        $attempt('broadcast', function () use ($giveaway, $winner) {
            $users = User::whereNotNull('fcm_token')->where('is_admin', 0)->get();
            foreach ($users as $user) {
                try {
                    $user->notify(new WinnerSelectedNotification($giveaway, $winner));
                } catch (\Throwable $e) {
                    // one bad token shouldn't stop the broadcast
                }
            }
            Log::info('Winner announced to ' . $users->count() . ' users (broadcast).');
        });

        return $ok;
    }
}
