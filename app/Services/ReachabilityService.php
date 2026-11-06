<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Exception\MessagingException;
use Kreait\Firebase\Messaging\CloudMessage;
use App\Services\FirebaseService;

/**
 * ReachabilityService
 *
 * Tells you whether a user's device can actually receive push notifications.
 *
 * Method overview:
 *
 *   classify(User $user): string
 *     Returns one of: 'reachable' | 'cooling' | 'stale' | 'no_app'
 *     Based purely on existing fields — no Firebase call.
 *
 *   ping(User $user): array
 *     Sends a SILENT (data-only, no visible notification) push to the user.
 *     If Firebase returns NotFound → clears the dead token.
 *     If success → bumps fcm_token_validated_at to now().
 *     Returns ['status' => 'reachable' | 'unreachable' | 'no_token', 'detail' => '...']
 *
 *   pingMany(iterable $users): array
 *     Batches ping() over many users with results summary.
 *
 * Why silent?  A normal push pops a notification on the user's lock screen.
 * A data-only message with no `notification` field passes through invisibly —
 * the OS reports back whether the token's still valid, but the user sees
 * nothing. Perfect for status checks.
 */
class ReachabilityService
{
    /** Threshold (days) after which a token is "cooling" — still has token,
     *  but it's been a while since we last confirmed it works. */
    public const COOLING_THRESHOLD_DAYS = 30;

    /** Threshold (days) after which a never-validated token is "stale". */
    public const STALE_THRESHOLD_DAYS = 90;

    /**
     * Classify the user's reachability state purely from local data.
     * No Firebase calls — fast, can be used in list views.
     */
    public function classify(User $user): string
    {
        // No token at all → never installed or fully uninstalled-and-cleared
        if (empty($user->fcm_token)) {
            return 'no_app';
        }

        // Coerce to Carbon defensively. The User model casts this column to
        // datetime so it normally arrives as a Carbon instance, but if the
        // cast is missing or the value came from a raw query we might get
        // a string. parse() handles both.
        $validatedAt = $user->fcm_token_validated_at
            ? \Illuminate\Support\Carbon::parse($user->fcm_token_validated_at)
            : null;
        $updatedAt = $user->updated_at
            ? \Illuminate\Support\Carbon::parse($user->updated_at)
            : null;

        // Token present but never validated → check token age (via updated_at as fallback)
        if (!$validatedAt) {
            // Token has never been confirmed working. Use account updated_at as a
            // (weak) proxy: a token attached to a recently-updated account is
            // probably fresher than one attached to a 2-year-old account.
            $ageDays = $updatedAt ? $updatedAt->diffInDays(now()) : 999;
            if ($ageDays > self::STALE_THRESHOLD_DAYS) {
                return 'stale';
            }
            return 'cooling'; // assume cooling until we validate
        }

        $daysSinceValidated = $validatedAt->diffInDays(now());

        if ($daysSinceValidated <= self::COOLING_THRESHOLD_DAYS) {
            return 'reachable';
        }
        if ($daysSinceValidated <= self::STALE_THRESHOLD_DAYS) {
            return 'cooling';
        }
        return 'stale';
    }

