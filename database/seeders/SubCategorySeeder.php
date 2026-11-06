<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\SubCategory;


class SubCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $subCategories = [
            [
                'category_id' => 1,
                'name' => 'Kindling',
                'description' => 'Kindling is a combustible material used for lighting fires and cooking.',
            ],
            [
                'category_id' => 1,
                'name' => 'Fire Starters',
                'description' => 'Fire starters are materials designed to help ignite fires quickly and easily.',
            ],
            [
                'category_id' => 2,
                'name' => 'Briquettes',
                'description' => 'Briquettes are compressed blocks of combustible material used for fuel.',
            ],
            [
                'category_id' => 2,
                'name' => 'Pellets',
                'description' => 'Pellets are small, cylindrical pieces of compressed biomass used as fuel.',
            ],
        ];

        foreach ($subCategories as $subCategory) {
            SubCategory::create($subCategory);
        }
    }
}
