<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds setmore_auto_cancel boolean to system_settings.
 *
 * When true (default), Setmore "Appointment Deleted" webhooks
 * automatically cancel the matched order — fires OrderCancelled event,
 * customer gets the cancellation email, paid orders appear in the
 * Refund Queue.
 *
 * When false, cancellations are recorded but require manual admin
 * confirmation in the Cancellations tab. Useful kill-switch if
 * something goes wrong with the integration.
 *
 * Default: TRUE (cancellations should "just work" out of the box —
 * the user explicitly asked for auto-cancellation behaviour).
 * Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('system_settings')) return;

        Schema::table('system_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('system_settings', 'setmore_auto_cancel')) {
                $table->boolean('setmore_auto_cancel')
                      ->default(true)
                      ->after('setmore_auto_apply');
            }
        });

        // Backfill existing rows to TRUE — the auto-cancel flow is the
        // default behaviour and existing installs should benefit immediately.
        if (Schema::hasColumn('system_settings', 'setmore_auto_cancel')) {
            \DB::table('system_settings')
                ->whereNull('setmore_auto_cancel')
                ->update(['setmore_auto_cancel' => true]);
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('system_settings')) return;

        Schema::table('system_settings', function (Blueprint $table) {
            if (Schema::hasColumn('system_settings', 'setmore_auto_cancel')) {
                $table->dropColumn('setmore_auto_cancel');
            }
        });
    }
};
