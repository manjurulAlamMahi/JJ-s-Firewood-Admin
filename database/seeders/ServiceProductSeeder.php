<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ServiceProduct;
use App\Models\ServiceCategory;
use App\Models\ServiceSubCategory;

class ServiceProductSeeder extends Seeder
{
    public function run()
    {
        $categories = ServiceCategory::all();

        if ($categories->count() == 0) {
            $this->command->info('No categories found. Please seed ServiceCategory first.');
            return;
        }

        foreach ($categories as $category) {
            $subcategories = ServiceSubCategory::where('service_category_id', $category->id)->get();

            if ($subcategories->count() == 0) {
                continue;
            }

            foreach ($subcategories as $subcategory) {
                for ($i = 1; $i <= 1; $i++) {

                    $product = ServiceProduct::create([
                        'service_category_id' => $category->id,
                        'service_sub_category_id' => $subcategory->id,
                        'name' => $subcategory->name . ' Product ' . $i,
                        'price' => rand(100, 1000),
                        'installation_included' => rand(0, 1),
                        'status' => 1,
                    ]);

                    $features = [
                        'Feature A ' . $i,
                        'Feature B ' . $i,
                        'Feature C ' . $i
                    ];

                    foreach ($features as $feature) {
                        $product->features()->create([
                            'name' => $feature,
                        ]);
                    }
                }
            }
        }
    }
}
