<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds Setmore tracking columns to orders so admin can see at a glance
 * how a delivery date was set:
 *   - scheduled_via        — 'manual' | 'setmore' | 'app'  (default null = unknown/legacy)
 *   - setmore_appointment_id — for traceability back to the Setmore event
 *
 * Idempotent — only adds columns that don't already exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('orders')) return;

        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'scheduled_via')) {
                $table->string('scheduled_via', 16)->nullable()->after('preferred_delivery_date');
            }
            if (!Schema::hasColumn('orders', 'setmore_appointment_id')) {
                $table->string('setmore_appointment_id', 100)->nullable()->after('scheduled_via');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('orders')) return;
        Schema::table('orders', function (Blueprint $table) {
            foreach (['scheduled_via', 'setmore_appointment_id'] as $col) {
                if (Schema::hasColumn('orders', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
