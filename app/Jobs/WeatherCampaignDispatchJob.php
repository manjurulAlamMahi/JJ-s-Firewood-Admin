<?php

namespace App\Jobs;

use App\Helpers\FirebaseHelper;
use App\Models\WeatherCampaign;
use App\Models\WeatherCampaignSend;
use App\Services\Brevo\BrevoTransactionalService;
use App\Services\WeatherCampaignAudienceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class WeatherCampaignDispatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1200;
    public int $tries = 1;

    public function __construct(
        public int $campaignId,
        public array $matchedZipCodes,
        public string $triggerDate,
        public array $weatherSnapshot = []
    ) {}

    public function handle(WeatherCampaignAudienceService $audienceService, BrevoTransactionalService $brevo): void
    {
        $campaign = WeatherCampaign::find($this->campaignId);
        if (!$campaign || $campaign->status !== 'active') {
            return;
        }

        $channels = array_intersect($campaign->channels ?? [], ['push', 'email']);
        if (empty($channels) || empty($this->matchedZipCodes)) {
            return;
        }

        $thresholds = $campaign->capacity_thresholds ?? [];
        if (!empty($thresholds['min_product_stock'])) {
            $productId = $thresholds['min_product_stock']['product_id'] ?? null;
            $minQty = (int) ($thresholds['min_product_stock']['min_quantity'] ?? 0);
            
            if ($productId) {
                $product = \App\Models\Product::find($productId);
                if (!$product || !$product->isInStock($minQty)) {
                    Log::info('WeatherCampaignDispatchJob: aborted due to insufficient stock.', [
                        'campaign_id' => $campaign->id,
                        'product_id' => $productId,
                        'min_quantity' => $minQty
                    ]);
                    return; // Prevent entire dispatch
                }
            }
        }

        // Audience Resolution
        $users = $audienceService->resolve($campaign);
        if ($users->isEmpty()) {
            return;
        }
        
        $users->load('shippingAddresses', 'notificationPreferences');

        foreach ($channels as $channel) {
            $this->sendChannel($campaign, $channel, $users, $brevo);
        }
    }

    private function sendChannel(WeatherCampaign $campaign, string $channel, \Illuminate\Database\Eloquent\Collection $users, BrevoTransactionalService $brevo): void
    {
        $alreadySentUserIds = WeatherCampaignSend::where('weather_campaign_id', $campaign->id)
            ->where('channel', $channel)
            ->where('trigger_date', $this->triggerDate)
            ->pluck('user_id')
            ->all();

        $users = $users->reject(fn ($u) => in_array($u->id, $alreadySentUserIds, true));

        foreach ($users as $user) {
            try {
                if ($channel === 'push') {
                    if (empty($user->fcm_token)) {
                        $this->logSend($campaign, $user->id, $channel, 'skipped', 'missing_token');
                        continue;
                    }

                    $rawTitle = $campaign->channel_content['push']['title'] ?? $campaign->title ?? '';
                    $rawBody = $campaign->channel_content['push']['body'] ?? $campaign->message ?? '';
                    $deepLink = $campaign->channel_content['push']['deep_link'] ?? null;

                    $title = $this->interpolate((string) $rawTitle, $user, $this->weatherSnapshot);
                    $body = $this->interpolate((string) $rawBody, $user, $this->weatherSnapshot);

                    $options = [
                        'screen' => 'campaign',
                        'data' => ['campaign_id' => (string) $campaign->id],
                    ];

                    if (!empty($deepLink)) {
                        if (filter_var($deepLink, FILTER_VALIDATE_URL) || strpos($deepLink, '://') !== false) {
                            $options['link'] = $deepLink;
                            unset($options['screen']);
                        } else {
                            $options['screen'] = $deepLink;
                        }
                    }

                    FirebaseHelper::sendNotification($user->fcm_token, $title, $body, $options);
                } elseif ($channel === 'email') {
                    if (empty($user->email)) {
                        $this->logSend($campaign, $user->id, $channel, 'skipped', 'missing_email');
                        continue;
                    }

                    $rawSubject = $campaign->channel_content['email']['subject'] ?? $campaign->title ?? "JJ's Firewood Perth";
                    $hasNewBody = isset($campaign->channel_content['email']['body_html']);
                    $rawBody = $campaign->channel_content['email']['body_html'] ?? $campaign->message ?? '';

                    $subject = $this->interpolate((string) $rawSubject, $user, $this->weatherSnapshot);
                    $body = $this->interpolate((string) $rawBody, $user, $this->weatherSnapshot);

                    $brevo->sendEmail(
                        to: $user->email,
                        subject: $subject,
                        htmlContent: $this->wrapEmailHtml($body, $hasNewBody)
                    );
                }

                $this->logSend($campaign, $user->id, $channel, 'sent');
            } catch (Throwable $e) {
                Log::warning('WeatherCampaignDispatchJob: send failed', [
                    'campaign_id' => $campaign->id,
                    'user_id' => $user->id,
                    'channel' => $channel,
                    'error' => $e->getMessage(),
                ]);
                $this->logSend($campaign, $user->id, $channel, 'failed', mb_substr($e->getMessage(), 0, 500));
            }
        }
    }

    private function interpolate(string $content, \App\Models\User $user, array $snapshot): string
    {
        $address = $user->shippingAddresses->where('is_default', 1)->first() ?? $user->shippingAddresses->first();
        $firstName = explode(' ', trim($user->name ?? 'there'))[0];

        $replacements = [
            '{{first_name}}' => $firstName ?: 'there',
            '{{forecast_max}}' => $snapshot['temp_max_c'] ?? '',
            '{{forecast_start_date}}' => $snapshot['date'] ?? '',
            '{{rain_probability}}' => isset($snapshot['pop']) ? ($snapshot['pop'] * 100) . '%' : '',
            '{{suburb}}' => $address ? ($address->suburb ?? 'your area') : 'your area',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }

    private function logSend(WeatherCampaign $campaign, int $userId, string $channel, string $status, ?string $error = null): void
    {
        try {
            WeatherCampaignSend::create([
                'weather_campaign_id' => $campaign->id,
                'user_id' => $userId,
                'channel' => $channel,
                'trigger_date' => $this->triggerDate,
                'status' => $status,
                'weather_snapshot' => $this->weatherSnapshot,
                'error' => $error,
                'sent_at' => $status === 'sent' ? Carbon::now() : null,
            ]);
        } catch (Throwable $e) {
            Log::info('WeatherCampaignDispatchJob: duplicate send suppressed by unique index', [
                'campaign_id' => $campaign->id,
                'user_id' => $userId,
                'channel' => $channel,
            ]);
        }
    }

    private function wrapEmailHtml(string $content, bool $isRawHtml = false): string
    {
        $safe = $isRawHtml ? $content : nl2br(e($content));

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
      <div style="font-size:14px;line-height:1.6;">{\$safe}</div>
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
