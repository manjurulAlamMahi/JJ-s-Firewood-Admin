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
        Schema::table('giveaways', function (Blueprint $table) {
            if (!Schema::hasColumn('giveaways', 'start_time')) {
                $table->time('start_time')->nullable()->after('start_date');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('giveaways', function (Blueprint $table) {
            if (Schema::hasColumn('giveaways', 'start_time')) {
                $table->dropColumn('start_time');
            }
        });
    }
};