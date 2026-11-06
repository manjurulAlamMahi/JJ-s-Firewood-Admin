{{--
    Delivery Booked — delivery confirmation for non-app bookings
    (phone orders and WooCommerce orders) scheduled via Setmore.

    Matches the styling of emails/orders/delivery-date-updated.blade.php (same
    base layout, calendar icon, and contact-block CSS classes) but is NOT driven
    by an Order model — phone/WC bookings have no matched order record.

    Expected variables (all optional except $appointmentDate):
        $customerName    — greeting name (pre-cleaned, e.g. "Michael")
        $appointmentDate — scheduled delivery date (string or Carbon)
        $addressLine     — street address, e.g. "9 Stennett Street"
        $suburb          — e.g. "Gosnells"
        $state           — e.g. "Western Australia" / "WA"
        $postcode        — e.g. "6110"
        $customerPhone   — contact number
        $customerEmail   — contact email
--}}
@extends('emails.layouts.jjs-base')

@section('title', 'Delivery Booked')
@section('header_badge', 'Delivery booked')

@section('header_icon_block')
    @include('emails.partials.calendar-icon', ['calendarDate' => $appointmentDate])
@endsection

@section('header_title', 'Delivery Booked')
@section('header_subtitle', 'Your delivery has been scheduled')

@section('content')
    <p>Hi {{ $customerName ?? 'there' }},</p>

    <p>Just a quick update on your recent <strong>JJ's Firewood</strong> order — your delivery has been booked in for <strong>{{ \Carbon\Carbon::parse($appointmentDate)->format('l, F j, Y') }}</strong>.</p>

    <p>This date is either the <strong>delivery date you requested</strong>, or the <strong>earliest available delivery date for your area</strong> if your preferred date wasn't available.</p>

    <p>Delivery times are confirmed on the day, as our drivers manage their schedules around other deliveries and live traffic conditions. You'll be contacted a few hours prior to delivery with an update once the driver has a clearer ETA.</p>

    <p>If you need to make any changes or have any questions before then, just reply to this email and let us know — happy to help.</p>

    @include('emails.partials.delivery-contact', [
        'customerName'  => $customerName ?? null,
        'addressLine'   => $addressLine ?? null,
        'suburb'        => $suburb ?? null,
        'state'         => $state ?? null,
        'postcode'      => $postcode ?? null,
        'customerPhone' => $customerPhone ?? null,
        'customerEmail' => $customerEmail ?? null,
    ])

    @include('emails.partials.app-download')
@endsection

