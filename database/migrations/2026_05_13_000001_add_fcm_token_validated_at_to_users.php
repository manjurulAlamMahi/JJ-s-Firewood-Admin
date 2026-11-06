<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Track when each user's FCM token was last verified to still be valid.
 *
 * This is what powers the "Reachable / Cooling / Unreachable" status badge
 * in the customer list. Updated by:
 *   - The scheduled tokens:validate command (weekly silent ping)
 *   - The manual "Test Reachability" admin button
 *   - Whenever a real notification successfully delivers via FcmChannel
 */
return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'fcm_token_validated_at')) {
                $table->timestamp('fcm_token_validated_at')->nullable()->after('fcm_token');
                $table->index('fcm_token_validated_at', 'users_fcm_token_validated_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'fcm_token_validated_at')) {
                $table->dropIndex('users_fcm_token_validated_idx');
                $table->dropColumn('fcm_token_validated_at');
            }
        });
    }
};
