<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notification Centre rebuild — extend notifications_queue with audience builder fields.
 *
 * Additive only. Existing columns (channel, audience, message, etc.) are unchanged.
 * Existing rows continue to work because all new columns are nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('notifications_queue')) return;

        Schema::table('notifications_queue', function (Blueprint $table) {
            // JSON spec describing the audience builder choices:
            //   {
            //     "include": [{"type":"all_users"}, {"type":"suburb","value":"Joondalup"}],
            //     "exclude": [{"type":"users","ids":[12,34]}],
            //     "handpicked_user_ids": [22, 88],
            //     "raw_recipients": ["+61400123456"]   // for SMS only — phones not in users table
            //   }
            if (!Schema::hasColumn('notifications_queue', 'audience_spec')) {
                $table->json('audience_spec')->nullable()->after('audience');
            }

            // Optional reference to a saved template
            if (!Schema::hasColumn('notifications_queue', 'template_ref_id')) {
                $table->unsignedBigInteger('template_ref_id')->nullable()->after('audience_spec');
            }

            // Estimated cost (set when SMS draft is saved, used to confirm before send)
            if (!Schema::hasColumn('notifications_queue', 'estimated_cost')) {
                $table->decimal('estimated_cost', 10, 2)->nullable()->after('template_ref_id');
            }

            // Estimated SMS segments (1, 2, 3...)
            if (!Schema::hasColumn('notifications_queue', 'sms_segments')) {
                $table->unsignedSmallInteger('sms_segments')->nullable()->after('estimated_cost');
            }

            // Whether marketing opt-outs are honoured (false for transactional broadcasts)
            if (!Schema::hasColumn('notifications_queue', 'respects_marketing_optout')) {
                $table->boolean('respects_marketing_optout')->default(true)->after('sms_segments');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('notifications_queue')) return;

        Schema::table('notifications_queue', function (Blueprint $table) {
            foreach (['audience_spec','template_ref_id','estimated_cost','sms_segments','respects_marketing_optout'] as $col) {
                if (Schema::hasColumn('notifications_queue', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
