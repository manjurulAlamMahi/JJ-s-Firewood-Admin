{{--
    Schedule Created — extends the JJ-branded base layout.
    All wording preserved verbatim from the original template.
    This uses $schedule (not $order). $schedule has order, user, product, preferred_date, repeat.
--}}
@extends('emails.layouts.jjs-base')

@section('title', 'Delivery Scheduled')
@section('header_badge', 'Scheduled')
@section('header_icon', '🗓️')
@section('header_title', 'Delivery Confirmed')
@section('header_subtitle', $schedule->order && $schedule->order->order_number ? 'Order #' . $schedule->order->order_number : '')

@section('content')
    <p>Hi {{ $schedule->user->name ?? 'Customer' }},</p>
    <p>Your delivery has been successfully scheduled. Here are the details for your upcoming delivery.</p>

    {{-- Date / Order / Type info cards --}}
    <table width="100%" border="0" cellpadding="0" cellspacing="0" style="margin: 24px 0;">
        <tr>
            <td width="33.33%" style="padding-right: 8px; vertical-align: top;">
                <div style="background: #faf6ec; border: 1px solid #e6dbb9; border-radius: 10px; padding: 14px 12px; text-align: center;">
                    <div style="font-size: 11px; color: #9b2d2d; text-transform: uppercase; letter-spacing: 1px; font-weight: 700; margin-bottom: 6px;">Date</div>
                    <div style="font-size: 14px; font-weight: 700; color: #3e1f1f;">{{ \Carbon\Carbon::parse($schedule->preferred_date)->format('M d, Y') }}</div>
                </div>
            </td>
            <td width="33.33%" style="padding-right: 8px; padding-left: 4px; vertical-align: top;">
                <div style="background: #faf6ec; border: 1px solid #e6dbb9; border-radius: 10px; padding: 14px 12px; text-align: center;">
                    <div style="font-size: 11px; color: #9b2d2d; text-transform: uppercase; letter-spacing: 1px; font-weight: 700; margin-bottom: 6px;">Order #</div>
                    <div style="font-size: 14px; font-weight: 700; color: #3e1f1f;">#{{ $schedule->order->order_number ?? 'N/A' }}</div>
                </div>
            </td>
            <td width="33.33%" style="padding-left: 8px; vertical-align: top;">
                <div style="background: #faf6ec; border: 1px solid #e6dbb9; border-radius: 10px; padding: 14px 12px; text-align: center;">
                    <div style="font-size: 11px; color: #9b2d2d; text-transform: uppercase; letter-spacing: 1px; font-weight: 700; margin-bottom: 6px;">Type</div>
                    <div style="font-size: 14px; font-weight: 700; color: {{ $schedule->repeat ? '#059669' : '#3e1f1f' }};">
                        {{ $schedule->repeat ? 'Recurring' : 'One-time' }}
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <h3>Delivery Details</h3>

    <table class="summary-table" width="100%" cellpadding="0" cellspacing="0" border="0">
        @php $hasItems = false; @endphp

        @if($schedule->order && $schedule->order->items->count() > 0)
            @foreach($schedule->order->items as $item)
                @php $hasItems = true; @endphp
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

                                {{-- Quantity --}}
                                <td style="text-align: right; vertical-align: top; white-space: nowrap; padding-left: 16px; width: 70px;">
                                    <div style="color: #9b2d2d; font-weight: 700; font-size: 15px;">×{{ number_format((float) ($item->quantity ?? 0), 0) }}</div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
            @endforeach
        @elseif($schedule->product)
            @php $hasItems = true; @endphp
            <tr>
                <td colspan="2" style="padding: 14px 0; border-bottom: 1px solid #f0e8d0;">
                    <table width="100%" cellpadding="0" cellspacing="0" border="0">
                        <tr>
                            <td style="vertical-align: top; padding-right: 14px; width: 70px;">
                                @if($schedule->product->image)
                                    <img src="{{ str_starts_with($schedule->product->image, 'http') ? $schedule->product->image : rtrim(config('app.url'), '/') . '/' . ltrim($schedule->product->image, '/') }}"
                                         alt="{{ $schedule->product->name }}"
                                         width="64" height="64"
                                         style="display: block; width: 64px; height: 64px; object-fit: cover; border-radius: 8px; border: 1px solid #e6dbb9;">
                                @else
                                    <div style="width: 64px; height: 64px; background: #faf6ec; border: 1px solid #e6dbb9; border-radius: 8px; text-align: center; line-height: 64px; color: #c89538; font-size: 11px;">No img</div>
                                @endif
                            </td>
                            <td style="vertical-align: top;">
                                <div style="font-weight: 600; color: #3e1f1f; font-size: 15px;">{{ $schedule->product->name }}</div>
                                @if($schedule->product?->category?->name)
                                    <div style="margin: 4px 0 0 0; line-height: 1.3;">
                                        <span style="font-size: 11px; color: #9b2d2d; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px;">{{ $schedule->product->category->name }}</span>
                                        @if($schedule->product?->subcategory?->name)
                                            <span style="font-size: 11px; color: #c89538; margin: 0 4px;">•</span>
                                            <span style="font-size: 11px; color: #6e4f3a; font-weight: 500;">{{ $schedule->product->subcategory->name }}</span>
                                        @endif
                                    </div>
                                @endif
                                @if($schedule->product->size_info)
                                    <div style="color: #6e4f3a; font-size: 13px; margin-top: 4px;">Wood Type: {{ $schedule->product->size_info }}</div>
                                @endif
                                @if($schedule->product->burn_quantity_info)
                                    <div style="color: #6e4f3a; font-size: 13px;">Load Size: {{ $schedule->product->burn_quantity_info }}</div>
                                @endif
                            </td>
                            <td style="text-align: right; vertical-align: top; padding-left: 16px; width: 70px;">
                                <div style="color: #9b2d2d; font-weight: 700; font-size: 15px;">×1</div>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        @endif

        @if(!$hasItems)
            <tr>
                <td colspan="2" style="text-align: center; padding: 32px; color: #8a6e58; font-style: italic;">
                    No specific item details available.
                </td>
            </tr>
        @endif
    </table>

    {{-- Contact / delivery address block from order if available --}}
    @if($schedule->order && $schedule->order->address)
        @include('emails.partials.contact-block', [
            'address' => $schedule->order->address,
            'userEmail' => optional($schedule->user)->email,
            'title' => 'Delivery address',
        ])
    @endif

    <p style="font-size: 14px; color: #6e4f3a; margin-top: 24px;">If you need to make any changes or have any questions, just reply to this email and let us know — happy to help.</p>

    <div class="signoff">
        Thanks again,<br>
        <strong>JJ's Firewood Perth</strong><br>
        <a href="mailto:admin@jjsfirewoodperth.com.au" style="color: #9b2d2d; text-decoration: none;">admin@jjsfirewoodperth.com.au</a>
    </div>
@endsection

@section('signoff', '')
