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
        Schema::create('giveaways', function (Blueprint $table) {
            $table->id();
            $table->string('prize_name');
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->decimal('prize_value', 10, 2);
            $table->enum('prize_type', ['bundle', 'package', 'accessory', 'others'])->default('bundle');
            $table->integer('required_entries')->default(10);
            $table->date('start_date');
            $table->date('draw_date');
            $table->integer('status')->default(1);
            $table->foreignId('winner_id')->nullable()->constrained('users')->onDelete('set null');
            $table->integer('total_entries')->default(0);
            $table->timestamp('winner_announced_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('giveaways');
    }
};
