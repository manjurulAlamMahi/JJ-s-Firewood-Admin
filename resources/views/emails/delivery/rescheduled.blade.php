{{--
    Delivery Rescheduled — reschedule notice for non-app bookings (phone / WC)
    whose Setmore appointment date changed. Matches order-email styling.

    Expected variables (all optional except $appointmentDate):
        $customerName    — greeting name (pre-cleaned)
        $appointmentDate — NEW delivery date (string or Carbon)
        $oldDate         — previous date, if known (shows struck-through)
        $addressLine, $suburb, $state, $postcode, $customerPhone, $customerEmail
--}}
@extends('emails.layouts.jjs-base')

@section('title', 'Delivery Rescheduled')
@section('header_badge', 'Delivery rescheduled')

@section('header_icon_block')
    @include('emails.partials.calendar-icon', ['calendarDate' => $appointmentDate])
@endsection

@section('header_title', 'Delivery Rescheduled')
@section('header_subtitle', 'Your delivery date has been updated')

@section('content')
    <p>Hi {{ $customerName ?? 'there' }},</p>

    <p>Just letting you know that the delivery date for your <strong>JJ's Firewood</strong> order has been updated to <strong>{{ \Carbon\Carbon::parse($appointmentDate)->format('l, F j, Y') }}</strong>.</p>

    @if(!empty($oldDate))
        <p style="color: #6e4f3a;">This was previously scheduled for <span style="text-decoration: line-through;">{{ \Carbon\Carbon::parse($oldDate)->format('l, F j, Y') }}</span>.</p>
    @endif

    <p>Delivery times are confirmed on the day, as our drivers manage their schedules around other deliveries and live traffic conditions. You'll be contacted a few hours prior to delivery with an update once the driver has a clearer ETA.</p>

    <p>If this new date doesn't suit you, just reply to this email and we'll happily organise an alternative.</p>

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

