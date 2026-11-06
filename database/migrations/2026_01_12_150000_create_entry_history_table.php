<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entry_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('giveaway_id')->constrained('giveaways')->onDelete('cascade');
            $table->foreignId('order_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('admin_user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->enum('action', ['add', 'remove']);
            $table->integer('quantity');
            $table->enum('source', ['purchase', 'admin', 'system', 'bonus'])->default('admin');
            $table->string('reason')->nullable();
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('giveaway_id');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entry_history');
    }
};