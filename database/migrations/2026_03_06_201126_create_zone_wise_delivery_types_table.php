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
        Schema::create('zone_wise_delivery_types', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('zip_code_group_id');
            $table->unsignedBigInteger('delivery_type_id');
            $table->decimal('fee', 8, 2);
            $table->timestamps();

            $table->foreign('zip_code_group_id')->references('id')->on('zip_code_groups')->onDelete('cascade');
            $table->foreign('delivery_type_id')->references('id')->on('delivery_types')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('zone_wise_delivery_types');
    }
};
