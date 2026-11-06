<?php

namespace App\Services;

use App\Models\Order;
use App\Models\SetmoreWebhookEvent;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Setmore → admin order matching logic.
 *
 * Public entry points:
 *   - ingestWebhook($payload)     — called by SetmoreWebhookController on POST
 *   - applyEvent($event, $admin)  — admin clicks Apply in the review panel
 *   - rejectEvent($event, $admin) — admin clicks Reject
 *
 * Matching strategy (per business decision: email-only-for-now-with-phone-fallback):
 *   1. Find user by lowercased email.
 *   2. If no user, fallback: find user by normalised phone (digits only).
 *   3. If a user is found, find their UNCONFIRMED orders awaiting scheduling:
 *        payment_status IN ('paid', 'pending') AND delivery_date_confirmed_at IS NULL
 *        AND order_status NOT IN (pending, delivered, cancelled).
 *      Customer-picked dates do NOT exclude an order — they're suggestions
 *      until admin confirms via Setmore or manual reschedule.
 *      Cash-on-delivery and bank-transfer orders ARE matched even though
 *      payment_status='pending' until the customer actually pays.
 *      No time window: an old unconfirmed order is more likely the right
 *      match than a recent one already confirmed.
 *   4. Record the candidate count + IDs on the event row.
 *   5. NEVER auto-apply. Status is always 'pending' (or 'no_match' if
 *      candidate_count = 0). Admin reviews in the panel.
 *
 * Apply behaviour:
 *   - Updates the chosen order's preferred_delivery_date
 *   - Sets scheduled_via='setmore', setmore_appointment_id=<appt id>
 *   - Fires OrderDeliveryDateUpdated event so existing notification flows run
 *   - Writes audit log on the event row (reviewed_by, reviewed_at, applied_order_id)
 *
 * Reject behaviour:
 *   - Marks event status='rejected', records reviewer + timestamp
 *   - Does NOT touch the order
 *   - Optional notes field for "why" (e.g. "wrong customer", "duplicate")
 *
 * Cancellation path (added 2026-05-17):
 *   When the payload indicates a cancellation (detectCancellation() returns
 *   true) AND we have an appointment_id, we auto-cancel the linked order:
 *     - Idempotent: re-firing the cancel webhook is a no-op
 *     - Safety: orders already delivered are NOT cancelled (flagged for review)
 *     - On success: order_status → 'cancelled', cancellation_requested_at set,
 *       OrderCancelled event fires (customer email), audit log written
 *     - Paid orders auto-appear in the Refund Queue (existing logic)
 */
class SetmoreMatchingService
{
    /**
     * Ingest a Zapier webhook payload and create a review event row.
     * Always returns the created event; never auto-applies.
     */
    public function ingestWebhook(array $payload): SetmoreWebhookEvent
    {
        // Normalise the incoming fields. Zapier might send slightly different
        // key names depending on how the user configures the trigger — accept
        // any of these common variants.
        $email = strtolower(trim(
            $payload['customer_email']
            ?? $payload['email']
            ?? ($payload['customer']['email'] ?? '')
        ));
        $phone = $this->normalisePhone(
            $payload['customer_phone']
            ?? $payload['phone']
            ?? ($payload['customer']['phone'] ?? '')
        );
        $name = trim(
            $payload['customer_name']
            ?? $payload['name']
            ?? ($payload['customer']['name'] ?? '')
        );

        $appointmentAt = $this->parseDate(
            $payload['appointment_at']
            ?? $payload['date']
            ?? $payload['scheduled_at']
            ?? $payload['start_time']
            ?? null
        );

        $appointmentId = $payload['appointment_id']
            ?? $payload['id']
            ?? null;

        // ============================================================
        // CANCELLATION DETECTION
        // ============================================================
        // Zapier "Appointment Cancelled" trigger fires for cancellations.
        // We accept several common field shapes:
        //   - kind: 'cancel' | 'cancelled' | 'cancellation'
        //   - event_type: 'cancelled' | 'cancellation' | 'appointment.cancelled'
        //   - status: 'cancelled' | 'cancel' | 'CANCELLED'
        //   - cancelled: true | "true" | "1"
        //
        // If detected AND we have an appointment_id, route to the cancel path.
        // Without appointment_id we can't reliably identify which order to
        // cancel, so we fall through to the normal flow which will record a
        // no_match event for admin review.
        // ============================================================
        $isCancellation = $this->detectCancellation($payload);
        if ($isCancellation && $appointmentId) {
            $cancelResult = $this->tryCancelByAppointmentId($appointmentId, $payload);
            if ($cancelResult) {
                return $cancelResult;
            }
            // No order has this appointment_id linked. Record explicitly as
            // a cancellation_no_match event with status='no_match' so it
            // appears in the Cancellations tab for admin visibility — even
            // when there's no matching customer in the admin DB at all
            // (e.g. phone-order customer that was deleted in Setmore).
            return $this->recordCancellationNoMatch($payload, $appointmentId, $appointmentAt, $email, $phone, $name);
        }

        // ============================================================
        // RESCHEDULE PATH
        // ============================================================
        // If we've seen this appointment_id before AND it was applied to
        // an order, treat this webhook as a reschedule. Update the
        // existing order's date directly. Bypass the candidate matching.
        //
        // Detection rule: the appointment_id matches a previous event
        // that was successfully applied OR matches an order that was
        // tagged with this setmore_appointment_id.
        // ============================================================
        if (!$isCancellation && $appointmentId && $appointmentAt) {
            $rescheduleResult = $this->tryRescheduleByAppointmentId(
                $appointmentId,
                $appointmentAt,
                $payload
            );
            if ($rescheduleResult) {
                return $rescheduleResult;
            }
        }

        // ============================================================
        // NEW APPOINTMENT PATH
        // ============================================================
        // Resolve candidates — but only from APP-PLACED orders. Phone
        // orders are entered directly into Setmore and never reach the
        // admin panel, so they should never match.
        //
        // Detection: the "Order Centre" tag is auto-applied to every
        // app order by OrderCompletionService. Phone orders won't have it.
        $user = $this->findUser($email, $phone);
        $candidates = $user ? $this->findUnscheduledOrdersForUser($user) : collect();

        $candidateIds = $candidates->pluck('id')->toArray();
        $count = $candidates->count();

        // Status decision:
        //   0 candidates AND user exists in admin → no_match (might be a missed app order)
        //   0 candidates AND no user found → ignored (likely a phone-only customer)
        //   1+ candidates → pending
        // 'ignored' events are hidden from main tabs but findable in All.
        if ($count >= 1) {
            $status = 'pending';
        } elseif ($user) {
            // Customer exists in admin but has no candidates. Could be:
            //   - all their orders are already confirmed (Setmore reschedule we missed)
            //   - they only have non-app orders (WC, etc.)
            // Either way: not actionable, mark ignored so it doesn't clutter no_match.
            $status = 'ignored';
        } else {
            // No user found anywhere — almost certainly a phone-order customer.
            $status = 'ignored';
        }

        $matchedOrderId = ($count === 1) ? $candidates->first()->id : null;

        $event = SetmoreWebhookEvent::create([
            'customer_email'      => $email ?: null,
            'customer_phone'      => $phone ?: null,
            'customer_name'       => $name ?: null,
            'appointment_at'      => $appointmentAt,
            'appointment_id'      => $appointmentId ? (string) $appointmentId : null,
            'raw_payload'         => $payload,
            'matched_order_id'    => $matchedOrderId,
            'candidate_order_ids' => $candidateIds,
            'candidate_count'     => $count,
            'status'              => $status,
        ]);

        Log::info('Setmore webhook ingested', [
            'event_id'        => $event->id,
            'email'           => $email,
            'phone'           => $phone,
            'candidate_count' => $count,
            'status'          => $status,
        ]);

        // AUTO-APPLY (configurable per system setting).
        // Conditions ALL required:
        //   - setmore_auto_apply system setting is true
        //   - candidate_count === 1 (unambiguous single match)
        //   - appointment_at was parseable (we have a date to apply)
        //   - event was created as 'pending'
        // 0 candidates → already saved as no_match.
        // 2+ candidates → admin must disambiguate, stays pending.
        if ($status === 'pending'
            && $count === 1
            && $appointmentAt
            && $this->isAutoApplyEnabled()) {
            try {
                // adminId = null signals system/auto-apply in the audit log
                $this->applyEvent($event, $matchedOrderId, null);
                $event->refresh();
                Log::info('Setmore webhook auto-applied', [
                    'event_id' => $event->id,
                    'order_id' => $matchedOrderId,
                ]);
            } catch (\Throwable $e) {
                // Don't let auto-apply failures break the webhook —
                // the event still exists in pending state for manual review.
                Log::warning('Setmore auto-apply failed; event left as pending', [
                    'event_id' => $event->id,
                    'error'    => $e->getMessage(),
                ]);
            }
        }

        // ── NON-APP delivery email (phone / WooCommerce) ──────────────────
        // Fire the server-side booked/rescheduled email ONLY for bookings that
        // did NOT match an app order. Those land as status 'ignored' (no app
        // candidates — either a phone-only customer or a WC-only customer).
        // App bookings are 'pending'/'applied' with a matched_order_id and keep
        // their own OrderDeliveryDateUpdatedMail flow, so this never doubles up.
        //
        // Entirely gated by the master switch inside DeliveryEmailService (OFF
        // by default), so this is a no-op until the admin enables it.
        if ($status === 'ignored' && $appointmentAt && ($email || $phone)) {
            $this->maybeSendDeliveryEmail('auto', $event, $payload, $appointmentAt);
        }

        return $event;
    }

