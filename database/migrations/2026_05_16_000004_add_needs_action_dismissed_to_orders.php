<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds needs_action_dismissed_at to orders.
 *
 * Lets admin dismiss an order from the Needs Action list without
 * resolving it. When NULL, the order shows in Needs Action (subject
 * to the other criteria). When set to a timestamp, the order is
 * hidden from the default Needs Action view but still findable via
 * the "Show dismissed" toggle.
 *
 * Auto-clears: the OrderObserver clears this column to NULL whenever
 * a "material" field changes (payment_status, order_status,
 * preferred_delivery_date, delivery_date_confirmed_at). That gives
 * the "comes back if something changes" behaviour.
 *
 * Idempotent — only adds if column doesn't already exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('orders')) return;

        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'needs_action_dismissed_at')) {
                $table->timestamp('needs_action_dismissed_at')
                      ->nullable()
                      ->after('delivery_date_confirmed_at')
                      ->index();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('orders')) return;

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'needs_action_dismissed_at')) {
                $table->dropColumn('needs_action_dismissed_at');
            }
        });
    }
};
