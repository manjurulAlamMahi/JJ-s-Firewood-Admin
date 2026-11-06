<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BankTransfersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run()
    {
        DB::table('bank_transfers')->insert([
            [
                'account_holder_name' => 'Nick Froehnelt',
                'account_number' => '1234567890',
                'bank_name' => 'Bank of ABC',
                'branch_name' => 'Downtown',
                'note' => 'First transfer',
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            
        ]);
    }
}
