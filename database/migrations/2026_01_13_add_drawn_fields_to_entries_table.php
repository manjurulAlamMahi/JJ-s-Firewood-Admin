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
        Schema::table('entries', function (Blueprint $table) {
            // Check if column exists before adding
            if (!Schema::hasColumn('entries', 'drawn')) {
                $table->boolean('drawn')->default(false)->after('amount_paid');
            }
            if (!Schema::hasColumn('entries', 'drawn_at')) {
                $table->timestamp('drawn_at')->nullable()->after('drawn');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('entries', function (Blueprint $table) {
            $table->dropColumn(['drawn', 'drawn_at']);
        });
    }
};