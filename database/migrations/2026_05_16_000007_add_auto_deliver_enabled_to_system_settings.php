<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds auto_deliver_enabled boolean to system_settings.
 *
 * When true, the daily auto-delivery sweep marks paid+verified orders as
 * delivered at the scheduled run time. When false (default), the scheduled
 * sweep is a no-op — admin marks everything manually.
 *
 * Defaults to false so existing deploys aren't surprised.
 * Idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('system_settings')) return;

        Schema::table('system_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('system_settings', 'auto_deliver_enabled')) {
                $table->boolean('auto_deliver_enabled')
                      ->default(false)
                      ->after('setmore_auto_apply');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('system_settings')) return;

        Schema::table('system_settings', function (Blueprint $table) {
            if (Schema::hasColumn('system_settings', 'auto_deliver_enabled')) {
                $table->dropColumn('auto_deliver_enabled');
            }
        });
    }
};
