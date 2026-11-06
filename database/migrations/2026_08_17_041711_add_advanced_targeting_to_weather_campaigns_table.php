<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('weather_campaigns', function (Blueprint $table) {
            $table->string('campaign_type', 50)->default('automated')->after('status');
            $table->boolean('requires_approval')->default(false)->after('campaign_type');
            $table->json('target_segments')->nullable()->after('target_zip_group_id');
            $table->json('exclusions')->nullable()->after('target_segments');
            $table->json('capacity_thresholds')->nullable()->after('exclusions');
            $table->json('channel_content')->nullable()->after('capacity_thresholds');
            $table->integer('lead_time_hours')->default(24)->after('weather_condition');
            $table->time('sending_time_start')->nullable()->after('active_until');
            $table->time('sending_time_end')->nullable()->after('sending_time_start');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('weather_campaigns', function (Blueprint $table) {
            $table->dropColumn([
                'campaign_type',
                'requires_approval',
                'target_segments',
                'exclusions',
                'capacity_thresholds',
                'channel_content',
                'lead_time_hours',
                'sending_time_start',
                'sending_time_end',
            ]);
        });
    }
};