    /**
     * Send a silent ping to the user's device and record the result.
     *
     * @return array{status: string, detail: string}
     */
    public function ping(User $user): array
    {
        if (empty($user->fcm_token)) {
            return ['status' => 'no_token', 'detail' => 'User has no FCM token (app not installed).'];
        }

        try {
            $messaging = FirebaseService::messaging();
            if (!$messaging) {
                return ['status' => 'error', 'detail' => 'Firebase not configured.'];
            }

            // DATA-ONLY MESSAGE — no `notification` block means the OS does
            // NOT display anything on the user's screen. We just want
            // delivery confirmation, not engagement.
            $message = CloudMessage::withTarget('token', $user->fcm_token)
                ->withData([
                    'type'        => 'reachability_check',
                    'silent'      => '1',
                    'timestamp'   => (string) now()->timestamp,
                ]);

            $messaging->send($message);

            // Success — bump the validated timestamp
            $user->fcm_token_validated_at = now();
            $user->save();

            Log::info('ReachabilityService: ping succeeded', [
                'user_id' => $user->id,
            ]);

            return ['status' => 'reachable', 'detail' => 'Device received the silent ping.'];

        } catch (NotFound $e) {
            // Firebase says the token is dead — clear it
            return $this->clearDeadToken($user, 'NotFound: ' . $e->getMessage());

        } catch (MessagingException $e) {
            // Firebase SDK uses several exception classes for what's essentially
            // the same outcome (dead/invalid token). Check the message + error
            // code to distinguish "dead token" from genuine service errors.
            $msg = strtolower($e->getMessage());
            $isDeadToken =
                str_contains($msg, 'not registered') ||
                str_contains($msg, 'unregistered') ||
                str_contains($msg, 'invalid registration') ||
                str_contains($msg, 'invalid argument') ||
                str_contains($msg, 'invalid-argument') ||
                str_contains($msg, 'requested entity was not found') ||
                str_contains($msg, 'mismatched credential') ||
                str_contains($msg, 'mismatched-credential') ||
                str_contains($msg, 'sender id mismatch');

            if ($isDeadToken) {
                return $this->clearDeadToken($user, get_class($e) . ': ' . $e->getMessage());
            }

            // Genuine Firebase error (rate limit, internal error, etc) — keep the token
            Log::warning('ReachabilityService: transient Firebase error (token kept)', [
                'user_id' => $user->id,
                'class'   => get_class($e),
                'err'     => $e->getMessage(),
            ]);
            return ['status' => 'error', 'detail' => 'Firebase error: ' . $e->getMessage()];

        } catch (\Throwable $e) {
            // Last-resort fallback. Same heuristic — some Kreait versions wrap
            // dead-token exceptions in plain RuntimeException or similar.
            $msg = strtolower($e->getMessage());
            $looksLikeDeadToken =
                str_contains($msg, 'not registered') ||
                str_contains($msg, 'unregistered') ||
                str_contains($msg, 'invalid registration') ||
                str_contains($msg, 'requested entity was not found');

            if ($looksLikeDeadToken) {
                return $this->clearDeadToken($user, 'Throwable: ' . $e->getMessage());
            }

            Log::error('ReachabilityService: ping failed', [
                'user_id' => $user->id,
                'class'   => get_class($e),
                'err'     => $e->getMessage(),
            ]);
            return ['status' => 'error', 'detail' => 'Firebase error: ' . $e->getMessage()];
        }
    }

    /**
     * Clear the user's dead FCM token and return the "unreachable" result.
     * Extracted because three different catch blocks may all need to call it
     * depending on which exception variant Firebase throws.
     */
    private function clearDeadToken(User $user, string $reason): array
    {
        $tokenPrefix = substr($user->fcm_token ?? '', 0, 20);
        $user->fcm_token = null;
        $user->fcm_token_validated_at = null;
        $user->save();

        Log::info('ReachabilityService: dead token cleared', [
            'user_id'      => $user->id,
            'token_prefix' => $tokenPrefix,
            'reason'       => $reason,
        ]);

        return ['status' => 'unreachable', 'detail' => 'Token rejected by Firebase — app was uninstalled or token expired. Cleared.'];
    }

    /**
     * Ping many users in a loop. Returns summary counts.
     */
    public function pingMany(iterable $users): array
    {
        $stats = [
            'total'       => 0,
            'reachable'   => 0,
            'unreachable' => 0,
            'no_token'    => 0,
            'error'       => 0,
        ];

        foreach ($users as $user) {
            $stats['total']++;
            $result = $this->ping($user);
            $stats[$result['status']] = ($stats[$result['status']] ?? 0) + 1;
        }

        return $stats;
    }
}
