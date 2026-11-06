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
        Schema::create('calculators', function (Blueprint $table) {
            $table->id();
            $table->integer('pieces_per_night');
            $table->integer('nights_per_week');
            $table->integer('months_in_season');
            $table->integer('total_pieces');
            $table->integer('firewood_bags_needed');
            $table->decimal('estimated_weight_tonnes', 8, 2);
            $table->integer('trailer_loads');
            $table->json('recommended_loads');
            $table->json('prices')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calculators');
    }
};
