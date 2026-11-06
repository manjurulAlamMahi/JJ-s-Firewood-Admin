<?php

namespace App\Services\Brevo;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;

class BrevoClient
{
    private Client $client;

    public function __construct()
    {
        $apiKey = config('services.brevo.api_key');

        $this->client = new Client([
            'base_uri' => 'https://api.brevo.com/v3/',
            'timeout'  => 20,
            'headers'  => [
                'accept' => 'application/json',
                'content-type' => 'application/json',
                'api-key' => $apiKey,
            ],
        ]);
    }

    public function post(string $path, array $json): array
    {
        $res = $this->client->post($path, ['json' => $json]);
        $body = (string) $res->getBody();

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            Log::warning('Brevo: non-json response', ['path' => $path, 'body' => $body]);
            return ['_raw' => $body];
        }

        return $decoded;
    }
}
