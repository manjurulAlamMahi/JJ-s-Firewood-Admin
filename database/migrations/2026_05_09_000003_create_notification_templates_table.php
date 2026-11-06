<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Reusable templates for the notification centre.
 *
 * Each template is per-channel (push/sms/email/marketing_email) so a "Christmas
 * Promo" template stores 4 variations — one per channel — sharing the same name.
 *
 * Variables like {{first_name}} get substituted at send time.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('notification_templates')) {
            Schema::create('notification_templates', function (Blueprint $table) {
                $table->id();
                $table->string('name');                          // "Christmas Promo 2026 — Push"
                $table->string('channel', 30);                   // push | sms | email | email_marketing
                $table->string('title')->nullable();             // push title or email subject
                $table->text('body');                            // push body or SMS or email body
                $table->json('payload')->nullable();             // deep links, extra metadata for push
                $table->unsignedBigInteger('brevo_template_id')->nullable(); // optional Brevo template ID
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index('channel');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};
