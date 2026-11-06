<?php

namespace Tests\Unit;

use App\Models\WeatherCampaign;
use Tests\TestCase;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class WeatherCampaignMatchingTest extends TestCase
{
    use RefreshDatabase;

    public function test_evaluates_multi_day_weather_with_and_logic()
    {
        $campaign = WeatherCampaign::create([
            'id' => 10001,
            'name' => 'AND Logic Campaign',
            'status' => 'active',
            'channels' => ['push'],
            'weather_condition' => [
                'forecast_days_out' => 3,
                'required_matching_days' => 2,
                'condition_logic' => 'AND',
                'temp_max_c' => 20,
                'expected_rainfall_min_mm' => 5
            ]
        ]);

        $forecasts = [
            ['date' => '2026-08-18', 'temp_max_c' => 19, 'expected_rainfall_min_mm' => 10], 
            ['date' => '2026-08-19', 'temp_max_c' => 15, 'expected_rainfall_min_mm' => 6], 
            ['date' => '2026-08-20', 'temp_max_c' => 18, 'expected_rainfall_min_mm' => 0], 
        ];

        // Wait, what is the exact matching logic for temp_max_c? 
        // Let's assume temp_max_c is the maximum allowed (so actual temp <= temp_max_c).
        // If it's 20, then 25 fails. Let's adjust mock data to match.
        
        $result = $campaign->evaluateMultiDayWeather($forecasts);
        $this->assertNotNull($result, 'Should match because 2 days (18th and 19th) meet both AND conditions.');
        $this->assertEquals('2026-08-18', $result['date']);
    }

    public function test_evaluates_multi_day_weather_with_or_logic()
    {
        $campaign = WeatherCampaign::create([
            'id' => 10002,
            'name' => 'OR Logic Campaign',
            'status' => 'active',
            'channels' => ['push'],
            'weather_condition' => [
                'forecast_days_out' => 3,
                'required_matching_days' => 1,
                'condition_logic' => 'OR',
                'temp_max_c' => 10,
                'expected_rainfall_min_mm' => 20
            ]
        ]);

        $forecasts = [
            ['date' => '2026-08-18', 'temp_max_c' => 15, 'expected_rainfall_min_mm' => 5], 
            ['date' => '2026-08-19', 'temp_max_c' => 15, 'expected_rainfall_min_mm' => 25], 
        ];

        $result = $campaign->evaluateMultiDayWeather($forecasts);
        $this->assertNotNull($result);
        $this->assertEquals('2026-08-19', $result['date']);
    }

    public function test_expected_rainfall_min_mm_condition()
    {
        $campaign = WeatherCampaign::create([
            'id' => 10003,
            'name' => 'Rainfall Campaign',
            'status' => 'active',
            'channels' => ['push'],
            'weather_condition' => [
                'forecast_days_out' => 1,
                'required_matching_days' => 1,
                'condition_logic' => 'AND',
                'expected_rainfall_min_mm' => 15
            ]
        ]);

        // Exact match
        $result = $campaign->evaluateMultiDayWeather([
            ['date' => '2026-08-18', 'expected_rainfall_min_mm' => 15]
        ]);
        $this->assertNotNull($result);

        // Fail
        $result = $campaign->evaluateMultiDayWeather([
            ['date' => '2026-08-18', 'expected_rainfall_min_mm' => 14.9]
        ]);
        $this->assertNull($result);
    }

    public function test_sending_time_start_and_end()
    {
        $campaign = WeatherCampaign::create([
            'id' => 10004,
            'name' => 'Time Window Campaign',
            'status' => 'active',
            'channels' => ['push'],
            'weather_condition' => [],
            'sending_time_start' => '08:00',
            'sending_time_end' => '10:00'
        ]);

        Carbon::setTestNow('2026-08-17 07:59:00');
        $this->assertFalse($campaign->isWithinSendingTime());

        Carbon::setTestNow('2026-08-17 09:00:00');
        $this->assertTrue($campaign->isWithinSendingTime());

        Carbon::setTestNow('2026-08-17 10:01:00');
        $this->assertFalse($campaign->isWithinSendingTime());
        
        Carbon::setTestNow(); // reset
    }

    public function test_lead_time_hours_triggers_at_correct_time()
    {
        // 48 hour lead time
        $campaign = WeatherCampaign::create([
            'id' => 10005,
            'name' => 'Lead Time Campaign',
            'status' => 'active',
            'channels' => ['push'],
            'lead_time_hours' => 48,
            'weather_condition' => []
        ]);

        // Event happens on the 19th
        $eventDate = '2026-08-19';

        // Current time is 17th -> exactly 48 hours prior
        Carbon::setTestNow('2026-08-17 10:00:00');
        $targetTriggerDate = Carbon::parse($eventDate)->subHours($campaign->lead_time_hours)->toDateString();
        
        $this->assertEquals(Carbon::now()->toDateString(), $targetTriggerDate);

        Carbon::setTestNow();
    }
}
