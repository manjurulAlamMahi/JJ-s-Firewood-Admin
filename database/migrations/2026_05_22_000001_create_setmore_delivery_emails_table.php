<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Log of server-sent delivery emails for non-app (phone / WooCommerce) Setmore
 * bookings. Two jobs:
 *   1. Activity log for the admin "Delivery Emails" view — what was sent, to
 *      whom, which type, success/failure, when.
 *   2. Source of truth for booked-vs-rescheduled detection: if we've already
 *      logged a 'booked' email for an appointment_id, the next date change for
 *      that appointment is a 'rescheduled'.
 *
 * Additive table — touches nothing existing.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('setmore_delivery_emails')) {
            return;
        }

        Schema::create('setmore_delivery_emails', function (Blueprint $table) {
            $table->id();

            // Link back to the webhook event that triggered this (nullable so a
            // manual/test send doesn't require one).
            $table->unsignedBigInteger('setmore_webhook_event_id')->nullable()->index();

            // The Setmore appointment this relates to (used for reschedule detection).
            $table->string('appointment_id')->nullable()->index();

            // booked | rescheduled | cancelled
            $table->string('type', 20)->index();

            // app | phone | woocommerce | unknown — provenance of the booking.
            $table->string('channel', 20)->default('unknown');

            // Who it was sent to (the actual recipient — in test mode this is the
            // test recipient, and real_recipient holds who it WOULD have gone to).
            $table->string('recipient')->nullable();
            $table->string('real_recipient')->nullable();

            // Customer-facing details captured at send time (for the log view).
            $table->string('customer_name')->nullable();
            $table->date('appointment_date')->nullable();

            // sent | failed | skipped | test
            $table->string('status', 20)->default('sent')->index();

            // Which mailer was used (brevo | gmail) and any error message.
            $table->string('mailer', 20)->nullable();
            $table->text('error')->nullable();

            // Was this a test-mode send (redirected away from the real customer)?
            $table->boolean('was_test')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setmore_delivery_emails');
    }
};
