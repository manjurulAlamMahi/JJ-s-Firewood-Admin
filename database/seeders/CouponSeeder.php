<?php
// database/seeders/CouponSeeder.php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Coupon;
use Carbon\Carbon;

class CouponSeeder extends Seeder
{
    public function run(): void
    {
        $coupons = [
            [
                'code' => 'WELCOME10',
                'type' => 'percentage',
                'value' => 10,
                'min_order_amount' => 0,
                'max_discount' => null,
                'usage_limit' => 1,
                'start_date' => Carbon::now(),
                'end_date' => Carbon::now()->addMonths(3),
                'is_active' => true,
            ],
            [
                'code' => 'FLAT50',
                'type' => 'fixed',
                'value' => 50,
                'min_order_amount' => 200,
                'max_discount' => null,
                'usage_limit' => 30,
                'start_date' => Carbon::now(),
                'end_date' => Carbon::now()->addMonth(),
                'is_active' => true,
            ],
        ];

        foreach ($coupons as $coupon) {
            Coupon::create($coupon);
        }
    }
}
