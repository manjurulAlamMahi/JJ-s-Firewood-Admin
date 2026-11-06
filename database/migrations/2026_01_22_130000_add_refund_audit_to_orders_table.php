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
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'refund_processed_at')) {
                $table->timestamp('refund_processed_at')->nullable()->after('payment_status');
            }
            if (!Schema::hasColumn('orders', 'refund_processed_by')) {
                $table->unsignedBigInteger('refund_processed_by')->nullable()->after('refund_processed_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (Schema::hasColumn('orders', 'refund_processed_at')) {
                $table->dropColumn('refund_processed_at');
            }
            if (Schema::hasColumn('orders', 'refund_processed_by')) {
                $table->dropColumn('refund_processed_by');
            }
        });
    }
};
