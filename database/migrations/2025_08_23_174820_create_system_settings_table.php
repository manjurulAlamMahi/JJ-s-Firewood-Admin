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
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('system_title', 150)->nullable();
            $table->string('system_short_title', 100)->nullable();
            $table->string('system_logo', 255)->default('uploads/systems/logo/logo.png');
            $table->string('system_favicon', 255)->default('uploads/systems/favicon/favicon.png');
            $table->string('company_name', 100)->nullable();
            $table->string('company_address', 255)->nullable();
            $table->string('tagline', 255)->nullable();
            $table->string('phone', 15)->nullable();
            $table->string('email', 50)->nullable();
            $table->string('timezone', 50)->nullable();
            $table->string('language', 50)->nullable();
            $table->text('copyright_text')->nullable();
            $table->string('admin_title', 150)->nullable();
            $table->string('short_title', 100)->nullable();
            $table->string('admin_logo', 255)->default('uploads/admins/logo/logo.png');
            $table->string('admin_favicon', 255)->default('uploads/admins/favicon/favicon.png');
            $table->text('admin_copyright_text')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};
