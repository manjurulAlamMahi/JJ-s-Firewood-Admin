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
        Schema::table('wc_orders', function (Blueprint $table) {
            $table->boolean('rewards_synced')->default(false)->after('wc_date_modified');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wc_orders', function (Blueprint $table) {
            $table->dropColumn('rewards_synced');
        });
    }
};
