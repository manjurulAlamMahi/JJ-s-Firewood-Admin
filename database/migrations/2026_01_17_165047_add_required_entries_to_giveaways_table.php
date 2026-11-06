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
            if (!Schema::hasColumn('giveaways', 'required_entries')) {
                $table->integer('required_entries')->default(10)->after('prize_type');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('giveaways', function (Blueprint $table) {
            if (Schema::hasColumn('giveaways', 'required_entries')) {
                $table->dropColumn('required_entries');
            }
        });
    }
};
