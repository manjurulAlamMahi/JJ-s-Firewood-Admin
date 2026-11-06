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
        Schema::table('tips_and_guides', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
            $table->string('category')->nullable()->after('id');
        });

        Schema::table('faqs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
            $table->string('category')->nullable()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('tips_and_guides', function (Blueprint $table) {
            $table->dropColumn('category');
            $table->foreignId('category_id')->nullable()->after('id')->constrained('tip_categories')->nullOnDelete();
        });

        Schema::table('faqs', function (Blueprint $table) {
            $table->dropColumn('category');
            $table->foreignId('category_id')->nullable()->after('id')->constrained('faq_categories')->nullOnDelete();
        });
    }
};
