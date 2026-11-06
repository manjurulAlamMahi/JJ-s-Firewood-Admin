<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ServiceCategory;

class ServiceCategorySeeder extends Seeder
{
    public function run()
    {
        $categories = [
            [
                'name'   => 'Heating Solutions',
            ],
            [
                'name'   => 'Chimney Services',
            ],
        ];

        foreach ($categories as $category) {
            ServiceCategory::create($category);
        }
    }
}
