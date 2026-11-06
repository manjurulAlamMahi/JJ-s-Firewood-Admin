<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Checkout | JJ's Firewood Perth</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script type="text/javascript" src="https://{{ $application_id && str_contains($application_id, 'sandbox') ? 'sandbox.' : '' }}web.squarecdn.com/v1/square.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #b85c4c;
            --primary-hover: #a04d3f;
            --bg: #faf9f7;
            --card-bg: #ffffff;
            --text: #1f2937;
            --text-light: #6b7280;
            --border: #e5e7eb;
            --border-light: #f3f4f6;
            --error: #ef4444;
            --success: #10b981;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--bg);
            color: var(--text);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
            line-height: 1.5;
        }

        .checkout-container {
            background: var(--card-bg);
            max-width: 520px;
            width: 100%;
            padding: 32px 40px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid var(--border-light);
        }

        .logo {
            text-align: center;
            margin-bottom: 20px;
            font-weight: 700;
            font-size: 22px;
            color: var(--primary);
        }

        h1 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 6px;
            text-align: center;
        }

        p.subtitle {
            font-size: 14px;
            color: var(--text-light);
            text-align: center;
            margin-bottom: 24px;
        }

        .order-summary {
            background: #f8f7f5;
            padding: 16px 20px;
            border-radius: 8px;
            margin-bottom: 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border: 1px solid var(--border);
        }

        .order-summary span {
            font-weight: 600;
            font-size: 15px;
        }

        .order-summary .amount {
            font-size: 18px;
            color: var(--primary);
        }

        /* Payment Options */
        .payment-options {
            margin-bottom: 24px;
        }

        .payment-option {
            border: 1px solid var(--border);
            border-radius: 8px;
            margin-bottom: 12px;
            overflow: hidden;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .payment-option:hover {
            border-color: #d1d5db;
        }

        .payment-option.selected {
            border-color: var(--primary);
            box-shadow: 0 0 0 1px var(--primary);
        }

        .payment-option-header {
            display: flex;
            align-items: center;
            padding: 16px 18px;
            cursor: pointer;
            background: #fff;
        }

        .payment-option-header input[type="radio"] {
            width: 18px;
            height: 18px;
            margin-right: 14px;
            accent-color: var(--primary);
            cursor: pointer;
            flex-shrink: 0;
        }

        .payment-option-label {
            display: flex;
            align-items: center;
            flex: 1;
            gap: 12px;
        }

        .payment-option-label span {
            font-weight: 500;
            font-size: 15px;
            color: var(--text);
        }

        .payment-icons {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .payment-icons img {
            height: 22px;
            width: auto;
            border-radius: 3px;
        }

        /* Payment Content (expandable) */
        .payment-content {
            display: none;
            padding: 0 18px 18px 50px;
            background: #fafafa;
            border-top: 1px solid var(--border-light);
        }

        .payment-option.selected .payment-content {
            display: block;
        }

        .payment-content p {
            font-size: 14px;
            color: var(--text-light);
            margin-top: 14px;
            line-height: 1.6;
        }

        .payment-content .info-text {
            font-style: italic;
            color: #9ca3af;
        }

        /* Square Card Container */
        #card-container {
            margin-top: 16px;
            min-height: 80px;
        }

        #card-container iframe {
            border-radius: 6px !important;
        }

        /* Place Order Button */
        #payment-button {
            width: 100%;
            padding: 16px;
            background-color: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s, transform 0.1s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        #payment-button:hover {
            background-color: var(--primary-hover);
        }

        #payment-button:active {
            transform: scale(0.99);
        }

        #payment-button:disabled {
            background-color: #9ca3af;
            cursor: not-allowed;
            transform: none;
        }

        #message {
            margin-top: 16px;
            font-size: 14px;
            text-align: center;
            min-height: 20px;
        }

        .error { color: var(--error); }
        .success { color: var(--success); }

        .loader {
            display: none;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: #fff;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Success Page */
        .success-page {
            text-align: center;
            padding: 40px 20px;
        }

        .success-icon {
            width: 80px;
            height: 80px;
            background: var(--success);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
        }

        .success-icon svg {
            width: 40px;
            height: 40px;
            color: #fff;
        }

        .success-page h1 {
            color: var(--success);
            font-size: 28px;
            margin-bottom: 12px;
        }

        .success-page p {
            font-size: 16px;
            color: var(--text-light);
        }

        /* Responsive */
        @media (max-width: 480px) {
            .checkout-container {
                padding: 24px 20px;
            }

            .payment-content {
                padding-left: 18px;
            }

            .payment-option-header {
                padding: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="checkout-container">
        <div id="checkout-form" style="display: block;">
            <div class="logo">JJ's Firewood Perth</div>
            <h1>Secure Payment</h1>
            <p class="subtitle">Complete your order for <strong>{{ $order_data['user_email'] ?? 'your account' }}</strong></p>

            <div class="order-summary">
                <span>Total Amount</span>
                <span class="amount">${{ number_format($order_data['total'], 2) }}</span>
            </div>

            @if(($order_data['type'] ?? '') === 'entry_package')
            <div style="background: #f8f7f5; padding: 16px 20px; border-radius: 8px; margin-bottom: 28px; border: 1px solid var(--border);">
                <div style="font-weight: 600; font-size: 14px; color: var(--text-light); margin-bottom: 12px; text-transform: uppercase; letter-spacing: 0.5px;">Entry Package Details</div>
                
                @if(!empty($order_data['giveaway_name']))
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <span style="font-size: 14px; color: var(--text-light);">Giveaway</span>
                    <span style="font-size: 14px; font-weight: 500;">{{ $order_data['giveaway_name'] }}</span>
                </div>
                @endif

                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <span style="font-size: 14px; color: var(--text-light);">Package</span>
                    <span style="font-size: 14px; font-weight: 500;">{{ $order_data['package_name'] ?? 'Entry Package' }}</span>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <span style="font-size: 14px; color: var(--text-light);">Entries</span>
                    <span style="font-size: 14px; font-weight: 500;">{{ $order_data['entry_quantity'] ?? '-' }} entries</span>
                </div>

                <div style="display: flex; justify-content: space-between; align-items: center; padding-top: 8px; border-top: 1px solid var(--border);">
                    <span style="font-size: 14px; color: var(--text-light);">Price</span>
                    <span style="font-size: 15px; font-weight: 600; color: var(--primary);">${{ number_format($order_data['price'] ?? 0, 2) }}</span>
                </div>
            </div>
            @endif

            <form id="payment-form">
                <!-- Payment Options -->
                <div class="payment-options">
                    @php
                        $firstMethod = null;
                        foreach(['square', 'cash_on_delivery', 'bank_transfer'] as $m) {
                            if(in_array($m, $payment_methods)) {
                                $firstMethod = $m === 'square' ? 'credit_card' : $m;
                                break;
                            }
                        }
                    @endphp

                    @if(in_array('bank_transfer', $payment_methods))
                    <!-- Direct Bank Transfer -->
                    <div class="payment-option" data-method="bank_transfer">
                        <label class="payment-option-header">
                            <input type="radio" name="payment_method" value="bank_transfer" {{ $firstMethod === 'bank_transfer' ? 'checked' : '' }}>
                            <div class="payment-option-label">
                                <span>Direct bank transfer</span>
                            </div>
                        </label>
                        <div class="payment-content">
                            @if(isset($bank_transfer) && $bank_transfer)
                            <div class="bank-details">
                                <p><strong>Account Name:</strong> {{ $bank_transfer->account_holder_name }}</p>
                                <p><strong>BSB:</strong> {{ $bank_transfer->bank_name }}</p>
                                <p><strong>Account Number:</strong> {{ $bank_transfer->account_number }}</p>
                                <p><strong>PAY ID:</strong> {{ $bank_transfer->branch_name }}</p>
                                <p><strong>Note:</strong> {{ $bank_transfer->note }}</p>
                            </div>
                            <p class="info-text" style="margin-top: 12px;">
                                Permanent giveaway entries will not be added until payment is confirmed.
                            </p>
                            @else
                            <p>Bank transfer details not available. Please contact support.</p>
                            @endif
                        </div>
                    </div>
                    @endif

                    @if(in_array('cash_on_delivery', $payment_methods))
                    <!-- Cash/EFT on Delivery -->
                    <div class="payment-option" data-method="cash_on_delivery">
                        <label class="payment-option-header">
                            <input type="radio" name="payment_method" value="cash_on_delivery" {{ $firstMethod === 'cash_on_delivery' ? 'checked' : '' }}>
                            <div class="payment-option-label">
                                <span>Cash/EFT on delivery</span>
                            </div>
                        </label>
                        <div class="payment-content">
                            <p>Pay on delivery with eftpos or cash upon delivery.</p>
                        </div>
                    </div>
                    @endif

                    @if(in_array('square', $payment_methods))
                    <!-- Credit Card (Square) -->
                    <div class="payment-option" data-method="credit_card">
                        <label class="payment-option-header">
                            <input type="radio" name="payment_method" value="credit_card" {{ $firstMethod === 'credit_card' ? 'checked' : '' }}>
                            <div class="payment-option-label">
                                <span>Credit Card</span>
                                <div class="payment-icons">
                                    <img src="/images/payment-icons/visa.svg" alt="Visa">
                                    <img src="/images/payment-icons/mastercard.svg" alt="Mastercard">
                                    <img src="/images/payment-icons/amex.svg" alt="American Express">
                                    <img src="/images/payment-icons/jcb.svg" alt="JCB">
                                </div>
                            </div>
                        </label>
                        <div class="payment-content">
                            <p>Pay securely using your credit card.</p>
                            <div id="card-container"></div>
                        </div>
                    </div>
                    @endif

                    @if(empty($payment_methods))
                        <div class="alert alert-warning">
                            No payment methods are currently available. Please contact support.
                        </div>
                    @endif
                </div>

                <button id="payment-button" type="button">
                    <span id="button-text">Place order</span>
                    <div class="loader" id="button-loader"></div>
                </button>
                <div id="message"></div>
            </form>
        </div>

        <div id="success-page" class="success-page" style="display: none;">
            <div class="success-icon">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>
            <h1>Payment Successful!</h1>
            <p>Your order has been placed successfully.</p>
        </div>
    </div>

    <script type="text/javascript">
        const appId = '{{ $application_id }}';
        const locationId = '{{ $location_id }}';
        let card = null;
        let squareInitialized = false;

        // Payment option selection handling
        document.querySelectorAll('.payment-option').forEach(option => {
            const radio = option.querySelector('input[type="radio"]');
            
            option.addEventListener('click', (e) => {
                if (e.target.tagName !== 'INPUT') {
                    radio.checked = true;
                }
                
                // Update selected state
                document.querySelectorAll('.payment-option').forEach(opt => {
                    opt.classList.remove('selected');
                });
                option.classList.add('selected');

                // Initialize Square if Credit Card selected and not yet initialized
                if (radio.value === 'credit_card' && !squareInitialized) {
                    initializePayments();
                }

                // Update button text based on payment method
                updateButtonText(radio.value);
            });

            // Set initial selected state
            if (radio.checked) {
                option.classList.add('selected');
            }
        });

        function updateButtonText(method) {
            const buttonText = document.getElementById('button-text');
            if (method === 'credit_card') {
                buttonText.textContent = 'Place order';
            } else {
                buttonText.textContent = 'Place order';
            }
        }

        async function initializePayments() {
            // Prevent multiple initializations
            if (squareInitialized && card) return card;
            if (window.isInitializing) return;

            window.isInitializing = true;

            // Clear any existing card elements to prevent duplicates
            const container = document.getElementById('card-container');
            if (container) {
                container.innerHTML = '';
            }

            if (!window.Square) {
                document.getElementById('message').innerText = 'Square.js failed to load.';
                window.isInitializing = false;
                return null;
            }

            let payments;
            try {
                payments = window.Square.payments(appId, locationId);
            } catch (e) {
                const statusContainer = document.getElementById('message');
                statusContainer.className = 'error';
                statusContainer.innerText = 'Authentication failed. Please check credentials.';
                window.isInitializing = false;
                return null;
            }

            try {
                card = await payments.card();
                await card.attach('#card-container');
                squareInitialized = true;
            } catch (e) {
                console.error('Initializing Card failed', e);
                window.isInitializing = false;
                return null;
            }

            window.isInitializing = false;
            return card;
        }

        async function createPayment(token, paymentMethod) {
            const body = JSON.stringify({
                source_id: token,
                payment_method: paymentMethod,
                shipping_address_id: '{{ $order_data["shipping_address_id"] ?? "" }}',
                preferred_delivery_date: '{{ $order_data["preferred_delivery_date"] ?? "" }}',
                notes: '{{ $order_data["notes"] ?? "" }}',
                session_id: '{{ $session_id }}'
            });

            const paymentResponse = await fetch('/checkout/process', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body,
            });

            if (paymentResponse.ok) {
                return paymentResponse.json();
            }

            const errorBody = await paymentResponse.json();
            
            // Check if message is a JSON string by mistake
            let errorMessage = errorBody.message || 'Payment failed';
            try {
                // If the message itself is a stringified JSON object (which shouldn't happen with our recent backend fix, but just in case)
                const parsed = JSON.parse(errorMessage);
                if (parsed && typeof parsed === 'object') {
                     // Extract the first error message if possible
                     errorMessage = parsed.message || parsed.detail || (parsed.errors && parsed.errors[0] ? parsed.errors[0].detail : 'An error occurred');
                }
            } catch (e) {
                // Not JSON, just normal text
            }
            
            throw new Error(errorMessage);
        }

        async function handlePaymentMethodSubmission(event) {
            event.preventDefault();

            const button = document.getElementById('payment-button');
            const buttonText = document.getElementById('button-text');
            const loader = document.getElementById('button-loader');
            const message = document.getElementById('message');
            
            const selectedMethod = document.querySelector('input[name="payment_method"]:checked');
            if (!selectedMethod) {
                message.className = 'error';
                message.innerText = 'Please select a payment method.';
                return;
            }

            const paymentMethod = selectedMethod.value;

            try {
                // Disable button and show loader
                button.disabled = true;
                buttonText.style.display = 'none';
                loader.style.display = 'block';
                message.innerText = '';

                let token = null;

                if (paymentMethod === 'credit_card') {
                    // Check if card is initialized
                    if (!card) {
                        await initializePayments();
                    }
                    
                    if (!card) {
                        throw new Error('Payment card failed to initialize. Please refresh the page and try again.');
                    }

                    const result = await card.tokenize();
                    if (result.status === 'OK') {
                        token = result.token;
                    } else {
                        // Just show a simple error for tokenization failure
                        throw new Error('Please check your card details and try again.');
                    }
                }

                // Process payment
                const paymentResult = await createPayment(token, paymentMethod);
                
                console.log('Payment successful:', paymentResult);
                
                // Change URL to success page
                const sessionId = '{{ $session_id }}';
                const successUrl = `/checkout/${sessionId}/success`;
                window.history.pushState({ page: 'success' }, 'Success', successUrl);
                
                // Hide payment form and show success message
                document.getElementById('checkout-form').style.display = 'none';
                document.getElementById('success-page').style.display = 'block';
                
            } catch (e) {
                button.disabled = false;
                buttonText.style.display = 'block';
                loader.style.display = 'none';
                message.className = 'error';
                message.innerText = e.message;
            }
        }

        document.addEventListener('DOMContentLoaded', async () => {
            const cardButton = document.getElementById('payment-button');
            
            // Initialize Square if credit card is selected by default
            const selectedMethod = document.querySelector('input[name="payment_method"]:checked');
            if (selectedMethod && selectedMethod.value === 'credit_card') {
                await initializePayments();
                if (!card) {
                    document.getElementById('message').className = 'error';
                    document.getElementById('message').innerText = 'Failed to initialize payment. Please refresh and try again.';
                }
            }
            
            cardButton.addEventListener('click', async (event) => {
                await handlePaymentMethodSubmission(event);
            });
        });
    </script>
</body>
</html>
