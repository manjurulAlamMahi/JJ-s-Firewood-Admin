<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Widen the enum first so existing 'inactive' rows can be converted
        // before that value is removed.
        Schema::table('schedules', function (Blueprint $table) {
            $table->enum('status', ['active', 'inactive', 'paused', 'cancelled'])->default('active')->change();
        });

        DB::table('schedules')->where('status', 'inactive')->update(['status' => 'paused']);

        Schema::table('schedules', function (Blueprint $table) {
            $table->enum('status', ['active', 'paused', 'cancelled'])->default('active')->change();
        });

        Schema::table('schedules', function (Blueprint $table) {
            $table->enum('frequency', ['every_week', 'every_2_weeks', 'every_month', 'custom'])
                ->default('every_2_weeks')
                ->after('repeat');
            $table->unsignedInteger('custom_days')->nullable()->after('frequency');
            $table->date('next_delivery_date')->nullable()->after('preferred_date');
            $table->string('preferred_time')->default('Anytime (6:00am - 5:00pm)')->after('next_delivery_date');
            $table->string('cancel_reason')->nullable()->after('status');
            $table->timestamp('cancelled_at')->nullable()->after('cancel_reason');
            $table->foreignId('shipping_address_id')->nullable()->constrained('shipping_addresses')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('products')->nullOnDelete();
            $table->decimal('load_size', 8, 2)->default(1.00);
            $table->decimal('unit_price', 10, 2)->default(0.00);
        });
    }

    public function down(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shipping_address_id');
            $table->dropConstrainedForeignId('product_id');
            $table->dropColumn([
                'frequency',
                'custom_days',
                'next_delivery_date',
                'preferred_time',
                'cancel_reason',
                'cancelled_at',
                'load_size',
                'unit_price',
            ]);
        });

        Schema::table('schedules', function (Blueprint $table) {
            $table->enum('status', ['active', 'inactive', 'paused', 'cancelled'])->default('active')->change();
        });

        DB::table('schedules')->whereIn('status', ['paused', 'cancelled'])->update(['status' => 'inactive']);

        Schema::table('schedules', function (Blueprint $table) {
            $table->enum('status', ['active', 'inactive'])->default('active')->change();
        });
    }
};
