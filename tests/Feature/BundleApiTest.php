<?php

namespace Tests\Feature;

use App\Models\Bundle;
use App\Models\BundleExtra;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BundleApiTest extends TestCase
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

    public function test_lists_only_active_bundles(): void
    {
        $base = $this->makeProduct();
        Bundle::create(['name' => 'Active Bundle', 'base_product_id' => $base->id, 'status' => 1]);
        Bundle::create(['name' => 'Inactive Bundle', 'base_product_id' => $base->id, 'status' => 0]);

        $response = $this->getJson('/api/v2/bundles');

        $response->assertStatus(200);
        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Active Bundle'));
        $this->assertFalse($names->contains('Inactive Bundle'));
    }

    public function test_detail_returns_base_product_and_active_extras_only(): void
    {
        $base = $this->makeProduct(['name' => 'Firewood Bag']);
        $extraActive = $this->makeProduct(['name' => 'Kindling']);
        $extraInactive = $this->makeProduct(['name' => 'Old Extra']);

        $bundle = Bundle::create(['name' => 'Starter Bundle', 'base_product_id' => $base->id, 'status' => 1]);
        BundleExtra::create(['bundle_id' => $bundle->id, 'product_id' => $extraActive->id, 'sort_order' => 0, 'status' => 1]);
        BundleExtra::create(['bundle_id' => $bundle->id, 'product_id' => $extraInactive->id, 'sort_order' => 1, 'status' => 0]);

        $response = $this->getJson("/api/v2/bundles/{$bundle->id}");

        $response->assertStatus(200);
        $this->assertSame('Firewood Bag', $response->json('data.base_product.name'));
        $extraNames = collect($response->json('data.extras'))->pluck('name');
        $this->assertTrue($extraNames->contains('Kindling'));
        $this->assertFalse($extraNames->contains('Old Extra'));
    }

    public function test_detail_404s_for_inactive_bundle(): void
    {
        $base = $this->makeProduct();
        $bundle = Bundle::create(['name' => 'Hidden', 'base_product_id' => $base->id, 'status' => 0]);

        $response = $this->getJson("/api/v2/bundles/{$bundle->id}");

        $response->assertStatus(404);
    }

    public function test_detail_404s_for_missing_bundle(): void
    {
        $response = $this->getJson('/api/v2/bundles/999999');

        $response->assertStatus(404);
    }

    public function test_list_shows_discounted_teaser_price(): void
    {
        $base = $this->makeProduct(['price' => '50.00']);
        Bundle::create([
            'name' => 'Deal Bundle',
            'base_product_id' => $base->id,
            'discount_percentage' => 10,
            'status' => 1,
        ]);

        $response = $this->getJson('/api/v2/bundles');

        $response->assertStatus(200);
        $bundle = collect($response->json('data'))->firstWhere('name', 'Deal Bundle');
        $this->assertEquals(50.00, $bundle['original_price']);
        $this->assertEquals(45.00, $bundle['effective_price']);
        $this->assertEquals(10, $bundle['discount_percentage']);
    }

    public function test_detail_flags_required_extras(): void
    {
        $base = $this->makeProduct();
        $required = $this->makeProduct(['name' => 'Fire Starter']);
        $optional = $this->makeProduct(['name' => 'Kindling']);

        $bundle = Bundle::create(['name' => 'Starter Bundle', 'base_product_id' => $base->id, 'status' => 1]);
        BundleExtra::create(['bundle_id' => $bundle->id, 'product_id' => $required->id, 'is_required' => true, 'sort_order' => 0, 'status' => 1]);
        BundleExtra::create(['bundle_id' => $bundle->id, 'product_id' => $optional->id, 'is_required' => false, 'sort_order' => 1, 'status' => 1]);

        $response = $this->getJson("/api/v2/bundles/{$bundle->id}");

        $response->assertStatus(200);
        $extras = collect($response->json('data.extras'))->keyBy('name');
        $this->assertTrue($extras['Fire Starter']['is_required']);
        $this->assertFalse($extras['Kindling']['is_required']);
    }
}
