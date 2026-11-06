{{--
    Order Refunded — extends the JJ-branded base layout.
    All wording preserved verbatim from the original template.
    Has the extra refund-specific data: $refundType, $refundAmount, $payment.
--}}
@extends('emails.layouts.jjs-base')

@section('title', 'Refund Processed')
@section('header_badge', $refundType === 'full' ? 'Full refund' : 'Partial refund')
@section('header_icon', '💵')
@section('header_title', 'Refund Processed')
@section('header_subtitle', 'Order #' . $order->order_number)

@section('content')
    <p>Hi {{ $order->user->name ?? 'Customer' }},</p>
    <p>Your refund for order <strong>#{{ $order->order_number }}</strong> has been successfully processed.</p>

    {{-- Refund amount callout --}}
    <div class="card-cream" style="text-align: center;">
        <div style="margin-bottom: 8px;">
            <span class="status-pill status-success">
                {{ $refundType === 'full' ? 'FULL REFUND' : 'PARTIAL REFUND' }}
            </span>
        </div>
        <div style="font-size: 14px; color: #6e4f3a; margin-top: 12px;">Amount refunded</div>
        <div style="font-size: 28px; font-weight: 800; color: #9b2d2d; margin-top: 4px;">
            ${{ number_format($refundAmount, 2) }}
        </div>
    </div>

    <h3>Order Summary</h3>

    {{-- Use the shared items table (with images, category, subcategory) --}}
    <table class="summary-table" width="100%" cellpadding="0" cellspacing="0" border="0">
        @foreach($order->items as $item)
        <tr>
            <td colspan="2" style="padding: 14px 0; border-bottom: 1px solid #f0e8d0;">
                <table width="100%" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                        {{-- Product image --}}
                        <td style="vertical-align: top; padding-right: 14px; width: 70px;">
                            @if($item->product?->image)
                                <img src="{{ str_starts_with($item->product->image, 'http') ? $item->product->image : rtrim(config('app.url'), '/') . '/' . ltrim($item->product->image, '/') }}"
                                     alt="{{ $item->product_name }}"
                                     width="64" height="64"
                                     style="display: block; width: 64px; height: 64px; object-fit: cover; border-radius: 8px; border: 1px solid #e6dbb9;">
                            @else
                                <div style="width: 64px; height: 64px; background: #faf6ec; border: 1px solid #e6dbb9; border-radius: 8px; text-align: center; line-height: 64px; color: #c89538; font-size: 11px;">No img</div>
                            @endif
                        </td>

                        {{-- Product info --}}
                        <td style="vertical-align: top;">
                            <div style="font-weight: 600; color: #3e1f1f; font-size: 15px;">{{ $item->product_name }}</div>
                            @if($item->product?->category?->name)
                                <div style="margin: 4px 0 0 0; line-height: 1.3;">
                                    <span style="font-size: 11px; color: #9b2d2d; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">
                                        {{ $item->product->category->name }}
                                    </span>
                                    @if($item->product?->subcategory?->name)
                                        <span style="font-size: 11px; color: #c89538; margin: 0 4px;">•</span>
                                        <span style="font-size: 11px; color: #6e4f3a; font-weight: 500;">{{ $item->product->subcategory->name }}</span>
                                    @endif
                                </div>
                            @endif
                            @if($item->product?->size_info)
                                <div style="color: #6e4f3a; font-size: 13px; margin-top: 4px;">Wood Type: {{ $item->product->size_info }}</div>
                            @endif
                            @if($item->product?->burn_quantity_info)
                                <div style="color: #6e4f3a; font-size: 13px;">Load Size: {{ $item->product->burn_quantity_info }}</div>
                            @endif
                        </td>

                        {{-- Quantity + price --}}
                        <td style="vertical-align: top; text-align: right; white-space: nowrap; padding-left: 16px; width: 100px;">
                            <div style="color: #6e4f3a; font-size: 13px;">×{{ $item->quantity }}</div>
                            <div style="color: #3e1f1f; font-weight: 600; margin-top: 4px;">${{ number_format((float) ($item->subtotal ?? $item->total_price ?? 0), 2) }}</div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        @endforeach
    </table>

    <table class="summary-table" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top: 16px;">
        <tr>
            <td class="label">Original Subtotal</td>
            <td class="value">${{ number_format($order->subtotal, 2) }}</td>
        </tr>
        @if(($order->delivery_fee ?? 0) > 0)
        <tr>
            <td class="label">Delivery Fee</td>
            <td class="value">${{ number_format($order->delivery_fee, 2) }}</td>
        </tr>
        @endif
        <tr class="total-row">
            <td class="label">Original Total</td>
            <td class="value">${{ number_format($order->total, 2) }}</td>
        </tr>
        <tr>
            <td class="label" style="padding-top: 14px; color: #b91c1c;">Amount Refunded (Current)</td>
            <td class="value" style="padding-top: 14px; color: #b91c1c; font-weight: 700;">-${{ number_format($refundAmount, 2) }}</td>
        </tr>
        @if($refundType === 'partial')
            <tr>
                <td class="label">Total Refunded thus far</td>
                <td class="value" style="color: #b91c1c;">-${{ number_format((float) ($payment->refund_amount ?? 0), 2) }}</td>
            </tr>
            <tr class="total-row">
                <td class="label" style="color: #6e4f3a;">Remaining Balance</td>
                <td class="value" style="color: #6e4f3a;">${{ number_format((float) ($payment->remaining_amount ?? 0), 2) }}</td>
            </tr>
        @endif
    </table>

    @include('emails.partials.contact-block', [
        'address' => $order->address,
        'userEmail' => optional($order->user)->email,
        'title' => 'Shipping address',
    ])

    <p style="margin-top: 25px;">The funds should appear in your account within 3-5 business days, depending on your bank's processing times.</p>

    <p>If you have any questions about this refund, please reply to this email or contact support.</p>
@endsection
