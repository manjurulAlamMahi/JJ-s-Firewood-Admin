<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('notifications_queue', function (Blueprint $table) {
            // Add only if missing (safe)
            if (!Schema::hasColumn('notifications_queue', 'rrule')) {
                $table->string('rrule', 255)->nullable()->after('schedule_type');
            }
            if (!Schema::hasColumn('notifications_queue', 'event_key')) {
                $table->string('event_key', 255)->nullable()->after('rrule');
            }

            // Optional but recommended for edit + progress UI (only if you don't already have them)
            if (!Schema::hasColumn('notifications_queue', 'total_targets')) {
                $table->unsignedInteger('total_targets')->nullable()->after('sent_count');
            }
            if (!Schema::hasColumn('notifications_queue', 'processed_targets')) {
                $table->unsignedInteger('processed_targets')->nullable()->after('total_targets');
            }
        });
    }

    public function down(): void
    {
        Schema::table('notifications_queue', function (Blueprint $table) {
            if (Schema::hasColumn('notifications_queue', 'processed_targets')) {
                $table->dropColumn('processed_targets');
            }
            if (Schema::hasColumn('notifications_queue', 'total_targets')) {
                $table->dropColumn('total_targets');
            }
            if (Schema::hasColumn('notifications_queue', 'event_key')) {
                $table->dropColumn('event_key');
            }
            if (Schema::hasColumn('notifications_queue', 'rrule')) {
                $table->dropColumn('rrule');
            }
        });
    }
};
