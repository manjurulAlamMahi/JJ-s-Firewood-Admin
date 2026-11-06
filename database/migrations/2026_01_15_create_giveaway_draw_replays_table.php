<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('giveaway_draw_replays', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('giveaway_id');
            $table->string('draw_id')->unique(); // e.g., DRAW-2026-031
            $table->enum('animation_style', ['lottery_balls', 'raffle_tickets'])->default('lottery_balls');
            $table->unsignedBigInteger('winner_id');
            $table->json('animation_data'); // Stores animation sequence
            $table->string('video_path')->nullable(); // Optional video file
            $table->integer('total_entries')->default(0);
            $table->integer('total_participants')->default(0);
            $table->timestamp('drawn_at');
            $table->unsignedBigInteger('drawn_by')->nullable();
            $table->timestamps();
            
            $table->foreign('giveaway_id')->references('id')->on('giveaways')->onDelete('cascade');
            $table->foreign('winner_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('drawn_by')->references('id')->on('users')->onDelete('set null');
            
            $table->index('drawn_at');
            $table->index('draw_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('giveaway_draw_replays');
    }
};