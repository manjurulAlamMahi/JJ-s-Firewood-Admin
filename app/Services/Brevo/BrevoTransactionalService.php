<?php

namespace App\Services\Brevo;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BrevoTransactionalService
{
    private string $apiKey;
    private string $baseUrl = 'https://api.brevo.com/v3';

    public function __construct()
    {
        $this->apiKey = config('services.brevo.api_key') ?? env('BREVO_API_KEY', '');
        
        if (empty($this->apiKey)) {
            throw new \RuntimeException('BREVO_API_KEY is not configured');
        }
    }

    public function sendEmail(
        string $to,
        string $subject,
        string $htmlContent,
        ?string $textContent = null,
        array $params = []
    ): array {
        $payload = [
            'sender' => [
                'name' => $params['senderName'] ?? env('BREVO_SENDER_NAME', "JJ's Firewood Perth"),
                'email' => $params['senderEmail'] ?? env('BREVO_SENDER_EMAIL', 'admin@jjsfirewoodperth.com.au'),
            ],
            'to' => [
                ['email' => $to]
            ],
            'subject' => $subject,
            'htmlContent' => $htmlContent,
        ];

        if ($textContent) {
            $payload['textContent'] = $textContent;
        }

        if (!empty($params['replyTo'])) {
            $payload['replyTo'] = ['email' => $params['replyTo']];
        } else {
            $payload['replyTo'] = ['email' => 'admin@jjsfirewoodperth.com.au'];
        }

        if (!empty($params['templateId'])) {
            $payload['templateId'] = (int) $params['templateId'];
            unset($payload['htmlContent']);
            unset($payload['textContent']);
            unset($payload['subject']);
        }

        if (!empty($params['templateParams'])) {
            $payload['params'] = $params['templateParams'];
        }

        $response = Http::withHeaders([
            'api-key' => $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post("{$this->baseUrl}/smtp/email", $payload);

        if (!$response->successful()) {
            $error = $response->json('message') ?? $response->body();
            Log::error('Brevo email failed', ['error' => $error, 'to' => $to]);
            throw new \Exception("Brevo email failed: {$error}");
        }

        return $response->json();
    }

    public function sendSms(string $to, string $message, ?string $sender = null): array
    {
        if (!str_starts_with($to, '+')) {
            $to = '+' . ltrim($to, '+');
        }

        $payload = [
            'type' => 'transactional',
            'recipient' => $to,
            'content' => $message,
        ];

        if ($sender) {
            $payload['sender'] = substr($sender, 0, 11);
        } else {
            $payload['sender'] = env('BREVO_SMS_SENDER', 'JJsFirewood');
        }

        $response = Http::withHeaders([
            'api-key' => $this->apiKey,
            'Content-Type' => 'application/json',
        ])->post("{$this->baseUrl}/transactionalSMS/sms", $payload);

        if (!$response->successful()) {
            $error = $response->json('message') ?? $response->body();
            Log::error('Brevo SMS failed', ['error' => $error, 'to' => $to]);
            throw new \Exception("Brevo SMS failed: {$error}");
        }

        return $response->json();
    }

    public function getEmailTemplates(int $limit = 50): array
    {
        $response = Http::withHeaders([
            'api-key' => $this->apiKey,
        ])->get("{$this->baseUrl}/smtp/templates", [
            'limit' => $limit,
            'offset' => 0,
        ]);

        if (!$response->successful()) {
            Log::warning('Failed to fetch Brevo templates');
            return [];
        }

        return $response->json('templates') ?? [];
    }

    public function normalizeAuToE164(string $phone): ?string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        if (strlen($phone) === 10 && str_starts_with($phone, '0')) {
            return '+61' . substr($phone, 1);
        }

        if (strlen($phone) === 11 && str_starts_with($phone, '61')) {
            return '+' . $phone;
        }

        if (str_starts_with($phone, '+61') && strlen($phone) === 12) {
            return $phone;
        }

        return null;
    }

    public function normalizePhoneNumbers(array $phones): array
    {
        $normalized = [];
        
        foreach ($phones as $phone) {
            $e164 = $this->normalizeAuToE164($phone);
            if ($e164) {
                $normalized[] = $e164;
            }
        }

        return array_unique($normalized);
    }

    public function countSmsCharacters(string $message): array
    {
        $length = mb_strlen($message);
        
        $gsm7Pattern = "@^[A-Za-z0-9 \\r\\n@£\$¥èéùìòÇØøÅå\u0394_\u03A6\u0393\u039B\u03A9\u03A0\u03A8\u03A3\u0398\u039EÆæßÉ!\"#\$%&'()*+,\\-./:;<=>?¡ÄÖÑÜ§¿äöñüà^{}\\\\\\[~\\]|\u20AC]*$@u";
        
        if (preg_match($gsm7Pattern, $message)) {
            $encoding = 'GSM-7';
            $singleLimit = 160;
            $multiLimit = 153;
        } else {
            $encoding = 'UCS-2';
            $singleLimit = 70;
            $multiLimit = 67;
        }

        if ($length <= $singleLimit) {
            $parts = 1;
        } else {
            $parts = (int) ceil($length / $multiLimit);
        }

        return [
            'length' => $length,
            'parts' => $parts,
            'encoding' => $encoding,
            'limit' => $parts === 1 ? $singleLimit : ($multiLimit * $parts),
        ];
    }
}