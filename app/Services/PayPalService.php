<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\PaymentSetting;

class PayPalService
{
    private string $base;
    private string $clientId;
    private string $secret;

    public function __construct()
    {
        $s = PaymentSetting::current();
        $this->clientId = $s->paypal_client_id ?: config('paypal.client_id', '');
        $this->secret   = $s->paypal_secret   ?: config('paypal.secret', '');
        $env = strtolower(($s->paypal_client_id ? $s->paypal_environment : null) ?: config('paypal.environment', 'sandbox'));
        $this->base = $env === 'production'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    private function token(): ?string
    {
        $res = Http::asForm()
            ->withBasicAuth($this->clientId, $this->secret)
            ->post("{$this->base}/v1/oauth2/token", ['grant_type' => 'client_credentials']);
        if (!$res->successful()) {
            Log::error('PayPal oauth failed', ['status' => $res->status(), 'body' => $res->body()]);
            return null;
        }
        return $res->json('access_token');
    }

    public function createOrder(float $amount, string $referenceId, string $returnUrl, string $cancelUrl): array
    {
        $token = $this->token();
        if (!$token) { return ['success' => false, 'message' => 'PayPal auth failed']; }

        $res = Http::withToken($token)->post("{$this->base}/v2/checkout/orders", [
            'intent' => 'CAPTURE',
            'purchase_units' => [[
                'reference_id' => $referenceId,
                'amount' => ['currency_code' => 'AUD', 'value' => number_format($amount, 2, '.', '')],
            ]],
            'application_context' => [
                'brand_name' => "JJ's Firewood",
                'user_action' => 'PAY_NOW',
                'shipping_preference' => 'NO_SHIPPING',
                'return_url' => $returnUrl,
                'cancel_url' => $cancelUrl,
            ],
        ]);

        if (!$res->successful()) {
            Log::error('PayPal create order failed', ['body' => $res->body()]);
            return ['success' => false, 'message' => 'Failed to create PayPal order'];
        }

        $approval = collect($res->json('links', []))->firstWhere('rel', 'approve')['href'] ?? null;
        return [
            'success' => true,
            'paypal_order_id' => $res->json('id'),
            'approval_url' => $approval,
        ];
    }

    public function captureOrder(string $paypalOrderId): array
    {
        $token = $this->token();
        if (!$token) { return ['success' => false, 'message' => 'PayPal auth failed']; }

        $res = Http::withToken($token)->post("{$this->base}/v2/checkout/orders/{$paypalOrderId}/capture");
        if (!$res->successful()) {
            Log::error('PayPal capture failed', ['body' => $res->body()]);
            return ['success' => false, 'message' => 'PayPal capture failed'];
        }

        $status = $res->json('status');
        $captureId = $res->json('purchase_units.0.payments.captures.0.id');
        return [
            'success' => $status === 'COMPLETED',
            'status' => $status,
            'capture_id' => $captureId,
        ];
    }
}
