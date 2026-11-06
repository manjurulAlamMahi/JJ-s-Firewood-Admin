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
        if (!Schema::hasTable('giveaway_draw_logs')) {
            Schema::create('giveaway_draw_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('giveaway_id');
                $table->unsignedBigInteger('winner_id');
                $table->integer('total_entries')->default(0);
                $table->integer('total_participants')->default(0);
                $table->timestamp('drawn_at');
                $table->unsignedBigInteger('drawn_by')->nullable();
                $table->timestamps();
                
                $table->foreign('giveaway_id')->references('id')->on('giveaways')->onDelete('cascade');
                $table->foreign('winner_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('drawn_by')->references('id')->on('users')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('giveaway_draw_logs');
    }
};