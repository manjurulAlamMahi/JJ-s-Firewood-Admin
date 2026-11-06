<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\ShippingAddress;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReorderSuggestionApiTest extends TestCase
{
    use RefreshDatabase;

    private function makeOrder(User $user, ShippingAddress $address, string $orderNumber): Order
    {
        return Order::create([
            'user_id' => $user->id,
            'shipping_address_id' => $address->id,
            'subtotal' => 10, 'total' => 10,
            'payment_method' => 'square', 'payment_status' => 'paid',
            'order_status' => 'delivered',
            'order_number' => $orderNumber,
        ]);
    }

    public function test_returns_most_ordered_products_when_user_has_2_or_more_orders(): void
    {
        $user = User::factory()->create();
        $address = ShippingAddress::create([
            'user_id' => $user->id, 'full_name' => 'T', 'phone' => '1',
            'email' => 't@t.com', 'address' => 'a', 'city' => 'c',
            'state' => 's', 'country' => 'c', 'zip_code' => '6000',
        ]);
        $productA = Product::create([
            'category_id' => 1, 'subcategory_id' => 1, 'name' => 'Product A',
            'price' => '10.00', 'quantity' => 10, 'status' => 1,
        ]);
        $productB = Product::create([
            'category_id' => 1, 'subcategory_id' => 1, 'name' => 'Product B',
            'price' => '5.00', 'quantity' => 10, 'status' => 1,
        ]);

        $order1 = $this->makeOrder($user, $address, 'ORD-1');
        $order1->orderItems()->create([
            'product_id' => $productA->id, 'product_name' => 'Product A',
            'quantity' => 3, 'price' => 10, 'subtotal' => 30,
        ]);
        $order1->orderItems()->create([
            'product_id' => $productB->id, 'product_name' => 'Product B',
            'quantity' => 1, 'price' => 5, 'subtotal' => 5,
        ]);

        $order2 = $this->makeOrder($user, $address, 'ORD-2');
        $order2->orderItems()->create([
            'product_id' => $productA->id, 'product_name' => 'Product A',
            'quantity' => 2, 'price' => 10, 'subtotal' => 20,
        ]);

        $response = $this->actingAs($user, 'api')->getJson('/api/v2/reorder-suggestions');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(2, $data);
        $this->assertSame($productA->id, $data[0]['product_id']);
        $this->assertEquals(5, $data[0]['total_quantity_ordered']);
        $this->assertSame($productB->id, $data[1]['product_id']);
        $this->assertEquals(1, $data[1]['total_quantity_ordered']);
    }

    public function test_falls_back_to_last_order_items_when_user_has_exactly_1_order(): void
    {
        $user = User::factory()->create();
        $address = ShippingAddress::create([
            'user_id' => $user->id, 'full_name' => 'T', 'phone' => '1',
            'email' => 't@t.com', 'address' => 'a', 'city' => 'c',
            'state' => 's', 'country' => 'c', 'zip_code' => '6000',
        ]);
        $product = Product::create([
            'category_id' => 1, 'subcategory_id' => 1, 'name' => 'Only Product',
            'price' => '10.00', 'quantity' => 10, 'status' => 1,
        ]);

        $order = $this->makeOrder($user, $address, 'ORD-1');
        $order->orderItems()->create([
            'product_id' => $product->id, 'product_name' => 'Only Product',
            'quantity' => 1, 'price' => 10, 'subtotal' => 10,
        ]);

        $response = $this->actingAs($user, 'api')->getJson('/api/v2/reorder-suggestions');

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertSame($product->id, $data[0]['product_id']);
    }

    public function test_returns_empty_list_when_user_has_no_orders(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'api')->getJson('/api/v2/reorder-suggestions');

        $response->assertStatus(200);
        $this->assertSame([], $response->json('data'));
    }

    public function test_excludes_out_of_stock_and_inactive_products(): void
    {
        $user = User::factory()->create();
        $address = ShippingAddress::create([
            'user_id' => $user->id, 'full_name' => 'T', 'phone' => '1',
            'email' => 't@t.com', 'address' => 'a', 'city' => 'c',
            'state' => 's', 'country' => 'c', 'zip_code' => '6000',
        ]);
        $activeProduct = Product::create([
            'category_id' => 1, 'subcategory_id' => 1, 'name' => 'Active Product',
            'price' => '10.00', 'quantity' => 10, 'status' => 1,
        ]);
        $outOfStockProduct = Product::create([
            'category_id' => 1, 'subcategory_id' => 1, 'name' => 'Out of Stock',
            'price' => '10.00', 'quantity' => 0, 'status' => 1,
        ]);

        $order1 = $this->makeOrder($user, $address, 'ORD-1');
        $order1->orderItems()->create([
            'product_id' => $activeProduct->id, 'product_name' => 'Active Product',
            'quantity' => 1, 'price' => 10, 'subtotal' => 10,
        ]);
        $order1->orderItems()->create([
            'product_id' => $outOfStockProduct->id, 'product_name' => 'Out of Stock',
            'quantity' => 1, 'price' => 10, 'subtotal' => 10,
        ]);

        $order2 = $this->makeOrder($user, $address, 'ORD-2');
        $order2->orderItems()->create([
            'product_id' => $activeProduct->id, 'product_name' => 'Active Product',
            'quantity' => 1, 'price' => 10, 'subtotal' => 10,
        ]);

        $response = $this->actingAs($user, 'api')->getJson('/api/v2/reorder-suggestions');

        $response->assertStatus(200);
        $data = $response->json('data');
        $productIds = array_column($data, 'product_id');
        $this->assertContains($activeProduct->id, $productIds);
        $this->assertNotContains($outOfStockProduct->id, $productIds);
    }
}
