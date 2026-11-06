<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Order;
use App\Models\Payment;
use App\Models\ShippingAddress;
use App\Models\DeliveryType;
use App\Services\OrderCompletionService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class OrderStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_cod_order_starts_as_pending()
    {
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
            'zip_code' => '12345'
        ]);

        $sessionId = 'test_session_' . uniqid();
        Cache::put($sessionId, [
            'user_id' => $user->id,
            'shipping_address_id' => $address->id,
            'coupon_id' => null,
            'subtotal' => 100.00,
            'delivery_fee' => 5.00,
            'discount' => 0,
            'tax' => 0,
            'total' => 105.00,
            'payment_method' => 'cash_on_delivery',
            'preferred_delivery_date' => null,
            'notes' => null,
            'loyalty_points' => 105,
            'delivery_type_id' => 1,
            'cart_items' => []
        ], now()->addHour());

        $service = new OrderCompletionService();
        $order = $service->completeOrder($sessionId, null, 'cod_123', 'PENDING', 105.00);

        $this->assertEquals('pending', $order->payment_status);
        $this->assertEquals('pending', $order->order_status);

        // Verify no points or entries
        $this->assertDatabaseMissing('point_histories', ['order_id' => $order->id]);
        $this->assertDatabaseMissing('entries', ['order_id' => $order->id]);
        $this->assertEquals(0, $user->fresh()->loyalty_points);
    }

    public function test_paid_square_order_starts_as_processing_and_has_rewards()
    {
        $user = User::factory()->create(['loyalty_points' => 0]);
        $address = ShippingAddress::create([
            'user_id' => $user->id,
            'full_name' => 'Test User',
            'phone' => '1234567890',
            'email' => 'test@example.com',
            'address' => '123 Street',
            'city' => 'City',
            'state' => 'State',
            'country' => 'Country',
            'zip_code' => '12345'
        ]);

        $sessionId = 'test_session_' . uniqid();
        Cache::put($sessionId, [
            'user_id' => $user->id,
            'shipping_address_id' => $address->id,
            'coupon_id' => null,
            'subtotal' => 200.00,
            'delivery_fee' => 5.00,
            'discount' => 0,
            'tax' => 0,
            'total' => 205.00,
            'payment_method' => 'square',
            'preferred_delivery_date' => null,
            'notes' => null,
            'loyalty_points' => 205,
            'delivery_type_id' => 1,
            'cart_items' => []
        ], now()->addHour());

        $service = new OrderCompletionService();
        $order = $service->completeOrder($sessionId, 'sq_order_123', 'pay_123', 'COMPLETED', 205.00);

        $this->assertEquals('paid', $order->payment_status);
        $this->assertEquals('processing', $order->order_status);

        // Verify points and entries
        $this->assertDatabaseHas('point_histories', ['order_id' => $order->id, 'type' => 'purchase', 'points' => 205]);
        $this->assertDatabaseHas('entries', ['order_id' => $order->id, 'purchase_type' => 'reward']); // total >= 200
        $this->assertEquals(205, $user->fresh()->loyalty_points);
    }

    public function test_webhook_updates_order_status_to_processing()
    {
        $user = User::factory()->create();
        $order = Order::create([
            'user_id' => $user->id,
            'shipping_address_id' => 1,
            'subtotal' => 100,
            'total' => 100,
            'payment_method' => 'square',
            'payment_status' => 'pending',
            'order_status' => 'pending',
            'order_number' => 'JJS99999'
        ]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'user_id' => $user->id,
            'payment_id' => 'pay_123',
            'amount' => 100,
            'currency' => 'GBP',
            'status' => 'pending'
        ]);

        $payload = [
            'type' => 'payment.updated',
            'data' => [
                'object' => [
                    'payment' => [
                        'id' => 'pay_123',
                        'status' => 'COMPLETED'
                    ]
                ]
            ]
        ];

        $response = $this->postJson('/api/square/webhook', $payload);
        $response->assertStatus(200);

        $order->refresh();
        $this->assertEquals('paid', $order->payment_status);
        $this->assertEquals('processing', $order->order_status);

        // Verify rewards added by webhook
        $this->assertDatabaseHas('point_histories', ['order_id' => $order->id]);
        $this->assertEquals(100, $user->fresh()->loyalty_points);
    }
    public function test_manual_admin_update_awards_rewards()
    {
        $admin = User::factory()->create(['is_admin' => 1]);
        $user = User::factory()->create(['loyalty_points' => 0]);
        
        $order = Order::create([
            'user_id' => $user->id,
            'shipping_address_id' => 1,
            'subtotal' => 200,
            'total' => 205,
            'payment_method' => 'cash_on_delivery',
            'payment_status' => 'pending',
            'order_status' => 'pending',
            'order_number' => 'JJS99998',
            'loyalty_points' => 205
        ]);

        // 1. Test updatePaymentStatus (Quick AJAX)
        $response = $this->actingAs($admin)
            ->postJson("/admin/orders/{$order->id}/payment-status", [
                'payment_status' => 'paid'
            ]);
        
        $response->assertStatus(200);
        $order->refresh();
        $this->assertEquals('paid', $order->payment_status);
        
        // Verify rewards
        $this->assertDatabaseHas('point_histories', ['order_id' => $order->id, 'type' => 'purchase']);
        $this->assertDatabaseHas('entries', ['order_id' => $order->id, 'purchase_type' => 'reward']);
        $this->assertEquals(205, $user->fresh()->loyalty_points);

        // Reset for next test
        DB::table('point_histories')->delete();
        DB::table('entries')->delete();
        $user->update(['loyalty_points' => 0]);
        $order->update(['payment_status' => 'pending']);

        // 2. Test full update (Edit Form)
        $response = $this->actingAs($admin)
            ->post("/admin/orders/{$order->id}/update", [
                'payment_status' => 'paid'
            ]);

        $order->refresh();
        $this->assertEquals('paid', $order->payment_status);
        
        // Verify rewards again
        $this->assertDatabaseHas('point_histories', ['order_id' => $order->id, 'type' => 'purchase']);
        $this->assertDatabaseHas('entries', ['order_id' => $order->id, 'purchase_type' => 'reward']);
        $this->assertEquals(205, $user->fresh()->loyalty_points);
    }
}
