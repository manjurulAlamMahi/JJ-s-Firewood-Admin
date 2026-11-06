<?php

namespace App\Services\Notifications;

use App\Models\NotificationSuppression;
use App\Models\Order;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * AudienceResolver — turns an audience_spec JSON into concrete recipient lists.
 *
 * The spec is a simple include/exclude/handpicked/raw structure:
 *
 *   {
 *     "include": [
 *       {"type": "all_users"},
 *       {"type": "suburb",        "value": "Joondalup"},
 *       {"type": "active_since",  "days": 30},
 *       {"type": "has_orders",    "min": 1},
 *       {"type": "min_lifetime_spend", "value": 200},
 *       {"type": "has_referral_code"},
 *       {"type": "is_admin"}
 *     ],
 *     "exclude": [
 *       {"type": "users", "ids": [12, 34]},
 *       {"type": "suburb", "value": "Mandurah"}
 *     ],
 *     "handpicked_user_ids": [22, 88],     // these always get added
 *     "raw_recipients": ["+61400123456"]    // SMS-only — these get appended without being a user
 *   }
 *
 * Resolution = (UNION of include rules) ∪ handpicked − (UNION of exclude rules) − suppressed.
 *
 * If `respects_marketing_optout` is true, users with notification_preferences.category='marketing'
 * and the relevant channel = false are also excluded.
 */
class AudienceResolver
{
    /**
     * Resolve the audience for a notification.
     *
     * @param array  $spec               The audience_spec JSON (decoded)
     * @param string $channel            'push' | 'sms' | 'email' | 'email_marketing'
     * @param bool   $respectsOptOut     Whether to filter out marketing opt-outs
     * @return array {
     *   users: Collection of User models with the right contact field populated,
     *   raw_phones: array of phone strings (for SMS-only raw recipients),
     *   raw_emails: array of email strings (for email-only raw recipients),
     *   total_estimate: int,
     *   suppressed_count: int,
     *   optout_count: int
     * }
     */
    public function resolve(array $spec, string $channel, bool $respectsOptOut = true): array
    {
        $includeRules = $spec['include'] ?? [];
        $excludeRules = $spec['exclude'] ?? [];
        $handpicked   = array_map('intval', $spec['handpicked_user_ids'] ?? []);

        // The rules-based pool. If no include rules: empty (no one is matched by rules).
        // Rules are OPTIONAL — handpicked + raw can carry the audience alone.
        $matchedIds = [];
        if (!empty($includeRules)) {
            $users = User::query()
                ->where('is_admin', '!=', 1)
                ->whereNotNull($this->contactColumnFor($channel))
                ->where(function ($q) use ($includeRules) {
                    foreach ($includeRules as $rule) {
                        $q->orWhere(function ($q2) use ($rule) {
                            $this->applyIncludeRule($q2, $rule);
                        });
                    }
                });

            // Apply exclude rules to the rule-based pool only
            // (Excludes never remove handpicked — handpicks are explicit overrides.)
            foreach ($excludeRules as $rule) {
                $this->applyExcludeRule($users, $rule);
            }

            $matchedIds = $users->pluck('users.id')->all();
        }

        // Combine: rules ∪ handpicks
        $allIds = array_unique(array_merge($matchedIds, $handpicked));

        if (empty($allIds)) {
            $finalUsers = collect();
        } else {
            $finalUsers = User::query()
                ->whereIn('id', $allIds)
                ->whereNotNull($this->contactColumnFor($channel))
                ->where('is_admin', '!=', 1)
                ->get();
        }

        // Filter out suppressed values + opt-outs
        $contactCol = $this->contactColumnFor($channel);
        $beforeSuppressed = $finalUsers->count();


        // Build the list of suppression channels to honour:
        //  - Always: the hard channel (email/sms/push) — bounces, complaints, full opt-outs
        //  - Plus, if this campaign respects marketing opt-outs: the *_marketing channel too
        $primary = $this->primaryChannelFor($channel);
        $suppressionChannels = array_unique([$primary, $channel]);
        if ($respectsOptOut) {
            $suppressionChannels[] = $primary . '_marketing';
        }

        $suppressedValues = NotificationSuppression::whereIn('channel', $suppressionChannels)
            ->pluck('value')->all();

        $finalUsers = $finalUsers->reject(function ($u) use ($contactCol, $suppressedValues) {
            return in_array((string) $u->{$contactCol}, $suppressedValues, true);
        });

        $afterSuppressed = $finalUsers->count();
        $suppressedCount = $beforeSuppressed - $afterSuppressed;

        // Marketing opt-outs (only if this campaign respects them)
        $optoutCount = 0;
        if ($respectsOptOut && Schema::hasTable('notification_preferences')) {
            $prefCol = $this->prefColumnFor($channel);
            if ($prefCol) {
                $optedOutIds = DB::table('notification_preferences')
                    ->where('category', 'marketing')
                    ->where($prefCol, false)
                    ->pluck('user_id')
                    ->all();

                $beforeOptout = $finalUsers->count();
                $finalUsers = $finalUsers->reject(fn ($u) => in_array($u->id, $optedOutIds, true));
                $optoutCount = $beforeOptout - $finalUsers->count();
            }
        }

        // Raw recipients for SMS/email (numbers/emails not associated with a user)
        $rawPhones = [];
        $rawEmails = [];
        foreach (($spec['raw_recipients'] ?? []) as $r) {
            $r = trim((string) $r);
            if ($r === '') continue;
            if (str_contains($r, '@')) {
                $rawEmails[] = $r;
            } else {
                $rawPhones[] = $r;
            }
        }
        // De-dup raw recipients against suppression list too
        $rawPhones = array_values(array_diff($rawPhones, $suppressedValues));
        $rawEmails = array_values(array_diff($rawEmails, $suppressedValues));

        return [
            'users'             => $finalUsers->values(),
            'raw_phones'        => array_values(array_unique($rawPhones)),
            'raw_emails'        => array_values(array_unique($rawEmails)),
            'total_estimate'    => $finalUsers->count() + count($rawPhones) + count($rawEmails),
            'suppressed_count'  => $suppressedCount,
            'optout_count'      => $optoutCount,
        ];
    }

