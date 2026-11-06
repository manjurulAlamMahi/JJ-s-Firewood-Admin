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
        Schema::create('service_products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('service_category_id');
            $table->unsignedBigInteger('service_sub_category_id');
            $table->string('name');
            $table->string('price');
            $table->boolean('installation_included')->default(true);
            $table->string('image')->nullable();
            $table->integer('status')->default(1);
            $table->foreign('service_sub_category_id')->references('id')->on('service_sub_categories')->onDelete('cascade');
            $table->foreign('service_category_id')->references('id')->on('service_categories')->onDelete('cascade');
            $table->timestamps();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('service_products');
    }
};
