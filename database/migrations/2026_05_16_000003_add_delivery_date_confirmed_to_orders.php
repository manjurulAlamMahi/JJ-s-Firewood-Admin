<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds delivery_date_confirmed_at to orders.
 *
 * Drives the Needs Action filter: NULL means no admin has confirmed the
 * delivery date yet (customer-picked dates don't count). Set when:
 *   1. Admin saves a date via the drawer's Reschedule button
 *   2. Admin uses the bulk Reschedule modal
 *   3. SetmoreMatchingService::applyEvent applies a date
 *
 * Backfill is handled by a separate artisan command — orders older than
 * 3 days at deploy time get marked as confirmed so existing data doesn't
 * suddenly flood Needs Action.
 *
 * Idempotent — only adds if column doesn't already exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('orders')) return;

        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'delivery_date_confirmed_at')) {
                $table->timestamp('delivery_date_confirmed_at')
                      ->nullable()
                      ->after('preferred_delivery_date')
                      ->index();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('orders')) return;

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'delivery_date_confirmed_at')) {
                $table->dropColumn('delivery_date_confirmed_at');
            }
        });
    }
};
