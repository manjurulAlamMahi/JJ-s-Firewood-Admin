<?php

namespace App\Jobs;

use App\Models\NotificationQueue;
use App\Models\NotificationRun;
use App\Services\Brevo\BrevoTransactionalService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class DispatchNotificationTestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries = 1;

    public function __construct(
        public int $notificationId,
        public string $channel, // email|sms
        public string $testRecipient // email address OR phone number
    ) {}

    public function handle(BrevoTransactionalService $brevo): void
    {
        $n = NotificationQueue::findOrFail($this->notificationId);

        try {
            if ($this->channel === 'email') {
                $subject = $n->title ?: "JJ's Firewood Perth";
                $html = $this->wrapEmailHtml((string)($n->message ?? ''));

                $brevo->sendEmail(
                    $this->testRecipient,
                    $subject,
                    $html,
                    strip_tags((string)($n->message ?? ''))
                );

                NotificationRun::create([
                    'notification_id' => $n->id,
                    'channel' => 'email',
                    'status' => 'sent',
                    'provider' => 'brevo',
                    'user_id' => null,
                    'recipient' => $this->testRecipient,
                    'error' => null,
                ]);

                return;
            }

            if ($this->channel === 'sms') {
                $e164 = $brevo->normalizeAuToE164($this->testRecipient);
                if (!$e164) {
                    NotificationRun::create([
                        'notification_id' => $n->id,
                        'channel' => 'sms',
                        'status' => 'failed',
                        'provider' => 'brevo',
                        'user_id' => null,
                        'recipient' => $this->testRecipient,
                        'error' => 'invalid_test_phone_format',
                    ]);
                    return;
                }

                $brevo->sendSms($e164, (string)($n->message ?? ''));

                NotificationRun::create([
                    'notification_id' => $n->id,
                    'channel' => 'sms',
                    'status' => 'sent',
                    'provider' => 'brevo',
                    'user_id' => null,
                    'recipient' => $e164,
                    'error' => null,
                ]);

                return;
            }

            // Should never happen
            Log::warning('DispatchNotificationTestJob: unknown channel', [
                'notification_id' => $n->id,
                'channel' => $this->channel
            ]);

        } catch (Throwable $e) {
            NotificationRun::create([
                'notification_id' => $n->id,
                'channel' => $this->channel,
                'status' => 'failed',
                'provider' => 'brevo',
                'user_id' => null,
                'recipient' => $this->testRecipient,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
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
