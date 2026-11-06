<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds setmore_auto_apply boolean to system_settings.
 *
 * When true, the Setmore webhook automatically applies the appointment date
 * to the matched order — but ONLY when there's exactly 1 candidate match
 * (safest behaviour). 0 candidates → no_match. 2+ candidates → pending for
 * admin to disambiguate.
 *
 * Default: false (no auto-apply; everything goes to pending review).
 * Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('system_settings')) return;

        Schema::table('system_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('system_settings', 'setmore_auto_apply')) {
                $table->boolean('setmore_auto_apply')
                      ->default(false)
                      ->after('id');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('system_settings')) return;

        Schema::table('system_settings', function (Blueprint $table) {
            if (Schema::hasColumn('system_settings', 'setmore_auto_apply')) {
                $table->dropColumn('setmore_auto_apply');
            }
        });
    }
};
