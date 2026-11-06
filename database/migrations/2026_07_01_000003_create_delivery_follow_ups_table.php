<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per delivered order, created the moment OrderDelivered fires.
 *
 * The unique index on order_id is what prevents repeat follow-up sends for
 * the same order — SendDeliveryFollowUp uses firstOrCreate() and only sends
 * when the row is freshly created / still pending.
 *
 * status moves pending -> sent -> opened -> (completed | dismissed). Once
 * completed or dismissed the row is terminal; the API rejects further
 * action calls for that order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_follow_ups', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('order_id');
            $table->foreign('order_id')->references('id')->on('orders')->cascadeOnDelete();
            $table->unique('order_id');

            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();

            $table->string('status', 20)->default('pending'); // pending | sent | opened | completed | dismissed
            $table->json('channels_sent')->nullable(); // e.g. ["push","database"]

            $table->string('action_taken', 20)->nullable(); // review | feedback | reorder
            $table->unsignedTinyInteger('feedback_rating')->nullable();
            $table->text('feedback_comment')->nullable();

            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();

            $table->text('error')->nullable();

            $table->timestamps();

            $table->index('user_id');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_follow_ups');
    }
};
