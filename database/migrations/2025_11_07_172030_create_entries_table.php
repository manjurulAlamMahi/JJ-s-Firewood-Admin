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
        Schema::create('entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('giveaway_id')->nullable()->constrained()->onDelete('cascade');
            $table->integer('entry_count')->default(1);
            $table->enum('purchase_type', ['free', 'paid', 'reward'])->default('paid');
            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->boolean('drawn')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entries');
    }
};