    /**
     * Lightweight estimate without loading user records — for live UI counts as
     * the user is building the audience.
     */
    public function estimate(array $spec, string $channel, bool $respectsOptOut = true): array
    {
        $contactCol = $this->contactColumnFor($channel);

        $includeRules = $spec['include'] ?? [];
        $excludeRules = $spec['exclude'] ?? [];
        $handpicked   = array_map('intval', $spec['handpicked_user_ids'] ?? []);

        // Rules-based count (zero if no rules — matching resolve() behaviour)
        $matchedIds = [];
        if (!empty($includeRules)) {
            $query = User::query()
                ->where('is_admin', '!=', 1)
                ->whereNotNull($contactCol)
                ->where(function ($q) use ($includeRules) {
                    foreach ($includeRules as $rule) {
                        $q->orWhere(function ($q2) use ($rule) {
                            $this->applyIncludeRule($q2, $rule);
                        });
                    }
                });
            foreach ($excludeRules as $rule) {
                $this->applyExcludeRule($query, $rule);
            }
            $matchedIds = $query->pluck('users.id')->all();
        }

        // Combine with handpicks (de-duped)
        $allIds = array_unique(array_merge($matchedIds, $handpicked));
        $matched = 0;
        if (!empty($allIds)) {
            $matched = User::whereIn('id', $allIds)
                ->where('is_admin', '!=', 1)
                ->whereNotNull($contactCol)
                ->count();
        }

        $rawCount = count(array_filter($spec['raw_recipients'] ?? []));

        return [
            'matched_users' => $matched,
            'raw_count'     => $rawCount,
            'total'         => $matched + $rawCount,
        ];
    }

    // ============================================================
    // INTERNAL — rule application
    // ============================================================

