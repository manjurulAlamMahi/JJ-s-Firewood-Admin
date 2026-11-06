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
        // Note: changing column defaults may require the doctrine/dbal package.
        Schema::table('entries', function (Blueprint $table) {
            $table->integer('entry_count')->default(0)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entries', function (Blueprint $table) {
            $table->integer('entry_count')->default(1)->change();
        });
    }
};
