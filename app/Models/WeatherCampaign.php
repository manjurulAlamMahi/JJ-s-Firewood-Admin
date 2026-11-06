<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WeatherCampaign extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'status',
        'channels',
        'title',
        'message',
        'banner_image',
        'banner_cta_text',
        'banner_cta_link',
        'target_zip_group_id',
        'weather_condition',
        'active_days',
        'active_from',
        'active_until',
        'respects_marketing_optout',
        'last_evaluated_at',
        'last_matched_zip_codes',
        'created_by',
        'campaign_type',
        'requires_approval',
        'target_segments',
        'exclusions',
        'capacity_thresholds',
        'channel_content',
        'lead_time_hours',
        'sending_time_start',
        'sending_time_end',
    ];

    protected $casts = [
        'channels' => 'array',
        'weather_condition' => 'array',
        'active_days' => 'array',
        'active_from' => 'date',
        'active_until' => 'date',
        'respects_marketing_optout' => 'boolean',
        'last_evaluated_at' => 'datetime',
        'last_matched_zip_codes' => 'array',
        'requires_approval' => 'boolean',
        'target_segments' => 'array',
        'exclusions' => 'array',
        'capacity_thresholds' => 'array',
        'channel_content' => 'array',
        'lead_time_hours' => 'integer',
    ];

    public function zipGroup()
    {
        return $this->belongsTo(ZipCodeGroup::class, 'target_zip_group_id');
    }

    public function sends()
    {
        return $this->hasMany(WeatherCampaignSend::class);
    }

    /**
     * Whether today falls within the rule's active_from/active_until window.
     */
    public function isWithinActiveDateWindow(?\DateTimeInterface $today = null): bool
    {
        $today = $today ?? now();

        if ($this->active_from && $today->lt($this->active_from)) {
            return false;
        }

        if ($this->active_until && $today->gt($this->active_until)) {
            return false;
        }

        return true;
    }

    /**
     * Whether today's ISO weekday (1=Monday .. 7=Sunday) is allowed by active_days.
     * A null/empty active_days means "every day".
     */
    public function isActiveOnDay(?\DateTimeInterface $today = null): bool
    {
        if (empty($this->active_days)) {
            return true;
        }

        $today = $today ?? now();

        return in_array((int) $today->format('N'), array_map('intval', $this->active_days), true);
    }

    /**
     * Whether the current time falls within the allowed sending_time_start/end.
     */
    public function isWithinSendingTime(?\DateTimeInterface $now = null): bool
    {
        if (empty($this->sending_time_start) && empty($this->sending_time_end)) {
            return true;
        }
        
        $now = $now ?? now();
        $currentTime = $now->format('H:i:s');

        if (!empty($this->sending_time_start) && $currentTime < $this->sending_time_start) {
            return false;
        }

        if (!empty($this->sending_time_end) && $currentTime > $this->sending_time_end) {
            return false;
        }

        return true;
    }

    /**
     * Evaluates a multi-day forecast array. Returns the matched snapshot containing the 
     * target date if the conditions are satisfied.
     * 
     * @param array $multiDayForecast Map of Y-m-d => daily forecast
     */
    public function evaluateMultiDayWeather(array $multiDayForecast): ?array
    {
        $condition = $this->weather_condition ?? [];
        
        // Fallback to defaults
        $forecastDaysOut = (int) ($condition['forecast_days_out'] ?? 3); 
        $requiredMatchingDays = (int) ($condition['required_matching_days'] ?? 1);
        $logic = strtoupper($condition['condition_logic'] ?? 'AND');

        // Slice the forecast array up to the forecast_days_out window
        $window = array_slice($multiDayForecast, 0, $forecastDaysOut, true);

        $matchedSnapshots = [];

        foreach ($window as $date => $dailyForecast) {
            if ($this->dayMatchesWeather($dailyForecast, $condition, $logic)) {
                $matchedSnapshots[] = $dailyForecast;
            }
        }

        if (count($matchedSnapshots) >= $requiredMatchingDays) {
            // The first matching day is our trigger event anchor
            return $matchedSnapshots[0];
        }

        return null;
    }

    private function dayMatchesWeather(array $forecast, array $condition, string $logic): bool
    {
        $matches = [];

        if (isset($condition['temp_max_c']) && isset($forecast['temp_max_c'])) {
            $matches[] = $forecast['temp_max_c'] <= (float) $condition['temp_max_c'];
        }

        if (isset($condition['rain_probability_min']) && isset($forecast['pop'])) {
            $popPercent = $forecast['pop'] * 100;
            $matches[] = $popPercent >= (float) $condition['rain_probability_min'];
        }

        if (isset($condition['expected_rainfall_min_mm']) && isset($forecast['expected_rainfall_min_mm'])) {
            $matches[] = $forecast['expected_rainfall_min_mm'] >= (float) $condition['expected_rainfall_min_mm'];
        }

        if (!empty($condition['condition_codes']) && isset($forecast['condition_main'])) {
            $matches[] = in_array($forecast['condition_main'], $condition['condition_codes'], true);
        }

        if (empty($matches)) {
            return true; // No conditions defined means it inherently matches
        }

        if ($logic === 'OR') {
            return in_array(true, $matches, true);
        }

        // AND logic
        return !in_array(false, $matches, true);
    }
}
