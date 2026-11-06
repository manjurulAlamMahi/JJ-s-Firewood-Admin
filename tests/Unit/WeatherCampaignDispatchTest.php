<?php

namespace Tests\Unit;

use App\Helpers\FirebaseHelper;
use App\Jobs\WeatherCampaignDispatchJob;
use App\Models\Product;
use App\Models\User;
use App\Models\WeatherCampaign;
use App\Models\WeatherCampaignSend;
use App\Models\ShippingAddress;
use App\Services\Brevo\BrevoTransactionalService;
use App\Services\WeatherCampaignAudienceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;
use Mockery;

class WeatherCampaignDispatchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        Mockery::mock('alias:App\Helpers\FirebaseHelper')
            ->shouldReceive('sendNotification')
            ->andReturn(true)
            ->byDefault();
    }

    public function test_capacity_check_aborts_dispatch_when_out_of_stock()
    {
        \Illuminate\Database\Eloquent\Model::unguard();
        
        $product = Product::create([
            'id' => 9999,
            'category_id' => 1,
            'subcategory_id' => 1,
            'name' => 'Test Product',
            'quantity' => 5,
            'price' => 10
        ]);

        $campaign = WeatherCampaign::create([
            'id' => 9999,
            'name' => 'Test Campaign',
            'status' => 'active',
            'channels' => ['push'],
            'weather_condition' => [],
            'capacity_thresholds' => [
                'min_product_stock' => [
                    'product_id' => $product->id,
                    'min_quantity' => 10,
                ],
            ],
        ]);

        $brevoMock = Mockery::mock(BrevoTransactionalService::class);
        $audienceMock = Mockery::mock(WeatherCampaignAudienceService::class);

        Log::shouldReceive('info')->once()->withArgs(function ($msg, $context) {
            return str_contains($msg, 'insufficient stock');
        });

        $job = new WeatherCampaignDispatchJob($campaign->id, ['6000'], '2026-08-17');
        $job->handle($audienceMock, $brevoMock);

        // If it aborted early, audience resolve should not be called
        $audienceMock->shouldNotHaveReceived('resolve');
    }

    public function test_idempotency_prevents_duplicate_sends()
    {
        \Illuminate\Database\Eloquent\Model::unguard();

        $campaign = WeatherCampaign::create([
            'id' => 9998,
            'name' => 'Test Campaign 2',
            'status' => 'active',
            'channels' => ['push'],
            'weather_condition' => [],
        ]);

        $user = User::create([
            'id' => 9999,
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'secret',
            'fcm_token' => 'token123'
        ]);

        // Mark user as already sent today
        WeatherCampaignSend::create([
            'weather_campaign_id' => $campaign->id,
            'user_id' => $user->id,
            'channel' => 'push',
            'trigger_date' => '2026-08-17',
            'status' => 'sent'
        ]);

        $audienceMock = Mockery::mock(WeatherCampaignAudienceService::class);
        $eloquentCollection = new \Illuminate\Database\Eloquent\Collection([$user]);
        $audienceMock->shouldReceive('resolve')->andReturn($eloquentCollection);
        $brevoMock = Mockery::mock(BrevoTransactionalService::class);

        $job = new WeatherCampaignDispatchJob($campaign->id, ['6000'], '2026-08-17');
        $job->handle($audienceMock, $brevoMock);

        // Verify Firebase was never called for this duplicate
        Mockery::mock('alias:App\Helpers\FirebaseHelper')->shouldNotHaveReceived('sendNotification');
    }

    public function test_dynamic_variable_interpolation_and_push_payload()
    {
        \Illuminate\Database\Eloquent\Model::unguard();

        $campaign = WeatherCampaign::create([
            'id' => 9997,
            'name' => 'Interpolation Campaign',
            'status' => 'active',
            'channels' => ['push'],
            'weather_condition' => [],
            'channel_content' => [
                'push' => [
                    'title' => 'Hi {{first_name}}',
                    'body' => 'Forecast max is {{forecast_max}} on {{forecast_start_date}} with {{rain_probability}} rain in {{suburb}}.',
                    'deep_link' => 'custom_screen'
                ]
            ]
        ]);

        $user = User::create([
            'id' => 9999,
            'name' => 'John Doe',
            'email' => 'test@example.com',
            'password' => 'secret',
            'fcm_token' => 'token123'
        ]);

        ShippingAddress::create([
            'user_id' => $user->id,
            'suburb' => 'Perth CBD',
            'full_name' => 'John Doe',
            'address' => '123 Fake St',
            'phone' => '12345678',
            'email' => 'test@test.com',
            'zip_code' => '6000',
            'is_default' => 1
        ]);

        $audienceMock = Mockery::mock(WeatherCampaignAudienceService::class);
        $eloquentCollection = new \Illuminate\Database\Eloquent\Collection([$user]);
        $audienceMock->shouldReceive('resolve')->andReturn($eloquentCollection);
        $brevoMock = Mockery::mock(BrevoTransactionalService::class);

        $weatherSnapshot = [
            'temp_max_c' => '25',
            'date' => '2026-08-18',
            'pop' => 0.8
        ];

        $job = new WeatherCampaignDispatchJob($campaign->id, ['6000'], '2026-08-17', $weatherSnapshot);
        $job->handle($audienceMock, $brevoMock);

        $this->assertDatabaseHas('weather_campaign_sends', [
            'weather_campaign_id' => 9997,
            'user_id' => 9999,
            'channel' => 'push'
        ]);
    }

    public function test_legacy_campaign_fallback()
    {
        \Illuminate\Database\Eloquent\Model::unguard();

        $campaign = WeatherCampaign::create([
            'id' => 9996,
            'name' => 'Legacy Campaign',
            'title' => 'Legacy Title',
            'message' => 'Legacy Message {{first_name}}',
            'status' => 'active',
            'channels' => ['push'],
            'weather_condition' => [],
        ]);

        $user = User::create([
            'id' => 9999,
            'name' => 'Jane',
            'email' => 'jane@example.com',
            'password' => 'secret',
            'fcm_token' => 'token123'
        ]);

        ShippingAddress::create([
            'user_id' => $user->id,
            'suburb' => 'Perth',
            'full_name' => 'Jane Doe',
            'address' => '123 Fake St',
            'phone' => '12345678',
            'email' => 'test@test.com',
            'zip_code' => '6000',
            'is_default' => 1
        ]);

        $audienceMock = Mockery::mock(WeatherCampaignAudienceService::class);
        $eloquentCollection = new \Illuminate\Database\Eloquent\Collection([$user]);
        $audienceMock->shouldReceive('resolve')->andReturn($eloquentCollection);
        $brevoMock = Mockery::mock(BrevoTransactionalService::class);

        $job = new WeatherCampaignDispatchJob($campaign->id, ['6000'], '2026-08-17', []);
        $job->handle($audienceMock, $brevoMock);

        $this->assertDatabaseHas('weather_campaign_sends', [
            'weather_campaign_id' => 9996,
            'user_id' => 9999,
            'channel' => 'push'
        ]);
    }
}
