<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wood_education_contents', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('short_description');
            $table->longText('long_description')->nullable();
            $table->string('image')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->dateTime('published_at')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('sub_category_id')->nullable()->constrained('sub_categories')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wood_education_contents');
    }
};
