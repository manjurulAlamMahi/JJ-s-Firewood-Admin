<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\SetmoreMatchingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Webhook endpoint for Zapier (Setmore → admin).
 *
 * Auth: shared secret via X-Webhook-Token header.
 *       Set SETMORE_WEBHOOK_TOKEN in .env to enable. If unset, the endpoint
 *       returns 503 — explicit opt-in. Without a secret, anyone could spam
 *       the endpoint with fake data.
 *
 * Endpoint: POST /api/webhooks/zapier/schedule-order
 *
 * Expected payload (any of these field names accepted — Zapier varies):
 *   {
 *     "customer_email":  "john@email.com",
 *     "customer_phone":  "+61400000000",
 *     "customer_name":   "John Doe",
 *     "appointment_at":  "2026-05-22 10:00:00",   // or ISO 8601
 *     "appointment_id":  "abc-123"
 *   }
 *
 * Response:
 *   200  { ok: true, event_id: 42, status: 'pending'|'no_match', candidate_count: N }
 *   400  { ok: false, message: 'reason' }
 *   401  { ok: false, message: 'invalid token' }
 *   503  { ok: false, message: 'webhook disabled' }
 *
 * The webhook NEVER auto-applies the date. Every hit creates a row that
 * admin reviews in the Setmore Reviews panel.
 */
class SetmoreWebhookController extends Controller
{
    public function handle(Request $request, SetmoreMatchingService $svc): JsonResponse
    {
        // 1. Disabled-by-default check
        $expectedToken = env('SETMORE_WEBHOOK_TOKEN');
        if (!$expectedToken) {
            return response()->json([
                'ok'      => false,
                'message' => 'Webhook is disabled. Set SETMORE_WEBHOOK_TOKEN in .env to enable.',
            ], 503);
        }

        // 2. Auth check — header or query string fallback (Zapier sometimes prefers query)
        $providedToken = $request->header('X-Webhook-Token')
            ?: $request->input('token');
        if (!hash_equals($expectedToken, (string) $providedToken)) {
            Log::warning('Setmore webhook: bad token', [
                'ip' => $request->ip(),
            ]);
            return response()->json([
                'ok'      => false,
                'message' => 'Invalid webhook token',
            ], 401);
        }

        // 3. Basic payload validation
        $payload = $request->all();
        $hasEmail = !empty($payload['customer_email']) || !empty($payload['email']) || !empty($payload['customer']['email'] ?? null);
        $hasPhone = !empty($payload['customer_phone']) || !empty($payload['phone']) || !empty($payload['customer']['phone'] ?? null);
        if (!$hasEmail && !$hasPhone) {
            return response()->json([
                'ok'      => false,
                'message' => 'Payload must include customer_email or customer_phone',
            ], 400);
        }

        // 4. Hand off to the service.
        // DB connections can occasionally refuse (Connection refused / deadlock
        // / read timeout). These are TRANSIENT — usually succeed on retry.
        // Strategy: try up to 3 times with backoff. If still failing, return
        // 503 with Retry-After so Zapier auto-retries the webhook later
        // (Zapier honours Retry-After for transient errors).
        $attempts = 0;
        $maxAttempts = 3;
        $lastException = null;

        while ($attempts < $maxAttempts) {
            $attempts++;
            try {
                $event = $svc->ingestWebhook($payload);
                $lastException = null;
                break;
            } catch (\Throwable $e) {
                $lastException = $e;
                $msg = $e->getMessage();

                // Detect transient infrastructure errors. Any of these merit a retry:
                //   - "Connection refused" (MySQL/Redis briefly down)
                //   - "Deadlock found"     (concurrent write conflict)
                //   - "Lock wait timeout"  (slow queries blocking)
                //   - "SQLSTATE[HY000] [2002]" (generic connection error)
                //   - "Lost connection"    (MySQL killed the connection)
                $isTransient = stripos($msg, 'Connection refused') !== false
                            || stripos($msg, '[2002]') !== false
                            || stripos($msg, 'Lost connection') !== false
                            || stripos($msg, 'Deadlock') !== false
                            || stripos($msg, 'Lock wait timeout') !== false;

                if (!$isTransient || $attempts >= $maxAttempts) {
                    break;
                }

                // Exponential-ish backoff: 250ms, 750ms before giving up
                Log::info('Setmore webhook: transient error, will retry', [
                    'attempt' => $attempts,
                    'error'   => $msg,
                ]);
                usleep(250000 * $attempts);
            }
        }

        if ($lastException) {
            $msg = $lastException->getMessage();
            $isTransient = stripos($msg, 'Connection refused') !== false
                        || stripos($msg, '[2002]') !== false
                        || stripos($msg, 'Lost connection') !== false
                        || stripos($msg, 'Deadlock') !== false
                        || stripos($msg, 'Lock wait timeout') !== false;

            Log::error('Setmore webhook: ingest failed', [
                'err'        => $msg,
                'attempts'   => $attempts,
                'transient'  => $isTransient,
                'payload'    => $payload,
            ]);

            if ($isTransient) {
                // Tell Zapier to retry in 60 seconds. 503 + Retry-After is
                // the well-defined contract for "temporary unavailability".
                return response()->json([
                    'ok'      => false,
                    'message' => 'Temporary database unavailability — please retry. (' . $msg . ')',
                ], 503)->header('Retry-After', '60');
            }

            return response()->json([
                'ok'      => false,
                'message' => 'Server error: ' . $msg,
            ], 500);
        }

        return response()->json([
            'ok'              => true,
            'event_id'        => $event->id,
            'status'          => $event->status,
            'candidate_count' => $event->candidate_count,
            'message'         => $event->status === 'no_match'
                ? 'No matching customer found — logged for admin review.'
                : 'Queued for admin review.',
        ]);
    }
}
