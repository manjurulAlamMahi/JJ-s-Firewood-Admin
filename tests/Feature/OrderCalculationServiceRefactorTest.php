<?php

namespace Tests\Feature;

use App\Models\Cart;
use App\Models\DeliveryType;
use App\Models\Product;
use App\Models\ShippingAddress;
use App\Models\User;
use App\Models\ZipCode;
use App\Models\ZipCodeGroup;
use App\Models\ZoneWiseDeliveryType;
use App\Services\OrderCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrderCalculationServiceRefactorTest extends TestCase
{
    use RefreshDatabase;

    public function test_uses_zone_fee_when_address_zip_matches_a_zone_override(): void
    {
        $user = User::factory()->create();
        $product = Product::create([
            'category_id' => 1, 'subcategory_id' => 1, 'name' => 'P',
            'price' => '10.00', 'quantity' => 10, 'status' => 1,
        ]);
        Cart::create(['user_id' => $user->id, 'product_id' => $product->id, 'quantity' => 1, 'price' => 10, 'subtotal' => 10]);

        $deliveryType = DeliveryType::create(['name' => 'Standard', 'fee' => 15, 'days' => '1-2']);
        $group = ZipCodeGroup::create(['name' => 'Zone A', 'status' => 1]);
        ZipCode::create(['zip_code' => '6000', 'status' => 1, 'group_id' => $group->id]);
        ZoneWiseDeliveryType::create([
            'zip_code_group_id' => $group->id, 'delivery_type_id' => $deliveryType->id, 'fee' => 5,
        ]);
        $address = ShippingAddress::create([
            'user_id' => $user->id, 'full_name' => 'T', 'phone' => '1',
            'email' => 't@t.com', 'address' => 'a', 'city' => 'c',
            'state' => 's', 'country' => 'c', 'zip_code' => '6000',
        ]);

        $result = OrderCalculationService::calculate($user, null, $deliveryType->id, $address->id);

        $this->assertEquals(5, $result['delivery_fee']);
    }

    public function test_uses_base_fee_when_no_zone_override_exists(): void
    {
        $user = User::factory()->create();
        $product = Product::create([
            'category_id' => 1, 'subcategory_id' => 1, 'name' => 'P',
            'price' => '10.00', 'quantity' => 10, 'status' => 1,
        ]);
        Cart::create(['user_id' => $user->id, 'product_id' => $product->id, 'quantity' => 1, 'price' => 10, 'subtotal' => 10]);

        $deliveryType = DeliveryType::create(['name' => 'Standard', 'fee' => 15, 'days' => '1-2']);
        $address = ShippingAddress::create([
            'user_id' => $user->id, 'full_name' => 'T', 'phone' => '1',
            'email' => 't@t.com', 'address' => 'a', 'city' => 'c',
            'state' => 's', 'country' => 'c', 'zip_code' => '9999',
        ]);

        $result = OrderCalculationService::calculate($user, null, $deliveryType->id, $address->id);

        $this->assertEquals(15, $result['delivery_fee']);
    }

    public function test_uses_base_fee_when_no_shipping_address_given(): void
    {
        $user = User::factory()->create();
        $product = Product::create([
            'category_id' => 1, 'subcategory_id' => 1, 'name' => 'P',
            'price' => '10.00', 'quantity' => 10, 'status' => 1,
        ]);
        Cart::create(['user_id' => $user->id, 'product_id' => $product->id, 'quantity' => 1, 'price' => 10, 'subtotal' => 10]);

        $deliveryType = DeliveryType::create(['name' => 'Standard', 'fee' => 15, 'days' => '1-2']);

        $result = OrderCalculationService::calculate($user, null, $deliveryType->id, null);

        $this->assertEquals(15, $result['delivery_fee']);
    }
}
