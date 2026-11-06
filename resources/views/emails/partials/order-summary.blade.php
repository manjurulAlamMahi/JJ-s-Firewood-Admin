{{--
    Order summary table — items + totals.
    Shows product image, category, subcategory, size/load info per item — matches
    the detail level of the original placed/delivered/cancelled emails.

    Expects $order (the Order model).
    Optional: $itemsRelation — defaults to 'items'. Cancelled emails use 'orderItems'.
--}}
@php
    $itemsRelation = $itemsRelation ?? 'items';
    if ($itemsRelation === 'orderItems' && isset($order->orderItems)) {
        $items = $order->orderItems;
    } else {
        $items = $order->items ?? collect();
    }
@endphp

<h3>Order summary</h3>

<table class="summary-table" width="100%" cellpadding="0" cellspacing="0" border="0">
    @foreach($items as $item)
    <tr>
        <td colspan="2" style="padding: 14px 0; border-bottom: 1px solid #f0e8d0;">
            <table width="100%" cellpadding="0" cellspacing="0" border="0">
                <tr>
                    {{-- Product image --}}
                    <td style="vertical-align: top; padding-right: 14px; width: 70px;">
                        @if($item->product?->image)
                            <img src="{{ str_starts_with($item->product->image, 'http') ? $item->product->image : rtrim(config('app.url'), '/') . '/' . ltrim($item->product->image, '/') }}"
                                 alt="{{ $item->product_name ?? $item->product?->name }}"
                                 width="64" height="64"
                                 style="display: block; width: 64px; height: 64px; object-fit: cover; border-radius: 8px; border: 1px solid #e6dbb9;">
                        @else
                            <div style="width: 64px; height: 64px; background: #faf6ec; border: 1px solid #e6dbb9; border-radius: 8px; text-align: center; line-height: 64px; color: #c89538; font-size: 11px;">No img</div>
                        @endif
                    </td>

                    {{-- Product info --}}
                    <td style="vertical-align: top;">
                        <div style="font-weight: 600; color: #3e1f1f; font-size: 15px; line-height: 1.3;">
                            {{ $item->product_name ?? $item->product?->name ?? 'Product' }}
                        </div>

                        @if($item->product?->category?->name)
                            <div style="margin: 4px 0 0 0; line-height: 1.3;">
                                <span style="font-size: 11px; color: #9b2d2d; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">
                                    {{ $item->product->category->name }}
                                </span>
                                @if($item->product?->subcategory?->name)
                                    <span style="font-size: 11px; color: #c89538; margin: 0 4px;">•</span>
                                    <span style="font-size: 11px; color: #6e4f3a; font-weight: 500;">
                                        {{ $item->product->subcategory->name }}
                                    </span>
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
                        <div style="color: #6e4f3a; font-size: 13px;">×{{ number_format((float) ($item->quantity ?? 0), 0) }}</div>
                        <div style="color: #9b2d2d; font-weight: 700; font-size: 15px; margin-top: 4px;">
                            ${{ number_format((float) ($item->subtotal ?? $item->total_price ?? 0), 2) }}
                        </div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    @endforeach
</table>

<table class="summary-table" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin-top: 16px;">
    <tr>
        <td class="label">Subtotal</td>
        <td class="value">${{ number_format((float) ($order->subtotal ?? 0), 2) }}</td>
    </tr>

    @if(($order->delivery_fee ?? 0) > 0)
        <tr>
            <td class="label">Delivery fee</td>
            <td class="value">${{ number_format((float) $order->delivery_fee, 2) }}</td>
        </tr>
    @else
        <tr>
            <td class="label">Delivery fee</td>
            <td class="value">FREE</td>
        </tr>
    @endif

    @if(isset($order->deliveryType) && $order->deliveryType)
        <tr>
            <td class="label">Delivery type</td>
            <td class="value">{{ $order->deliveryType->name }}{{ $order->deliveryType->days ? ' — within ' . $order->deliveryType->days : '' }}</td>
        </tr>
    @endif

    @if(!empty($order->preferred_delivery_date))
        <tr>
            <td class="label">Preferred delivery date</td>
            <td class="value">
                @if(is_object($order->preferred_delivery_date))
                    {{ $order->preferred_delivery_date->format('j F, Y') }}
                @else
                    {{ \Carbon\Carbon::parse($order->preferred_delivery_date)->format('j F, Y') }}
                @endif
            </td>
        </tr>
    @endif

    @if(($order->discount ?? 0) > 0)
        <tr>
            <td class="label">Discount</td>
            <td class="value" style="color: #059669;">-${{ number_format((float) $order->discount, 2) }}</td>
        </tr>
    @endif

    <tr class="total-row">
        <td class="label">Total</td>
        <td class="value">${{ number_format((float) ($order->total ?? 0), 2) }}</td>
    </tr>

    @if(!empty($order->payment_method))
        <tr>
            <td class="label" style="padding-top: 18px;">Payment method</td>
            <td class="value" style="padding-top: 18px;">
                @php
                    $pm = $order->payment_method;
                    $pmLabel = match($pm) {
                        'cash_on_delivery' => 'Cash/EFT',
                        'bank_transfer' => 'Bank transfer',
                        'square' => 'Credit card',
                        default => ucfirst(str_replace('_', ' ', $pm))
                    };
                @endphp
                {{ $pmLabel }}
            </td>
        </tr>
    @endif
</table>
