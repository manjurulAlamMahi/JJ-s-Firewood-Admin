<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Info;

class InfoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Info::create([
            'title' => 'Company Info',
            'phone' => '01712345678',
            'email' => 'company@example.com',
        ]);

        Info::create([
            'title' => 'Survice Info',
            'phone' => '01912345678',
            'email' => 'survice@example.com',
        ]);
    }
}
