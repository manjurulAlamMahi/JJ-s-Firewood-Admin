<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * setmore_webhook_events — review queue for Setmore-via-Zapier integration.
 *
 * Every webhook hit from Zapier creates a row here. Admin reviews each row
 * in the Setmore Reviews panel and clicks Apply (fills order's
 * preferred_delivery_date + fires OrderDeliveryDateUpdated) or Reject
 * (logs & ignores).
 *
 * Columns:
 *   - customer_email / customer_phone / customer_name — what Setmore sent
 *   - appointment_at        — the scheduled delivery date/time Zapier sent
 *   - appointment_id        — Setmore's appointment ID, audit only
 *   - raw_payload (JSON)    — the entire Zapier payload, in case we need it
 *   - matched_order_id      — primary suggested order (only when exactly one match)
 *   - candidate_order_ids   — JSON array of all candidate order IDs (for ambiguous cases)
 *   - candidate_count       — 0 / 1 / 2+, used to filter the review panel
 *   - status                — pending | applied | rejected | no_match
 *   - applied_order_id      — which order admin actually applied to (may differ from matched if admin picks from ambiguous)
 *   - reviewed_by           — admin user id
 *   - reviewed_at           — timestamp of Apply/Reject
 *   - notes                 — optional admin note
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('setmore_webhook_events')) return;

        Schema::create('setmore_webhook_events', function (Blueprint $table) {
            $table->id();

            $table->string('customer_email', 191)->nullable()->index();
            $table->string('customer_phone', 32)->nullable()->index();
            $table->string('customer_name', 191)->nullable();

            $table->dateTime('appointment_at')->nullable();
            $table->string('appointment_id', 100)->nullable()->index();
            $table->json('raw_payload')->nullable();

            $table->unsignedBigInteger('matched_order_id')->nullable()->index();
            $table->json('candidate_order_ids')->nullable();
            $table->unsignedInteger('candidate_count')->default(0);

            // pending | applied | rejected | no_match
            $table->string('status', 16)->default('pending')->index();

            $table->unsignedBigInteger('applied_order_id')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->string('notes', 500)->nullable();

            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setmore_webhook_events');
    }
};
