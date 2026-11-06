<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->integer('position')->default(0)->after('status');
            $table->index('position');
        });

        // Set initial positions based on current ID order
        $categories = DB::table('categories')->orderBy('id')->get();
        foreach ($categories as $index => $category) {
            DB::table('categories')
                ->where('id', $category->id)
                ->update(['position' => $index + 1]);
        }
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('position');
        });
    }
};