    /**
     * Send a non-app delivery email via DeliveryEmailService, defensively.
     * Never throws — an email failure must not break webhook ingestion.
     *
     * @param string $type 'auto' | 'booked' | 'rescheduled' | 'cancelled'
     */
    protected function maybeSendDeliveryEmail(string $type, SetmoreWebhookEvent $event, array $payload, $appointmentAt = null): void
    {
        try {
            if (!class_exists(\App\Services\DeliveryEmailService::class)) {
                return;
            }

            $dateStr = null;
            if ($appointmentAt) {
                $dateStr = $appointmentAt instanceof \Carbon\Carbon
                    ? $appointmentAt->toDateString()
                    : (string) $appointmentAt;
            }

            $booking = [
                'appointment_id' => $event->appointment_id ?? ($payload['appointment_id'] ?? ''),
                'event_id'       => $event->id,
                'channel'        => 'phone', // non-app booking; phone or WC (no origin field in payload)
                'name'           => $event->customer_name ?? ($payload['customer_name'] ?? null),
                'email'          => $event->customer_email ?? ($payload['customer_email'] ?? null),
                'phone'          => $event->customer_phone ?? ($payload['customer_phone'] ?? null),
                'address'        => $payload['customer_address'] ?? null,
                'date'           => $dateStr,
            ];

            (new \App\Services\DeliveryEmailService())->send($type, $booking);
        } catch (\Throwable $e) {
            Log::warning('maybeSendDeliveryEmail failed', [
                'event_id' => $event->id ?? null,
                'type'     => $type,
                'error'    => $e->getMessage(),
            ]);
        }
    }

