<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('shipping_address_id')->constrained('shipping_addresses')->onDelete('cascade');
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('subtotal', 10, 2);
            $table->decimal('delivery_fee', 10, 2)->default(0);
            $table->decimal('discount', 10, 2)->default(0);
            $table->decimal('tax', 10, 2)->default(0);
            $table->decimal('total', 10, 2);
            $table->enum('payment_method', ['paypal', 'stripe', 'google_pay', 'square']);
            $table->enum('payment_status', ['pending', 'completed', 'failed'])->default('pending');
            $table->string('transaction_id')->nullable();
            $table->date('preferred_delivery_date')->nullable();
            $table->enum('order_status', ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'])->default('pending');
            $table->integer('giveaway_entries')->default(0);
            $table->integer('loyalty_points')->default(0);
            $table->boolean('is_stock_deducted')->default(false);
            $table->boolean('is_coupon_used')->default(false);
            $table->string('idempotency_key')->nullable()->unique();
            $table->text('notes')->nullable();
            $table->string('payment_link')->nullable();
            $table->string('payment_link_id')->nullable();
            $table->string('square_order_id')->nullable();
            $table->timestamp('cancellation_requested_at')->nullable();
            $table->timestamp('refund_processed_at')->nullable();
            $table->unsignedBigInteger('refund_processed_by')->nullable();
            $table->foreign('refund_processed_by')->references('id')->on('users')->onDelete('set null');
            $table->timestamp('payment_link_expires_at')->nullable();


            $table->timestamps();
        });
    }


    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
