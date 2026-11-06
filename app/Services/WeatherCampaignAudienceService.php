<?php

namespace App\Services;

use App\Models\User;
use App\Models\WeatherCampaign;
use App\Models\ZipCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class WeatherCampaignAudienceService
{
    /**
     * Resolves the audience for a given weather campaign.
     *
     * @param WeatherCampaign $campaign
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function resolve(WeatherCampaign $campaign)
    {
        $query = $this->buildFullQuery($campaign);
        return $query->get();
    }

    /**
     * Estimates the audience size for a given weather campaign.
     *
     * @param WeatherCampaign $campaign
     * @return array
     */
    public function estimate(WeatherCampaign $campaign): array
    {
        $baseQuery = $this->buildBaseQuery();
        $this->applyTargetSegments($baseQuery, $campaign);
        
        $totalEligible = (clone $baseQuery)->count();
        
        $this->applyExclusions($baseQuery, $campaign);
        $this->applyMarketingRules($baseQuery, $campaign);
        
        $finalCount = $baseQuery->count();

        return [
            'total_eligible' => $totalEligible,
            'total_excluded' => $totalEligible - $finalCount,
            'final_count' => $finalCount,
        ];
    }

    /**
     * Builds the complete query applying all segments, exclusions, and marketing rules.
     *
     * @param WeatherCampaign $campaign
     * @return Builder
     */
    protected function buildFullQuery(WeatherCampaign $campaign): Builder
    {
        $query = $this->buildBaseQuery();
        $this->applyTargetSegments($query, $campaign);
        $this->applyExclusions($query, $campaign);
        $this->applyMarketingRules($query, $campaign);
        return $query;
    }

    /**
     * Initializes the base query for marketable users.
     *
     * @return Builder
     */
    protected function buildBaseQuery(): Builder
    {
        return User::query()
            ->where('is_admin', 0)
            ->where('is_guest', 0);
    }

    /**
     * Applies target segment logic (AND/OR groups) to the query, or falls back to legacy zip group.
     *
     * @param Builder $query
     * @param WeatherCampaign $campaign
     */
    protected function applyTargetSegments(Builder $query, WeatherCampaign $campaign): void
    {
        $targetSegments = $campaign->target_segments;

        if (empty($targetSegments) || empty($targetSegments['rules'])) {
            if (!empty($campaign->target_zip_group_id)) {
                // Legacy fallback: Target just the users in the specified zip group
                $query->whereHas('shippingAddresses', function (Builder $q) use ($campaign) {
                    $q->whereIn('zip_code', function ($sub) use ($campaign) {
                        $sub->select('zip_code')
                            ->from('zip_codes')
                            ->where('group_id', $campaign->target_zip_group_id);
                    });
                });
                return;
            }

            // Default safe behavior if no rules and no legacy target are defined: zero matches.
            $query->whereRaw('1 = 0');
            return;
        }

        $query->where(function (Builder $q) use ($targetSegments) {
            $this->applyRuleGroup($q, $targetSegments);
        });
    }

    /**
     * Recursively applies a group of rules with AND/OR logic.
     *
     * @param Builder $query
     * @param array $group
     */
    protected function applyRuleGroup(Builder $query, array $group): void
    {
        $logic = strtoupper($group['logic'] ?? 'AND');
        
        if (!in_array($logic, ['AND', 'OR'])) {
            $query->whereRaw('1 = 0'); // Fail safely
            return;
        }
        
        $rules = $group['rules'] ?? [];
        if (empty($rules)) {
            $query->whereRaw('1 = 0'); // Fail safely
            return;
        }

        foreach ($rules as $rule) {
            if (isset($rule['logic']) && isset($rule['rules'])) {
                // Nested group
                if ($logic === 'OR') {
                    $query->orWhere(function (Builder $subQ) use ($rule) {
                        $this->applyRuleGroup($subQ, $rule);
                    });
                } else {
                    $query->where(function (Builder $subQ) use ($rule) {
                        $this->applyRuleGroup($subQ, $rule);
                    });
                }
            } else {
                // Leaf rule
                if ($logic === 'OR') {
                    $query->orWhere(function (Builder $subQ) use ($rule) {
                        $this->applyRule($subQ, $rule);
                    });
                } else {
                    $query->where(function (Builder $subQ) use ($rule) {
                        $this->applyRule($subQ, $rule);
                    });
                }
            }
        }
    }

    /**
     * Applies an individual leaf rule.
     *
     * @param Builder $query
     * @param array $rule
     */
    protected function applyRule(Builder $query, array $rule): void
    {
        $type = $rule['type'] ?? '';

        switch ($type) {
            case 'all_marketable':
                $query->whereRaw('1 = 1');
                break;
                
            case 'delivery_customer':
                $query->whereHas('orders', function (Builder $q) {
                    $q->confirmed();
                });
                break;
                
            case 'min_orders':
                $min = (int) ($rule['value'] ?? 1);
                $query->has('orders', '>=', $min, 'and', function (Builder $q) {
                    $q->confirmed();
                });
                break;
                
            case 'previous_winter_customer':
                $lastYear = Carbon::now()->subYear()->year;
                $start = Carbon::create($lastYear, 6, 1)->startOfDay();
                $end = Carbon::create($lastYear, 8, 31)->endOfDay();
                
                $query->whereHas('orders', function (Builder $q) use ($start, $end) {
                    $q->confirmed()->whereBetween('created_at', [$start, $end]);
                });
                break;
                
            case 'no_order_days':
                $days = (int) ($rule['value'] ?? 30);
                $cutoff = Carbon::now()->subDays($days);
                
                $query->whereDoesntHave('orders', function (Builder $q) use ($cutoff) {
                    $q->confirmed()->where('created_at', '>=', $cutoff);
                });
                break;
                
            case 'zip_groups':
                $groupIds = $rule['value'] ?? [];
                if (empty($groupIds)) {
                    $query->whereRaw('1 = 0');
                    return;
                }
                
                $query->whereHas('shippingAddresses', function (Builder $q) use ($groupIds) {
                    $q->whereIn('zip_code', function ($sub) use ($groupIds) {
                        $sub->select('zip_code')
                            ->from('zip_codes')
                            ->whereIn('group_id', $groupIds);
                    });
                });
                break;
                
            case 'postcodes':
                $postcodes = $rule['value'] ?? [];
                if (empty($postcodes)) {
                    $query->whereRaw('1 = 0');
                    return;
                }
                $query->whereHas('shippingAddresses', function (Builder $q) use ($postcodes) {
                    $q->whereIn('zip_code', $postcodes);
                });
                break;
                
            default:
                // Unknown rule — match nothing (safe default)
                $query->whereRaw('1 = 0');
                break;
        }
    }

    /**
     * Applies exclusion constraints to the query.
     *
     * @param Builder $query
     * @param WeatherCampaign $campaign
     */
    protected function applyExclusions(Builder $query, WeatherCampaign $campaign): void
    {
        $exclusions = $campaign->exclusions ?? [];

        // Enforce default 7-day cooldown for the SAME campaign to prevent spamming
        // if a weather forecast shifts and retriggers the same event.
        $campaignCutoff = Carbon::now()->subDays(7);
        $query->whereNotExists(function ($sub) use ($campaignCutoff, $campaign) {
            $sub->select(\Illuminate\Support\Facades\DB::raw(1))
                ->from('weather_campaign_sends')
                ->whereColumn('weather_campaign_sends.user_id', 'users.id')
                ->where('weather_campaign_id', $campaign->id)
                ->where('created_at', '>=', $campaignCutoff);
        });

        if (!empty($exclusions['has_current_order'])) {
            $query->whereDoesntHave('orders', function (Builder $q) {
                $q->whereIn('order_status', ['pending', 'confirmed', 'processing', 'shipped']);
            });
        }

        if (!empty($exclusions['recent_order_days'])) {
            $days = (int) $exclusions['recent_order_days'];
            $cutoff = Carbon::now()->subDays($days);
            $query->whereDoesntHave('orders', function (Builder $q) use ($cutoff) {
                $q->confirmed()->where('created_at', '>=', $cutoff);
            });
        }

        if (!empty($exclusions['recent_promo_days'])) {
            $days = (int) $exclusions['recent_promo_days'];
            $cutoff = Carbon::now()->subDays($days);
            $query->whereNotExists(function ($sub) use ($cutoff) {
                $sub->select(\Illuminate\Support\Facades\DB::raw(1))
                    ->from('weather_campaign_sends')
                    ->whereColumn('weather_campaign_sends.user_id', 'users.id')
                    ->where('created_at', '>=', $cutoff);
            });
        }

        if (!empty($exclusions['max_promos_per_week'])) {
            $max = (int) $exclusions['max_promos_per_week'];
            $cutoff = Carbon::now()->subDays(7);
            
            $query->where(function (Builder $q) use ($cutoff, $max) {
                $q->whereRaw('(SELECT COUNT(*) FROM weather_campaign_sends WHERE weather_campaign_sends.user_id = users.id AND created_at >= ?) < ?', [$cutoff, $max]);
            });
        }
    }

    /**
     * Applies marketing opt-out logic.
     *
     * @param Builder $query
     * @param WeatherCampaign $campaign
     */
    protected function applyMarketingRules(Builder $query, WeatherCampaign $campaign): void
    {
        // Only enforce marketing opt-out if the campaign requires it
        if (!empty($campaign->respects_marketing_optout)) {
            $channels = $campaign->channels ?? [];
            
            // If the campaign uses push, exclude users who opted out of marketing push
            if (in_array('push', $channels)) {
                $query->whereDoesntHave('notificationPreferences', function (Builder $q) {
                    $q->where('category', 'marketing')->where('push', false);
                });
            }
            
            // If the campaign uses email, exclude users who opted out of marketing email
            if (in_array('email', $channels)) {
                $query->whereDoesntHave('notificationPreferences', function (Builder $q) {
                    $q->where('category', 'marketing')->where('email', false);
                });
            }
        }
    }
}
