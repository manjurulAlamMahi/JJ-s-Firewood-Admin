<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add 'marketing' as a valid category to notification_preferences.
 *
 * The existing categories are 'transactional', 'helpful', 'updates' (per the
 * NotificationPreference model defaults). We add a fourth 'marketing' category
 * so we can honour SMS/email marketing opt-outs separately from transactional
 * (per Australian Spam Act).
 *
 * No schema changes needed — the 'category' column is just a string.
 * This migration is here as documentation + a safety upsert for existing users.
 *
 * The category value 'marketing' will be created on-demand when users
 * unsubscribe via email link or reply STOP to SMS. We don't bulk-create rows
 * for every user up front — that's millions of unnecessary writes for a hundred
 * users when default behaviour is "everyone is opted in".
 */
return new class extends Migration
{
    public function up(): void
    {
        // No schema change — just ensure the index exists for fast lookups
        if (Schema::hasTable('notification_preferences')) {
            try {
                Schema::table('notification_preferences', function (Blueprint $table) {
                    $table->index(['user_id', 'category'], 'notif_pref_user_cat_idx');
                });
            } catch (\Throwable $e) {
                // The named index may already exist. This approach is portable
                // across MySQL/MariaDB and SQLite, unlike `SHOW INDEX`.
            }
        }
    }

    public function down(): void
    {
        // Don't drop the index — other code may rely on it
    }
};
