<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('payment_id')->nullable()->unique();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3);
            $table->string('status', 50)->default('pending');
            $table->string('payment_method')->default('square');
            $table->string('source_id')->nullable();
            $table->json('card_details')->nullable();
            $table->text('receipt_url')->nullable();
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();
            $table->string('refund_id')->nullable()->unique();
            $table->decimal('refund_amount', 10, 2)->nullable();
            $table->enum('refund_status', ['none', 'partial', 'full'])->default('none');
            $table->text('refund_reason')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->boolean('webhook_received')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
