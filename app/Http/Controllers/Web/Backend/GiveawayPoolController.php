<?php

namespace App\Http\Controllers\Web\Backend;

use App\Http\Controllers\Controller;
use App\Models\EntryPackage;
use App\Models\Giveaway;
use App\Models\GiveawayPoolSetting;
use App\Models\GiveawayTemplate;
use App\Services\Giveaways\GiveawayDrawService;
use App\Services\Giveaways\GiveawayPoolService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Admin endpoints for the "Auto Pool" tab of the Giveaway Centre.
 */
class GiveawayPoolController extends Controller
{
    public function __construct(
        private GiveawayPoolService $pool,
        private GiveawayDrawService $draws,
    ) {
    }

    public function data()
    {
        $settings = $this->pool->settings();
        $tz = $this->pool->tz();
        $fmt = fn (?Carbon $c) => $c ? $c->copy()->timezone($tz)->format('D j M, g:ia') : null;

        $live = $this->pool->liveGiveaways();

        $liveData = $live->map(fn (Giveaway $g) => [
            'id' => $g->id,
            'name' => $g->prize_name,
            'image' => $g->image,
            'draws_at' => $fmt($this->pool->drawAt($g)),
            'entries' => (int) $this->draws->eligibleEntries($g)->sum('entry_count'),
            'auto_draw' => (bool) $g->auto_draw,
            'source' => $g->source ?? 'manual',
        ]);

        // Work out what fills the slot after the current live giveaway.
        $firstLiveDraw = $live->isNotEmpty() ? $this->pool->drawAt($live->first()) : null;
        $followingSlot = $this->pool->nextDrawAt($firstLiveDraw && $firstLiveDraw->isFuture() ? $firstLiveDraw : null);

        $scheduledNext = $this->pool->scheduledForSlot($followingSlot);
        $upNextTemplate = $this->pool->upNextTemplate();

        if ($scheduledNext) {
            $upNext = [
                'type' => 'scheduled',
                'id' => $scheduledNext->id,
                'name' => $scheduledNext->prize_name,
                'image' => $scheduledNext->image,
            ];
        } elseif ($settings->enabled && $upNextTemplate) {
            $upNext = [
                'type' => 'pool',
                'id' => $upNextTemplate->id,
                'name' => $upNextTemplate->prize_name,
                'image' => $upNextTemplate->image_url,
            ];
        } else {
            $upNext = ['type' => 'none'];
        }

        $scheduled = $this->pool->scheduledGiveaways()->map(fn (Giveaway $g) => [
            'id' => $g->id,
            'name' => $g->prize_name,
            'starts' => $fmt($this->pool->startAt($g)),
            'draws' => $fmt($this->pool->drawAt($g)),
            'takes_next_slot' => $scheduledNext && $scheduledNext->id === $g->id,
        ]);

        $templates = GiveawayTemplate::withCount('entryPackages')
            ->orderByDesc('is_active')
            ->orderBy('prize_name')
            ->get()
            ->map(fn (GiveawayTemplate $t) => [
                'id' => $t->id,
                'name' => $t->prize_name,
                'image' => $t->image_url,
                'prize_value' => number_format((float) $t->prize_value, 2),
                'prize_type' => $t->prize_type,
                'packages' => $t->entry_packages_count,
                'times_used' => $t->times_used,
                'last_used' => $t->last_used_at ? $t->last_used_at->timezone($tz)->format('j M Y') : null,
                'is_active' => $t->is_active,
                'is_next' => $upNext['type'] === 'pool' && $upNext['id'] === $t->id,
            ]);

        $importable = Giveaway::whereNull('template_id')
            ->orderByDesc('id')
            ->limit(200)
            ->get(['id', 'prize_name'])
            ->unique(fn ($g) => preg_replace('/\s\((Copy|\d+)\)$/', '', $g->prize_name))
            ->take(60)
            ->map(fn ($g) => ['id' => $g->id, 'name' => $g->prize_name])
            ->values();

        $beat = $settings->last_heartbeat_at;

        return response()->json([
            'settings' => [
                'enabled' => $settings->enabled,
                'draw_day' => $settings->draw_day,
                'draw_time' => substr((string) $settings->draw_time, 0, 5),
                'rotation_mode' => $settings->rotation_mode,
            ],
            'modes' => GiveawayPoolSetting::ROTATION_MODES,
            'days' => GiveawayPoolSetting::DAYS,
            'health' => [
                'healthy' => $this->pool->isSchedulerHealthy(),
                'last_heartbeat' => $beat ? $beat->diffForHumans() : null,
            ],
            'live' => $liveData,
            'next_slot' => $live->isNotEmpty() ? $fmt($followingSlot) : 'within a minute',
            'up_next' => $upNext,
            'scheduled' => $scheduled,
            'templates' => $templates,
            'importable' => $importable,
        ]);
    }

