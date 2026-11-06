<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Create zip_code_groups table
        Schema::create('zip_code_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('status')->default(1);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        // 2. Add group_id to zip_codes table
        Schema::table('zip_codes', function (Blueprint $table) {
            $table->unsignedBigInteger('group_id')->nullable()->after('id');
            $table->foreign('group_id')->references('id')->on('zip_code_groups')->onDelete('cascade');
        });

        // 3. Migrate existing zip codes into a default group
        $existingCount = DB::table('zip_codes')->count();
        if ($existingCount > 0) {
            $groupId = DB::table('zip_code_groups')->insertGetId([
                'name' => 'Default',
                'status' => 1,
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('zip_codes')->whereNull('group_id')->update(['group_id' => $groupId]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('zip_codes', function (Blueprint $table) {
            $table->dropForeign(['group_id']);
            $table->dropColumn('group_id');
        });

        Schema::dropIfExists('zip_code_groups');
    }
};