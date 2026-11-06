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
        Schema::create('referral_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_user_id')->constrained('users')->onDelete('cascade'); 
            $table->foreignId('referred_user_id')->constrained('users')->onDelete('cascade'); 
            $table->string('referral_code');
            $table->decimal('discount_amount', 10, 2)->default(10.00); 
            $table->boolean('bonus_given')->default(false);
            $table->integer('referral_bonus')->default(100); 
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
            
            $table->unique(['referred_user_id', 'referral_code']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('referral_usages');
    }
};
