<?php

namespace App\Services;

use Square\SquareClient;
use Square\Types\Money;
use Square\Payments\Requests\CreatePaymentRequest;
use Square\Exceptions\ApiException;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;


class SquarePaymentServiceMock
{
    protected $client;
    protected $locationId;
    protected $testMode;

    public function __construct()
    {
        $this->locationId = config('square.location_id');
        $this->testMode = env('SQUARE_TEST_MODE', false);
        $accessToken = config('square.access_token');

        if (empty($accessToken) && !$this->testMode) {
            throw new \Exception('SQUARE_ACCESS_TOKEN is not configured');
        }

        $environment = config('square.environment', 'sandbox');
        $baseUrl = $environment === 'production'
            ? 'https://connect.squareup.com'
            : 'https://connect.squareupsandbox.com';

        if (!$this->testMode) {
            $this->client = new \Square\SquareClient(
                $accessToken,
                null,
                ['baseUrl' => $baseUrl]
            );
        }

        \Log::info('Square Client Initialized', [
            'environment' => $environment,
            'baseUrl' => $baseUrl,
            'location_id' => $this->locationId,
            'test_mode' => $this->testMode,
            'token_prefix' => substr($accessToken, 0, 20),
        ]);
    }

    public function processPayment($sourceId, $amount, $currency = 'USD', $note = null)
    {
        try {
            Log::info('Process Payment Called', [
                'sourceId' => $sourceId,
                'amount' => $amount,
                'currency' => $currency,
                'test_mode' => $this->testMode,
            ]);

            if ($amount <= 0) {
                return ['success' => false, 'message' => 'Amount must be greater than zero'];
            }

            if (empty($sourceId)) {
                return ['success' => false, 'message' => 'Source ID is required'];
            }

            if ($this->testMode) {
                Log::info('TEST MODE: Returning mock payment response');
                return [
                    'success' => true,
                    'payment_id' => 'test_' . Str::uuid(),
                    'status' => 'COMPLETED',
                    'amount' => $amount,
                    'currency' => $currency,
                    'created_at' => now()->toIso8601String(),
                    'test_mode' => true,
                    'message' => 'TEST MODE: Mock payment response',
                ];
            }

            $amountMoney = new Money();
            $amountMoney->setAmount((int)($amount * 100));
            $amountMoney->setCurrency($currency);

            $requestData = [
                'sourceId' => $sourceId,
                'idempotencyKey' => Str::uuid()->toString(),
                'amountMoney' => $amountMoney,
                'autocomplete' => true,
                'locationId' => $this->locationId,
            ];

            if ($note) {
                $requestData['note'] = $note;
            }

            Log::info('Square Payment Request Data:', $requestData);

            $body = new CreatePaymentRequest($requestData);
            $apiResponse = $this->client->payments->create($body);

            if ($apiResponse->isSuccess()) {
                $payment = $apiResponse->getResult()->getPayment();

                Log::info('Square Payment Success', [
                    'payment_id' => $payment->getId(),
                    'status' => $payment->getStatus(),
                    'amount' => $amount
                ]);

                return [
                    'success' => true,
                    'payment_id' => $payment->getId(),
                    'status' => $payment->getStatus(),
                    'amount' => $amount,
                    'currency' => $currency,
                ];
            } else {
                $errors = $apiResponse->getErrors();
                $errorMessage = isset($errors[0]) ? $errors[0]->getDetail() : 'Payment failed';

                Log::error('Square payment failed', [
                    'errors' => $errors
                ]);

                return [
                    'success' => false,
                    'message' => $errorMessage,
                ];
            }
        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();

            Log::error('Square payment exception', [
                'error' => $errorMsg,
            ]);

            return [
                'success' => false,
                'message' => 'Payment processing error: ' . $errorMsg
            ];
        }
    }
}
