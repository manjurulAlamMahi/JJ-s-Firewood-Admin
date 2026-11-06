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
        Schema::table('order_items', function (Blueprint $table) {
            // Make product_id nullable
            $table->foreignId('product_id')->nullable()->change();
            
            // Add giveaway_id
            $table->foreignId('giveaway_id')->nullable()->after('product_id')->constrained('giveaways')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropForeign(['giveaway_id']);
            $table->dropColumn('giveaway_id');
            $table->foreignId('product_id')->nullable(false)->change();
        });
    }
};
