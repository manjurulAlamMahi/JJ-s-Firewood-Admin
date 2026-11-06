<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $methods = [
            [
                'name' => 'Card/Square',
                'slug' => 'square',
                'is_active' => true,
            ],
            [
                'name' => 'Cash on Delivery',
                'slug' => 'cash_on_delivery',
                'is_active' => true,
            ],
            [
                'name' => 'Bank Transfer',
                'slug' => 'bank_transfer',
                'is_active' => true,
            ],
            [
                'name' => 'PayPal',
                'slug' => 'paypal',
                'is_active' => true,
            ],
        ];

        foreach ($methods as $method) {
            \App\Models\PaymentMethod::updateOrCreate(['slug' => $method['slug']], $method);
        }
    }
}
