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
        Schema::table('orders', function (Blueprint $table) {
            // Change payment_status from enum to string
            $table->string('payment_status')->default('pending')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Revert back to enum if needed (Note: might need exact enum values to match previous state)
            // Using drop and recreate approach or raw statement might be safer for complex reversions, 
            // but for standard laravel 'change', we attempt to reverse logically.
            // However, enum support in 'change' can be tricky. 
            // We'll leave it simple for now or commented if strictly not needed.
            // $table->enum('payment_status', ['pending', 'completed', 'failed'])->default('pending')->change();
        });
    }
};
