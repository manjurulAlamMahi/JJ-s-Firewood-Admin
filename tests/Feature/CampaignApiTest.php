<?php

namespace Tests\Feature;

use App\Models\WeatherCampaign;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CampaignApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_an_active_campaign_by_id(): void
    {
        $campaign = WeatherCampaign::create([
            'name' => 'Cold Snap Test',
            'status' => 'active',
            'channels' => ['banner'],
            'weather_condition' => 'cold',
            'title' => 'Stock up before the cold snap',
            'message' => '20% off firewood bundles this week.',
            'banner_cta_text' => 'Shop Now',
            'banner_cta_link' => 'https://example.com',
        ]);

        $response = $this->getJson("/api/v2/campaigns/{$campaign->id}");

        $response->assertStatus(200);
        $this->assertSame('Stock up before the cold snap', $response->json('data.title'));
        $this->assertSame('Shop Now', $response->json('data.cta_text'));
    }

    public function test_404s_for_inactive_campaign(): void
    {
        $campaign = WeatherCampaign::create([
            'name' => 'Draft Campaign',
            'status' => 'draft',
            'channels' => ['banner'],
            'weather_condition' => 'cold',
            'title' => 'Not live yet',
            'message' => 'Should not be visible.',
        ]);

        $response = $this->getJson("/api/v2/campaigns/{$campaign->id}");

        $response->assertStatus(404);
    }

    public function test_404s_for_missing_campaign(): void
    {
        $response = $this->getJson('/api/v2/campaigns/999999');

        $response->assertStatus(404);
    }
}
