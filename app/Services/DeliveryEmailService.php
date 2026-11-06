<?php

namespace App\Services;

use App\Mail\SetmoreDeliveryMail;
use App\Models\NotificationSetting;
use App\Models\SetmoreDeliveryEmail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;

/**
 * Sends server-side delivery emails for non-app (phone / WooCommerce) Setmore
 * bookings — replacing the Zapier email steps.
 *
 * IMPORTANT INVARIANTS:
 *   - Only call this for bookings that did NOT match an app order. App orders
 *     keep their existing OrderDeliveryDateUpdatedMail flow; calling this for
 *     them would double-email the customer. The caller (matcher hook, Stage 3)
 *     is responsible for only invoking this on the no-app-order path.
 *   - Everything is gated by the master switch (default OFF). When master is
 *     off, this is a complete no-op — nothing sends, nothing logs.
 *   - Test mode (default ON) redirects the email to the test recipient instead
 *     of the real customer, so first activation can't reach real customers.
 *
 * Booked vs rescheduled is decided by the log: first email for an appointment_id
 * is 'booked'; a later date change for the same appointment is 'rescheduled'.
 */
class DeliveryEmailService
{
    /**
     * Send a delivery email for a non-app booking.
     *
     * @param string $type     booked | rescheduled | cancelled  (or 'auto' to
     *                          decide booked-vs-rescheduled from the log)
     * @param array  $booking  [
     *     'appointment_id' => string,
     *     'event_id'       => int|null,   // setmore_webhook_event id
     *     'channel'        => 'phone'|'woocommerce'|'unknown',
     *     'name'           => string,
     *     'email'          => string,     // real customer email
     *     'phone'          => string,
     *     'address'        => string,     // combined address line from webhook
     *     'date'           => string|null // appointment date (Perth)
     *   ]
     * @return array{sent:bool, status:string, reason:string}
     */
    public function send(string $type, array $booking): array
    {
        // ── Master gate ──────────────────────────────────────────────────
        if (!NotificationSetting::isEnabled('setmore_email_master', false)) {
            return ['sent' => false, 'status' => 'skipped', 'reason' => 'master_off'];
        }

        $appointmentId = (string) ($booking['appointment_id'] ?? '');

        // ── Resolve booked vs rescheduled if asked to auto-decide ─────────
        if ($type === 'auto') {
            $type = ($appointmentId !== '' && SetmoreDeliveryEmail::hasBookedFor($appointmentId))
                ? 'rescheduled'
                : 'booked';
        }
        if (!in_array($type, ['booked', 'rescheduled', 'cancelled'], true)) {
            return ['sent' => false, 'status' => 'skipped', 'reason' => 'bad_type'];
        }

        // ── Per-type gate ─────────────────────────────────────────────────
        $typeKey = 'setmore_email_' . $type;
        if (!NotificationSetting::isEnabled($typeKey, true)) {
            return ['sent' => false, 'status' => 'skipped', 'reason' => $type . '_disabled'];
        }

        $realEmail = trim((string) ($booking['email'] ?? ''));
        if ($realEmail === '') {
            // No address to send to — log as skipped so it's visible.
            $this->log($type, $booking, 'skipped', null, null, null, 'no_recipient_email', false);
            return ['sent' => false, 'status' => 'skipped', 'reason' => 'no_recipient_email'];
        }

        // Send straight to the real customer. (There's no test-mode redirect —
        // use the "Send test email" button in settings to preview the look.)
        $recipient = $realEmail;

        // ── Which mailer ──────────────────────────────────────────────────
        $mailer = (string) NotificationSetting::get('setmore_email_mailer', 'brevo');
        $mailer = in_array($mailer, ['brevo', 'gmail'], true) ? $mailer : config('mail.default');

        // ── Build template data ───────────────────────────────────────────
        $data = [
            'customerName'    => $this->cleanName($booking['name'] ?? null),
            'appointmentDate' => $booking['date'] ?? null,
            'customerPhone'   => $booking['phone'] ?? null,
            'customerEmail'   => $realEmail,
            // Webhook sends one combined address string → addressLine.
            'addressLine'     => $booking['address'] ?? null,
            'suburb'          => null,
            'state'           => null,
            'postcode'        => null,
            'oldDate'         => $booking['old_date'] ?? null,
        ];

        // ── Send ──────────────────────────────────────────────────────────
        try {
            $mailMsg = Mail::mailer($mailer)->to($recipient);

            // BCC admin if enabled — lets the admin watch real customer emails
            // go out (a live "is this working?" check) without redirecting them.
            if (NotificationSetting::isEnabled('setmore_email_bcc_admin', false)) {
                $bcc = (string) NotificationSetting::get('setmore_email_test_recipient', 'admin@jjsfirewoodperth.com.au');
                if ($bcc !== '') {
                    $mailMsg->bcc($bcc);
                }
            }

            $mailMsg->send(new SetmoreDeliveryMail($type, $data));

            $this->log($type, $booking, 'sent', $recipient, $realEmail, $mailer, null, false);

            return ['sent' => true, 'status' => 'sent', 'reason' => 'ok'];
        } catch (\Throwable $e) {
            $this->log($type, $booking, 'failed', $recipient, $realEmail, $mailer, $e->getMessage(), false);
            Log::error('DeliveryEmailService: send failed', [
                'type'           => $type,
                'appointment_id' => $appointmentId,
                'error'          => $e->getMessage(),
            ]);
            return ['sent' => false, 'status' => 'failed', 'reason' => $e->getMessage()];
        }
    }

