<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('customer')->after('is_guest');
            $table->index('role');
        });

        // Set existing users' roles based on is_admin
        DB::table('users')->where('is_admin', 1)->update(['role' => 'admin']);
        DB::table('users')->where('is_admin', 0)->update(['role' => 'customer']);
    }

    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
    }
};