<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Models\User;
use App\Models\Order;
use App\Models\ShippingAddress;
use App\Models\PointHistory;
use App\Models\Entry;
use App\Events\OrderCancelled;
use App\Services\OrderCompletionService;
use Illuminate\Support\Facades\Cache;

class OrderCancellationRewardTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancelling_order_retracts_points_and_entries()
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

        // Place a paid order that gives rewards
        $sessionId = 'test_session_' . uniqid();
        Cache::put($sessionId, [
            'user_id' => $user->id,
            'shipping_address_id' => $address->id,
            'coupon_id' => null,
            'subtotal' => 300.00,
            'delivery_fee' => 0,
            'discount' => 0,
            'tax' => 0,
            'total' => 300.00,
            'payment_method' => 'square',
            'preferred_delivery_date' => null,
            'notes' => null,
            'loyalty_points' => 300,
            'delivery_type_id' => 1,
            'cart_items' => []
        ], now()->addHour());

        $service = new OrderCompletionService();
        $order = $service->completeOrder($sessionId, 'sq_order_123', 'pay_123', 'COMPLETED', 300.00);

        // Verify rewards were given
        $this->assertEquals(300, $user->fresh()->loyalty_points);
        $this->assertDatabaseHas('entries', ['order_id' => $order->id, 'purchase_type' => 'reward']);

        // Now cancel the order - we'll simulate the event being handled
        $order->order_status = 'cancelled';
        $order->save();

        // Normally the event is dispatched from the controller. 
        // Let's call the listener directly or just trigger the event.
        event(new OrderCancelled($order));

        // Verify points were retracted
        $this->assertEquals(0, $user->fresh()->loyalty_points);
        $this->assertDatabaseHas('point_histories', [
            'order_id' => $order->id,
            'type' => 'cancellation',
            'points' => -300
        ]);

        // Verify entries were retracted
        $this->assertDatabaseMissing('entries', ['order_id' => $order->id]);
    }

    public function test_partial_retraction_when_user_spent_some_points()
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

        // Order gives 100 points
        $order = Order::create([
            'user_id' => $user->id,
            'shipping_address_id' => $address->id,
            'subtotal' => 100,
            'total' => 100,
            'payment_method' => 'square',
            'payment_status' => 'paid',
            'order_status' => 'processing',
            'loyalty_points' => 100,
            'order_number' => 'JJS111'
        ]);

        $service = new OrderCompletionService();
        $service->processLoyaltyPoints($order);

        $this->assertEquals(100, $user->fresh()->loyalty_points);

        // User spends 50 points (manually simulate)
        $user->decrement('loyalty_points', 50);

        // Cancel order - should only deduct 50 points to stay at 0 (as per current logic)
        event(new OrderCancelled($order));

        $this->assertEquals(0, $user->fresh()->loyalty_points);
        $this->assertDatabaseHas('point_histories', [
            'order_id' => $order->id,
            'type' => 'cancellation',
            'points' => -50
        ]);
    }
}
