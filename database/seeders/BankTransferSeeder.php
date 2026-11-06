<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\BankTransfer;

class BankTransferSeeder extends Seeder
{
    public function run(): void
    {
        // Idempotently ensure a single active bank-transfer details row exists.
        // Columns mirror the bank_transfers migration
        // (account_holder_name, account_number, bank_name, branch_name, note, status).
        BankTransfer::updateOrCreate(
            ['status' => 1],
            [
                'account_holder_name' => 'Nick Froehnelt',
                'account_number' => '1234567890',
                'bank_name' => 'Bank of ABC',
                'branch_name' => 'Downtown',
                'note' => 'Use your Order ID as the payment reference.',
                'status' => 1,
            ]
        );
    }
}
