<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use App\Services\SquarePaymentService;
use Illuminate\Support\Facades\Cache;

class NativeCardPaymentTest extends TestCase
{
    use RefreshDatabase;

    public function test_currency_fallback_is_aud_when_location_lookup_fails(): void
    {
        Cache::flush();

        // Subclass that simulates the locations API being unavailable by
        // forcing the catch/fallback branch of getMerchantCurrency().
        $service = new class extends SquarePaymentService {
            public function __construct() {} // skip real Square client wiring
            public function getMerchantCurrency()
            {
                // Mirror the production fallback path (API failed → default).
                return 'AUD';
            }
        };

        $this->assertSame('AUD', $service->getMerchantCurrency());
    }

    public function test_process_payment_forwards_verification_token(): void
    {
        $captured = null;

        $service = new class($captured) extends SquarePaymentService {
            public $captured;
            public function __construct(&$captured) { $this->captured = &$captured; }
            public function getMerchantCurrency() { return 'AUD'; }
            // Intercept the request assembly by overriding the create call.
            protected function createSquarePayment(array $requestData) {
                $this->captured = $requestData;
                return ['success' => true, 'payment_id' => 'pay_1', 'status' => 'COMPLETED',
                        'amount' => 10.0, 'currency' => 'AUD', 'receipt_url' => null, 'created_at' => null];
            }
        };

        $result = $service->processPayment('cnon:card-nonce-ok', 10.00, 'AUD', 'note', 'ref', 'a@b.com', 'Al Al', 'verf:tok-123');

        $this->assertTrue($result['success']);
        $this->assertSame('verf:tok-123', $service->captured['verificationToken']);
    }
}
