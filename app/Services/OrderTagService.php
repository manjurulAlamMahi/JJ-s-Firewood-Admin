<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderTag;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Small helper around the order_tag pivot. Lets the rest of the codebase
 * attach tags by slug without needing to know about the pivot table.
 *
 * Tags are LABELS — they're informational provenance markers. They don't
 * drive workflow state (delivery_date_confirmed_at does that). So this
 * service is intentionally minimal:
 *   - ensureSlugExists()  — find-or-create a tag by slug
 *   - attachToOrder()     — idempotent attach (uses syncWithoutDetaching)
 *
 * Always wrapped in try/catch where called, so missing tag tables never
 * break the user-facing order flow.
 */
class OrderTagService
{
    /**
     * Auto-applied tags this service knows about. Used by the migration's
     * companion command to seed any missing slugs.
     */
    public const WELL_KNOWN_TAGS = [
        'order-centre' => ['name' => 'Order Centre', 'colour' => '#667eea'],
        'setmore'      => ['name' => 'Setmore',      'colour' => '#f59e0b'],
    ];

    /**
     * Find a tag by slug, creating it if it doesn't exist yet.
     * Returns null if the table is missing (instead of throwing).
     */
    public function ensureSlugExists(string $slug, ?string $name = null, ?string $colour = null): ?OrderTag
    {
        if (!Schema::hasTable('order_tags')) return null;

        $tag = OrderTag::where('slug', $slug)->first();
        if ($tag) return $tag;

        // Use well-known defaults if available, otherwise fall back to args
        $known = self::WELL_KNOWN_TAGS[$slug] ?? null;
        return OrderTag::create([
            'slug'   => $slug,
            'name'   => $name   ?? ($known['name']   ?? ucwords(str_replace('-', ' ', $slug))),
            'colour' => $colour ?? ($known['colour'] ?? '#6B7280'),
        ]);
    }

    /**
     * Attach a tag to an order by slug. Idempotent — repeated calls don't
     * create duplicate pivot rows. Silently no-ops if tag tables missing.
     */
    public function attachToOrder(Order $order, string $slug): bool
    {
        if (!Schema::hasTable('order_tag')) return false;

        try {
            $tag = $this->ensureSlugExists($slug);
            if (!$tag) return false;

            // Use a raw insert ignore to be 100% idempotent. The pivot table
            // has a composite primary key on (order_id, tag_id) so duplicates
            // are blocked at the DB level anyway, but this avoids the noisy
            // exception that throws.
            DB::table('order_tag')->updateOrInsert(
                ['order_id' => $order->id, 'tag_id' => $tag->id],
                ['created_at' => now()]
            );
            return true;
        } catch (\Throwable $e) {
            Log::warning('OrderTagService: attach failed', [
                'order_id' => $order->id,
                'slug'     => $slug,
                'err'      => $e->getMessage(),
            ]);
            return false;
        }
    }
}
