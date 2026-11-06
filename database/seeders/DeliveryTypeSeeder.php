<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DeliveryTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = Carbon::now();

        DB::table('delivery_types')->insert([
            [
                'name' => 'Free Delivery',
                'fee' => 0,
                'days' => '1-7 days',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Priority Delivery',
                'fee' => 90,
                'days' => '0-4 days',
                'status' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'name' => 'Urgent Delivery',
                'fee' => 180,
                'days' => '0-24 hours',
                'status' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);
    }
}
