<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\EntryPackage;
use App\Models\Payment;
use App\Services\SquarePaymentService;
use Illuminate\Support\Facades\Cache;

class EntryPackagePaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_buy_with_money_creates_payment_link()
    {
        $user = User::factory()->create();
        $package = EntryPackage::factory()->create([
            'status' => 1,
            'price' => 10.00,
            'entry_quantity' => 5
        ]);

        // Create a fake service
        $fakeSquare = new class extends SquarePaymentService {
            public function __construct() {} // Skip constructor logic
            public function createPaymentLink($order, $options = []) {
                return [
                    'success' => true,
                    'payment_link' => 'https://sandbox.square.com/pay/test',
                    'provider_order_id' => 'order_123',
                ];
            }
        };

        $this->app->instance(SquarePaymentService::class, $fakeSquare);

        $response = $this->actingAs($user)
            ->postJson("/api/entry-packages/{$package->id}/buy-money");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'payment_link' => 'https://sandbox.square.com/pay/test',
            ]);

        // Verify Cache
        $sessionId = $response->json('session_id');
        $this->assertTrue(Cache::has($sessionId));
        $this->assertEquals('entry_package', Cache::get($sessionId . '_type'));
    }

    public function test_callback_completes_purchase()
    {
        $user = User::factory()->create();
        $sessionId = 'test_session_' . $user->id;
        
        Cache::put($sessionId, [
            'type' => 'entry_package',
            'user_id' => $user->id,
            'package_id' => 1,
            'entry_quantity' => 10,
            'price' => 20.00,
        ], now()->addHour());
        
        Cache::put($sessionId . '_type', 'entry_package', now()->addHour());
        Cache::put($sessionId . '_square_order_id', 'order_123', now()->addHour());

        $fakeSquare = new class extends SquarePaymentService {
            public function __construct() {}
            public function getPayment($paymentId) {
                return [
                    'success' => true,
                    'payment' => [
                        'status' => 'COMPLETED',
                        'amount' => 20.00,
                        'currency' => 'GBP',
                        'receipt_url' => 'http://receipt',
                        'source_type' => 'CARD',
                    ]
                ];
            }
        };

        $this->app->instance(SquarePaymentService::class, $fakeSquare);

        $response = $this->get("/api/payment/callback?session_id={$sessionId}&transactionId=pay_123&redirect_url=http://frontend/callback");

        $response->assertRedirect();
        
        $this->assertDatabaseHas('entries', [
            'user_id' => $user->id,
            'entry_count' => 10,
            'purchase_type' => 'paid',
        ]);
        
        $this->assertDatabaseHas('payments', [
            'user_id' => $user->id,
            'amount' => 20.00,
            'payment_id' => 'pay_123'
        ]);
    }
}
