<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates four new tables for Order Centre:
     *   - order_notes        : staff-only notes per order
     *   - order_audit_logs   : auto-logged history (status, payment, refund, etc.)
     *   - order_tags         : reusable tags / labels (VIP, Fragile, etc.)
     *   - order_tag (pivot)  : attaches tags to orders
     *
     * None of these touch existing tables. Existing functionality unaffected.
     */
    public function up(): void
    {
        // -----------------------------
        // order_notes
        // -----------------------------
        if (!Schema::hasTable('order_notes')) {
            Schema::create('order_notes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->text('body');
                $table->timestamps();

                $table->index('order_id');
            });
        }

        // -----------------------------
        // order_audit_logs (immutable: no updated_at)
        // -----------------------------
        if (!Schema::hasTable('order_audit_logs')) {
            Schema::create('order_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action', 64)->index();      // e.g. 'status_changed', 'payment_marked_paid'
                $table->text('description')->nullable();
                $table->json('metadata')->nullable();        // { old: ..., new: ..., bulk_batch_id: ... }
                $table->timestamp('created_at')->useCurrent();

                $table->index(['order_id', 'created_at']);
            });
        }

        // -----------------------------
        // order_tags
        // -----------------------------
        if (!Schema::hasTable('order_tags')) {
            Schema::create('order_tags', function (Blueprint $table) {
                $table->id();
                $table->string('name', 64);
                $table->string('slug', 80)->unique();
                $table->string('colour', 16)->default('#6B7280');  // hex
                $table->timestamps();
            });

            // Seed sensible defaults
            DB::table('order_tags')->insert([
                ['name' => 'VIP',         'slug' => 'vip',         'colour' => '#FBBF24', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Fragile',     'slug' => 'fragile',     'colour' => '#EF4444', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Recurring',   'slug' => 'recurring',   'colour' => '#10B981', 'created_at' => now(), 'updated_at' => now()],
                ['name' => 'Phone Order', 'slug' => 'phone-order', 'colour' => '#8B5CF6', 'created_at' => now(), 'updated_at' => now()],
            ]);
        }

        // -----------------------------
        // order_tag (pivot)
        // -----------------------------
        if (!Schema::hasTable('order_tag')) {
            Schema::create('order_tag', function (Blueprint $table) {
                $table->foreignId('order_id')->constrained('orders')->cascadeOnDelete();
                $table->foreignId('tag_id')->constrained('order_tags')->cascadeOnDelete();
                $table->timestamp('created_at')->useCurrent();

                $table->primary(['order_id', 'tag_id']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('order_tag');
        Schema::dropIfExists('order_tags');
        Schema::dropIfExists('order_audit_logs');
        Schema::dropIfExists('order_notes');
    }
};
