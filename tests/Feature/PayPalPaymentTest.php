<?php
namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use App\Models\PaymentSetting;
use App\Models\User;
use App\Models\ShippingAddress;
use App\Models\ZipCode;
use App\Models\Cart;
use App\Services\PayPalService;

class PayPalPaymentTest extends TestCase
{
    use RefreshDatabase;

    private function seedKeys(): void
    {
        PaymentSetting::create([
            'paypal_client_id' => 'id', 'paypal_secret' => 'sec', 'paypal_environment' => 'sandbox',
        ]);
    }

    public function test_square_service_prefers_payment_setting_over_env(): void
    {
        \App\Models\PaymentSetting::create([
            'square_application_id' => 'app_db', 'square_location_id' => 'loc_db',
            'square_access_token' => 'tok_db', 'square_environment' => 'sandbox',
        ]);
        config(['square.access_token' => 'tok_env', 'square.location_id' => 'loc_env']);

        $svc = new \App\Services\SquarePaymentService();
        $this->assertSame('loc_db', $svc->getLocationId());
    }

    public function test_create_order_returns_approval_url(): void
    {
        $this->seedKeys();
        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'tok', 'expires_in' => 3200], 200),
            '*/v2/checkout/orders' => Http::response([
                'id' => 'PP-ORDER-1', 'status' => 'CREATED',
                'links' => [['rel' => 'approve', 'href' => 'https://paypal.test/approve?token=PP-ORDER-1']],
            ], 201),
        ]);

        $svc = new PayPalService();
        $res = $svc->createOrder(135.00, 'sess_1', 'https://api.test/return', 'https://api.test/cancel');

        $this->assertTrue($res['success']);
        $this->assertSame('PP-ORDER-1', $res['paypal_order_id']);
        $this->assertSame('https://paypal.test/approve?token=PP-ORDER-1', $res['approval_url']);
    }

    public function test_capture_order_reports_completed(): void
    {
        $this->seedKeys();
        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'tok', 'expires_in' => 3200], 200),
            '*/v2/checkout/orders/PP-ORDER-1/capture' => Http::response([
                'status' => 'COMPLETED',
                'purchase_units' => [['payments' => ['captures' => [['id' => 'CAP-1', 'status' => 'COMPLETED']]]]],
            ], 201),
        ]);

        $svc = new PayPalService();
        $res = $svc->captureOrder('PP-ORDER-1');

        $this->assertTrue($res['success']);
        $this->assertSame('COMPLETED', $res['status']);
        $this->assertSame('CAP-1', $res['capture_id']);
    }

    public function test_newstore_paypal_returns_approval_url(): void
    {
        $this->seedKeys();
        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'tok', 'expires_in' => 3200], 200),
            '*/v2/checkout/orders' => Http::response([
                'id' => 'PP-1', 'links' => [['rel' => 'approve', 'href' => 'https://paypal.test/approve']],
            ], 201),
        ]);

        // Arrange: user + deliverable shipping address + a cart item (mirrors
        // the user/address setup pattern in OrderStatusTest).
        $user = User::factory()->create();
        $address = ShippingAddress::create([
            'user_id' => $user->id,
            'full_name' => 'Test User',
            'phone' => '1234567890',
            'email' => 'test@example.com',
            'address' => '123 Street',
            'city' => 'City',
            'state' => 'State',
            'country' => 'Country',
            'zip_code' => '12345',
        ]);
        ZipCode::create(['zip_code' => '12345', 'status' => 1]);
        Cart::create([
            'user_id' => $user->id,
            'product_id' => null,
            'giveaway_id' => null,
            'quantity' => 1,
            'price' => 105.00,
            'subtotal' => 105.00,
            'delivery_type_id' => 1,
        ]);

        $res = $this->actingAs($user)->postJson('/api/orders/store', [
            'shipping_address_id' => $address->id,
            'payment_method' => 'paypal',
        ]);
        $res->assertStatus(200)
            ->assertJsonPath('payment_method', 'paypal')
            ->assertJsonPath('approval_url', 'https://paypal.test/approve');
    }

    public function test_capture_completes_order(): void
    {
        $this->seedKeys();

        $user = User::factory()->create();
        $address = ShippingAddress::create([
            'user_id' => $user->id,
            'full_name' => 'Test User',
            'phone' => '1234567890',
            'email' => 'test@example.com',
            'address' => '123 Street',
            'city' => 'City',
            'state' => 'State',
            'country' => 'Country',
            'zip_code' => '12345',
        ]);

        // Arrange a cached pending session exactly as newstore leaves it
        // (keys read by OrderCompletionService::completeOrder).
        $sessionId = 'pending_order_test_1';
        Cache::put($sessionId, [
            'user_id' => $user->id,
            'user_email' => $user->email,
            'shipping_address_id' => $address->id,
            'coupon_id' => null,
            'coupon_info' => null,
            'referral_info' => null,
            'subtotal' => 105.00,
            'delivery_fee' => 0,
            'discount' => 0,
            'tax' => 0,
            'total' => 105.00,
            'payment_method' => 'paypal',
            'preferred_delivery_date' => null,
            'notes' => null,
            'loyalty_points' => 105,
            'delivery_type_id' => 1,
            'delivery_info' => null,
            'cart_items' => [],
        ], now()->addHours(3));
        Cache::put($sessionId . '_paypal_order_id', 'PP-1', now()->addHours(3));

        Http::fake([
            '*/v1/oauth2/token' => Http::response(['access_token' => 'tok', 'expires_in' => 3200], 200),
            '*/v2/checkout/orders/PP-1/capture' => Http::response([
                'status' => 'COMPLETED',
                'purchase_units' => [['payments' => ['captures' => [['id' => 'CAP-1']]]]],
            ], 201),
        ]);

        $res = $this->actingAs($user)->postJson('/api/paypal/capture', ['session_id' => $sessionId]);
        $res->assertStatus(200)->assertJsonPath('success', true);
    }
}