    /**
     * Try to handle this webhook as a reschedule.
     *
     * If the appointment_id matches a previously-applied event OR an order
     * with this setmore_appointment_id, treat it as a date change to that
     * specific order — bypass the candidate matching entirely.
     *
     * Returns the created SetmoreWebhookEvent on success, or null if this
     * doesn't look like a reschedule (caller falls through to new-appointment
     * logic).
     *
     * Reschedules ALWAYS auto-apply (regardless of the auto-apply setting)
     * because the appointment_id match is exact — there's no ambiguity to
     * resolve manually. The same appointment moving to a new date is just
     * a date change, not a matching decision.
     */
    protected function tryRescheduleByAppointmentId(
        string $appointmentId,
        Carbon $appointmentAt,
        array $payload
    ): ?SetmoreWebhookEvent {
        $appointmentId = (string) $appointmentId;

        // 1. Direct order lookup — orders.setmore_appointment_id was set
        //    when the original event was applied
        $targetOrderId = null;
        if (Schema::hasColumn('orders', 'setmore_appointment_id')) {
            $targetOrderId = Order::where('setmore_appointment_id', $appointmentId)
                ->whereNotIn('order_status', ['delivered', 'cancelled'])
                ->orderBy('id', 'desc')
                ->value('id');
        }

        // 2. Fallback: find via the events table. A previous event with
        //    this appointment_id may have been applied to an order.
        if (!$targetOrderId) {
            $prevEvent = SetmoreWebhookEvent::where('appointment_id', $appointmentId)
                ->where('status', 'applied')
                ->whereNotNull('matched_order_id')
                ->orderBy('id', 'desc')
                ->first();
            if ($prevEvent) {
                $targetOrderId = $prevEvent->matched_order_id;
                // Sanity check: order must still be active
                $stillActive = Order::where('id', $targetOrderId)
                    ->whereNotIn('order_status', ['delivered', 'cancelled'])
                    ->exists();
                if (!$stillActive) {
                    $targetOrderId = null;
                }
            }
        }

        if (!$targetOrderId) {
            // Not a reschedule — caller will treat this as a new appointment
            return null;
        }

        // DEDUPE CHECK: if the target order already has this exact delivery date,
        // there's nothing to do. Setmore fires the "Appointment Reschedule"
        // trigger for ANY edit (name, notes, phone, etc.), not just date changes.
        // Without this check, every notes-only edit would re-apply the same date
        // and fire OrderDeliveryDateUpdated, spamming the customer with
        // "your delivery date is now X" emails.
        $targetOrder = Order::find($targetOrderId);
        if ($targetOrder) {
            $currentDate = $targetOrder->preferred_delivery_date?->toDateString();
            $newDate = $appointmentAt->toDateString();

            if ($currentDate === $newDate) {
                // Still record the event so we have an audit trail of the
                // duplicate fire, but mark it as 'ignored' (not applied) so
                // it doesn't pollute History and doesn't trigger any emails.
                $email = strtolower(trim(
                    $payload['customer_email']
                    ?? $payload['email']
                    ?? ($payload['customer']['email'] ?? '')
                ));
                $phone = $this->normalisePhone(
                    $payload['customer_phone']
                    ?? $payload['phone']
                    ?? ($payload['customer']['phone'] ?? '')
                );
                $name = trim(
                    $payload['customer_name']
                    ?? $payload['name']
                    ?? ($payload['customer']['name'] ?? '')
                );

                $event = SetmoreWebhookEvent::create([
                    'customer_email'      => $email ?: null,
                    'customer_phone'      => $phone ?: null,
                    'customer_name'       => $name ?: null,
                    'appointment_at'      => $appointmentAt,
                    'appointment_id'      => $appointmentId,
                    'raw_payload'         => array_merge($payload, ['_kind' => 'reschedule_duplicate']),
                    'matched_order_id'    => $targetOrderId,
                    'candidate_order_ids' => [(int) $targetOrderId],
                    'candidate_count'     => 1,
                    'status'              => 'ignored',
                    'notes'               => 'Setmore appointment updated but delivery date unchanged — no action needed.',
                ]);

                Log::info('Setmore webhook: reschedule with unchanged date — skipped', [
                    'event_id'       => $event->id,
                    'appointment_id' => $appointmentId,
                    'order_id'       => $targetOrderId,
                    'date'           => $newDate,
                ]);

                return $event;
            }
        }

        // Extract email/phone/name from payload for the event record
        $email = strtolower(trim(
            $payload['customer_email']
            ?? $payload['email']
            ?? ($payload['customer']['email'] ?? '')
        ));
        $phone = $this->normalisePhone(
            $payload['customer_phone']
            ?? $payload['phone']
            ?? ($payload['customer']['phone'] ?? '')
        );
        $name = trim(
            $payload['customer_name']
            ?? $payload['name']
            ?? ($payload['customer']['name'] ?? '')
        );

        // Create the event record FIRST so we can apply against it
        // (applyEvent expects an existing pending event).
        $event = SetmoreWebhookEvent::create([
            'customer_email'      => $email ?: null,
            'customer_phone'      => $phone ?: null,
            'customer_name'       => $name ?: null,
            'appointment_at'      => $appointmentAt,
            'appointment_id'      => $appointmentId,
            'raw_payload'         => array_merge($payload, ['_kind' => 'reschedule']),
            'matched_order_id'    => $targetOrderId,
            'candidate_order_ids' => [(int) $targetOrderId],
            'candidate_count'     => 1,
            'status'              => 'pending',
        ]);

        Log::info('Setmore webhook: reschedule detected', [
            'event_id'       => $event->id,
            'appointment_id' => $appointmentId,
            'order_id'       => $targetOrderId,
        ]);

        // Auto-apply the date change. Bypass user toggle — reschedules are
        // always exact matches, no ambiguity. forceOverride=true because
        // the order is the single candidate.
        try {
            $this->applyEvent($event, $targetOrderId, null, true);
            $event->refresh();
            Log::info('Setmore reschedule auto-applied', [
                'event_id' => $event->id,
                'order_id' => $targetOrderId,
                'new_date' => $appointmentAt->toDateString(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Setmore reschedule auto-apply failed; left pending', [
                'event_id' => $event->id,
                'error'    => $e->getMessage(),
            ]);
        }

        return $event;
    }

    /**
     * Detect whether a Zapier payload represents a cancellation.
     *
     * We accept several common shapes because Zapier users configure their
     * Zaps differently — there's no single canonical Setmore field.
     * If any of these patterns match → cancellation.
     */
    protected function detectCancellation(array $payload): bool
    {
        // Explicit _kind marker (we set this ourselves on subsequent calls)
        $kind = strtolower((string) ($payload['_kind'] ?? ''));
        if (in_array($kind, ['cancel', 'cancelled', 'cancellation', 'cancellation_no_match'], true)) {
            return true;
        }

        // event_type field (Zapier's standard convention)
        $eventType = strtolower((string) ($payload['event_type'] ?? $payload['type'] ?? ''));
        if (str_contains($eventType, 'cancel')) {
            return true;
        }

        // Generic status field
        $status = strtolower((string) ($payload['status'] ?? $payload['appointment_status'] ?? ''));
        if (in_array($status, ['cancelled', 'canceled', 'cancel'], true)) {
            return true;
        }

        // Boolean cancelled flag (some Setmore Zap setups use this)
        $cancelledFlag = $payload['cancelled'] ?? $payload['is_cancelled'] ?? null;
        if ($cancelledFlag === true || $cancelledFlag === 'true' || $cancelledFlag === 1 || $cancelledFlag === '1') {
            return true;
        }

        return false;
    }

    /**
     * Cancel an order that's linked to this Setmore appointment_id.
     *
     * Strategy:
     *   1. Find the order linked to this appointment_id (orders.setmore_appointment_id)
     *   2. If not found → return null (caller will fall through to no_match path)
     *   3. If order is already cancelled → idempotent noop (record ignored event)
     *   4. If order is delivered → DON'T cancel (record event for admin review)
     *   5. Otherwise → cancel the order, fire OrderCancelled event (email), audit log
     *
     * Returns the created SetmoreWebhookEvent on success, or null if no matching
     * order was found (caller continues to the normal no_match path).
     */
    protected function tryCancelByAppointmentId(string $appointmentId, array $payload): ?SetmoreWebhookEvent
    {
        // Find the linked order. We use the orders.setmore_appointment_id column
        // which is set when an appointment is applied (initial booking or
        // reschedule). If the customer cancelled an appointment that was never
        // applied to an order, there'll be no match — and that's fine.
        $order = null;
        if (Schema::hasColumn('orders', 'setmore_appointment_id')) {
            $order = Order::where('setmore_appointment_id', $appointmentId)->first();
        }

        if (!$order) {
            // Caller will fall through and record a no_match event
            return null;
        }

        // Extract identifying fields from the payload for the event record
        $email = strtolower(trim(
            $payload['customer_email']
            ?? $payload['email']
            ?? ($payload['customer']['email'] ?? '')
        ));
        $phone = $this->normalisePhone(
            $payload['customer_phone']
            ?? $payload['phone']
            ?? ($payload['customer']['phone'] ?? '')
        );
        $name = trim(
            $payload['customer_name']
            ?? $payload['name']
            ?? ($payload['customer']['name'] ?? '')
        );
        $appointmentAt = $this->parseDate(
            $payload['appointment_at']
            ?? $payload['date']
            ?? $payload['scheduled_at']
            ?? $payload['start_time']
            ?? null
        );

        // ============================================================
        // IDEMPOTENCY: order is already cancelled — record but do nothing.
        // Setmore can re-fire the cancel webhook; we don't want to send a
        // second cancellation email or write duplicate audit entries.
        // ============================================================
        if ($order->order_status === 'cancelled') {
            $event = SetmoreWebhookEvent::create([
                'customer_email'      => $email ?: null,
                'customer_phone'      => $phone ?: null,
                'customer_name'       => $name ?: null,
                'appointment_at'      => $appointmentAt,
                'appointment_id'      => $appointmentId,
                'raw_payload'         => array_merge($payload, ['_kind' => 'cancellation_duplicate']),
                'matched_order_id'    => $order->id,
                'candidate_order_ids' => [(int) $order->id],
                'candidate_count'     => 1,
                'status'              => 'ignored',
                'notes'               => 'Setmore cancellation received but order #' . $order->order_number . ' is already cancelled — no action taken.',
            ]);

            Log::info('Setmore webhook: cancellation but order already cancelled', [
                'event_id'       => $event->id,
                'appointment_id' => $appointmentId,
                'order_id'       => $order->id,
            ]);
            return $event;
        }

        // ============================================================
        // SAFETY CHECK: order is already delivered — DON'T auto-cancel.
        // This is a real risk: an admin might cancel an old Setmore
        // appointment AFTER the delivery was already completed. We
        // should not un-deliver a delivered order. Flag for review.
        // ============================================================
        if ($order->order_status === 'delivered') {
            $event = SetmoreWebhookEvent::create([
                'customer_email'      => $email ?: null,
                'customer_phone'      => $phone ?: null,
                'customer_name'       => $name ?: null,
                'appointment_at'      => $appointmentAt,
                'appointment_id'      => $appointmentId,
                'raw_payload'         => array_merge($payload, ['_kind' => 'cancellation_after_delivered']),
                'matched_order_id'    => $order->id,
                'candidate_order_ids' => [(int) $order->id],
                'candidate_count'     => 1,
                'status'              => 'pending',
                'notes'               => 'Setmore appointment cancelled, but order #' . $order->order_number . ' is already DELIVERED. Review needed — do not auto-cancel.',
            ]);

            Log::warning('Setmore webhook: cancellation for already-delivered order — flagged for review', [
                'event_id'       => $event->id,
                'appointment_id' => $appointmentId,
                'order_id'       => $order->id,
            ]);
            return $event;
        }

        // ============================================================
        // AUTO-CANCEL PATH (or pending review if auto-cancel disabled)
        // Order is in a cancellable state (processing/etc).
        //
        // If auto-cancel is enabled (default):
        //   - Perform the cancel + fire OrderCancelled + audit log,
        //     all in a transaction. Status starts pending and moves to
        //     applied on success.
        //
        // If auto-cancel is disabled:
        //   - Record the event with status='pending' so it shows in BOTH
        //     the Pending tab AND the Cancellations tab. Admin must
        //     manually action it via the Setmore Reviews panel.
        //   - The OrderCancelled event does NOT fire (no customer email).
        //   - Order remains in its current state until admin acts.
        // ============================================================
        $autoCancelEnabled = $this->isAutoCancelEnabled();

        $event = SetmoreWebhookEvent::create([
            'customer_email'      => $email ?: null,
            'customer_phone'      => $phone ?: null,
            'customer_name'       => $name ?: null,
            'appointment_at'      => $appointmentAt,
            'appointment_id'      => $appointmentId,
            'raw_payload'         => array_merge($payload, ['_kind' => 'cancellation']),
            'matched_order_id'    => $order->id,
            'candidate_order_ids' => [(int) $order->id],
            'candidate_count'     => 1,
            'status'              => 'pending',
            'notes'               => $autoCancelEnabled
                ? null
                : 'Auto-cancel is OFF — awaiting admin confirmation. Order remains in current state until actioned.',
        ]);

        // If auto-cancel is disabled, stop here. The event sits in 'pending'
        // and admin handles it via the existing Apply/Reject flow.
        if (!$autoCancelEnabled) {
            Log::info('Setmore webhook: cancellation queued (auto-cancel disabled)', [
                'event_id'       => $event->id,
                'appointment_id' => $appointmentId,
                'order_id'       => $order->id,
            ]);
            return $event;
        }

        try {
            DB::transaction(function () use ($order, $event) {
                // Capture current state for the audit log
                $oldStatus = $order->order_status;

                $update = ['order_status' => 'cancelled'];
                // Set cancellation_requested_at if the column exists — used by
                // the Refund Queue to surface this order to staff.
                if (Schema::hasColumn('orders', 'cancellation_requested_at')) {
                    $update['cancellation_requested_at'] = now();
                }
                $order->update($update);

                // Mark the event as applied. Once a cancel succeeds, the
                // event status moves from 'pending' → 'applied' so it shows
                // in History and doesn't get re-processed.
                $event->update([
                    'status'           => 'applied',
                    'applied_order_id' => $order->id,
                    'reviewed_at'      => now(),
                ]);

                // Audit log entry — best-effort, don't fail the cancel if this fails
                try {
                    if (class_exists('\\App\\Models\\OrderAuditLog')) {
                        \App\Models\OrderAuditLog::create([
                            'order_id'    => $order->id,
                            'admin_id'    => null,  // system-triggered, no admin
                            'action'      => 'cancelled_via_setmore',
                            'before_value'=> $oldStatus,
                            'after_value' => 'cancelled',
                            'note'        => 'Auto-cancelled by Setmore webhook (appointment ' . ($event->appointment_id ?? '') . ', event #' . $event->id . ')',
                        ]);
                    }
                } catch (\Throwable $e) {
                    // non-critical
                }
            });

            // Fire OrderCancelled event AFTER the DB transaction succeeds.
            // This sends the customer email (via existing notification flow).
            // Wrapped separately so a notification failure doesn't roll back
            // the cancellation itself.
            if (class_exists('\\App\\Events\\OrderCancelled')) {
                try {
                    event(new \App\Events\OrderCancelled($order));
                } catch (\Throwable $e) {
                    Log::warning('Setmore cancel: OrderCancelled event fire failed', [
                        'event_id' => $event->id,
                        'order_id' => $order->id,
                        'err'      => $e->getMessage(),
                    ]);
                }
            }

            Log::info('Setmore webhook: order auto-cancelled', [
                'event_id'       => $event->id,
                'appointment_id' => $appointmentId,
                'order_id'       => $order->id,
                'order_number'   => $order->order_number,
            ]);
        } catch (\Throwable $e) {
            // Mark the event as no_match with details so admin can act.
            // We don't want the webhook to return an error — Zapier would
            // keep retrying — so we always return a valid event.
            $event->update([
                'status' => 'no_match',
                'notes'  => 'Auto-cancel failed: ' . $e->getMessage(),
            ]);
            Log::error('Setmore cancel: transaction failed', [
                'event_id' => $event->id,
                'order_id' => $order->id,
                'error'    => $e->getMessage(),
            ]);
        }

        return $event;
    }

    /**
     * Record a cancellation event when no matching order was found.
     *
     * Called when Setmore deletes an appointment that:
     *   - was never linked to an app order (e.g. phone-only customer), OR
     *   - was linked but the orders.setmore_appointment_id lookup failed
     *
     * We surface these in the Cancellations tab so admin can spot real misses
     * (e.g. an app order that should have been linked but wasn't).
     */
    protected function recordCancellationNoMatch(
        array $payload,
        string $appointmentId,
        ?\Illuminate\Support\Carbon $appointmentAt,
        string $email,
        string $phone,
        string $name
    ): SetmoreWebhookEvent {
        $event = SetmoreWebhookEvent::create([
            'customer_email'      => $email ?: null,
            'customer_phone'      => $phone ?: null,
            'customer_name'       => $name ?: null,
            'appointment_at'      => $appointmentAt,
            'appointment_id'      => $appointmentId,
            'raw_payload'         => array_merge($payload, ['_kind' => 'cancellation_no_match']),
            'matched_order_id'    => null,
            'candidate_order_ids' => [],
            'candidate_count'     => 0,
            'status'              => 'no_match',
            'notes'               => 'Setmore appointment cancelled but no matching app order found. May be a phone-order customer (expected) or a missed link.',
        ]);

        Log::info('Setmore webhook: cancellation with no matching order', [
            'event_id'       => $event->id,
            'appointment_id' => $appointmentId,
            'email'          => $email ?: null,
        ]);

        // Non-app cancellation (no matching app order) → send the server-side
        // cancelled email. App-order cancellations are handled by tryCancelBy-
        // AppointmentId (which fires OrderCancelled), so this never doubles up.
        // Gated by the master switch inside DeliveryEmailService (OFF default).
        if ($email || $phone) {
            $this->maybeSendDeliveryEmail('cancelled', $event, array_merge($payload, [
                'customer_email' => $email,
                'customer_phone' => $phone,
                'customer_name'  => $name,
            ]), $appointmentAt);
        }

        return $event;
    }


    /**
     * Check whether auto-apply is currently enabled.
     * Defaults to FALSE (everything goes to manual review) when:
     *   - the column doesn't exist (pre-migration deploys)
     *   - no system_settings row exists
     *   - column exists but value is null/false
     */
    public function isAutoApplyEnabled(): bool
    {
        try {
            if (!Schema::hasTable('system_settings')) return false;
            if (!Schema::hasColumn('system_settings', 'setmore_auto_apply')) return false;

            $value = \DB::table('system_settings')
                ->orderBy('id')
                ->value('setmore_auto_apply');
            return (bool) $value;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Check whether auto-cancel is currently enabled.
     *
     * Defaults to TRUE (auto-cancellation is the standard behaviour the
     * user asked for) when:
     *   - the column doesn't exist (pre-migration deploys) — safer to
     *     auto-cancel than to silently queue cancellations the admin
     *     might miss
     *   - no system_settings row exists
     *
     * Returns FALSE only when the column exists AND is explicitly set to
     * false by an admin who's toggled it off via the panel.
     */
    public function isAutoCancelEnabled(): bool
    {
        try {
            if (!Schema::hasTable('system_settings')) return true;
            if (!Schema::hasColumn('system_settings', 'setmore_auto_cancel')) return true;

            $value = \DB::table('system_settings')
                ->orderBy('id')
                ->value('setmore_auto_cancel');
            // Treat NULL as TRUE (column added before any row was updated)
            return $value === null ? true : (bool) $value;
        } catch (\Throwable $e) {
            // Defensive: any DB error → don't block cancellations, keep auto on
            return true;
        }
    }

    /**
     * Apply the Setmore date to the chosen order.
     *
     * If $orderId is provided, it must be one of the candidate_order_ids
     * (or admin can force-override by passing any valid order id with
     * $forceOverride=true). Defaults to the matched_order_id.
     */
    public function applyEvent(
        SetmoreWebhookEvent $event,
        ?int $orderId = null,
        ?int $adminId = null,
        bool $forceOverride = false
    ): array {
        if ($event->status !== 'pending') {
            return ['ok' => false, 'message' => 'Event is not pending (status: ' . $event->status . ').'];
        }

        // Detect if this is a cancellation event (queued because auto-cancel
        // was off or because the order was delivered). If so, route to the
        // cancellation apply path which cancels the order instead of setting
        // a delivery date. The cancel path doesn't need appointment_at.
        $payload = is_array($event->raw_payload) ? $event->raw_payload : [];
        $payloadKind = (string) ($payload['_kind'] ?? '');
        if (str_starts_with($payloadKind, 'cancellation')) {
            return $this->applyCancellationEvent($event, $adminId);
        }

        if (!$event->appointment_at) {
            return ['ok' => false, 'message' => 'Event has no appointment date.'];
        }

        $orderId = $orderId ?? $event->matched_order_id;
        if (!$orderId) {
            return ['ok' => false, 'message' => 'No order selected to apply to.'];
        }

        // Safety: applied order must be in the candidate list unless forced.
        $candidates = is_array($event->candidate_order_ids) ? $event->candidate_order_ids : [];
        if (!$forceOverride && !in_array((int) $orderId, array_map('intval', $candidates), true)) {
            return ['ok' => false, 'message' => 'Selected order is not in the candidate list.'];
        }

        $order = Order::find($orderId);
        if (!$order) {
            return ['ok' => false, 'message' => 'Order not found.'];
        }

        $newDate = $event->appointment_at->toDateString();
        $oldDate = $order->preferred_delivery_date?->toDateString();

        // Was this order's delivery date already confirmed BEFORE this apply?
        // If the column exists and was null, this is the FIRST confirmation —
        // which must notify the customer even when the new date equals their
        // originally-preferred date. If it was already set, only a genuine date
        // change is a reschedule worth notifying about.
        $hadConfirmedDate = Schema::hasColumn('orders', 'delivery_date_confirmed_at')
            ? !is_null($order->delivery_date_confirmed_at)
            : !is_null($order->preferred_delivery_date);
        $isFirstBooking = !$hadConfirmedDate;

        DB::transaction(function () use ($event, $order, $newDate, $oldDate, $adminId) {
            // Update the order
            $update = [
                'preferred_delivery_date' => $newDate,
            ];
            if (Schema::hasColumn('orders', 'scheduled_via')) {
                $update['scheduled_via'] = 'setmore';
            }
            if (Schema::hasColumn('orders', 'setmore_appointment_id')) {
                $update['setmore_appointment_id'] = $event->appointment_id;
            }
            // Mark the delivery date as admin-confirmed — drives Needs Action.
            // This is the single field that means "an admin has touched the date".
            if (Schema::hasColumn('orders', 'delivery_date_confirmed_at')) {
                $update['delivery_date_confirmed_at'] = now();
            }
            $order->update($update);

            // Auto-tag with "Setmore" — pure provenance label, says "this
            // order's date was set via the Setmore integration".
            try {
                if (class_exists('\App\Services\OrderTagService')) {
                    app(\App\Services\OrderTagService::class)->attachToOrder($order, 'setmore');
                }
            } catch (\Throwable $e) { /* non-critical */ }

            // Update the event
            $event->update([
                'status'           => 'applied',
                'applied_order_id' => $order->id,
                'reviewed_by'      => $adminId,
                'reviewed_at'      => now(),
            ]);
        });

        // Fire the customer notification (email + push) via the existing event.
        // We notify when:
        //   (a) this is the FIRST confirmation of a delivery date for the order
        //       (even if the new date equals the customer's preferred date), OR
        //   (b) the date genuinely CHANGED (a reschedule).
        // We stay silent only on a true no-op: an already-confirmed order being
        // re-applied with the same date (e.g. a duplicate Setmore re-send). This
        // restores the original behaviour where customers always get a booking
        // confirmation, while still preventing duplicate-resend spam.
        //
        // NOTE: $oldDate and $newDate are STRINGS (toDateString() results).
        // The $isFirstBooking flag tells the listener which wording to use
        // (scheduled vs rescheduled).
        $dateChanged = ($oldDate !== $newDate);
        $shouldNotify = $isFirstBooking || $dateChanged;

        if ($shouldNotify && class_exists('\App\Events\OrderDeliveryDateUpdated')) {
            try {
                event(new \App\Events\OrderDeliveryDateUpdated($order, $oldDate, $newDate, $isFirstBooking));
            } catch (\Throwable $e) {
                Log::warning('Setmore apply: OrderDeliveryDateUpdated event fire failed', [
                    'event_id' => $event->id,
                    'order_id' => $order->id,
                    'err'      => $e->getMessage(),
                ]);
            }
        } else {
            Log::info('Setmore apply: already-confirmed date unchanged, skipping customer notification', [
                'event_id' => $event->id,
                'order_id' => $order->id,
                'date'     => $newDate,
            ]);
        }

        // OrderAuditLog entry (best-effort — don't fail the whole flow if it errors)
        try {
            if (class_exists('\App\Models\OrderAuditLog')) {
                \App\Models\OrderAuditLog::create([
                    'order_id'    => $order->id,
                    'admin_id'    => $adminId,
                    'action'      => 'date_set_via_setmore',
                    'before_value'=> (string) $oldDate,
                    'after_value' => (string) $newDate,
                    'note'        => 'Setmore appointment ' . ($event->appointment_id ?? '') . ' (event #' . $event->id . ')',
                ]);
            }
        } catch (\Throwable $e) {
            // non-critical
        }

        Log::info('Setmore event applied', [
            'event_id' => $event->id,
            'order_id' => $order->id,
            'new_date' => $newDate,
            'admin_id' => $adminId,
        ]);

        return [
            'ok'         => true,
            'message'    => 'Delivery date applied to order #' . $order->order_number . '.',
            'order_id'   => $order->id,
            'new_date'   => $newDate,
        ];
    }

    /**
     * Apply a queued CANCELLATION event manually. Called from applyEvent()
     * when the event's _kind is a cancellation variant.
     *
     * Performs the same cancel work as the auto-cancel path in
     * tryCancelByAppointmentId(): cancel the order, fire OrderCancelled
     * event (customer email), write audit log, set cancellation_requested_at.
     */
    protected function applyCancellationEvent(SetmoreWebhookEvent $event, ?int $adminId): array
    {
        $orderId = $event->matched_order_id;
        if (!$orderId) {
            return ['ok' => false, 'message' => 'Cancellation event has no matched order.'];
        }

        $order = Order::find($orderId);
        if (!$order) {
            return ['ok' => false, 'message' => 'Order not found (id: ' . $orderId . ').'];
        }

        // Safety: if the order has somehow already been cancelled (e.g. admin
        // cancelled manually in the meantime), just mark the event as
        // applied — no double-cancel.
        if ($order->order_status === 'cancelled') {
            $event->update([
                'status'           => 'applied',
                'applied_order_id' => $order->id,
                'reviewed_by'      => $adminId,
                'reviewed_at'      => now(),
                'notes'            => trim(($event->notes ?? '') . ' Order was already cancelled when admin actioned event.'),
            ]);
            return [
                'ok'      => true,
                'message' => 'Order #' . $order->order_number . ' was already cancelled. Event marked applied.',
                'order_id'=> $order->id,
            ];
        }

        try {
            DB::transaction(function () use ($order, $event, $adminId) {
                $oldStatus = $order->order_status;

                $update = ['order_status' => 'cancelled'];
                if (Schema::hasColumn('orders', 'cancellation_requested_at')) {
                    $update['cancellation_requested_at'] = now();
                }
                $order->update($update);

                $event->update([
                    'status'           => 'applied',
                    'applied_order_id' => $order->id,
                    'reviewed_by'      => $adminId,
                    'reviewed_at'      => now(),
                ]);

                try {
                    if (class_exists('\\App\\Models\\OrderAuditLog')) {
                        \App\Models\OrderAuditLog::create([
                            'order_id'    => $order->id,
                            'admin_id'    => $adminId,
                            'action'      => 'cancelled_via_setmore_manual',
                            'before_value'=> $oldStatus,
                            'after_value' => 'cancelled',
                            'note'        => 'Manually applied Setmore cancellation (appointment ' . ($event->appointment_id ?? '') . ', event #' . $event->id . ')',
                        ]);
                    }
                } catch (\Throwable $e) {
                    // non-critical
                }
            });

            // Fire OrderCancelled event AFTER the transaction succeeds so the
            // customer gets the cancellation email.
            if (class_exists('\\App\\Events\\OrderCancelled')) {
                try {
                    event(new \App\Events\OrderCancelled($order));
                } catch (\Throwable $e) {
                    Log::warning('Setmore manual cancel: OrderCancelled event fire failed', [
                        'event_id' => $event->id,
                        'order_id' => $order->id,
                        'err'      => $e->getMessage(),
                    ]);
                }
            }

            Log::info('Setmore cancellation applied manually', [
                'event_id' => $event->id,
                'order_id' => $order->id,
                'admin_id' => $adminId,
            ]);

            return [
                'ok'      => true,
                'message' => 'Order #' . $order->order_number . ' cancelled.',
                'order_id'=> $order->id,
            ];
        } catch (\Throwable $e) {
            Log::error('Setmore manual cancel failed', [
                'event_id' => $event->id,
                'order_id' => $order->id,
                'err'      => $e->getMessage(),
            ]);
            return ['ok' => false, 'message' => 'Failed to cancel order: ' . $e->getMessage()];
        }
    }

    /**
     * Reject the event — admin determines it's a wrong/duplicate match.
     * No order side-effects.
     */
    public function rejectEvent(SetmoreWebhookEvent $event, ?int $adminId = null, ?string $notes = null): array
    {
        if ($event->status === 'applied') {
            return ['ok' => false, 'message' => 'Cannot reject an already-applied event.'];
        }

        $event->update([
            'status'      => 'rejected',
            'reviewed_by' => $adminId,
            'reviewed_at' => now(),
            'notes'       => $notes,
        ]);

        Log::info('Setmore event rejected', [
            'event_id' => $event->id,
            'admin_id' => $adminId,
            'notes'    => $notes,
        ]);

        return ['ok' => true, 'message' => 'Event rejected.'];
    }

    /**
     * Count pending review events for the sidebar badge.
     */
    public function pendingCount(): int
    {
        if (!Schema::hasTable('setmore_webhook_events')) return 0;
        return SetmoreWebhookEvent::pending()->count();
    }

    // -----------------------------------------------------------------
    // Internal helpers
    // -----------------------------------------------------------------

    /**
     * Find a user by email (primary) or phone (fallback).
     *
     * Matches against BOTH the users table and the shipping_addresses
     * table, because Apple-ID OAuth signups have a private-relay email
     * in users.email (and often empty users.phone), but the customer
     * enters their real email + phone at checkout in shipping_addresses.
     *
     * Order of attempts:
     *   1. users.email (lowercase) exact match
     *   2. shipping_addresses.email (lowercase) → resolve user_id
     *   3. users.phone last-9-digits LIKE match
     *   4. shipping_addresses.phone last-9-digits → resolve user_id
     *
     * Returns null if none match.
     */
    private function findUser(string $email, string $phone): ?User
    {
        // 1. Email on users table
        if ($email) {
            $u = User::whereRaw('LOWER(email) = ?', [$email])->first();
            if ($u) return $u;

            // 2. Email on shipping_addresses (Apple-ID fallback)
            if (Schema::hasTable('shipping_addresses')) {
                $userId = \DB::table('shipping_addresses')
                    ->whereRaw('LOWER(email) = ?', [$email])
                    ->whereNotNull('user_id')
                    ->value('user_id');
                if ($userId) {
                    $u = User::find($userId);
                    if ($u) return $u;
                }
            }
        }

        // 3. Phone on users table — last 9 digits, ignore +/-/space
        if ($phone) {
            $needle = substr($phone, -9);
            if (strlen($needle) >= 6) {  // sanity: don't match on short fragments
                $u = User::whereRaw(
                    "REPLACE(REPLACE(REPLACE(phone, ' ', ''), '+', ''), '-', '') LIKE ?",
                    ['%' . $needle]
                )->first();
                if ($u) return $u;

                // 4. Phone on shipping_addresses
                if (Schema::hasTable('shipping_addresses')) {
                    $userId = \DB::table('shipping_addresses')
                        ->whereRaw(
                            "REPLACE(REPLACE(REPLACE(phone, ' ', ''), '+', ''), '-', '') LIKE ?",
                            ['%' . $needle]
                        )
                        ->whereNotNull('user_id')
                        ->value('user_id');
                    if ($userId) {
                        $u = User::find($userId);
                        if ($u) return $u;
                    }
                }
            }
        }

        return null;
    }

    /**
     * Find this user's "unconfirmed" orders — i.e. ones that need admin to
     * confirm the delivery date via Setmore.
     *
     * Criteria changed in 2026-05-17:
     *   OLD: payment_status='paid' + delivery_date_confirmed_at IS NULL + not delivered/cancelled
     *   NEW: payment_status IN ('paid','pending') + delivery_date_confirmed_at IS NULL + not delivered/cancelled
     *
     * Why: customers who pay by Cash on Delivery (or Bank Transfer) have
     * payment_status='pending' until they actually pay after delivery. The
     * OLD criteria excluded these orders from Setmore matching — wrong,
     * because COD orders DO need a Setmore appointment booked.
     *
     * To stay safe, we still exclude 'delivered' / 'cancelled' orders.
     *
     * 2026-05-20 fix: previously this ALSO excluded order_status='pending'
     * outright, on the assumption that pending = abandoned checkout. That is
     * true for CARD orders, but WRONG for bank transfer / COD orders, where
     * pending is the normal state while awaiting payment. The admin books
     * those into Setmore (often before marking them paid), so excluding all
     * pending orders silently dropped every bank-transfer/COD Setmore match
     * and filed the appointment as 'ignored'. We now exclude pending orders
     * ONLY when they are card orders; pending bank-transfer/COD/cash orders
     * remain matchable.
     *
     * Earlier change (2026-05-16): switched from preferred_delivery_date
     * to delivery_date_confirmed_at so customer-picked dates don't exclude
     * orders that still need admin confirmation.
     *
     * Falls back to the legacy criteria if the column doesn't exist yet.
     */
    private function findUnscheduledOrdersForUser(User $user)
    {
        $query = Order::where('user_id', $user->id)
            ->whereIn('payment_status', ['paid', 'pending'])
            // Always exclude finished/dead orders.
            ->whereNotIn('order_status', ['delivered', 'cancelled'])
            // For order_status = 'pending' we must distinguish two cases:
            //   - CARD orders: pending means checkout was never completed
            //     (abandoned) — exclude them, they're not real orders.
            //   - BANK TRANSFER / COD orders: pending is the NORMAL state
            //     while awaiting payment. These ARE real orders, and the
            //     admin books them into Setmore (often before marking them
            //     paid). They MUST remain matchable.
            // So: keep any non-pending order; for pending ones, keep only
            // the manual-payment types (bank transfer / COD / cash).
            ->where(function ($q) {
                $q->where('order_status', '!=', 'pending')
                  ->orWhere(function ($q2) {
                      $q2->where('order_status', 'pending')
                         ->where(function ($q3) {
                             $q3->where('payment_method', 'like', '%bank%')
                                ->orWhere('payment_method', 'like', '%transfer%')
                                ->orWhere('payment_method', 'like', '%cash%')
                                ->orWhere('payment_method', 'like', '%cod%')
                                ->orWhere('payment_method', 'cash_on_delivery');
                         });
                  });
            });

        if (Schema::hasColumn('orders', 'delivery_date_confirmed_at')) {
            // Preferred path: admin hasn't confirmed this order's date yet
            $query->whereNull('delivery_date_confirmed_at');
        } else {
            // Legacy fallback: no confirmed-timestamp column means we have
            // to rely on the customer-picked-date proxy.
            $query->whereNull('preferred_delivery_date');
        }

        // CRITICAL: only match orders that came from the app.
        // Phone orders are entered directly into Setmore and never reach
        // the orders table — but to be safe, exclude anything without the
        // "order-centre" tag. The tag is auto-applied by
        // OrderCompletionService on every app-placed order.
        //
        // This protects against an admin manually creating an order in
        // the admin panel (which would otherwise be a Setmore candidate).
        if (Schema::hasTable('order_tags') && Schema::hasTable('order_tag')) {
            $appTagId = \DB::table('order_tags')
                ->where('slug', 'order-centre')
                ->value('id');
            if ($appTagId) {
                $query->whereIn('orders.id', function ($sub) use ($appTagId) {
                    $sub->select('order_id')
                        ->from('order_tag')
                        ->where('tag_id', $appTagId);
                });
            }
            // If the tag doesn't exist yet (very early deploys), don't
            // add any filter — fall back to "user.id match" behaviour.
        }

        return $query
            ->orderBy('created_at', 'asc')  // oldest first — most urgent
            ->get();
    }

    /**
     * Strip non-digits from phone for matching. Keeps leading-zero issues
     * minimal because we only match the last 9 digits.
     */
    private function normalisePhone(?string $phone): string
    {
        if (!$phone) return '';
        return preg_replace('/\D/', '', $phone);
    }

    /**
     * Parse a date/time string from Zapier — accept multiple formats.
     *
     * The business is Australian, so any slash-formatted date is treated as
     * DD/MM (NEVER US-style MM/DD). Carbon's default parser does US-first
     * for slashes, so we try the AU formats explicitly BEFORE falling back
     * to Carbon::parse() for ISO 8601 etc.
     *
     * Setmore via Zapier has been observed sending these formats:
     *   - "23/05/26"                  AU date, 2-digit year   (Setmore short)
     *   - "23/05/2026"                AU date, 4-digit year
     *   - "23/05/26 14:30"            AU date+time
     *   - "2026-05-23 14:30:00"       Zapier formatted
     *   - "2026-05-23T14:30:00+08:00" ISO 8601 with timezone
     *   - "May 23, 2026 2:30 PM"      US-style some Zapier steps use
     *   - "1716256800"                Unix timestamp
     *
     * If only a date is supplied, time defaults to 00:00 (midnight).
     * Returns null if value is empty or unparseable; logs the attempt
     * so we can see what Zapier sent when parsing fails.
     */
    private function parseDate($value): ?Carbon
    {
        if ($value === null || $value === '') return null;

        $perthTz = 'Australia/Perth';

        // Handle Unix timestamps (numeric strings or ints). A Unix timestamp is
        // an absolute moment in time (UTC by definition), so convert to Perth.
        if (is_numeric($value)) {
            try {
                $ts = (int) $value;
                // sanity: timestamps before year 2000 are almost certainly wrong
                if ($ts > 946684800) {
                    return Carbon::createFromTimestamp($ts)->setTimezone($perthTz);
                }
            } catch (\Throwable $e) { /* fall through */ }
        }

        $str = trim((string) $value);

        // STEP 1: Try AU formats FIRST for slash dates (so 23/05/26 → 23 May,
        // not interpreted as month-23 by Carbon::parse's US-first defaults).
        // Order matters: try more-specific (with time) before less-specific.
        //
        // These are BARE LOCAL formats with no timezone info. The business is in
        // Perth and these come from Setmore/Zapier already expressed in Perth
        // local time, so we interpret them AS Perth local — we do NOT convert
        // (converting would shift the date). createFromFormat's 3rd arg sets the
        // zone the string is interpreted in.
        $auFormats = [
            // AU date + time
            'd/m/y H:i',  'd/m/y H:i:s',  'd/m/y h:i A',
            'd/m/Y H:i',  'd/m/Y H:i:s',  'd/m/Y h:i A',
            'd-m-y H:i',  'd-m-Y H:i',
            // AU date only — time will default to 00:00
            'd/m/y',      'd/m/Y',
            'd-m-y',      'd-m-Y',
        ];
        foreach ($auFormats as $fmt) {
            try {
                $d = Carbon::createFromFormat($fmt, $str, $perthTz);
                if ($d !== false) return $d;
            } catch (\Throwable $e) { /* try next */ }
        }

        // STEP 2: Try Carbon's default parser (handles ISO 8601, RFC formats,
        // "May 23 2026" etc.). Safe to use now that AU slash-dates are out.
        //
        // IMPORTANT TIMEZONE HANDLING:
        //   - If the string carries explicit timezone info (e.g. a trailing 'Z'
        //     or "+08:00"), it's an ABSOLUTE moment (typically UTC from Setmore).
        //     Carbon::parse honours that zone; we then convert to Perth so the
        //     extracted delivery date is the correct Perth calendar day. Without
        //     this, an evening-UTC appointment would record the date a day early.
        //   - If the string has NO timezone info (a bare "2026-05-23 14:30:00"
        //     that Zapier's formatter used to produce), we interpret it as Perth
        //     local — same as the AU formats above — and do NOT shift it.
        $hasExplicitZone = (bool) preg_match('/(Z|[+\-]\d{2}:?\d{2})$/', $str);
        try {
            if ($hasExplicitZone) {
                // Absolute moment — convert to Perth before the date is used.
                return Carbon::parse($str)->setTimezone($perthTz);
            }
            // Bare local datetime — interpret in Perth, no shift.
            return Carbon::parse($str, $perthTz);
        } catch (\Throwable $e) {
            // Fall through to format-specific attempts below
        }

        // STEP 3: A few extra named-month formats Zapier sometimes produces.
        // These are bare local formats — interpret as Perth, no conversion.
        $namedMonthFormats = [
            'd M Y H:i',   'd M Y h:i A',   'd M Y',
            'd M y H:i',   'd M y',
            'D d M Y H:i', 'D d M Y',
            'M j, Y g:i A','M j, Y',
            'j M Y',
        ];
        foreach ($namedMonthFormats as $fmt) {
            try {
                $d = Carbon::createFromFormat($fmt, $str, $perthTz);
                if ($d !== false) return $d;
            } catch (\Throwable $e) { /* try next */ }
        }

        // Couldn't parse — log so we know what's coming in
        Log::warning('SetmoreMatchingService: could not parse date value', [
            'value'      => $str,
            'value_type' => gettype($value),
        ]);

        return null;
    }
}
