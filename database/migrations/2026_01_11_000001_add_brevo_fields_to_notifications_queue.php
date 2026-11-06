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
        Schema::table('notifications_queue', function (Blueprint $table) {
            // Add template_id for Brevo email templates
            if (!Schema::hasColumn('notifications_queue', 'template_id')) {
                $table->unsignedBigInteger('template_id')->nullable()->after('message');
            }
            
            // Add recipient_list for CSV uploads (JSON array of phone numbers/emails)
            if (!Schema::hasColumn('notifications_queue', 'recipient_list')) {
                $table->text('recipient_list')->nullable()->after('audience');
            }

            // Add brevo_campaign_id for tracking campaign API sends
            if (!Schema::hasColumn('notifications_queue', 'brevo_campaign_id')) {
                $table->string('brevo_campaign_id', 100)->nullable()->after('template_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications_queue', function (Blueprint $table) {
            $table->dropColumn(['template_id', 'recipient_list', 'brevo_campaign_id']);
        });
    }
};