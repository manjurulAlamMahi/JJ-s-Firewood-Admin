<?php

namespace Tests\Feature;

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

class OrderBundleCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_completing_an_order_persists_bundle_id_and_snapshotted_extras(): void
    {
        // OrderPlaced broadcasts over a real Reverb/Pusher connection with no
        // network access in this test environment (the same pre-existing issue
        // already fails OrderCancellationRewardTest, unrelated to this feature).
        // Faking it lets the assertions below actually run against data that's
        // already committed to the DB before the event fires.
        Event::fake([OrderPlaced::class]);

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

        $orderItem = $order->orderItems()->first();
        $this->assertSame($bundle->id, $orderItem->bundle_id);
        $this->assertSame(1, $orderItem->bundleExtras()->count());
        $bundleExtra = $orderItem->bundleExtras()->first();
        $this->assertSame($extra->id, $bundleExtra->product_id);
        $this->assertSame('Kindling', $bundleExtra->product_name);
        $this->assertEquals(2, (float) $bundleExtra->quantity);
        $this->assertEquals(5.00, (float) $bundleExtra->price);

        // Stock must actually be consumed — the base product by the order item's
        // own quantity, and each extra by its own snapshotted quantity.
        $this->assertSame(9, $base->fresh()->quantity);
        $this->assertSame(8, $extra->fresh()->quantity);
    }
}
