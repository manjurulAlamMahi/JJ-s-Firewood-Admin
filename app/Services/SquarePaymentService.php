<?php

namespace App\Services;

use Square\SquareClient;
use Square\Environments;
use Square\Types\Money;
use Square\Types\Order;
use Square\Types\OrderLineItem;
use Square\Types\CheckoutOptions;
use Square\Types\PrePopulatedData;
use Square\Checkout\PaymentLinks\Requests\CreatePaymentLinkRequest;
use Square\Payments\Requests\CreatePaymentRequest;
use Square\Types\OrderLineItemDiscount;
use Square\Types\OrderLineItemDiscountScope;
use Square\Refunds\Requests\RefundPaymentRequest;
use Square\Payments\Requests\CancelPaymentsRequest;
use Square\Payments\Requests\CompletePaymentRequest;
use Square\Payments\Requests\ListPaymentsRequest;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Square\Locations\Requests\GetLocationsRequest;
use Square\Exceptions\SquareApiException;
use Exception;

class SquarePaymentService
{
    protected $client;
    protected $locationId;
    protected $testMode;
    protected $environment;

    public function __construct()
    {
        $s = \App\Models\PaymentSetting::current();

        $this->locationId  = $s->square_location_id  ?: config('square.location_id');
        $accessToken       = $s->square_access_token ?: config('square.access_token');
        // Column defaults to 'sandbox', so only trust it when the admin panel actually holds the credentials.
        $this->environment = strtolower(($s->square_access_token ? $s->square_environment : null) ?: config('square.environment', 'sandbox'));
        $this->testMode    = env('SQUARE_TEST_MODE', false);

        if (empty($accessToken)) {
            throw new Exception('Square access token is not configured');
        }

        if (empty($this->locationId)) {
            throw new Exception('Square location id is not configured');
        }

        $baseUrl = match ($this->environment) {
            'production' => Environments::Production->value,
            default => Environments::Sandbox->value,
        };

        $this->client = new SquareClient(
            token: $accessToken,
            options: [
                'baseUrl' => $baseUrl
            ]
        );

        Log::info('SquarePaymentService initialized', [
            'environment' => $this->environment,
            'location_id' => $this->locationId,
            'test_mode' => $this->testMode,
        ]);
    }

