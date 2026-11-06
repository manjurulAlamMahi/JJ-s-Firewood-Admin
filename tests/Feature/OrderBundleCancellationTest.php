<?php

namespace Tests\Feature;

use App\Events\OrderCancelled;
use App\Events\OrderPlaced;
use App\Models\Bundle;
use App\Models\DeliveryType;
use App\Models\Product;
use App\Models\ShippingAddress;
use App\Models\User;
use App\Services\OrderCompletionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class OrderBundleCancellationTest extends TestCase
{
    use RefreshDatabase;

    public function test_cancelling_a_bundle_order_restocks_base_product_and_extras(): void
    {
        // Both OrderPlaced and OrderCancelled broadcast over a real Reverb/Pusher
        // connection with no network access in this test environment — the same
        // pre-existing issue already covered by OrderBundleCompletionTest.
        Event::fake([OrderPlaced::class, OrderCancelled::class]);

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

        $base = Product::create([
            'category_id' => 1, 'subcategory_id' => 1, 'name' => 'Base Product',
            'price' => '30.00', 'quantity' => 10, 'status' => 1,
        ]);
        $extra = Product::create([
            'category_id' => 1, 'subcategory_id' => 1, 'name' => 'Kindling',
            'price' => '5.00', 'quantity' => 10, 'status' => 1,
        ]);
        $bundle = Bundle::create(['name' => 'Starter', 'base_product_id' => $base->id, 'status' => 1]);
        $deliveryType = DeliveryType::create(['name' => 'Standard', 'fee' => 0, 'days' => '1-2', 'status' => 1]);

        $sessionId = 'test_session_' . uniqid();
        Cache::put($sessionId, [
            'user_id' => $user->id,
            'shipping_address_id' => $address->id,
            'coupon_id' => null,
            'subtotal' => 40.00,
            'delivery_fee' => 0,
            'discount' => 0,
            'tax' => 0,
            'total' => 40.00,
            'payment_method' => 'square',
            'preferred_delivery_date' => null,
            'notes' => null,
            'loyalty_points' => 40,
            'delivery_type_id' => $deliveryType->id,
            'cart_items' => [
                [
                    'product_id' => null,
                    'giveaway_id' => null,
                    'bundle_id' => $bundle->id,
                    'product_name' => 'Starter',
                    'quantity' => 1,
                    'price' => 40.00,
                    'subtotal' => 40.00,
                    'bundle_extras' => [
                        [
                            'product_id' => $extra->id,
                            'product_name' => 'Kindling',
                            'quantity' => 2,
                            'price' => 5.00,
                        ],
                    ],
                ],
            ],
        ], now()->addHours(1));

        $order = (new OrderCompletionService())->completeOrder(
            $sessionId, null, 'test_payment_' . uniqid(), 'PAID', 40.00, 'AUD'
        );

        // Completion already decremented stock — confirm the starting point before cancelling.
        $this->assertSame(9, $base->fresh()->quantity);
        $this->assertSame(8, $extra->fresh()->quantity);

        $response = $this->actingAs($user, 'api')->postJson("/api/orders/cancel/{$order->id}");

        $response->assertStatus(200);
        $this->assertSame('cancelled', $order->fresh()->order_status);

        // Cancellation must restore what completion consumed.
        $this->assertSame(10, $base->fresh()->quantity);
        $this->assertSame(10, $extra->fresh()->quantity);
    }
}
