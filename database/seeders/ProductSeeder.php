<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\ProductOtherImage;
use App\Models\Category;
use App\Models\SubCategory;

class ProductSeeder extends Seeder
{
    public function run()
    {
        $categories = Category::all();
        $subcategories = SubCategory::all();

        if ($categories->isEmpty() || $subcategories->isEmpty()) {
            $this->command->info('Please seed categories and subcategories first.');
            return;
        }

        // Product 1
        $product1 = Product::create([
            'category_id' => $categories->random()->id,
            'subcategory_id' => $subcategories->random()->id,
            'name' => 'Sample Product 1',
            'short_title' => 'Premium hardwood that burns longer, cleaner and hotter',
            'price' => 500,
            'quantity' => 10,
            'description' => 'Description for Sample Product 1',
            'status' => 1,
            'size_info' => 'Size info 1',
            'burn_quantity_info' => 'Burn info 1',
            'delivery_info' => 'Delivery info 1',
        ]);

        ProductOtherImage::create([
            'product_id' => $product1->id,
        ]);

        // Product 2
        $product2 = Product::create([
            'category_id' => $categories->random()->id,
            'subcategory_id' => $subcategories->random()->id,
            'name' => 'Sample Product 2',
            'short_title' => 'Premium hardwood that burns longer, cleaner and hotter',
            'price' => 1000,
            'quantity' => 5,
            'description' => 'Description for Sample Product 2',
            'status' => 1,
            'size_info' => 'Size info 2',
            'burn_quantity_info' => 'Burn info 2',
            'delivery_info' => 'Delivery info 2',
        ]);

        ProductOtherImage::create([
            'product_id' => $product2->id,
        ]);

        $this->command->info('2 sample products seeded successfully.');
    }
}
