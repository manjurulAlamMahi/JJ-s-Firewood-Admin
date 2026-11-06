<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('woocommerce_settings')) {
            Schema::create('woocommerce_settings', function (Blueprint $table) {
                $table->id();
                $table->string('site_url', 255)->nullable();
                $table->string('consumer_key', 255)->nullable();
                $table->text('consumer_secret')->nullable();
                $table->text('webhook_secret')->nullable();
                $table->boolean('sync_enabled')->default(false);
                $table->timestamp('last_sync_at')->nullable();
                $table->json('last_sync_result')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('woocommerce_settings');
    }
};
