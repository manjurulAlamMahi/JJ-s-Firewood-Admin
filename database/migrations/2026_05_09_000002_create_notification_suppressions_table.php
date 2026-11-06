<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Notification suppressions — global opt-outs and bounce list.
 *
 * Examples:
 *   - User replied STOP to an SMS → record an 'sms' suppression for that phone
 *   - Email hard-bounced → record an 'email' suppression
 *   - User clicked unsubscribe in marketing email → record 'email_marketing' suppression
 *
 * The audience resolver checks this table before sending and excludes matches.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('notification_suppressions')) {
            Schema::create('notification_suppressions', function (Blueprint $table) {
                $table->id();
                $table->string('channel', 30);            // sms | email | push | sms_marketing | email_marketing | push_marketing
                $table->string('value', 255);             // phone (E.164) or email address or device token
                $table->string('reason', 100)->nullable(); // 'user_unsubscribed' | 'hard_bounce' | 'complaint' | 'manual' | etc
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('user_id')->nullable();      // if we know which user
                $table->unsignedBigInteger('added_by')->nullable();     // staff member who added (for manual ones)
                $table->timestamp('suppressed_at')->useCurrent();
                $table->timestamps();

                $table->unique(['channel', 'value']);
                $table->index('user_id');
                $table->index('channel');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_suppressions');
    }
};
