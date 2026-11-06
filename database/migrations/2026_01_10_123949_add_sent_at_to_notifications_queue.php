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
    Schema::table('notifications_queue', function (\Illuminate\Database\Schema\Blueprint $table) {
        $table->dateTime('sent_at')->nullable()->after('scheduled_at');
    });
}

public function down(): void
{
Schema::table('notifications_queue', function (\Illuminate\Database\Schema\Blueprint $table) {
    $table->dropColumn('sent_at');
});

}

};
