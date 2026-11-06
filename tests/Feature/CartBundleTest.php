<?php

namespace Tests\Feature;

use App\Models\Bundle;
use App\Models\BundleExtra;
use App\Models\Cart;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartBundleTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(array $attrs = []): Product
    {
        return Product::create(array_merge([
            'category_id' => 1,
            'subcategory_id' => 1,
            'name' => 'Test Product',
            'price' => '20.00',
            'quantity' => 10,
            'status' => 1,
        ], $attrs));
    }

    public function test_adds_bundle_with_extras_to_cart_and_recomputes_price_server_side(): void
    {
        $user = User::factory()->create();
        $base = $this->makeProduct(['price' => '30.00']);
        $extra = $this->makeProduct(['name' => 'Kindling', 'price' => '5.00']);
        $bundle = Bundle::create(['name' => 'Starter', 'base_product_id' => $base->id, 'status' => 1]);
        BundleExtra::create(['bundle_id' => $bundle->id, 'product_id' => $extra->id, 'sort_order' => 0, 'status' => 1]);

        $response = $this->actingAs($user, 'api')->postJson('/api/cart/store', [
            'bundle_id' => $bundle->id,
            'quantity' => 1,
            'extras' => [
                ['product_id' => $extra->id, 'quantity' => 2],
            ],
            // Tamper attempt — server must ignore this and recompute.
            'price' => '0.01',
        ]);

        $response->assertStatus(200);

        $cart = Cart::where('user_id', $user->id)->where('bundle_id', $bundle->id)->first();
        $this->assertNotNull($cart);
        // 30.00 base + (5.00 * 2 extra) = 40.00
        $this->assertEquals(40.00, (float) $cart->price);
        $this->assertEquals(1, $cart->bundleExtras()->count());
    }

    public function test_rejects_out_of_stock_base_product(): void
    {
        $user = User::factory()->create();
        $base = $this->makeProduct(['quantity' => 0]);
        $bundle = Bundle::create(['name' => 'Out Of Stock Base', 'base_product_id' => $base->id, 'status' => 1]);

        $response = $this->actingAs($user, 'api')->postJson('/api/cart/store', [
            'bundle_id' => $bundle->id,
            'quantity' => 1,
        ]);

        // 500, not 422 — matches this method's pre-existing convention for business-rule
        // rejections thrown inside the transaction (e.g. the giveaway "not the winner" /
        // "already claimed" checks a few lines up also surface as 500 via the same outer
        // catch (\Throwable $e) { return $this->error(..., 500); } in store()).
        $response->assertStatus(500);
    }

    public function test_rejects_out_of_stock_extra(): void
    {
        $user = User::factory()->create();
        $base = $this->makeProduct();
        $extra = $this->makeProduct(['name' => 'Sold Out Extra', 'quantity' => 0]);
        $bundle = Bundle::create(['name' => 'Bundle With OOS Extra', 'base_product_id' => $base->id, 'status' => 1]);
        BundleExtra::create(['bundle_id' => $bundle->id, 'product_id' => $extra->id, 'sort_order' => 0, 'status' => 1]);

        $response = $this->actingAs($user, 'api')->postJson('/api/cart/store', [
            'bundle_id' => $bundle->id,
            'quantity' => 1,
            'extras' => [
                ['product_id' => $extra->id, 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(500);
    }

    public function test_rejects_extra_not_curated_for_bundle(): void
    {
        $user = User::factory()->create();
        $base = $this->makeProduct();
        $unrelatedProduct = $this->makeProduct(['name' => 'Not An Extra']);
        $bundle = Bundle::create(['name' => 'No Extras Bundle', 'base_product_id' => $base->id, 'status' => 1]);

        $response = $this->actingAs($user, 'api')->postJson('/api/cart/store', [
            'bundle_id' => $bundle->id,
            'quantity' => 1,
            'extras' => [
                ['product_id' => $unrelatedProduct->id, 'quantity' => 1],
            ],
        ]);

        $response->assertStatus(500);
    }

    public function test_required_extra_is_included_even_when_not_submitted_by_client(): void
    {
        $user = User::factory()->create();
        $base = $this->makeProduct(['price' => '30.00']);
        $required = $this->makeProduct(['name' => 'Fire Starter', 'price' => '4.00']);
        $bundle = Bundle::create(['name' => 'Starter', 'base_product_id' => $base->id, 'status' => 1]);
        BundleExtra::create(['bundle_id' => $bundle->id, 'product_id' => $required->id, 'is_required' => true, 'sort_order' => 0, 'status' => 1]);

        // Client submits no extras at all — the required one must still be charged and attached.
        $response = $this->actingAs($user, 'api')->postJson('/api/cart/store', [
            'bundle_id' => $bundle->id,
            'quantity' => 1,
        ]);

        $response->assertStatus(200);

        $cart = Cart::where('user_id', $user->id)->where('bundle_id', $bundle->id)->first();
        // 30.00 base + 4.00 required = 34.00
        $this->assertEquals(34.00, (float) $cart->price);
        $this->assertEquals(1, $cart->bundleExtras()->count());
        $this->assertEquals($required->id, $cart->bundleExtras()->first()->product_id);
    }

    public function test_required_extra_out_of_stock_blocks_add_to_cart(): void
    {
        $user = User::factory()->create();
        $base = $this->makeProduct();
        $required = $this->makeProduct(['name' => 'Sold Out Required', 'quantity' => 0]);
        $bundle = Bundle::create(['name' => 'Bundle With OOS Required', 'base_product_id' => $base->id, 'status' => 1]);
        BundleExtra::create(['bundle_id' => $bundle->id, 'product_id' => $required->id, 'is_required' => true, 'sort_order' => 0, 'status' => 1]);

        $response = $this->actingAs($user, 'api')->postJson('/api/cart/store', [
            'bundle_id' => $bundle->id,
            'quantity' => 1,
        ]);

        $response->assertStatus(500);
    }

    public function test_discount_percentage_is_applied_server_side_and_cannot_be_bypassed(): void
    {
        $user = User::factory()->create();
        $base = $this->makeProduct(['price' => '50.00']);
        $extra = $this->makeProduct(['name' => 'Kindling', 'price' => '10.00']);
        $bundle = Bundle::create([
            'name' => 'Discounted Bundle',
            'base_product_id' => $base->id,
            'discount_percentage' => 20,
            'status' => 1,
        ]);
        BundleExtra::create(['bundle_id' => $bundle->id, 'product_id' => $extra->id, 'sort_order' => 0, 'status' => 1]);

        $response = $this->actingAs($user, 'api')->postJson('/api/cart/store', [
            'bundle_id' => $bundle->id,
            'quantity' => 1,
            'extras' => [
                ['product_id' => $extra->id, 'quantity' => 1],
            ],
            // Tamper attempt on both price and the discount itself.
            'price' => '1.00',
            'discount_percentage' => 100,
        ]);

        $response->assertStatus(200);

        $cart = Cart::where('user_id', $user->id)->where('bundle_id', $bundle->id)->first();
        // (50.00 base + 10.00 extra) = 60.00, minus 20% = 48.00 — the submitted
        // 100% discount and $1.00 price are both ignored; only the admin-set
        // discount_percentage on the Bundle row itself is ever honored.
        $this->assertEquals(48.00, (float) $cart->price);
    }

    public function test_required_extra_submitted_as_optional_is_not_double_charged(): void
    {
        $user = User::factory()->create();
        $base = $this->makeProduct(['price' => '30.00']);
        $required = $this->makeProduct(['name' => 'Fire Starter', 'price' => '4.00']);
        $bundle = Bundle::create(['name' => 'Starter', 'base_product_id' => $base->id, 'status' => 1]);
        BundleExtra::create(['bundle_id' => $bundle->id, 'product_id' => $required->id, 'is_required' => true, 'sort_order' => 0, 'status' => 1]);

        // Client redundantly resubmits the required product id in `extras`.
        $response = $this->actingAs($user, 'api')->postJson('/api/cart/store', [
            'bundle_id' => $bundle->id,
            'quantity' => 1,
            'extras' => [
                ['product_id' => $required->id, 'quantity' => 3],
            ],
        ]);

        $response->assertStatus(200);

        $cart = Cart::where('user_id', $user->id)->where('bundle_id', $bundle->id)->first();
        // Still 34.00, not 30 + 4*3=42 — the required-extra loop already
        // included it at quantity 1; the client's resubmission is ignored.
        $this->assertEquals(34.00, (float) $cart->price);
        $this->assertEquals(1, $cart->bundleExtras()->count());
    }
}