    public function saveSettings(Request $request)
    {
        $data = $request->validate([
            'enabled' => 'required|boolean',
            'draw_day' => 'required|integer|between:0,6',
            'draw_time' => ['required', 'regex:/^\d{2}:\d{2}$/'],
            'rotation_mode' => ['required', Rule::in(array_keys(GiveawayPoolSetting::ROTATION_MODES))],
        ]);

        $settings = $this->pool->settings();
        $settings->fill([
            'enabled' => $data['enabled'],
            'draw_day' => $data['draw_day'],
            'draw_time' => $data['draw_time'] . ':00',
            'rotation_mode' => $data['rotation_mode'],
        ])->save();

        return response()->json([
            'success' => true,
            'message' => 'Pool settings saved. Already-live giveaways keep their draw time; new ones use the new schedule.',
        ]);
    }

    /** Same as the every-minute cron, on demand. */
    public function runNow()
    {
        $r = $this->pool->tick();

        $parts = array_merge(
            array_map(fn ($l) => "Drawn: {$l}", $r['drawn']),
            array_map(fn ($l) => "Extended: {$l}", $r['extended']),
            $r['activated'] ? ["Now live: {$r['activated']}"] : [],
            array_map(fn ($l) => "Error: {$l}", $r['errors']),
        );

        return response()->json([
            'success' => empty($r['errors']),
            'message' => $parts ? implode("\n", $parts) : 'Checked — nothing due right now.',
        ]);
    }

    public function reroll()
    {
        $t = $this->pool->reroll();
        return response()->json([
            'success' => (bool) $t,
            'message' => $t ? "Up next: {$t->prize_name}" : 'No active templates in the pool.',
        ]);
    }

    public function show($id)
    {
        $t = GiveawayTemplate::with('entryPackages')->findOrFail($id);

        return response()->json([
            'id' => $t->id,
            'prize_name' => $t->prize_name,
            'description' => $t->description,
            'prize_type' => $t->prize_type,
            'prize_value' => $t->prize_value,
            'image' => $t->image_url,
            'max_entries_per_user' => $t->max_entries_per_user,
            'required_entries' => $t->required_entries,
            'animation_style' => $t->animation_style,
            'is_active' => $t->is_active,
            'packages' => $t->entryPackages->mapWithKeys(fn ($p) => [
                $p->id => $p->pivot->custom_price,
            ]),
        ]);
    }

    public function store(Request $request)
    {
        return $this->save($request, new GiveawayTemplate());
    }

    public function update(Request $request, $id)
    {
        return $this->save($request, GiveawayTemplate::findOrFail($id));
    }

    public function destroy($id)
    {
        $t = GiveawayTemplate::findOrFail($id);
        $t->delete();

        $settings = $this->pool->settings();
        if ((int) $settings->next_template_id === (int) $id) {
            $settings->next_template_id = null;
            $settings->save();
        }

        return response()->json(['success' => true, 'message' => 'Template removed from the pool.']);
    }

