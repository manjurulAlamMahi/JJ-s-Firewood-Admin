{{--
    Delivery Date Updated / Scheduled — extends the JJ-branded base layout.
    Has $oldDate (nullable) and $newDate (the new date).
    Optional $isFirstBooking (bool): when true, wording says "scheduled" (first
    confirmation); when false/absent, wording says "rescheduled/updated".
--}}
@php $isFirstBooking = $isFirstBooking ?? false; @endphp
@extends('emails.layouts.jjs-base')

@section('title', $isFirstBooking ? 'Delivery Scheduled' : 'Delivery Rescheduled')
@section('header_badge', $isFirstBooking ? 'Scheduled' : 'Rescheduled')

@section('header_icon_block')
    @include('emails.partials.calendar-icon', ['calendarDate' => $newDate])
@endsection

@section('header_title', $isFirstBooking ? 'Delivery Scheduled' : 'Delivery Rescheduled')
@section('header_subtitle', 'Order #' . $order->order_number)

@section('content')
    <p>Hi {{ $order->user->name ?? 'Customer' }},</p>

    @if($isFirstBooking)
        <p>Good news — your delivery has been scheduled for <strong>{{ \Carbon\Carbon::parse($newDate)->format('l, F j, Y') }}</strong>.</p>
    @else
        <p>Your delivery date has been updated to <strong>{{ \Carbon\Carbon::parse($newDate)->format('l, F j, Y') }}</strong>.</p>
        @if(!empty($oldDate))
            <p style="color: #6e4f3a;">This was previously scheduled for <span style="text-decoration: line-through;">{{ \Carbon\Carbon::parse($oldDate)->format('l, F j, Y') }}</span>.</p>
        @endif
    @endif

    <p>Delivery times are confirmed on the day, as our drivers manage their schedules around other deliveries and live traffic conditions. You'll be contacted a few hours prior to delivery with an update once the driver has a clearer ETA.</p>

    <p>If you need to make any changes or have any questions before then, just reply to this email and let us know — happy to help.</p>

    @include('emails.partials.order-summary', ['order' => $order, 'itemsRelation' => 'items'])

    @include('emails.partials.contact-block', [
        'address' => $order->address ?? null,
        'userEmail' => optional($order->user)->email,
        'title' => 'Delivery address',
    ])
@endsection
