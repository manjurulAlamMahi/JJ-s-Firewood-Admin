<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $table = 'notifications_queue';

    private function indexExists(string $indexName): bool
    {
        return Schema::hasIndex($this->table, $indexName);
    }

    private function columnExists(string $column): bool
    {
        return Schema::hasColumn($this->table, $column);
    }

    public function up(): void
    {
        Schema::table($this->table, function (Blueprint $table) {

            /**
             * ✅ Add columns ONLY if missing.
             * Edit this list to match exactly what you want in notifications_queue.
             */

            if (!Schema::hasColumn('notifications_queue', 'sent_at')) {
                $table->timestamp('sent_at')->nullable()->after('next_run_at');
            }

            if (!Schema::hasColumn('notifications_queue', 'rrule')) {
                $table->string('rrule', 255)->nullable()->after('schedule_type');
            }

            if (!Schema::hasColumn('notifications_queue', 'event_key')) {
                $table->string('event_key', 255)->nullable()->after('rrule');
            }

            if (!Schema::hasColumn('notifications_queue', 'total_targets')) {
                $table->unsignedInteger('total_targets')->default(0)->after('failed_count');
            }

            if (!Schema::hasColumn('notifications_queue', 'processed_targets')) {
                $table->unsignedInteger('processed_targets')->default(0)->after('total_targets');
            }

            if (!Schema::hasColumn('notifications_queue', 'paused_at')) {
                $table->timestamp('paused_at')->nullable()->after('status');
            }
        });

        // ✅ Add index only if missing (NO Doctrine needed)
        $indexName = 'notifications_queue_channel_status_index';

        if (!$this->indexExists($indexName)) {
            Schema::table($this->table, function (Blueprint $table) use ($indexName) {
                $table->index(['channel', 'status'], $indexName);
            });
        }
    }

    public function down(): void
    {
        $indexName = 'notifications_queue_channel_status_index';

        if ($this->indexExists($indexName)) {
            Schema::table($this->table, function (Blueprint $table) use ($indexName) {
                $table->dropIndex($indexName);
            });
        }

        Schema::table($this->table, function (Blueprint $table) {

            // Drop columns only if they exist (safe rollback)
            if (Schema::hasColumn('notifications_queue', 'sent_at')) {
                $table->dropColumn('sent_at');
            }

            if (Schema::hasColumn('notifications_queue', 'rrule')) {
                $table->dropColumn('rrule');
            }

            if (Schema::hasColumn('notifications_queue', 'event_key')) {
                $table->dropColumn('event_key');
            }

            if (Schema::hasColumn('notifications_queue', 'total_targets')) {
                $table->dropColumn('total_targets');
            }

            if (Schema::hasColumn('notifications_queue', 'processed_targets')) {
                $table->dropColumn('processed_targets');
            }

            if (Schema::hasColumn('notifications_queue', 'paused_at')) {
                $table->dropColumn('paused_at');
            }
        });
    }
};
