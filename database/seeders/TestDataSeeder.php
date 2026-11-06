<?php

namespace Database\Seeders;

use App\Models\Bundle;
use App\Models\BundleExtra;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductUpsell;
use App\Models\ShippingAddress;
use App\Models\SubCategory;
use App\Models\User;
use App\Models\ZipCode;
use Illuminate\Database\Seeder;

/**
 * One-command test data for manual QA — run after the base seeders
 * (categories/subcategories/products/delivery types) via `php artisan db:seed`.
 * Safe to re-run: everything is keyed by a distinctive name/email so repeat
 * runs update in place rather than duplicating rows.
 *
 * Gives you, without touching the admin panel or tinker by hand:
 * - A customer login ready to place a real order (test@jjsfirewood.test / password123)
 * - A deliverable postcode (6000) so checkout doesn't reject the address
 * - A spread of real products across categories, priced tax-inclusive
 *   (see the class docblock note below on the 10% GST convention)
 * - Two bundles: one with a discount + a required extra + optional extras,
 *   one plain (no discount) for contrast
 * - Product upsells wired to the checkout screen's "You might also want" row
 */
class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedDeliverableZipCode();
        $testUser = $this->seedCustomer();
        $products = $this->seedProducts();
        $this->seedBundles($products);
        $this->seedUpsells($products);

        $this->command->info('Test data ready. Login: test@jjsfirewood.test / password123 (postcode 6000 is deliverable).');
    }

    private function seedDeliverableZipCode(): void
    {
        ZipCode::firstOrCreate(
            ['zip_code' => '6000'],
            ['status' => 1, 'group_id' => null]
        );
    }

    private function seedCustomer(): User
    {
        $user = User::firstOrCreate(
            ['email' => 'test@jjsfirewood.test'],
            [
                'name' => 'Test Customer',
                'phone' => '0400000000',
                'password' => 'password123',
            ]
        );

        ShippingAddress::firstOrCreate(
            ['user_id' => $user->id, 'label' => 'Home'],
            [
                'full_name' => 'Test Customer',
                'phone' => '0400000000',
                'email' => 'test@jjsfirewood.test',
                'address' => '123 Test Street',
                'suburb' => 'Perth',
                'city' => 'Perth',
                'state' => 'WA',
                'country' => 'Australia',
                'zip_code' => '6000',
                'is_default' => 1,
            ]
        );

        return $user;
    }

    /**
     * @return array<string, Product> keyed by a short handle used by
     *         seedBundles()/seedUpsells() below — not persisted, just local
     *         bookkeeping so those methods can refer to "the kindling one"
     *         without re-querying by name.
     */
    private function seedProducts(): array
    {
        $firewoodCategory = Category::where('name', 'Firewoods')->first();
        $accessoriesCategory = Category::where('name', 'Accessories')->first();
        $kindlingSub = SubCategory::where('name', 'Kindling')->first();
        $fireStartersSub = SubCategory::where('name', 'Fire Starters')->first();

        // Prices below are tax-inclusive, matching how admin is expected to
        // enter prices app-wide — the app derives the displayed 10% GST line
        // by subtracting it back out of this price, it doesn't add tax on top.
        $specs = [
            'jarrah_bag' => [
                'category_id' => $firewoodCategory?->id ?? 1,
                'subcategory_id' => $kindlingSub?->id ?? 1,
                'name' => 'Jarrah Firewood Bag (Test)',
                'short_title' => 'Dense, long-burning hardwood',
                'price' => '45.00',
                'quantity' => 50,
                'description' => 'A full bag of seasoned Jarrah firewood.',
            ],
            'kindling_bundle' => [
                'category_id' => $firewoodCategory?->id ?? 1,
                'subcategory_id' => $kindlingSub?->id ?? 1,
                'name' => 'Kindling Pack (Test)',
                'short_title' => 'Quick-lighting kindling',
                'price' => '12.00',
                'quantity' => 100,
                'description' => 'Small dry offcuts for getting a fire started.',
            ],
            'fire_starters' => [
                'category_id' => $firewoodCategory?->id ?? 1,
                'subcategory_id' => $fireStartersSub?->id ?? 2,
                'name' => 'Natural Fire Starters (Test)',
                'short_title' => '12-pack, no chemicals',
                'price' => '8.00',
                'quantity' => 100,
                'description' => 'Wax and wood-fibre fire starters, 12 per pack.',
            ],
            'fire_gloves' => [
                'category_id' => $accessoriesCategory?->id ?? 4,
                'subcategory_id' => $fireStartersSub?->id ?? 2,
                'name' => 'Heat-Resistant Gloves (Test)',
                'short_title' => 'Handle logs and grates safely',
                'price' => '18.00',
                'quantity' => 30,
                'description' => 'Pair of heat-resistant firewood handling gloves.',
            ],
            'firewood_rack' => [
                'category_id' => $accessoriesCategory?->id ?? 4,
                'subcategory_id' => $fireStartersSub?->id ?? 2,
                'name' => 'Compact Firewood Rack (Test)',
                'short_title' => 'Keeps stacked wood off the ground',
                'price' => '35.00',
                'quantity' => 20,
                'description' => 'Steel rack for dry, off-ground firewood storage.',
            ],
        ];

        $products = [];
        foreach ($specs as $handle => $attrs) {
            $products[$handle] = Product::firstOrCreate(
                ['name' => $attrs['name']],
                array_merge($attrs, ['status' => 1])
            );
        }

        return $products;
    }

    /** @param array<string, Product> $products */
    private function seedBundles(array $products): void
    {
        // Bundle 1: a real discount + one mandatory extra + one optional extra.
        $starter = Bundle::firstOrCreate(
            ['name' => 'Weekend Starter Bundle (Test)'],
            [
                'description' => 'Everything you need to get a fire going this weekend — 15% off when bundled.',
                'base_product_id' => $products['jarrah_bag']->id,
                'discount_percentage' => 15,
                'status' => 1,
            ]
        );
        BundleExtra::updateOrCreate(
            ['bundle_id' => $starter->id, 'product_id' => $products['fire_starters']->id],
            ['is_required' => true, 'sort_order' => 0, 'status' => 1]
        );
        BundleExtra::updateOrCreate(
            ['bundle_id' => $starter->id, 'product_id' => $products['kindling_bundle']->id],
            ['is_required' => false, 'sort_order' => 1, 'status' => 1]
        );

        // Bundle 2: no discount, purely optional extras — contrast case.
        $accessory = Bundle::firstOrCreate(
            ['name' => 'Firewood Handling Kit (Test)'],
            [
                'description' => 'Base firewood bag with optional handling accessories.',
                'base_product_id' => $products['jarrah_bag']->id,
                'discount_percentage' => 0,
                'status' => 1,
            ]
        );
        BundleExtra::updateOrCreate(
            ['bundle_id' => $accessory->id, 'product_id' => $products['fire_gloves']->id],
            ['is_required' => false, 'sort_order' => 0, 'status' => 1]
        );
        BundleExtra::updateOrCreate(
            ['bundle_id' => $accessory->id, 'product_id' => $products['firewood_rack']->id],
            ['is_required' => false, 'sort_order' => 1, 'status' => 1]
        );
    }

    /** @param array<string, Product> $products */
    private function seedUpsells(array $products): void
    {
        // Buying the firewood bag on its own suggests kindling (extra) and
        // gloves (upgrade) at checkout.
        ProductUpsell::updateOrCreate(
            ['product_id' => $products['jarrah_bag']->id, 'upsell_product_id' => $products['kindling_bundle']->id],
            ['type' => 'extra', 'sort_order' => 0, 'status' => 1]
        );
        ProductUpsell::updateOrCreate(
            ['product_id' => $products['jarrah_bag']->id, 'upsell_product_id' => $products['fire_gloves']->id],
            ['type' => 'upgrade', 'sort_order' => 1, 'status' => 1]
        );
    }
}
