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
        Schema::create('tips_and_guides', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('short_description');
            $table->longText('long_description')->nullable();
            $table->string('image')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tips_and_guides');
    }
};
