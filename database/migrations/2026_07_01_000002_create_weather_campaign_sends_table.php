<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-recipient send log for weather-triggered campaigns.
 *
 * The unique index on (weather_campaign_id, user_id, channel, trigger_date)
 * is what stops a customer receiving the same campaign twice for the same
 * qualifying day (e.g. the scheduled command runs every 3 hours while the
 * weather condition still matches), while still allowing a fresh send the
 * next time the condition matches on a different day.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weather_campaign_sends', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('weather_campaign_id');
            $table->foreign('weather_campaign_id')->references('id')->on('weather_campaigns')->cascadeOnDelete();

            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('channel', 20); // push | email
            $table->date('trigger_date');

            $table->string('status', 20); // sent | failed | skipped
            $table->json('weather_snapshot')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();

            $table->timestamps();

            $table->unique(['weather_campaign_id', 'user_id', 'channel', 'trigger_date'], 'weather_campaign_sends_unique');
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weather_campaign_sends');
    }
};
