<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds Order Centre support columns to the orders table.
     * All columns have safe defaults so existing rows / existing OrderController
     * are unaffected. The legacy /order/* system continues to work unchanged.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Source of the order: 'native' (current site) or 'woocommerce' (future sync)
            if (!Schema::hasColumn('orders', 'source')) {
                $table->string('source', 32)->default('native')->index()->after('order_status');
            }

            // External order ID for WooCommerce orders. Nullable, unique when set.
            if (!Schema::hasColumn('orders', 'woocommerce_order_id')) {
                $table->string('woocommerce_order_id')->nullable()->unique()->after('source');
            }

            // Allows admin to dismiss an auto-detected refund-eligible order
            // from the Refunds Queue tab without taking action.
            if (!Schema::hasColumn('orders', 'is_dismissed_from_refund_queue')) {
                $table->boolean('is_dismissed_from_refund_queue')->default(false)->after('woocommerce_order_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'is_dismissed_from_refund_queue')) {
                $table->dropColumn('is_dismissed_from_refund_queue');
            }
            if (Schema::hasColumn('orders', 'woocommerce_order_id')) {
                $table->dropUnique(['woocommerce_order_id']);
                $table->dropColumn('woocommerce_order_id');
            }
            if (Schema::hasColumn('orders', 'source')) {
                $table->dropIndex(['source']);
                $table->dropColumn('source');
            }
        });
    }
};