    public function getOrder($orderId)
    {
        try {
            $request = new \Square\Orders\Requests\GetOrdersRequest([
                'orderId' => $orderId
            ]);
            
            $apiResponse = $this->client->orders->get($request);

            if ($apiResponse->getErrors()) {
                 $errors = $apiResponse->getErrors();
                 return [
                    'success' => false,
                    'message' => 'Failed to retrieve order',
                    'errors' => $errors
                 ];
            }

            // The apiResponse IS the GetOrderResponse object already
            $orderData = $apiResponse->getOrder();
            
            if (!$orderData) {
                 return ['success' => false, 'message' => 'Order not found in response'];
            }

            return [
                'success' => true,
                'order' => [
                    'id' => $orderData->getId(),
                    'state' => $orderData->getState(),
                    'total_money' => [
                        'amount' => $orderData->getTotalMoney()->getAmount(),
                        'currency' => $orderData->getTotalMoney()->getCurrency(),
                    ],
                    'tenders' => $orderData->getTenders(),
                ]
            ];

        } catch (Exception $e) {
            Log::error('Get order exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function createPaymentLink($order, $options = [])
    {
        try {
            $referenceId = isset($options['session_id']) ? $options['session_id'] : (string)$order->id;
            
            // Auto-detect currency if not provided (though Order usually has amounts, we need valid currency code)
            $currency = $this->getMerchantCurrency();
            
            Log::info('Creating payment link for order', [
                'order_id' => $order->id ?? 'N/A',
                'order_number' => $order->order_number ?? 'N/A',
                'total' => $order->total,
                'reference_id' => $referenceId,
                'currency' => $currency
            ]);


            $lineItems = [];

            foreach ($order->orderItems as $item) {
                $lineItems[] = new OrderLineItem([
                    'name' => $item->product_name,
                    'quantity' => (string)$item->quantity,
                    'basePriceMoney' => new Money([
                        'amount' => (int)($item->price * 100),
                        'currency' => $currency
                    ])
                ]);
            }

            if ($order->delivery_fee > 0) {
                $lineItems[] = new OrderLineItem([
                    'name' => 'Delivery Fee',
                    'quantity' => '1',
                    'basePriceMoney' => new Money([
                        'amount' => (int)($order->delivery_fee * 100),
                        'currency' => $currency
                    ])
                ]);
            }

            $discounts = [];
            if ($order->discount > 0) {
                
                $discounts[] = new OrderLineItemDiscount([
                    'name' => 'Discount Applied',
                    'amountMoney' => new Money([
                        'amount' => (int)($order->discount * 100), 
                        'currency' => $currency
                    ]),
                    'scope' => OrderLineItemDiscountScope::Order->value
                ]);
            }

            if ($order->tax > 0) {
                $lineItems[] = new OrderLineItem([
                    'name' => 'Tax',
                    'quantity' => '1',
                    'basePriceMoney' => new Money([
                        'amount' => (int)($order->tax * 100),
                        'currency' => $currency
                    ])
                ]);
            }

            $orderDataConfig = [
                'locationId' => $this->locationId,
                'lineItems' => $lineItems,
                'referenceId' => $referenceId,
            ];
            
            if (!empty($discounts)) {
                $orderDataConfig['discounts'] = $discounts;
            }

            $orderData = new Order($orderDataConfig);

            $frontendUrl = config('app.frontend_url', 'http://localhost:3000');

            $redirectUrl = $options['redirectUrl'] ?? ($frontendUrl . '/payment/success?' . (isset($options['session_id']) 
                ? 'session_id=' . $options['session_id'] 
                : 'order_id=' . $order->id));

            $checkoutOptions = new CheckoutOptions([
                'redirectUrl' => $redirectUrl,
                'askForShippingAddress' => false,
                'merchantSupportEmail' => config('mail.from.address', 'support@example.com'),
            ]);

            $prePopulatedData = new PrePopulatedData([
                'buyerEmail' => $order->user->email ?? null,
            ]);

            $idempotencyKey = uniqid('order_' . $referenceId . '_' . time() . '_');

            $body = new CreatePaymentLinkRequest([
                'idempotencyKey' => $idempotencyKey,
                'order' => $orderData,
                'checkoutOptions' => $checkoutOptions,
                'prePopulatedData' => $prePopulatedData,
            ]);

            $apiResponse = $this->client->checkout->paymentLinks->create(request: $body);

            $paymentLink = $apiResponse->getPaymentLink();

            if ($paymentLink) {
                Log::info('Square payment link created successfully', [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'payment_link_id' => $paymentLink->getId(),
                    'payment_link_url' => $paymentLink->getUrl(),
                    'square_order_id' => $paymentLink->getOrderId(),
                ]);

                return [
                    'success' => true,
                    'payment_link' => $paymentLink->getUrl(),
                    'payment_link_id' => $paymentLink->getId(),
                    'square_order_id' => $paymentLink->getOrderId(),
                    'created_at' => $paymentLink->getCreatedAt(),
                ];
            }

            $errors = $apiResponse->getErrors() ?? [];

            Log::error('Square payment link creation failed', [
                'order_id' => $order->id,
                'errors' => array_map(function ($error) {
                    return [
                        'category' => $error->getCategory(),
                        'code' => $error->getCode(),
                        'detail' => $error->getDetail(),
                    ];
                }, $errors),
            ]);

            return [
                'success' => false,
                'message' => $errors[0]->getDetail() ?? 'Failed to create payment link',
                'errors' => $errors,
            ];
        } catch (Exception $e) {
            Log::error('Square payment link exception', [
                'order_id' => $order->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Payment link creation error: ' . $e->getMessage(),
            ];
        }
    }

    public function getPaymentLink($paymentLinkId)
    {
        try {
            $apiResponse = $this->client->checkout->paymentLinks->get(id: $paymentLinkId);

            $paymentLink = $apiResponse->getPaymentLink();

            if ($paymentLink) {
                return [
                    'success' => true,
                    'payment_link' => $paymentLink,
                    'url' => $paymentLink->getUrl(),
                    'order_id' => $paymentLink->getOrderId(),
                    'created_at' => $paymentLink->getCreatedAt(),
                ];
            }

            $errors = $apiResponse->getErrors() ?? [];
            return [
                'success' => false,
                'message' => $errors[0]->getDetail() ?? 'Payment link not found',
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error retrieving payment link: ' . $e->getMessage(),
            ];
        }
    }


    public function processPayment($sourceId, $amount, $currency = null, $note = null, $referenceId = null, $buyerEmail = null, $buyerName = null, $verificationToken = null)
    {
        try {
            if ($amount <= 0) {
                return ['success' => false, 'message' => 'Amount must be greater than zero'];
            }
            if (empty($sourceId)) {
                return ['success' => false, 'message' => 'Source ID is required'];
            }
            if (empty($currency)) {
                $currency = $this->getMerchantCurrency();
            }

            $amountMoney = new Money(['amount' => (int)($amount * 100), 'currency' => $currency]);

            $requestData = [
                'sourceId' => $sourceId,
                'idempotencyKey' => Str::uuid()->toString(),
                'amountMoney' => $amountMoney,
                'autocomplete' => true,
                'locationId' => $this->locationId,
                'note' => $note,
            ];

            if ($referenceId) { $requestData['referenceId'] = $referenceId; }
            if ($buyerEmail) { $requestData['buyerEmailAddress'] = $buyerEmail; }
            if (!empty($verificationToken)) { $requestData['verificationToken'] = $verificationToken; }
            if ($buyerName) {
                $nameParts = explode(' ', trim($buyerName), 2);
                $addressData = [];
                if (isset($nameParts[0])) { $addressData['firstName'] = $nameParts[0]; }
                if (isset($nameParts[1])) { $addressData['lastName'] = $nameParts[1]; }
                if (!empty($addressData)) {
                    $requestData['billingAddress'] = new \Square\Types\Address($addressData);
                }
            }

            return $this->createSquarePayment($requestData);
        } catch (SquareApiException $e) {
            return $this->mapSquareException($e);
        } catch (Exception $e) {
            Log::error('Payment processing exception', ['error' => $e->getMessage()]);
            return ['success' => false, 'message' => 'Payment processing error. Please try again or contact support.'];
        }
    }

    protected function createSquarePayment(array $requestData)
    {
        $body = new CreatePaymentRequest($requestData);
        $apiResponse = $this->client->payments->create(request: $body);
        $payment = $apiResponse->getPayment();

        if ($payment) {
            return [
                'success' => true,
                'payment_id' => $payment->getId(),
                'status' => $payment->getStatus(),
                'amount' => $requestData['amountMoney']->getAmount() / 100,
                'currency' => $requestData['amountMoney']->getCurrency(),
                'receipt_url' => $payment->getReceiptUrl(),
                'created_at' => $payment->getCreatedAt(),
            ];
        }

        $errors = $apiResponse->getErrors() ?? [];
        return [
            'success' => false,
            'message' => $this->getFriendlyErrorMessage($errors[0]->getCode() ?? 'UNKNOWN', $errors[0]->getDetail() ?? 'Payment failed'),
            'error_code' => $errors[0]->getCode() ?? 'PAYMENT_FAILED',
            'errors' => $errors,
        ];
    }

    protected function mapSquareException(SquareApiException $e)
    {
        $errors = $e->getErrors();
        $code = 'UNKNOWN';
        $detail = 'Payment processing failed';
        if (!empty($errors) && isset($errors[0])) {
            $code = $errors[0]->getCode() ?? 'UNKNOWN';
            $detail = $errors[0]->getDetail() ?? 'Payment failed';
        } elseif ($e->getBody()) {
            $body = json_decode($e->getBody(), true);
            if (isset($body['errors'][0])) {
                $code = $body['errors'][0]['code'] ?? 'UNKNOWN';
                $detail = $body['errors'][0]['detail'] ?? 'Payment failed';
            }
        }
        Log::error('Square API Exception', ['code' => $code, 'detail' => $detail, 'raw' => $e->getMessage()]);
        return ['success' => false, 'message' => $this->getFriendlyErrorMessage($code, $detail)];
    }


    public function getPayment($paymentId)
    {
        try {
            if ($this->testMode) {
                return [
                    'success' => true,
                    'payment' => [
                        'id' => $paymentId,
                        'status' => 'COMPLETED',
                        'amount' => 100.00,
                        'currency' => 'AUD',
                        'receipt_url' => 'https://squareupsandbox.com/receipt/test_' . $paymentId,
                        'created_at' => now()->toIso8601String(),
                        'note' => 'Test payment',
                        'source_type' => 'CARD',
                    ],
                ];
            }

            $apiResponse = $this->client->payments->get(paymentId: $paymentId);

            $payment = $apiResponse->getPayment();

            if ($payment) {
                return [
                    'success' => true,
                    'payment' => [
                        'id' => $payment->getId(),
                        'status' => $payment->getStatus(),
                        'amount' => $payment->getAmountMoney()->getAmount() / 100,
                        'currency' => $payment->getAmountMoney()->getCurrency(),
                        'receipt_url' => $payment->getReceiptUrl(),
                        'created_at' => $payment->getCreatedAt(),
                        'note' => $payment->getNote(),
                        'source_type' => $payment->getSourceType(),
                    ],
                ];
            }

            $errors = $apiResponse->getErrors() ?? [];
            return [
                'success' => false,
                'message' => $errors[0]->getDetail() ?? 'Payment not found'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error retrieving payment: ' . $e->getMessage()
            ];
        }
    }


    public function refundPayment($paymentId, $amount, $currency = null, $reason = null)
    {
        try {
            if ($amount <= 0) {
                return [
                    'success' => false,
                    'message' => 'Refund amount must be greater than zero'
                ];
            }
            
            // Auto-detect currency if not provided
            if (empty($currency)) {
                $currency = $this->getMerchantCurrency();
            }

            Log::info('Processing refund', [
                'payment_id' => $paymentId,
                'amount' => $amount,
                'reason' => $reason,
            ]);

            if ($this->testMode) {
                return [
                    'success' => true,
                    'refund_id' => 'test_refund_' . Str::uuid(),
                    'status' => 'COMPLETED',
                    'amount' => $amount,
                    'currency' => $currency,
                    'refund' => [
                        'id' => 'test_refund_' . Str::uuid(),
                        'status' => 'COMPLETED',
                        'amount' => $amount,
                        'currency' => $currency,
                    ]
                ];
            }

            $amountMoney = new Money([
                'amount' => (int)($amount * 100),
                'currency' => $currency,
            ]);

            $idempotencyKey = Str::uuid()->toString();

            $body = new RefundPaymentRequest([
                'idempotencyKey' => $idempotencyKey,
                'amountMoney' => $amountMoney,
                'paymentId' => $paymentId,
                'reason' => $reason,
            ]);


            $apiResponse = $this->client->refunds->refundPayment(request: $body);

            $refund = $apiResponse->getRefund();

            if ($refund) {
                Log::info('Refund processed successfully', [
                    'refund_id' => $refund->getId(),
                    'status' => $refund->getStatus(),
                    'amount' => $amount,
                ]);

                return [
                    'success' => true,
                    'refund_id' => $refund->getId(),
                    'status' => $refund->getStatus(),
                    'amount' => $amount,
                    'currency' => $currency,
                    'created_at' => $refund->getCreatedAt(),
                    'refund' => [
                        'id' => $refund->getId(),
                        'status' => $refund->getStatus(),
                        'amount' => $amount,
                        'currency' => $currency,
                    ]
                ];
            }

            $errors = $apiResponse->getErrors() ?? [];

            Log::error('Refund processing failed', [
                'payment_id' => $paymentId,
                'errors' => $errors,
            ]);

            return [
                'success' => false,
                'message' => $errors[0]->getDetail() ?? 'Refund failed',
                'errors' => $errors,
            ];
        } catch (Exception $e) {
            Log::error('Refund processing exception', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Error processing refund: ' . $e->getMessage()
            ];
        }
    }

    public function cancelPayment($paymentId)
    {
        try {
            Log::info('Cancelling payment', [
                'payment_id' => $paymentId,
            ]);

            if ($this->testMode) {
                return [
                    'success' => true,
                    'payment_id' => $paymentId,
                    'status' => 'CANCELED',
                    'message' => 'Payment cancelled successfully',
                ];
            }

            $request = new CancelPaymentsRequest(['paymentId' => $paymentId]);
            $apiResponse = $this->client->payments->cancel(request: $request);

            $payment = $apiResponse->getPayment();

            if ($payment) {
                Log::info('Payment cancelled successfully', [
                    'payment_id' => $payment->getId(),
                    'status' => $payment->getStatus(),
                ]);

                return [
                    'success' => true,
                    'payment_id' => $payment->getId(),
                    'status' => $payment->getStatus()
                ];
            }

            $errors = $apiResponse->getErrors() ?? [];
            return [
                'success' => false,
                'message' => $errors[0]->getDetail() ?? 'Cancel payment failed'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Cancel payment error: ' . $e->getMessage()
            ];
        }
    }

    public function completePayment($paymentId)
    {
        try {
            $request = new CompletePaymentRequest(['paymentId' => $paymentId]);
            $apiResponse = $this->client->payments->complete(request: $request);

            $payment = $apiResponse->getPayment();

            if ($payment) {
                return [
                    'success' => true,
                    'payment_id' => $payment->getId(),
                    'status' => $payment->getStatus()
                ];
            }

            $errors = $apiResponse->getErrors() ?? [];
            return [
                'success' => false,
                'message' => $errors[0]->getDetail() ?? 'Complete payment failed'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Complete payment error: ' . $e->getMessage()
            ];
        }
    }


    public function listPayments($limit = 10, $cursor = null)
    {
        try {
            $request = new ListPaymentsRequest([
                'locationId' => $this->locationId,
                'limit' => $limit,
                'cursor' => $cursor
            ]);

            $pager = $this->client->payments->list(request: $request);
            
            $payments = [];
            foreach ($pager->getPages() as $page) {
                $payments = $page->getItems();
                break; 
            }
             
             if ($payments !== null) {
                return [
                    'success' => true,
                    'payments' => $payments,
                    'cursor' => null, 
                ];
            }

            return [
                'success' => true,
                'payments' => [],
                'cursor' => null,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Error listing payments: ' . $e->getMessage()
            ];
        }
    }


    public function verifyConnection()
    {
        try {
            $apiResponse = $this->client->locations->list();

            $locations = $apiResponse->getLocations() ?? [];

            if ($locations !== null) {
                Log::info('Square connection verified', [
                    'locations_count' => count($locations),
                    'current_location_id' => $this->locationId,
                ]);

                return [
                    'success' => true,
                    'locations_count' => count($locations),
                    'current_location_id' => $this->locationId,
                    'environment' => $this->environment,
                ];
            }

            $errors = $apiResponse->getErrors() ?? [];
            return [
                'success' => false,
                'message' => $errors[0]->getDetail() ?? 'Square connection failed'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Square connection exception: ' . $e->getMessage()
            ];
        }
    }


    public function getApplicationId()
    {
        return \App\Models\PaymentSetting::current()->square_application_id ?: config('square.application_id');
    }


    public function getLocationId()
    {
        return $this->locationId;
    }


    public function getEnvironment()
    {
        return $this->environment;
    }


    public function isTestMode()
    {
        return $this->testMode;
    }
    public function getMerchantCurrency()
    {
        $currency = \Illuminate\Support\Facades\Cache::remember('square_merchant_currency_v5', 60 * 60, function () {
            try {
                Log::info('Fetching Square Merchant Currency from API...');

                $request = new GetLocationsRequest(['locationId' => $this->locationId]);
                $apiResponse = $this->client->locations->get($request);

                if (empty($apiResponse->getErrors())) {
                    $location = $apiResponse->getLocation();
                    $currency = $location->getCurrency();
                    Log::info('Fetched Square Location Currency', [
                        'currency' => $currency,
                        'name' => $location->getName()
                    ]);
                    return $currency ?? 'AUD';
                }

                Log::error('Failed to retrieve Square location currency', [
                    'errors' => $apiResponse->getErrors()
                ]);

                return 'AUD';
            } catch (Exception $e) {
                Log::error('Error fetching Square currency: ' . $e->getMessage());
                return 'AUD';
            }
        });

        Log::info('Square Merchant Currency being used:', ['currency' => $currency]);
        return $currency;
    }
    private function getFriendlyErrorMessage($code, $detail)
    {
        $map = [
            'GENERIC_DECLINE' => 'Card declined. Please check your card information or try a different card.',
            'CVV_FAILURE' => 'The CVV number is incorrect. Please check your card details.',
            'ADDRESS_VERIFICATION_FAILURE' => 'Postal code check failed. Please check your billing details.',
            'INVALID_EXPIRATION' => 'The card expiration date is invalid.',
            'INSUFFICIENT_FUNDS' => 'Insufficient funds on the card.',
            'LOST_OR_STOLEN' => 'This card has been reported lost or stolen.',
            'INVALID_CARD_DATA' => 'The card data is invalid. Please check your card number.',
            'PAN_FAILURE' => 'Invalid card number. Please check your card details.',
            'EXPIRATION_FAILURE' => 'Card expired. Please use a valid card.',
            'CARD_NOT_SUPPORTED' => 'This card type is not supported.',
            'CURRENCY_MISMATCH' => 'Currency mismatch. Please ensure you are using a card relevant to this store.',
        ];

        return $map[$code] ?? $detail ?? 'Payment failed. Please try again.';
    }
}
