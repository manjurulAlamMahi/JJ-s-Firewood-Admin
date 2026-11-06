<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('notification_runs')) {
            return;
        }

        Schema::create('notification_runs', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('notification_id')->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();

            $table->string('channel', 20); // push|email|sms|marketing
            $table->string('status', 20);  // sent|failed|skipped
            $table->string('provider', 50)->nullable(); // brevo|firebase etc
            $table->string('recipient', 255)->nullable(); // email or phone
            $table->text('error')->nullable();

            $table->timestamps();

            // If you want FK later, you can add it safely once stable:
            // $table->foreign('notification_id')->references('id')->on('notifications_queue')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_runs');
    }
};
