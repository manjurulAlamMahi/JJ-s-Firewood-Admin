<?php
namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\PaymentSetting;
use App\Models\PaymentMethod;

class PaymentConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_publishable_config_without_secrets(): void
    {
        PaymentSetting::create([
            'square_application_id' => 'app_1', 'square_location_id' => 'loc_1',
            'square_access_token' => 'SECRET_TOKEN', 'square_environment' => 'production',
            'paypal_client_id' => 'ppid', 'paypal_secret' => 'PP_SECRET', 'paypal_environment' => 'production',
        ]);
        foreach (['square','paypal','bank_transfer','cash_on_delivery'] as $slug) {
            PaymentMethod::create(['name' => $slug, 'slug' => $slug, 'is_active' => true]);
        }

        $res = $this->getJson('/api/payment-config');
        $res->assertStatus(200)
            ->assertJsonPath('data.square.application_id', 'app_1')
            ->assertJsonPath('data.square.environment', 'production')
            ->assertJsonPath('data.paypal.enabled', true)
            ->assertJsonPath('data.methods.card', true)
            ->assertJsonPath('data.methods.paypal', true);

        $body = $res->getContent();
        $this->assertStringNotContainsString('SECRET_TOKEN', $body);
        $this->assertStringNotContainsString('PP_SECRET', $body);
    }
}