    protected function applyIncludeRule($q, array $rule): void
    {
        $type = $rule['type'] ?? '';
        switch ($type) {
            case 'all_users':
                $q->whereRaw('1=1');
                break;

            case 'suburb':
                // Match users with at least one shipping_address in this suburb
                $suburb = trim((string) ($rule['value'] ?? ''));
                if ($suburb === '') { $q->whereRaw('1=0'); return; }
                $q->whereExists(function ($sub) use ($suburb) {
                    $sub->select(DB::raw(1))
                        ->from('shipping_addresses')
                        ->whereColumn('shipping_addresses.user_id', 'users.id')
                        ->where(function ($w) use ($suburb) {
                            $w->where('suburb', 'LIKE', "%{$suburb}%")
                              ->orWhere('city',  'LIKE', "%{$suburb}%");
                        });
                });
                break;

            case 'zip_codes':
                // Match users with at least one shipping_address whose zip_code
                // is in an explicit list (used by weather-triggered campaigns,
                // where the matched list is computed per run, not a static group).
                $zips = array_map('strval', $rule['zip_codes'] ?? []);
                if (empty($zips)) { $q->whereRaw('1=0'); return; }
                $q->whereExists(function ($sub) use ($zips) {
                    $sub->select(DB::raw(1))
                        ->from('shipping_addresses')
                        ->whereColumn('shipping_addresses.user_id', 'users.id')
                        ->whereIn('shipping_addresses.zip_code', $zips);
                });
                break;

            case 'state':
                $state = trim((string) ($rule['value'] ?? ''));
                if ($state === '') { $q->whereRaw('1=0'); return; }
                $q->whereExists(function ($sub) use ($state) {
                    $sub->select(DB::raw(1))
                        ->from('shipping_addresses')
                        ->whereColumn('shipping_addresses.user_id', 'users.id')
                        ->where('state', $state);
                });
                break;

            case 'active_since':
                $days = (int) ($rule['days'] ?? 30);
                $cutoff = Carbon::now()->subDays($days);
                $q->where(function ($w) use ($cutoff) {
                    $w->where('updated_at', '>=', $cutoff)
                      ->orWhereExists(function ($sub) use ($cutoff) {
                          $sub->select(DB::raw(1))
                              ->from('orders')
                              ->whereColumn('orders.user_id', 'users.id')
                              ->where('orders.created_at', '>=', $cutoff);
                      });
                });
                break;

            case 'has_orders':
                $min = (int) ($rule['min'] ?? 1);
                $q->whereRaw('(SELECT COUNT(*) FROM orders WHERE orders.user_id = users.id) >= ?', [$min]);
                break;

            case 'min_lifetime_spend':
                $value = (float) ($rule['value'] ?? 0);
                $q->whereRaw(
                    "(SELECT COALESCE(SUM(total),0) FROM orders WHERE orders.user_id = users.id AND orders.payment_status='paid') >= ?",
                    [$value]
                );
                break;

            case 'has_referral_code':
                $q->whereNotNull('referral_code');
                break;

            case 'never_ordered':
                $q->whereDoesntHave('orders');
                break;

            default:
                // Unknown rule — match nothing (safe default)
                $q->whereRaw('1=0');
        }
    }

    protected function applyExcludeRule($query, array $rule): void
    {
        $type = $rule['type'] ?? '';
        switch ($type) {
            case 'users':
                $ids = array_map('intval', $rule['ids'] ?? []);
                if (!empty($ids)) $query->whereNotIn('users.id', $ids);
                break;

            case 'suburb':
                $suburb = trim((string) ($rule['value'] ?? ''));
                if ($suburb === '') return;
                $query->whereNotExists(function ($sub) use ($suburb) {
                    $sub->select(DB::raw(1))
                        ->from('shipping_addresses')
                        ->whereColumn('shipping_addresses.user_id', 'users.id')
                        ->where(function ($w) use ($suburb) {
                            $w->where('suburb', 'LIKE', "%{$suburb}%")
                              ->orWhere('city',  'LIKE', "%{$suburb}%");
                        });
                });
                break;
        }
    }

    /** Which user column holds the contact info for this channel? */
    protected function contactColumnFor(string $channel): string
    {
        return match (true) {
            str_contains($channel, 'email') => 'email',
            str_contains($channel, 'sms')   => 'phone',
            str_contains($channel, 'push')  => 'fcm_token',
            default                         => 'email',
        };
    }

    /** Which preference column ('email' or 'push') in notification_preferences? */
    protected function prefColumnFor(string $channel): ?string
    {
        return match (true) {
            str_contains($channel, 'email') => 'email',
            str_contains($channel, 'push')  => 'push',
            default                         => null,
        };
    }

    /** Map sub-channels to primary channel keys for suppression lookups */
    protected function primaryChannelFor(string $channel): string
    {
        if (str_contains($channel, 'sms'))   return 'sms';
        if (str_contains($channel, 'email')) return 'email';
        if (str_contains($channel, 'push'))  return 'push';
        return $channel;
    }

    /**
     * Return suburb suggestions for the audience builder dropdown.
     */
    public function getSuburbList(int $limit = 100): array
    {
        return DB::table('shipping_addresses')
            ->select('suburb')
            ->whereNotNull('suburb')
            ->where('suburb', '!=', '')
            ->groupBy('suburb')
            ->orderByRaw('COUNT(*) DESC')
            ->limit($limit)
            ->pluck('suburb')
            ->all();
    }
}
