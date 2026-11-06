<?php

namespace App\Jobs;

use App\Helpers\FirebaseHelper;
use App\Models\NotificationQueue;
use App\Models\NotificationRun;
use App\Models\User;
use App\Services\Brevo\BrevoTransactionalService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

class DispatchNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1200; // 20 min
    public int $tries = 1;

    public function __construct(public int $notificationId) {}

    public function handle(BrevoTransactionalService $brevo): void
    {
        $n = NotificationQueue::findOrFail($this->notificationId);

        // Stop if cancelled/paused
        if (in_array($n->status, ['cancelled', 'paused'], true)) {
            return;
        }

        // CRITICAL FIX: Prevent duplicate sends
        // If notification is already sent/failed (non-recurring), don't re-send
        if (in_array($n->status, ['sent', 'failed'], true) && $n->schedule_type !== 'recurring') {
            Log::warning('DispatchNotificationJob: Attempted to re-send already completed notification', [
                'notification_id' => $n->id,
                'status' => $n->status,
            ]);
            return;
        }

        // Mark running
        $n->status = 'running';
        $n->save();

        $sent = 0;
        $failed = 0;
        $skipped = 0;
        $lastError = null;

        /** @var Builder $usersQuery */
        $usersQuery = User::query()
            ->select(['id', 'email', 'phone', 'fcm_token', 'is_admin', 'is_guest'])
            ->where('is_guest', false);

        // CRITICAL FIX: Exclude users who already received this notification
        // This prevents duplicate sends if the job is accidentally dispatched twice
        $alreadySentUserIds = NotificationRun::where('notification_id', $n->id)
            ->where('status', 'sent')
            ->pluck('user_id')
            ->toArray();
        
        if (count($alreadySentUserIds) > 0) {
            $usersQuery->whereNotIn('id', $alreadySentUserIds);
            Log::info('DispatchNotificationJob: Excluding users who already received notification', [
                'notification_id' => $n->id,
                'excluded_count' => count($alreadySentUserIds),
            ]);
        }

        // Channel audience filter
        if ($n->channel === 'push') {
            $usersQuery->whereNotNull('fcm_token')->where('fcm_token', '!=', '');
        } elseif ($n->channel === 'email' || $n->channel === 'email_transactional' || $n->channel === 'email_campaign') {
            $usersQuery->whereNotNull('email')->where('email', '!=', '');
        } elseif ($n->channel === 'sms') {
            $usersQuery->whereNotNull('phone')->where('phone', '!=', '');
        } else {
            $n->status = 'failed';
            $n->last_ran_at = now();
            $n->last_error = 'Unknown channel: ' . ($n->channel ?? 'null');
            $n->save();
            return;
        }

        // TEST MODE = admins only
        if ((int) $n->test_mode === 1) {
            $usersQuery->where('is_admin', 1);
        }

        // ============================================================
        // CENTRE REBUILD: honour audience_spec when present.
        //
        // If the campaign was built with the new audience builder, we resolve
        // the spec to a concrete list of user IDs + raw recipients, then narrow
        // the existing user query to just those IDs. Raw recipients (non-users)
        // are sent in a separate loop after the chunk run.
        //
        // If audience_spec is absent (legacy campaigns), nothing changes —
        // we fall through to the original behaviour.
        // ============================================================
        $rawPhones = [];
        $rawEmails = [];
        if (\Schema::hasColumn('notifications_queue', 'audience_spec') && !empty($n->audience_spec)) {
            try {
                $resolver = new \App\Services\Notifications\AudienceResolver();
                $spec = is_array($n->audience_spec) ? $n->audience_spec : (json_decode($n->audience_spec, true) ?: []);
                $respectsOptOut = property_exists($n, 'respects_marketing_optout')
                    ? (bool) $n->respects_marketing_optout
                    : true;
                $resolved = $resolver->resolve($spec, $n->channel, $respectsOptOut);

                $resolvedUserIds = $resolved['users']->pluck('id')->all();
                $rawPhones = $resolved['raw_phones'] ?? [];
                $rawEmails = $resolved['raw_emails'] ?? [];

                // If the spec resolved to NO users and NO raw recipients, fail fast.
                if (empty($resolvedUserIds) && empty($rawPhones) && empty($rawEmails)) {
                    Log::warning('DispatchNotificationJob: audience_spec resolved to no recipients', [
                        'notification_id' => $n->id,
                        'spec' => $spec,
                    ]);
                    $n->status = 'failed';
                    $n->last_ran_at = now();
                    $n->last_error = 'Audience resolved to zero recipients (check rules / handpicks / suppressions / opt-outs).';
                    $n->save();
                    return;
                }

                // Constrain to resolved users (or none if only raw recipients)
                if (!empty($resolvedUserIds)) {
                    $usersQuery->whereIn('id', $resolvedUserIds);
                } else {
                    $usersQuery->whereRaw('1=0'); // no user-table sends, only raw
                }

                Log::info('DispatchNotificationJob: audience_spec applied', [
                    'notification_id' => $n->id,
                    'matched_users' => count($resolvedUserIds),
                    'raw_phones' => count($rawPhones),
                    'raw_emails' => count($rawEmails),
                ]);
            } catch (\Throwable $e) {
                Log::error('DispatchNotificationJob: audience_spec resolve failed', [
                    'notification_id' => $n->id,
                    'err' => $e->getMessage(),
                ]);
                // Fall through to legacy behaviour rather than failing the send
            }
        }

        // Progress init (correctly check DB columns)
        $totalTargets = (clone $usersQuery)->count() + count($rawPhones) + count($rawEmails);

        if (Schema::hasColumn('notifications_queue', 'total_targets')) {
            $n->total_targets = $totalTargets;
        }
        if (Schema::hasColumn('notifications_queue', 'processed_targets')) {
            $n->processed_targets = 0;
        }
        $n->save();

        // EDGE CASE: If all users already received it, mark as sent
        if ($totalTargets === 0 && count($alreadySentUserIds) > 0) {
            $n->status = 'sent';
            $n->last_ran_at = now();
            $n->save();
            Log::info('DispatchNotificationJob: All target users already received notification', [
                'notification_id' => $n->id,
            ]);
            return;
        }

        // Chunk send
        $usersQuery->orderBy('id')->chunkById(300, function ($users) use ($n, $brevo, &$sent, &$failed, &$skipped, &$lastError) {

            foreach ($users as $user) {
                // Stop mid-run if paused/cancelled
                $n->refresh();
                if (in_array($n->status, ['cancelled', 'paused'], true)) {
                    return false; // stops chunking
                }

                try {
                    if ($n->channel === 'push') {
                        $token = $user->fcm_token;

                        if (!$token) {
                            $skipped++;
                            NotificationRun::create([
                                'notification_id' => $n->id,
                                'channel' => 'push',
                                'status' => 'skipped',
                                'provider' => 'firebase',
                                'user_id' => $user->id,
                                'recipient' => null,
                                'error' => 'missing_token',
                            ]);
                        } else {
                            FirebaseHelper::sendNotification($token, $n->title ?? '', $n->message ?? '');

                            $sent++;
                            NotificationRun::create([
                                'notification_id' => $n->id,
                                'channel' => 'push',
                                'status' => 'sent',
                                'provider' => 'firebase',
                                'user_id' => $user->id,
                                'recipient' => 'token:' . substr($token, 0, 18) . '…',
                                'error' => null,
                            ]);
                        }

                    } elseif ($n->channel === 'email' || $n->channel === 'email_transactional' || $n->channel === 'email_campaign') {

                        if (!$user->email) {
                            $skipped++;
                            NotificationRun::create([
                                'notification_id' => $n->id,
                                'channel' => $n->channel,
                                'status' => 'skipped',
                                'provider' => 'brevo',
                                'user_id' => $user->id,
                                'recipient' => null,
                                'error' => 'missing_email',
                            ]);
                        } else {
                            // TEMPLATE SUPPORT: Check if using Brevo template
                            if ($n->template_id) {
                                // Use Brevo template
                                $templateParams = [];
                                if (!empty($n->message)) {
                                    $decoded = json_decode($n->message, true);
                                    if (is_array($decoded)) {
                                        $templateParams = $decoded;
                                    }
                                }
                                
                                $brevo->sendEmail($user->email, '', '', null, [
                                    'templateId' => (int) $n->template_id,
                                    'templateParams' => $templateParams,
                                ]);
                            } else {
                                // Use custom HTML
                                $subject = $n->title ?: "JJ's Firewood Perth";
                                $html = $this->wrapEmailHtml((string) ($n->message ?? ''));

                                $brevo->sendEmail(
                                    to: $user->email,
                                    subject: $subject,
                                    htmlContent: $html,
                                    textContent: strip_tags((string) ($n->message ?? ''))
                                );
                            }

                            $sent++;
                            NotificationRun::create([
                                'notification_id' => $n->id,
                                'channel' => $n->channel,
                                'status' => 'sent',
                                'provider' => 'brevo',
                                'user_id' => $user->id,
                                'recipient' => $user->email,
                                'error' => null,
                            ]);
                        }

                    } elseif ($n->channel === 'sms') {

                        $e164 = $brevo->normalizeAuToE164((string) $user->phone);

                        if (!$e164) {
                            $skipped++;
                            NotificationRun::create([
                                'notification_id' => $n->id,
                                'channel' => 'sms',
                                'status' => 'skipped',
                                'provider' => 'brevo',
                                'user_id' => $user->id,
                                'recipient' => (string) $user->phone,
                                'error' => 'invalid_phone_format',
                            ]);
                        } else {
                            $brevo->sendSms($e164, (string) ($n->message ?? ''));

                            $sent++;
                            NotificationRun::create([
                                'notification_id' => $n->id,
                                'channel' => 'sms',
                                'status' => 'sent',
                                'provider' => 'brevo',
                                'user_id' => $user->id,
                                'recipient' => $e164,
                                'error' => null,
                            ]);
                        }
                    }

                } catch (Throwable $e) {
                    $failed++;
                    $lastError = $e->getMessage();

                    Log::warning('DispatchNotificationJob: send failed', [
                        'notification_id' => $n->id,
                        'user_id' => $user->id,
                        'channel' => $n->channel,
                        'error' => $lastError,
                    ]);

                    NotificationRun::create([
                        'notification_id' => $n->id,
                        'channel' => (string) $n->channel,
                        'status' => 'failed',
                        'provider' => ($n->channel === 'push') ? 'firebase' : 'brevo',
                        'user_id' => $user->id,
                        'recipient' => ($n->channel === 'email' || $n->channel === 'email_transactional' || $n->channel === 'email_campaign')
                            ? $user->email
                            : ($n->channel === 'sms'
                                ? (string) $user->phone
                                : null),
                        'error' => $lastError,
                    ]);
                } finally {
                    // progress increment efficiently (no per-user Eloquent save)
                    if (Schema::hasColumn('notifications_queue', 'processed_targets')) {
                        DB::table('notifications_queue')
                            ->where('id', $n->id)
                            ->increment('processed_targets', 1);
                    }
                }
            }

            return true;
        });

        // ============================================================
        // CENTRE REBUILD: send to raw recipients (non-users) for SMS/email.
        // Push doesn't support raw FCM tokens.
        // ============================================================
        if (!empty($rawPhones) && $n->channel === 'sms') {
            foreach ($rawPhones as $phone) {
                $n->refresh();
                if (in_array($n->status, ['cancelled', 'paused'], true)) break;
                try {
                    $e164 = $brevo->normalizeAuToE164((string) $phone) ?? $phone;
                    $brevo->sendSms($e164, (string) ($n->message ?? ''));
                    $sent++;
                    NotificationRun::create([
                        'notification_id' => $n->id,
                        'channel' => 'sms',
                        'status' => 'sent',
                        'provider' => 'brevo',
                        'user_id' => null,
                        'recipient' => $e164,
                        'error' => null,
                    ]);
                } catch (Throwable $e) {
                    $failed++; $lastError = $e->getMessage();
                    NotificationRun::create([
                        'notification_id' => $n->id,
                        'channel' => 'sms',
                        'status' => 'failed',
                        'provider' => 'brevo',
                        'user_id' => null,
                        'recipient' => (string) $phone,
                        'error' => mb_substr($e->getMessage(), 0, 500),
                    ]);
                } finally {
                    if (Schema::hasColumn('notifications_queue', 'processed_targets')) {
                        DB::table('notifications_queue')->where('id', $n->id)->increment('processed_targets', 1);
                    }
                }
            }
        }

        if (!empty($rawEmails) && in_array($n->channel, ['email', 'email_transactional', 'email_campaign'], true)) {
            $subject = $n->title ?: "JJ's Firewood Perth";
            $html = $this->wrapEmailHtml((string) ($n->message ?? ''));
            $textContent = strip_tags((string) ($n->message ?? ''));
            foreach ($rawEmails as $email) {
                $n->refresh();
                if (in_array($n->status, ['cancelled', 'paused'], true)) break;
                try {
                    $brevo->sendEmail(
                        to: $email,
                        subject: $subject,
                        htmlContent: $html,
                        textContent: $textContent
                    );
                    $sent++;
                    NotificationRun::create([
                        'notification_id' => $n->id,
                        'channel' => $n->channel,
                        'status' => 'sent',
                        'provider' => 'brevo',
                        'user_id' => null,
                        'recipient' => $email,
                        'error' => null,
                    ]);
                } catch (Throwable $e) {
                    $failed++; $lastError = $e->getMessage();
                    NotificationRun::create([
                        'notification_id' => $n->id,
                        'channel' => $n->channel,
                        'status' => 'failed',
                        'provider' => 'brevo',
                        'user_id' => null,
                        'recipient' => $email,
                        'error' => mb_substr($e->getMessage(), 0, 500),
                    ]);
                } finally {
                    if (Schema::hasColumn('notifications_queue', 'processed_targets')) {
                        DB::table('notifications_queue')->where('id', $n->id)->increment('processed_targets', 1);
                    }
                }
            }
        }

        // Final queue row update
        $n->sent_count = (int) ($n->sent_count ?? 0) + $sent;
        $n->failed_count = (int) ($n->failed_count ?? 0) + $failed;
        $n->last_ran_at = now();
        $n->last_error = $lastError;

        if (Schema::hasColumn('notifications_queue', 'sent_at') && $n->schedule_type !== 'recurring') {
            $n->sent_at = now();
        }

        if ($n->schedule_type === 'recurring') {
            // NOTE: later we'll compute next run from RRULE
            $n->next_run_at = ($n->next_run_at ?? now())->addWeek();
            $n->status = 'scheduled';
        } else {
            $n->status = ($failed > 0 && $sent === 0) ? 'failed' : 'sent';
        }

        $n->save();

        Log::info('DispatchNotificationJob: completed', [
            'notification_id' => $n->id,
            'channel' => $n->channel,
            'sent' => $sent,
            'failed' => $failed,
            'skipped' => $skipped,
        ]);
    }

    private function wrapEmailHtml(string $content): string
    {
        $safe = nl2br(e($content));

        return <<<HTML
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
</head>
<body style="margin:0;padding:0;background:#f6f7fb;">
  <div style="max-width:680px;margin:0 auto;padding:24px;">
    <div style="background:#ffffff;border-radius:12px;padding:20px;font-family:Arial,Helvetica,sans-serif;color:#111;">
      <h2 style="margin:0 0 12px 0;font-size:18px;">JJ's Firewood Perth</h2>
      <div style="font-size:14px;line-height:1.6;">{$safe}</div>
      <hr style="border:none;border-top:1px solid #eee;margin:18px 0;">
      <div style="font-size:12px;color:#666;">
        Reply to this email to contact us: <b>admin@jjsfirewoodperth.com.au</b>
      </div>
    </div>
  </div>
</body>
</html>
HTML;
    }
}