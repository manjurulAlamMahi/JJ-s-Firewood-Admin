<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class PushService
{
    protected $client;
    protected $serverKey;
    protected $endpoint;

    public function __construct()
    {
        $this->client = new Client();
        $this->serverKey = config('services.fcm.server_key') ?? env('FCM_SERVER_KEY');
        $this->endpoint = env('FCM_ENDPOINT', 'https://fcm.googleapis.com/fcm/send');
    }


    public function sendToToken(string $token, array $payload): bool
    {
        try {
            if (!$token || !$this->serverKey) {
                Log::warning('FCM: Missing token or server key');
                return false;
            }

            $body = array_merge(['to' => $token], $payload);

            $response = $this->client->post($this->endpoint, [
                'headers' => [
                    'Authorization' => "key={$this->serverKey}",
                    'Content-Type' => 'application/json',
                ],
                'json' => $body,
                'timeout' => 5,
            ]);

            if ($response->getStatusCode() === 200) {
                Log::info('FCM notification sent successfully', ['token' => substr($token, 0, 10) . '...']);
                return true;
            }

            Log::warning('FCM send failed', ['status' => $response->getStatusCode()]);
            return false;
        } catch (GuzzleException $e) {
            Log::error('FCM send exception', [
                'error' => $e->getMessage(),
                'token' => substr($token ?? '', 0, 10) . '...',
            ]);
            return false;
        } catch (\Exception $e) {
            Log::error('FCM unexpected error', ['error' => $e->getMessage()]);
            return false;
        }
    }


    public function sendToMultiple(array $tokens, array $payload): array
    {
        $results = [];
        foreach ($tokens as $token) {
            $results[$token] = $this->sendToToken($token, $payload);
        }
        return $results;
    }
}
