<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingAddress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderShowApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_returns_the_same_shape_as_the_orders_list(): void
    {
        $user = User::factory()->create();
        $address = ShippingAddress::create([
            'user_id' => $user->id, 'full_name' => 'T', 'phone' => '1',
            'email' => 't@t.com', 'address' => 'a', 'city' => 'c',
            'state' => 's', 'country' => 'c', 'zip_code' => '6000',
        ]);
        $product = Product::create([
            'category_id' => 1, 'subcategory_id' => 1, 'name' => 'Test Product',
            'price' => '20.00', 'quantity' => 10, 'status' => 1,
        ]);
        $order = Order::create([
            'user_id' => $user->id,
            'shipping_address_id' => $address->id,
            'subtotal' => 20, 'total' => 20,
            'payment_method' => 'square', 'payment_status' => 'paid',
            'order_status' => 'delivered',
            'order_number' => 'JJS-TEST-1',
        ]);
        $order->orderItems()->create([
            'product_id' => $product->id, 'product_name' => 'Test Product',
            'quantity' => 1, 'price' => 20, 'subtotal' => 20,
        ]);

        $response = $this->actingAs($user, 'api')->getJson("/api/orders/{$order->id}");

        $response->assertStatus(200);
        $this->assertSame($order->id, $response->json('data.order_id'));
        $this->assertSame($order->fresh()->order_number, $response->json('data.order_number'));
        $this->assertSame('delivered', $response->json('data.order_status'));
        $this->assertEquals(20, $response->json('data.total_amount'));
        $this->assertIsArray($response->json('data.products'));
        $this->assertSame('Test Product', $response->json('data.products.0.name'));
    }

    public function test_show_404s_for_another_users_order(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $address = ShippingAddress::create([
            'user_id' => $owner->id, 'full_name' => 'T', 'phone' => '1',
            'email' => 't@t.com', 'address' => 'a', 'city' => 'c',
            'state' => 's', 'country' => 'c', 'zip_code' => '6000',
        ]);
        $order = Order::create([
            'user_id' => $owner->id,
            'shipping_address_id' => $address->id,
            'subtotal' => 20, 'total' => 20,
            'payment_method' => 'square', 'payment_status' => 'paid',
            'order_status' => 'delivered',
            'order_number' => 'JJS-TEST-2',
        ]);

        $response = $this->actingAs($intruder, 'api')->getJson("/api/orders/{$order->id}");

        $response->assertStatus(500);
    }
}