    public function toggle($id)
    {
        $t = GiveawayTemplate::findOrFail($id);
        $t->is_active = !$t->is_active;
        $t->save();

        return response()->json([
            'success' => true,
            'message' => $t->is_active ? 'Back in the rotation.' : 'Paused — it won\'t be picked.',
        ]);
    }

    public function useNext($id)
    {
        $t = GiveawayTemplate::active()->findOrFail($id);
        $this->pool->setNext($t);

        return response()->json(['success' => true, 'message' => "{$t->prize_name} will be next from the pool."]);
    }

    /** Turn an existing (past or current) giveaway into a pool template. */
    public function importFromGiveaway($giveawayId)
    {
        $g = Giveaway::with('entryPackages')->findOrFail($giveawayId);
        $name = preg_replace('/\s\((Copy|\d+)\)$/', '', $g->prize_name);

        $t = DB::transaction(function () use ($g, $name) {
            $t = GiveawayTemplate::create([
                'prize_name' => $name,
                'description' => $g->description,
                'prize_type' => $g->prize_type ?: 'others',
                'prize_value' => $g->prize_value ?? 0,
                'image' => $g->getRawOriginal('image'),
                'max_entries_per_user' => $g->max_entries_per_user,
                'required_entries' => $g->required_entries,
                'animation_style' => $g->animation_style ?: 'lottery_balls',
                'is_active' => true,
            ]);

            $packages = [];
            foreach ($g->entryPackages as $p) {
                $packages[$p->id] = ['custom_price' => $p->pivot->custom_price];
            }
            if ($packages) {
                $t->entryPackages()->attach($packages);
            }

            return $t;
        });

        return response()->json(['success' => true, 'message' => "Added \"{$t->prize_name}\" to the pool."]);
    }

    private function save(Request $request, GiveawayTemplate $t)
    {
        $data = $request->validate([
            'prize_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'prize_type' => 'required|in:bundle,discount,accessory,others',
            'prize_value' => 'required|numeric|min:0',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:5120',
            'max_entries_per_user' => 'nullable|integer|min:1',
            'required_entries' => 'nullable|integer|min:0',
            'animation_style' => 'nullable|in:lottery_balls,raffle_tickets',
            'is_active' => 'nullable|boolean',
            'entry_packages' => 'nullable|array',
            'entry_packages.*' => 'integer|exists:entry_packages,id',
            'package_custom_price' => 'nullable|array',
        ]);

        try {
            DB::transaction(function () use ($request, $t, $data) {
                if ($request->hasFile('image')) {
                    $file = $request->file('image');
                    $name = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
                    if (!file_exists(public_path('giveaway_images'))) {
                        mkdir(public_path('giveaway_images'), 0755, true);
                    }
                    $file->move(public_path('giveaway_images'), $name);
                    $t->image = 'giveaway_images/' . $name;
                }

                $t->fill([
                    'prize_name' => $data['prize_name'],
                    'description' => $data['description'] ?? null,
                    'prize_type' => $data['prize_type'],
                    'prize_value' => $data['prize_value'],
                    'max_entries_per_user' => $data['max_entries_per_user'] ?? null,
                    'required_entries' => $data['required_entries'] ?? null,
                    'animation_style' => $data['animation_style'] ?? 'lottery_balls',
                    'is_active' => $request->boolean('is_active', true),
                ]);
                $t->save();

                $sync = [];
                foreach ($data['entry_packages'] ?? [] as $pkgId) {
                    $pkg = EntryPackage::find($pkgId);
                    if (!$pkg) {
                        continue;
                    }
                    $custom = $request->input("package_custom_price.$pkgId");
                    $sync[$pkgId] = [
                        'custom_price' => ($custom !== null && $custom !== '' && (float) $custom != (float) $pkg->price)
                            ? $custom
                            : null,
                    ];
                }
                $t->entryPackages()->sync($sync);
            });
        } catch (\Throwable $e) {
            Log::error('Saving giveaway template failed', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Could not save: ' . $e->getMessage()], 500);
        }

        return response()->json(['success' => true, 'message' => 'Template saved.']);
    }
}
