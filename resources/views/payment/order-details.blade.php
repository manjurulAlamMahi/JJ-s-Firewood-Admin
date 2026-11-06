<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details | JJ's Firewood Perth</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #b85c4c;
            --primary-hover: #a04d3f;
            --bg: #f4f7f9;
            --card-bg: #ffffff;
            --text: #1f2937;
            --text-light: #6b7280;
            --border: #e5e7eb;
            --border-light: #f3f4f6;
            --success: #10b981;
            --warning: #f59e0b;
            --error: #ef4444;
            --info: #3b82f6;
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
            min-height: 100vh;
            padding: 20px;
            line-height: 1.6;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
        }

        .logo {
            text-align: center;
            margin-bottom: 30px;
            font-weight: 700;
            font-size: 24px;
            color: var(--primary);
        }

        .card {
            background: var(--card-bg);
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid var(--border-light);
            margin-bottom: 20px;
            overflow: hidden;
        }

        .card-header {
            background: linear-gradient(135deg, #1a2a6c, #b21f1f, #fdbb2d);
            padding: 24px 30px;
            color: #fff;
        }

        .card-header h1 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .card-header p {
            opacity: 0.9;
            font-size: 14px;
        }

        .card-body {
            padding: 30px;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            text-transform: capitalize;
        }

        .status-pending { background: #fef3c7; color: #d97706; }
        .status-processing { background: #dbeafe; color: #2563eb; }
        .status-shipped { background: #e0e7ff; color: #4f46e5; }
        .status-delivered { background: #d1fae5; color: #059669; }
        .status-completed { background: #d1fae5; color: #059669; }
        .status-cancelled { background: #fee2e2; color: #dc2626; }
        .status-refunded { background: #fce7f3; color: #db2777; }

        .payment-paid { background: #d1fae5; color: #059669; }
        .payment-pending { background: #fef3c7; color: #d97706; }
        .payment-failed { background: #fee2e2; color: #dc2626; }
        .payment-refunded { background: #fce7f3; color: #db2777; }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .info-item {
            padding: 16px;
            background: #f8fafc;
            border-radius: 8px;
            border: 1px solid var(--border);
        }

        .info-item label {
            display: block;
            font-size: 12px;
            color: var(--text-light);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 6px;
        }

        .info-item span {
            font-size: 15px;
            font-weight: 600;
            color: var(--text);
        }

        .section-title {
            font-size: 16px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .section-title svg {
            width: 20px;
            height: 20px;
            color: var(--primary);
        }

        .products-table {
            width: 100%;
            border-collapse: collapse;
        }

        .products-table th {
            text-align: left;
            padding: 12px 16px;
            background: #f8fafc;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-light);
            border-bottom: 1px solid var(--border);
        }

        .products-table td {
            padding: 16px;
            border-bottom: 1px solid var(--border-light);
        }

        .products-table tr:last-child td {
            border-bottom: none;
        }

        .product-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .product-image {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            object-fit: cover;
            background: #f1f5f9;
        }

        .product-name {
            font-weight: 500;
            color: var(--text);
        }

        .price-summary {
            background: #f8fafc;
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
            border: 1px solid var(--border);
        }

        .price-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            font-size: 14px;
        }

        .price-row.total {
            border-top: 2px solid var(--border);
            margin-top: 10px;
            padding-top: 16px;
            font-size: 18px;
            font-weight: 700;
        }

        .price-row.discount {
            color: var(--success);
        }

        .price-row.refund {
            color: var(--error);
        }

        .address-box {
            background: #f8fafc;
            border-radius: 8px;
            padding: 20px;
            border: 1px solid var(--border);
        }

        .address-box p {
            margin: 4px 0;
            font-size: 14px;
        }

        .address-box .name {
            font-weight: 600;
            font-size: 16px;
            margin-bottom: 8px;
        }

        .refund-notice {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 8px;
            padding: 16px 20px;
            margin-top: 20px;
        }

        .refund-notice h4 {
            color: #dc2626;
            margin-bottom: 4px;
            font-size: 14px;
        }

        .refund-notice p {
            font-size: 14px;
            color: #7f1d1d;
        }

        .footer {
            text-align: center;
            padding: 30px;
            color: var(--text-light);
            font-size: 13px;
        }

        .footer a {
            color: var(--primary);
            text-decoration: none;
        }

        @media (max-width: 600px) {
            .card-body {
                padding: 20px;
            }
            
            .products-table th:nth-child(3),
            .products-table td:nth-child(3) {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">JJ's Firewood Perth</div>
        
        <div class="card">
            <div class="card-header">
                <h1>Order #{{ $order->order_number }}</h1>
                <p>Placed on {{ $order->created_at->format('F d, Y \a\t h:i A') }}</p>
            </div>
            
            <div class="card-body">
                <!-- Status Row -->
                <div class="info-grid">
                    <div class="info-item">
                        <label>Order Status</label>
                        <span class="status-badge status-{{ $order->order_status }}">
                            {{ ucfirst($order->order_status) }}
                        </span>
                    </div>
                    <div class="info-item">
                        <label>Payment Status</label>
                        <span class="status-badge payment-{{ $order->payment_status }}">
                            {{ ucfirst($order->payment_status) }}
                        </span>
                    </div>
                    <div class="info-item">
                        <label>Payment Method</label>
                        <span>{{ ucwords(str_replace('_', ' ', $order->payment_method)) }}</span>
                    </div>
                    @if($order->preferred_delivery_date)
                    <div class="info-item">
                        <label>Expected Delivery</label>
                        <span>{{ \Carbon\Carbon::parse($order->preferred_delivery_date)->format('M d, Y') }}</span>
                    </div>
                    @endif
                </div>

                <!-- Refund Notice (if applicable) -->
                @if($order->refund_amount > 0)
                <div class="refund-notice">
                    <h4>Refund Processed</h4>
                    <p>A refund of <strong>${{ number_format($order->refund_amount, 2) }}</strong> has been processed for this order.
                    @if($order->refund_reason) Reason: {{ $order->refund_reason }}@endif</p>
                </div>
                @endif

                <!-- Products Section -->
                <div style="margin-top: 30px;">
                    <h3 class="section-title">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path></svg>
                        Order Items
                    </h3>
                    <table class="products-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th style="text-align: center;">Qty</th>
                                <th style="text-align: right;">Price</th>
                                <th style="text-align: right;">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($order->orderItems as $item)
                            <tr>
                                <td>
                                    <div class="product-info">
                                        @if($item->product && $item->product->image)
                                        <img src="{{ asset('uploads/product_image/' . $item->product->image) }}" alt="{{ $item->product_name }}" class="product-image">
                                        @elseif($item->giveaway && $item->giveaway->image)
                                        <img src="{{ asset($item->giveaway->image) }}" alt="{{ $item->product_name }}" class="product-image">
                                        @else
                                        <div class="product-image"></div>
                                        @endif
                                        <span class="product-name">{{ $item->product_name }}</span>
                                    </div>
                                </td>
                                <td style="text-align: center;">{{ $item->quantity }}</td>
                                <td style="text-align: right;">${{ number_format($item->price, 2) }}</td>
                                <td style="text-align: right;">${{ number_format($item->subtotal ?? $item->total_price, 2) }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <div class="price-summary">
                        <div class="price-row">
                            <span>Subtotal</span>
                            <span>${{ number_format($order->subtotal, 2) }}</span>
                        </div>
                        @if($order->delivery_fee > 0)
                        <div class="price-row">
                            <span>Delivery Fee</span>
                            <span>${{ number_format($order->delivery_fee, 2) }}</span>
                        </div>
                        @endif
                        @if($order->discount > 0)
                        <div class="price-row discount">
                            <span>Discount</span>
                            <span>-${{ number_format($order->discount, 2) }}</span>
                        </div>
                        @endif
                        @if($order->tax > 0)
                        <div class="price-row">
                            <span>Tax</span>
                            <span>${{ number_format($order->tax, 2) }}</span>
                        </div>
                        @endif
                        <div class="price-row total">
                            <span>Total</span>
                            <span>${{ number_format($order->total, 2) }}</span>
                        </div>
                        @if($order->refund_amount > 0)
                        <div class="price-row refund">
                            <span>Refund Issued</span>
                            <span>-${{ number_format($order->refund_amount, 2) }}</span>
                        </div>
                        @endif
                    </div>
                </div>

                <!-- Shipping Address -->
                @if($order->address)
                <div style="margin-top: 30px;">
                    <h3 class="section-title">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                        Shipping Address
                    </h3>
                    <div class="address-box">
                        <p class="name">{{ $order->address->full_name }}</p>
                        <p>{{ $order->address->address }}</p>
                        @if($order->address->suburb)
                        <p>{{ $order->address->suburb }}</p>
                        @endif
                        <p>{{ $order->address->city }}, {{ $order->address->state }} {{ $order->address->zip_code }}</p>
                        @if($order->address->phone)
                        <p>Phone: {{ $order->address->phone }}</p>
                        @endif
                    </div>
                </div>
                @endif

                <!-- Order Notes -->
                @if($order->notes)
                <div style="margin-top: 30px;">
                    <h3 class="section-title">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        Order Notes
                    </h3>
                    <div class="address-box">
                        <p>{{ $order->notes }}</p>
                    </div>
                </div>
                @endif
            </div>
        </div>

        <div class="footer">
            <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
            <p>Questions? <a href="mailto:support@jjsfirewoodperth.com.au">Contact Support</a></p>
        </div>
    </div>
</body>
</html>
