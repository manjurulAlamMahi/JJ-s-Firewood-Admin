<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Models\WeatherCampaign;
use App\Models\WeatherCampaignSend;
use App\Models\ShippingAddress;
use App\Models\NotificationPreference;
use App\Models\ZipCode;
use App\Models\ZipCodeGroup;
use App\Services\WeatherCampaignAudienceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class WeatherCampaignAudienceTest extends TestCase
{
    use RefreshDatabase;

    protected WeatherCampaignAudienceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new WeatherCampaignAudienceService();
    }

    protected function createCampaign(array $attributes = []): WeatherCampaign
    {
        return WeatherCampaign::forceCreate(array_merge([
            'name' => 'Test Campaign',
            'status' => 'draft',
            'campaign_type' => 'automated',
            'requires_approval' => false,
            'lead_time_hours' => 24,
            'respects_marketing_optout' => false,
            'channels' => [],
            'weather_condition' => ['temp_max_c' => 20],
        ], $attributes));
    }

    protected function createOrder(int $userId, string $status, array $extra = [])
    {
        // Ensure a valid shipping address exists to satisfy foreign key constraints
        $address = ShippingAddress::where('user_id', $userId)->first();
        if (!$address) {
            $address = $this->createShippingAddress($userId, '1000');
        }

        return Order::forceCreate(array_merge([
            'user_id' => $userId,
            'shipping_address_id' => $address->id,
            'order_status' => $status,
            'subtotal' => 0.00,
            'delivery_fee' => 0.00,
            'discount' => 0.00,
            'tax' => 0.00,
            'total' => 0.00,
            'payment_status' => 'paid',
            'payment_method' => 'card',
        ], $extra));
    }

    protected function createShippingAddress(int $userId, string $zipCode)
    {
        return ShippingAddress::forceCreate([
            'user_id' => $userId,
            'zip_code' => $zipCode,
            'full_name' => 'Test Name',
            'email' => 'test@example.com',
            'phone' => '12345678',
            'address' => '123 Test St',
            'city' => 'Test City',
            'state' => 'WA',
            'country' => 'AU'
        ]);
    }

    public function test_all_marketable_excludes_admins_and_guests()
    {
        $admin = User::factory()->create(['is_admin' => 1]);
        $guest = User::factory()->create(['is_guest' => 1]);
        $user = User::factory()->create(['is_admin' => 0, 'is_guest' => 0]);

        $campaign = $this->createCampaign([
            'target_segments' => ['logic' => 'AND', 'rules' => [['type' => 'all_marketable']]]
        ]);

        $result = $this->service->resolve($campaign);

        $this->assertCount(1, $result);
        $this->assertEquals($user->id, $result->first()->id);
    }

    public function test_delivery_customer_only_includes_users_with_confirmed_orders()
    {
        $noOrderUser = User::factory()->create();
        
        $pendingOrderUser = User::factory()->create();
        $this->createOrder($pendingOrderUser->id, 'pending');
        
        $cancelledOrderUser = User::factory()->create();
        $this->createOrder($cancelledOrderUser->id, 'cancelled');
        
        $deliveryUser = User::factory()->create();
        $this->createOrder($deliveryUser->id, 'delivered');

        $campaign = $this->createCampaign([
            'target_segments' => ['logic' => 'AND', 'rules' => [['type' => 'delivery_customer']]]
        ]);

        $result = $this->service->resolve($campaign);

        $this->assertCount(1, $result);
        $this->assertEquals($deliveryUser->id, $result->first()->id);
    }

    public function test_min_orders()
    {
        $user1 = User::factory()->create();
        $this->createOrder($user1->id, 'delivered');
        $this->createOrder($user1->id, 'delivered');
        
        $user2 = User::factory()->create();
        $this->createOrder($user2->id, 'delivered');
        $this->createOrder($user2->id, 'delivered');
        $this->createOrder($user2->id, 'delivered');

        $campaign = $this->createCampaign([
            'target_segments' => ['logic' => 'AND', 'rules' => [['type' => 'min_orders', 'value' => 3]]]
        ]);

        $result = $this->service->resolve($campaign);

        $this->assertCount(1, $result);
        $this->assertEquals($user2->id, $result->first()->id);
    }

    public function test_previous_winter_customer()
    {
        $lastYear = Carbon::now()->subYear()->year;
        
        $winterUser = User::factory()->create();
        $this->createOrder($winterUser->id, 'delivered', [
            'created_at' => Carbon::create($lastYear, 7, 15)
        ]);

        $summerUser = User::factory()->create();
        $this->createOrder($summerUser->id, 'delivered', [
            'created_at' => Carbon::create($lastYear, 12, 15)
        ]);

        $campaign = $this->createCampaign([
            'target_segments' => ['logic' => 'AND', 'rules' => [['type' => 'previous_winter_customer']]]
        ]);

        $result = $this->service->resolve($campaign);

        $this->assertCount(1, $result);
        $this->assertEquals($winterUser->id, $result->first()->id);
    }

    public function test_no_order_days()
    {
        $userRecent = User::factory()->create();
        $this->createOrder($userRecent->id, 'delivered', [
            'created_at' => Carbon::now()->subDays(10)
        ]);

        $userOld = User::factory()->create();
        $this->createOrder($userOld->id, 'delivered', [
            'created_at' => Carbon::now()->subDays(40)
        ]);

        $campaign = $this->createCampaign([
            'target_segments' => ['logic' => 'AND', 'rules' => [['type' => 'no_order_days', 'value' => 30]]]
        ]);

        $result = $this->service->resolve($campaign);

        $this->assertCount(1, $result);
        $this->assertEquals($userOld->id, $result->first()->id);
    }

    public function test_zip_groups_and_postcodes()
    {
        $group = ZipCodeGroup::forceCreate(['name' => 'Group 1', 'status' => 1]);
        ZipCode::forceCreate(['group_id' => $group->id, 'zip_code' => '1000', 'status' => 1]);
        
        $userInGroup = User::factory()->create();
        $this->createShippingAddress($userInGroup->id, '1000');
        
        $userInPostcode = User::factory()->create();
        $this->createShippingAddress($userInPostcode->id, '2000');

        $userOutside = User::factory()->create();
        $this->createShippingAddress($userOutside->id, '3000');

        $campaign = $this->createCampaign([
            'target_segments' => [
                'logic' => 'OR',
                'rules' => [
                    ['type' => 'zip_groups', 'value' => [$group->id]],
                    ['type' => 'postcodes', 'value' => ['2000']]
                ]
            ]
        ]);

        $result = $this->service->resolve($campaign);

        $this->assertCount(2, $result);
        $this->assertTrue($result->contains($userInGroup));
        $this->assertTrue($result->contains($userInPostcode));
    }

    public function test_has_current_order_exclusion()
    {
        $userWithCurrent = User::factory()->create();
        $this->createOrder($userWithCurrent->id, 'processing');

        $userWithoutCurrent = User::factory()->create();
        $this->createOrder($userWithoutCurrent->id, 'delivered');

        $campaign = $this->createCampaign([
            'target_segments' => ['logic' => 'AND', 'rules' => [['type' => 'all_marketable']]],
            'exclusions' => ['has_current_order' => true]
        ]);

        $result = $this->service->resolve($campaign);

        $this->assertCount(1, $result);
        $this->assertEquals($userWithoutCurrent->id, $result->first()->id);
    }

    public function test_marketing_opt_out_exclusion()
    {
        $userOptIn = User::factory()->create();
        NotificationPreference::forceCreate(['user_id' => $userOptIn->id, 'category' => 'marketing', 'push' => true]);

        $userOptOut = User::factory()->create();
        NotificationPreference::forceCreate(['user_id' => $userOptOut->id, 'category' => 'marketing', 'push' => false]);

        $campaign = $this->createCampaign([
            'target_segments' => ['logic' => 'AND', 'rules' => [['type' => 'all_marketable']]],
            'respects_marketing_optout' => true,
            'channels' => ['push']
        ]);

        $result = $this->service->resolve($campaign);

        $this->assertCount(1, $result);
        $this->assertEquals($userOptIn->id, $result->first()->id);
    }

    public function test_estimation_returns_correct_counts()
    {
        $user1 = User::factory()->create();
        $this->createOrder($user1->id, 'pending');
        
        $user2 = User::factory()->create();
        
        $campaign = $this->createCampaign([
            'target_segments' => ['logic' => 'AND', 'rules' => [['type' => 'all_marketable']]],
            'exclusions' => ['has_current_order' => true]
        ]);

        $estimate = $this->service->estimate($campaign);

        $this->assertEquals(2, $estimate['total_eligible']);
        $this->assertEquals(1, $estimate['total_excluded']);
        $this->assertEquals(1, $estimate['final_count']);
    }

    public function test_invalid_rules_produce_zero_matches()
    {
        User::factory()->create();
        
        $campaign = $this->createCampaign([
            'target_segments' => ['logic' => 'AND', 'rules' => [['type' => 'invalid_rule_xyz']]]
        ]);

        $result = $this->service->resolve($campaign);
        $this->assertCount(0, $result);
    }
}
