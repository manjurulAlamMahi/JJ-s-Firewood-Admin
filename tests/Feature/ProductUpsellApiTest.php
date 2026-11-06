<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductUpsell;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductUpsellApiTest extends TestCase
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

    public function test_returns_active_upsells_ordered_by_sort_order(): void
    {
        $anchor = $this->makeProduct(['name' => 'Firewood Bag']);
        $upsellA = $this->makeProduct(['name' => 'Kindling']);
        $upsellB = $this->makeProduct(['name' => 'Fire Starter']);

        ProductUpsell::create(['product_id' => $anchor->id, 'upsell_product_id' => $upsellB->id, 'type' => 'extra', 'sort_order' => 1, 'status' => 1]);
        ProductUpsell::create(['product_id' => $anchor->id, 'upsell_product_id' => $upsellA->id, 'type' => 'extra', 'sort_order' => 0, 'status' => 1]);

        $response = $this->getJson("/api/v2/products/{$anchor->id}/upsells");

        $response->assertStatus(200);
        $names = collect($response->json('data'))->pluck('name');
        $this->assertSame(['Kindling', 'Fire Starter'], $names->toArray());
    }

    public function test_excludes_inactive_upsells(): void
    {
        $anchor = $this->makeProduct();
        $inactive = $this->makeProduct(['name' => 'Old Upsell']);
        ProductUpsell::create(['product_id' => $anchor->id, 'upsell_product_id' => $inactive->id, 'type' => 'extra', 'sort_order' => 0, 'status' => 0]);

        $response = $this->getJson("/api/v2/products/{$anchor->id}/upsells");

        $response->assertStatus(200);
        $this->assertSame([], $response->json('data'));
    }

    public function test_404s_for_missing_anchor_product(): void
    {
        $response = $this->getJson('/api/v2/products/999999/upsells');

        $response->assertStatus(404);
    }
}
