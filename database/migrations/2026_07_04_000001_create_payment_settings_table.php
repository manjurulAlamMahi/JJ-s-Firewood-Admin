<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('payment_settings', function (Blueprint $table) {
            $table->id();
            $table->string('square_application_id')->nullable();
            $table->string('square_location_id')->nullable();
            $table->text('square_access_token')->nullable();
            $table->string('square_environment')->default('sandbox');
            $table->string('paypal_client_id')->nullable();
            $table->text('paypal_secret')->nullable();
            $table->string('paypal_environment')->default('sandbox');
            $table->text('webhook_signature_key')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('payment_settings'); }
};
