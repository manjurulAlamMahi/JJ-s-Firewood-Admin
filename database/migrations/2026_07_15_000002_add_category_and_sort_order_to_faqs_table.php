<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('faqs', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('id')->constrained('faq_categories')->nullOnDelete();
            $table->integer('sort_order')->default(0)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('faqs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
            $table->dropColumn('sort_order');
        });
    }
};
