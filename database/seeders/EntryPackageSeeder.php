<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EntryPackage;

class EntryPackageSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        EntryPackage::create([
            'name' => '3 Entries',
            'price' => 2.99,
            'status' => 0,
            'entry_quantity' => 3
        ]);

        EntryPackage::create([
            'name' => '5 Entries',
            'price' => 4.99,
            'status' => 1,
            'entry_quantity' => 5
        ]);


    }
}
