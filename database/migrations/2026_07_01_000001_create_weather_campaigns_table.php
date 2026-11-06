<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Weather-triggered campaign rules.
 *
 * A rule targets a zip code group (or all deliverable zip codes if null),
 * defines a weather condition, an optional day-of-week / date window, and
 * the channels to fire on match (push, email, banner).
 *
 * `last_evaluated_at` / `last_matched_zip_codes` are written by the
 * `weather-campaigns:process` scheduled command each run, and read by the
 * public "active campaigns" endpoint so the app never triggers a live
 * weather lookup on request.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weather_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('status', 20)->default('draft'); // draft | active | paused

            $table->json('channels'); // subset of: push, email, banner

            $table->string('title')->nullable();
            $table->text('message')->nullable();
            $table->string('banner_image')->nullable();
            $table->string('banner_cta_text')->nullable();
            $table->string('banner_cta_link')->nullable();

            $table->unsignedBigInteger('target_zip_group_id')->nullable();
            $table->foreign('target_zip_group_id')->references('id')->on('zip_code_groups')->nullOnDelete();

            $table->json('weather_condition'); // {temp_max_c, rain_probability_min, condition_codes}
            $table->json('active_days')->nullable(); // ISO weekdays 1-7, null = every day
            $table->date('active_from')->nullable();
            $table->date('active_until')->nullable();

            $table->boolean('respects_marketing_optout')->default(true);

            $table->timestamp('last_evaluated_at')->nullable();
            $table->json('last_matched_zip_codes')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('weather_campaigns');
    }
};
