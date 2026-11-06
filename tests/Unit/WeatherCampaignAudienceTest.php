<?php

namespace Tests\Unit;

use App\Models\User;
use App\Models\WeatherCampaign;
use App\Models\WeatherCampaignSend;
use App\Models\Order;
use App\Models\ShippingAddress;
use App\Services\WeatherCampaignAudienceService;
use Tests\TestCase;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class WeatherCampaignAudienceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Database\Eloquent\Model::unguard();
    }

    public function test_has_current_order_exclusion()
    {
        $service = new WeatherCampaignAudienceService();
        $campaign = WeatherCampaign::create([
            'name' => 'Test',
            'status' => 'active',
            'channels' => ['push'],
            'target_segments' => [
                'logic' => 'AND',
                'rules' => [['type' => 'all_marketable']]
            ],
            'exclusions' => ['has_current_order' => true],
            'weather_condition' => []
        ]);

        $userWithOrder = User::create(['name' => 'A', 'email' => 'a@test.com', 'password' => 'secret']);
        $sa1 = ShippingAddress::create(['user_id' => $userWithOrder->id, 'full_name' => 'A', 'phone' => '1', 'email' => 'a@test.com', 'address' => '1']);
        Order::create(['user_id' => $userWithOrder->id, 'order_status' => 'processing', 'total' => 10, 'subtotal' => 10, 'payment_method' => 'card', 'order_number' => 'ORD1', 'shipping_address_id' => $sa1->id]);
        
        $userWithoutOrder = User::create(['name' => 'B', 'email' => 'b@test.com', 'password' => 'secret']);

        $resolved = $service->resolve($campaign);

        $this->assertFalse($resolved->contains('id', $userWithOrder->id));
        $this->assertTrue($resolved->contains('id', $userWithoutOrder->id));
    }

    public function test_recent_order_days_exclusion()
    {
        $service = new WeatherCampaignAudienceService();
        $campaign = WeatherCampaign::create([
            'name' => 'Test',
            'status' => 'active',
            'channels' => ['push'],
            'target_segments' => [
                'logic' => 'AND',
                'rules' => [['type' => 'all_marketable']]
            ],
            'exclusions' => ['recent_order_days' => 14],
            'weather_condition' => []
        ]);

        $userRecent = User::create(['name' => 'A', 'email' => 'a@test.com', 'password' => 'secret']);
        $sa2 = ShippingAddress::create(['user_id' => $userRecent->id, 'full_name' => 'A', 'phone' => '1', 'email' => 'a@test.com', 'address' => '1']);
        Order::create(['user_id' => $userRecent->id, 'order_status' => 'delivered', 'total' => 10, 'subtotal' => 10, 'payment_method' => 'card', 'order_number' => 'ORD1', 'created_at' => Carbon::now()->subDays(5), 'shipping_address_id' => $sa2->id]);
        
        $userOld = User::create(['name' => 'B', 'email' => 'b@test.com', 'password' => 'secret']);
        $sa3 = ShippingAddress::create(['user_id' => $userOld->id, 'full_name' => 'B', 'phone' => '1', 'email' => 'b@test.com', 'address' => '1']);
        Order::create(['user_id' => $userOld->id, 'order_status' => 'delivered', 'total' => 10, 'subtotal' => 10, 'payment_method' => 'card', 'order_number' => 'ORD2', 'created_at' => Carbon::now()->subDays(20), 'shipping_address_id' => $sa3->id]);

        $resolved = $service->resolve($campaign);

        $this->assertFalse($resolved->contains('id', $userRecent->id));
        $this->assertTrue($resolved->contains('id', $userOld->id));
    }

    public function test_recent_promo_days_and_max_promos_per_week()
    {
        $service = new WeatherCampaignAudienceService();
        
        $campaign = WeatherCampaign::create([
            'name' => 'Test',
            'status' => 'active',
            'channels' => ['push'],
            'target_segments' => [
                'logic' => 'AND',
                'rules' => [['type' => 'all_marketable']]
            ],
            'exclusions' => [
                'recent_promo_days' => 7,
                'max_promos_per_week' => 2
            ],
            'weather_condition' => []
        ]);

        $campaign1 = WeatherCampaign::create(['name' => 'Dummy 1', 'channels' => ['push'], 'weather_condition' => []]);
        $campaign2 = WeatherCampaign::create(['name' => 'Dummy 2', 'channels' => ['push'], 'weather_condition' => []]);
        $campaign3 = WeatherCampaign::create(['name' => 'Dummy 3', 'channels' => ['push'], 'weather_condition' => []]);

        $userRecentPromo = User::create(['name' => 'A', 'email' => 'a@test.com', 'password' => 'secret']);
        WeatherCampaignSend::create([
            'weather_campaign_id' => $campaign1->id,
            'user_id' => $userRecentPromo->id,
            'channel' => 'push',
            'trigger_date' => Carbon::now()->subDays(2)->toDateString(),
            'status' => 'sent',
        ]);

        $userTooManyPromos = User::create(['name' => 'B', 'email' => 'b@test.com', 'password' => 'secret']);
        WeatherCampaignSend::create([
            'weather_campaign_id' => $campaign2->id,
            'user_id' => $userTooManyPromos->id,
            'channel' => 'push',
            'trigger_date' => Carbon::now()->subDays(3)->toDateString(),
            'status' => 'sent',
        ]);
        WeatherCampaignSend::create([
            'weather_campaign_id' => $campaign3->id,
            'user_id' => $userTooManyPromos->id,
            'channel' => 'email',
            'trigger_date' => Carbon::now()->subDays(4)->toDateString(),
            'status' => 'sent',
        ]);

        $userClean = User::create(['name' => 'C', 'email' => 'c@test.com', 'password' => 'secret']);

        $resolved = $service->resolve($campaign);

        // userRecentPromo excluded by recent_promo_days
        $this->assertFalse($resolved->contains('id', $userRecentPromo->id));
        
        // userTooManyPromos actually fails max_promos_per_week
        $this->assertFalse($resolved->contains('id', $userTooManyPromos->id));
        
        $this->assertTrue($resolved->contains('id', $userClean->id));
    }

    public function test_marketing_opt_out()
    {
        $service = new WeatherCampaignAudienceService();
        $campaign = WeatherCampaign::create([
            'name' => 'Test',
            'status' => 'active',
            'channels' => ['email'],
            'target_segments' => [
                'logic' => 'AND',
                'rules' => [['type' => 'all_marketable']]
            ],
            'respects_marketing_optout' => true,
            'weather_condition' => []
        ]);

        $userOptOut = User::create(['name' => 'A', 'email' => 'a@test.com', 'password' => 'secret']);
        $userOptOut->notificationPreferences()->create([
            'category' => 'marketing',
            'email' => false
        ]);

        $userOptIn = User::create(['name' => 'B', 'email' => 'b@test.com', 'password' => 'secret']);

        $resolved = $service->resolve($campaign);

        $this->assertFalse($resolved->contains('id', $userOptOut->id));
        $this->assertTrue($resolved->contains('id', $userOptIn->id));
    }

    public function test_min_orders_repeat_customer()
    {
        $service = new WeatherCampaignAudienceService();
        $campaign = WeatherCampaign::create([
            'name' => 'Test',
            'status' => 'active',
            'channels' => ['push'],
            'target_segments' => [
                'logic' => 'AND',
                'rules' => [
                    ['type' => 'min_orders', 'value' => 3]
                ]
            ],
            'weather_condition' => []
        ]);

        $userVIP = User::create(['name' => 'A', 'email' => 'a@test.com', 'password' => 'secret']);
        $sa4 = ShippingAddress::create(['user_id' => $userVIP->id, 'full_name' => 'A', 'phone' => '1', 'email' => 'a@test.com', 'address' => '1']);
        for($i = 0; $i < 3; $i++) {
            Order::create(['user_id' => $userVIP->id, 'order_status' => 'delivered', 'total' => 10, 'subtotal' => 10, 'payment_method' => 'card', 'order_number' => "ORD_$i", 'shipping_address_id' => $sa4->id]);
        }

        $userNormal = User::create(['name' => 'B', 'email' => 'b@test.com', 'password' => 'secret']);
        $sa5 = ShippingAddress::create(['user_id' => $userNormal->id, 'full_name' => 'B', 'phone' => '1', 'email' => 'b@test.com', 'address' => '1']);
        Order::create(['user_id' => $userNormal->id, 'order_status' => 'delivered', 'total' => 10, 'subtotal' => 10, 'payment_method' => 'card', 'order_number' => 'ORDX', 'shipping_address_id' => $sa5->id]);

        $resolved = $service->resolve($campaign);

        $this->assertTrue($resolved->contains('id', $userVIP->id));
        $this->assertFalse($resolved->contains('id', $userNormal->id));
    }

    public function test_default_7_day_same_campaign_cooldown()
    {
        $service = new WeatherCampaignAudienceService();
        $campaign = WeatherCampaign::create([
            'id' => 12345,
            'name' => 'Same Campaign Cooldown',
            'status' => 'active',
            'channels' => ['push'],
            'target_segments' => [
                'logic' => 'AND',
                'rules' => [['type' => 'all_marketable']]
            ],
            'weather_condition' => []
        ]);

        $userCooledDown = User::create(['name' => 'A', 'email' => 'a@test.com', 'password' => 'secret']);
        WeatherCampaignSend::create([
            'weather_campaign_id' => $campaign->id,
            'user_id' => $userCooledDown->id,
            'channel' => 'push',
            'trigger_date' => Carbon::now()->subDays(3)->toDateString(),
            'status' => 'sent',
            'created_at' => Carbon::now()->subDays(3)
        ]);

        $userEligible = User::create(['name' => 'B', 'email' => 'b@test.com', 'password' => 'secret']);
        WeatherCampaignSend::create([
            'weather_campaign_id' => $campaign->id,
            'user_id' => $userEligible->id,
            'channel' => 'push',
            'trigger_date' => Carbon::now()->subDays(8)->toDateString(),
            'status' => 'sent',
            'created_at' => Carbon::now()->subDays(8)
        ]);

        $resolved = $service->resolve($campaign);

        $this->assertFalse($resolved->contains('id', $userCooledDown->id));
        $this->assertTrue($resolved->contains('id', $userEligible->id));
    }
}
