<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SquareWebhookSignatureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['square.webhook_signature_key' => 'test_signature_key']);
        config(['square.webhook_url' => 'https://example.test/api/webhooks/square']);
    }

    public function test_rejects_webhook_with_missing_signature(): void
    {
        $response = $this->postJson('/api/webhooks/square', ['type' => 'payment.updated', 'data' => ['object' => []]]);
        $response->assertStatus(401);
    }

    public function test_rejects_webhook_with_invalid_signature(): void
    {
        $response = $this->withHeaders(['x-square-hmacsha256-signature' => 'wrong'])
            ->postJson('/api/webhooks/square', ['type' => 'payment.updated', 'data' => ['object' => []]]);
        $response->assertStatus(401);
    }

    public function test_accepts_webhook_with_valid_signature(): void
    {
        $payload = json_encode(['type' => 'payment.updated', 'data' => ['object' => ['id' => 'p1', 'status' => 'PENDING']]]);
        $url = config('square.webhook_url');
        $expected = base64_encode(hash_hmac('sha256', $url . $payload, config('square.webhook_signature_key'), true));

        $response = $this->call('POST', '/api/webhooks/square', [], [], [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_X_SQUARE_HMACSHA256_SIGNATURE' => $expected], $payload);

        $response->assertStatus(200);
    }
}
