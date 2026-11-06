<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds auto_delivered_at to orders.
 *
 * Set when the daily auto-delivery job marks an order as delivered (because
 * it was paid via a verified method like Square/PayPal/card on the scheduled
 * delivery date). NULL on orders marked delivered manually.
 *
 * Used to render an "auto-delivered" badge in the drawer so admin can spot
 * orders that went through automated finalisation vs manual confirmation.
 * Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('orders')) return;

        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'auto_delivered_at')) {
                $table->timestamp('auto_delivered_at')
                      ->nullable()
                      ->after('needs_action_dismissed_at')
                      ->index();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('orders')) return;

        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'auto_delivered_at')) {
                $table->dropColumn('auto_delivered_at');
            }
        });
    }
};
