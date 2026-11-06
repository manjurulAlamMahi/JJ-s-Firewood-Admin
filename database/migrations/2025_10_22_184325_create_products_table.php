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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->integer('category_id');
            $table->integer('subcategory_id');
            $table->string('name');
            $table->string('short_title')->nullable();
            $table->string('price');
            $table->integer('quantity')->nullable();
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->text('size_info')->nullable();
            $table->text('burn_quantity_info')->nullable();
            $table->text('delivery_info')->nullable();
            $table->boolean('status')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
