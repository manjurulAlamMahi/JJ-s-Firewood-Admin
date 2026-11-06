<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Firewoods',
            ],
            [
                'name' => 'Bagged Products',
            ],
            [
                'name' => 'Kindling & Starters',
            ],
            [
                'name' => 'Accessories',
            ],
        ];

        foreach ($categories as $category) {
            Category::create($category);
        }
    }
}
