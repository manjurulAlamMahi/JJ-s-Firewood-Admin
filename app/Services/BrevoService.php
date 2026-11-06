<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BrevoService
{
    private string $baseUrl;
    private string $apiKey;

    public function __construct()
    {
        $this->baseUrl = 'https://api.brevo.com/v3';
        $this->apiKey  = (string) config('services.brevo.api_key');
    }

    private function client()
    {
        return Http::withHeaders([
            'accept' => 'application/json',
            'content-type' => 'application/json',
            'api-key' => $this->apiKey, // Brevo uses `api-key` header :contentReference[oaicite:2]{index=2}
        ])->timeout(20);
    }

    /**
     * Transactional email
     * Endpoint docs reference /v3/smtp/email :contentReference[oaicite:3]{index=3}
     */
    public function sendEmail(string $toEmail, string $subject, string $textBody, ?string $htmlBody = null): array
    {
        $fromEmail = config('services.brevo.email_from');
        $fromName  = config('services.brevo.email_from_name');
        $replyTo   = config('services.brevo.email_reply_to');

        $payload = [
            'sender' => [
                'email' => $fromEmail,
                'name'  => $fromName,
            ],
            'to' => [
                ['email' => $toEmail],
            ],
            'subject' => $subject,
            'textContent' => $textBody,
        ];

        if ($replyTo) {
            $payload['replyTo'] = ['email' => $replyTo];
        } else {
            $payload['replyTo'] = ['email' => 'admin@jjsfirewoodperth.com.au'];
        }

        if ($htmlBody) {
            $payload['htmlContent'] = $htmlBody;
        }

        $res = $this->client()->post($this->baseUrl . '/smtp/email', $payload);

        if (!$res->successful()) {
            Log::warning('Brevo sendEmail failed', [
                'status' => $res->status(),
                'body' => $res->body(),
            ]);
        }

        return [
            'ok' => $res->successful(),
            'status' => $res->status(),
            'body' => $res->json(),
            'raw' => $res->body(),
        ];
    }

    /**
     * Transactional SMS
     * Endpoint: POST https://api.brevo.com/v3/transactionalSMS/send :contentReference[oaicite:4]{index=4}
     */
    public function sendSms(string $phoneE164, string $content, string $type = 'transactional', ?string $tag = null): array
    {
        $sender = config('services.brevo.sms_sender');

        $payload = [
            'sender' => $sender,
            'recipient' => $phoneE164,
            'content' => $content,
            'type' => $type,
        ];

        if ($tag) $payload['tag'] = $tag;

        $res = $this->client()->post($this->baseUrl . '/transactionalSMS/send', $payload);

        if (!$res->successful()) {
            Log::warning('Brevo sendSms failed', [
                'status' => $res->status(),
                'body' => $res->body(),
            ]);
        }

        return [
            'ok' => $res->successful(),
            'status' => $res->status(),
            'body' => $res->json(),
            'raw' => $res->body(),
        ];
    }
}
