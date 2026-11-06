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
        Schema::table('push_notifications', function (Blueprint $table) {
            // Add category field (transactional, helpful, updates)
            if (!Schema::hasColumn('push_notifications', 'category')) {
                $table->string('category')->default('updates')->after('message');
            }

            // Add flag to respect user preferences
            if (!Schema::hasColumn('push_notifications', 'use_preferences')) {
                $table->boolean('use_preferences')->default(true)->after('category');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('push_notifications', function (Blueprint $table) {
            $table->dropColumn(['category', 'use_preferences']);
        });
    }
};
