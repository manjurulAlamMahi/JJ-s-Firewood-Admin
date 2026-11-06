<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('remaining_amount', 10, 2)->after('refund_amount')->nullable();
        });

        // Initialize remaining_amount for existing records
        \App\Models\Payment::all()->each(function ($payment) {
            $payment->remaining_amount = (float)$payment->amount - (float)($payment->refund_amount ?? 0);
            $payment->save();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('remaining_amount');
        });
    }
};
