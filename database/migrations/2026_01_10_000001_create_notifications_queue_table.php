<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('notifications_queue', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Type/channel (SMS later)
            $table->string('channel', 20); // 'push' | 'email' | later 'sms'

            // Audience (for now: all users only)
            $table->string('audience', 30)->default('all_users'); // extensible later

            // Content
            $table->string('title')->nullable();     // push title or email subject
            $table->text('message');                 // push body or email body
            $table->json('payload')->nullable();     // deep links, extra metadata

            // Scheduling
            $table->string('schedule_type', 20)->default('one_time'); // one_time | recurring | event_driven
            $table->timestamp('scheduled_at')->nullable();            // for one-time
            $table->string('timezone', 40)->default('Australia/Perth');

            // Recurring (simple RRULE-like)
            $table->string('rrule')->nullable(); // e.g. "FREQ=WEEKLY;BYDAY=MO;BYHOUR=9;BYMINUTE=0"
            $table->timestamp('next_run_at')->nullable();

            // Event-driven
            $table->string('event_key')->nullable(); // e.g. "delivery_tomorrow_6pm"
            $table->json('event_params')->nullable(); // e.g. {"offset_hours":18}

            // Control & status
            $table->string('status', 20)->default('draft'); // draft|scheduled|running|sent|cancelled|failed|paused
            $table->boolean('test_mode')->default(true);    // your requirement: safe by default
            $table->unsignedBigInteger('created_by')->nullable();

            // Totals (quick overview)
            $table->unsignedInteger('sent_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);

            $table->timestamp('last_ran_at')->nullable();
            $table->text('last_error')->nullable();

            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
            $table->index(['next_run_at']);
            $table->index(['event_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications_queue');
    }
};
