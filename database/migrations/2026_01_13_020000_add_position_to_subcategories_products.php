<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Add position to sub_categories
        Schema::table('sub_categories', function (Blueprint $table) {
            $table->integer('position')->default(0)->after('status');
            $table->index('position');
        });

        // Set initial positions for subcategories based on current ID order
        $subcategories = DB::table('sub_categories')->orderBy('id')->get();
        foreach ($subcategories as $index => $subcategory) {
            DB::table('sub_categories')
                ->where('id', $subcategory->id)
                ->update(['position' => $index + 1]);
        }

        // Add position to products
        Schema::table('products', function (Blueprint $table) {
            $table->integer('position')->default(0)->after('status');
            $table->index('position');
        });

        // Set initial positions for products based on current ID order
        $products = DB::table('products')->orderBy('id')->get();
        foreach ($products as $index => $product) {
            DB::table('products')
                ->where('id', $product->id)
                ->update(['position' => $index + 1]);
        }
    }

    public function down(): void
    {
        Schema::table('sub_categories', function (Blueprint $table) {
            $table->dropColumn('position');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('position');
        });
    }
};