<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ServiceCategory;
use App\Models\ServiceSubCategory;
use App\Models\ServiceSubCategoryFeature;

class ServiceSubCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all existing categories
        $categories = ServiceCategory::all();

        foreach ($categories as $category) {

            for ($i = 1; $i <= 2; $i++) {
                $subCategory = ServiceSubCategory::create([
                    'service_category_id' => $category->id,
                    'name' => $category->name . " Sub Category $i",
                    'description' => "Description for " . $category->name . " Sub Category $i",
                    'image' => null,
                    'status' => 1,
                ]);

                for ($j = 1; $j <= 3; $j++) {
                    ServiceSubCategoryFeature::create([
                        'sub_category_id' => $subCategory->id,
                        'name' => "Feature $j of " . $subCategory->name,
                    ]);
                }
            }
        }
    }
}
