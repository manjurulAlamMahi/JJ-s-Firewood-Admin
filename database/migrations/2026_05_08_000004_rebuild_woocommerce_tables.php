<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 10: Rebuild WooCommerce as a fully separate world.
 *
 * The old approach mixed WC orders into the native `orders` table via a
 * `source` column. That coupled WC line items to the local `products`
 * foreign key and caused 200 sync failures. This migration:
 *
 *   1. Removes any leftover WC rows from the native `orders` table
 *   2. Drops `orders.source` and `orders.woocommerce_order_id` (no longer needed)
 *      Keeps `orders.is_dismissed_from_refund_queue` (native still uses it)
 *   3. Creates the new wc_* table set with proper FKs to wc_orders.id only
 */
return new class extends Migration
{
    public function up(): void
    {
        // ---- 1. Clean up native orders that snuck in via the old sync ----
        if (Schema::hasColumn('orders', 'source')) {
            DB::table('orders')->where('source', 'woocommerce')->delete();
        }

        // ---- 2. Drop legacy WC columns from orders ----
        if (Schema::hasColumn('orders', 'woocommerce_order_id')) {
            // The earlier order-centre migration created a unique index for this
            // column. Drop it explicitly before the column so the migration is
            // valid on SQLite as well as MySQL/MariaDB.
            Schema::table('orders', function (Blueprint $table) {
                $table->dropUnique('orders_woocommerce_order_id_unique');
            });

            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('woocommerce_order_id');
            });
        }

        if (Schema::hasColumn('orders', 'source')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropIndex('orders_source_index');
            });

            Schema::table('orders', function (Blueprint $table) {
                $table->dropColumn('source');
            });
        }

        // ---- 3. wc_customers ----
        if (!Schema::hasTable('wc_customers')) {
            Schema::create('wc_customers', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('woocommerce_id')->nullable()->index(); // 0 for guests
                $table->string('email', 255)->nullable()->index();
                $table->string('first_name', 100)->nullable();
                $table->string('last_name', 100)->nullable();
                $table->string('phone', 50)->nullable();
                $table->json('billing_data')->nullable();
                $table->json('shipping_data')->nullable();
                $table->boolean('is_guest')->default(false);

                // Denormalized counters refreshed during sync
                $table->unsignedInteger('total_orders')->default(0);
                $table->decimal('total_spent', 10, 2)->default(0);
                $table->timestamp('last_order_at')->nullable();

                $table->timestamps();
            });
        }

        // ---- 4. wc_orders ----
        if (!Schema::hasTable('wc_orders')) {
            Schema::create('wc_orders', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('woocommerce_id')->unique();
                $table->string('woocommerce_number', 50)->nullable();

                $table->foreignId('wc_customer_id')->nullable()->constrained('wc_customers')->onDelete('set null');

                // Status (raw WC status string + a normalized version)
                $table->string('wc_status', 50);                  // pending|on-hold|processing|completed|cancelled|refunded|failed
                $table->string('payment_status', 50)->default('pending'); // pending|paid|refunded|failed

                // Currency + money
                $table->string('currency', 10)->default('AUD');
                $table->decimal('subtotal', 10, 2)->default(0);
                $table->decimal('shipping_total', 10, 2)->default(0);
                $table->decimal('tax_total', 10, 2)->default(0);
                $table->decimal('discount_total', 10, 2)->default(0);
                $table->decimal('total', 10, 2)->default(0);
                $table->decimal('refund_total', 10, 2)->default(0);

                // Payment
                $table->string('payment_method', 100)->nullable();
                $table->string('payment_method_title', 255)->nullable();
                $table->string('transaction_id', 255)->nullable();

                // Customer snapshot at order time (denormalized for search)
                $table->string('customer_email', 255)->nullable()->index();
                $table->string('customer_phone', 50)->nullable();
                $table->string('customer_first_name', 100)->nullable();
                $table->string('customer_last_name', 100)->nullable();

                // Address snapshots
                $table->json('billing_address')->nullable();
                $table->json('shipping_address')->nullable();

                // Denormalized fields for fast queries
                $table->string('suburb', 100)->nullable()->index();
                $table->string('state', 50)->nullable();
                $table->string('postcode', 20)->nullable();

                // Notes/dates
                $table->text('customer_note')->nullable();
                $table->date('preferred_delivery_date')->nullable()->index();

                // WC timestamps
                $table->timestamp('wc_date_created')->nullable();
                $table->timestamp('wc_date_modified')->nullable();

                // Local tracking
                $table->boolean('is_dismissed_from_refund_queue')->default(false);
                $table->timestamp('last_synced_at')->nullable();

                $table->timestamps();

                $table->index('wc_status');
                $table->index('payment_status');
            });
        }

        // ---- 5. wc_order_items ----
        if (!Schema::hasTable('wc_order_items')) {
            Schema::create('wc_order_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('wc_order_id')->constrained('wc_orders')->onDelete('cascade');

                // WC product/variation IDs are stored for reference only — no FK
                // to local products table since WC products have no local match
                $table->unsignedBigInteger('woocommerce_product_id')->nullable();
                $table->unsignedBigInteger('woocommerce_variation_id')->nullable();

                $table->string('sku', 100)->nullable();
                $table->string('name', 255);
                $table->decimal('quantity', 10, 3)->default(1);
                $table->decimal('price', 10, 2)->default(0);
                $table->decimal('subtotal', 10, 2)->default(0);
                $table->decimal('total', 10, 2)->default(0);
                $table->decimal('tax', 10, 2)->default(0);
                $table->json('meta')->nullable();
                $table->timestamps();
            });
        }

        // ---- 6. wc_order_notes ----
        if (!Schema::hasTable('wc_order_notes')) {
            Schema::create('wc_order_notes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('wc_order_id')->constrained('wc_orders')->onDelete('cascade');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->text('body');
                $table->timestamps();
                $table->index('wc_order_id');
            });
        }

        // ---- 7. wc_order_audit_logs ----
        if (!Schema::hasTable('wc_order_audit_logs')) {
            Schema::create('wc_order_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('wc_order_id')->constrained('wc_orders')->onDelete('cascade');
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('action', 100);
                $table->text('description')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->index(['wc_order_id', 'created_at']);
            });
        }

        // ---- 8. wc_order_tag pivot (uses shared order_tags definitions) ----
        if (!Schema::hasTable('wc_order_tag')) {
            Schema::create('wc_order_tag', function (Blueprint $table) {
                $table->foreignId('wc_order_id')->constrained('wc_orders')->onDelete('cascade');
                $table->foreignId('tag_id')->constrained('order_tags')->onDelete('cascade');
                $table->timestamps();
                $table->primary(['wc_order_id', 'tag_id']);
            });
        }

        // ---- 9. wc_order_refunds ----
        if (!Schema::hasTable('wc_order_refunds')) {
            Schema::create('wc_order_refunds', function (Blueprint $table) {
                $table->id();
                $table->foreignId('wc_order_id')->constrained('wc_orders')->onDelete('cascade');
                $table->unsignedBigInteger('woocommerce_refund_id')->nullable()->index();
                $table->decimal('amount', 10, 2);
                $table->text('reason')->nullable();
                $table->unsignedBigInteger('refunded_by')->nullable(); // staff user id
                $table->timestamp('refunded_at')->nullable();
                $table->json('api_response')->nullable();
                $table->string('status', 30)->default('completed'); // completed|failed
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('wc_order_refunds');
        Schema::dropIfExists('wc_order_tag');
        Schema::dropIfExists('wc_order_audit_logs');
        Schema::dropIfExists('wc_order_notes');
        Schema::dropIfExists('wc_order_items');
        Schema::dropIfExists('wc_orders');
        Schema::dropIfExists('wc_customers');

        // Re-add the legacy columns if rolling back
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'source')) {
                $table->string('source', 20)->default('native')->after('id');
            }
            if (!Schema::hasColumn('orders', 'woocommerce_order_id')) {
                $table->unsignedBigInteger('woocommerce_order_id')->nullable()->after('source');
            }
        });
    }
};