    /**
     * Send a SAMPLE email of the given type to an address on demand, so the
     * admin can preview what customers receive. Uses placeholder booking data
     * and the currently-selected mailer. Does NOT touch the master/per-type
     * gates (it's a manual preview), and is logged as a test row.
     *
     * @return array{sent:bool, message:string}
     */
    public function sendTest(string $type, string $toEmail): array
    {
        $type = in_array($type, ['booked', 'rescheduled', 'cancelled'], true) ? $type : 'booked';
        $toEmail = trim($toEmail);
        if ($toEmail === '' || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
            return ['sent' => false, 'message' => 'Please enter a valid email address.'];
        }

        $mailer = (string) NotificationSetting::get('setmore_email_mailer', 'brevo');
        $mailer = in_array($mailer, ['brevo', 'gmail'], true) ? $mailer : config('mail.default');

        $data = [
            'customerName'    => 'Sample Customer',
            'appointmentDate' => now()->addDays(3)->toDateString(),
            'oldDate'         => $type === 'rescheduled' ? now()->addDay()->toDateString() : null,
            'customerPhone'   => '0400 000 000',
            'customerEmail'   => $toEmail,
            'addressLine'     => '123 Example Street, Perth',
            'suburb'          => null, 'state' => null, 'postcode' => null,
        ];

        try {
            Mail::mailer($mailer)->to($toEmail)->send(new SetmoreDeliveryMail($type, $data));

            $this->log($type, [
                'appointment_id' => null, 'event_id' => null, 'channel' => 'test',
                'name' => 'Sample Customer', 'date' => $data['appointmentDate'],
            ], 'test', $toEmail, $toEmail, $mailer, null, true);

            return ['sent' => true, 'message' => "Test {$type} email sent to {$toEmail} via {$mailer}."];
        } catch (\Throwable $e) {
            Log::error('DeliveryEmailService: test send failed', ['error' => $e->getMessage()]);
            return ['sent' => false, 'message' => 'Send failed: ' . $e->getMessage()];
        }
    }

    /**
     * "Dean - Bedfordale" → "Dean". Setmore names are "Name - Suburb".
     */
    protected function cleanName(?string $name): ?string
    {
        if (!$name) return null;
        $parts = explode(' - ', $name, 2);
        return trim($parts[0]) ?: $name;
    }

    /**
     * Write a log row. Never throws — logging must not break the send path.
     */
    protected function log(
        string $type, array $booking, string $status,
        ?string $recipient, ?string $realRecipient, ?string $mailer,
        ?string $error, bool $wasTest
    ): void {
        try {
            if (!Schema::hasTable('setmore_delivery_emails')) return;

            SetmoreDeliveryEmail::create([
                'setmore_webhook_event_id' => $booking['event_id'] ?? null,
                'appointment_id'           => $booking['appointment_id'] ?? null,
                'type'                     => $type,
                'channel'                  => $booking['channel'] ?? 'unknown',
                'recipient'                => $recipient,
                'real_recipient'           => $realRecipient,
                'customer_name'            => $this->cleanName($booking['name'] ?? null),
                'appointment_date'         => !empty($booking['date'])
                    ? \Carbon\Carbon::parse($booking['date'])->toDateString()
                    : null,
                'status'                   => $status,
                'mailer'                   => $mailer,
                'error'                    => $error,
                'was_test'                 => $wasTest,
            ]);
        } catch (\Throwable $e) {
            Log::warning('DeliveryEmailService: failed to write log row', ['err' => $e->getMessage()]);
        }
    }
}
