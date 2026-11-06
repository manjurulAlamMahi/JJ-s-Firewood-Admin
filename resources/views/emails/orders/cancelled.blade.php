{{--
    Order Cancelled — extends the JJ-branded base layout.
    All wording preserved verbatim from the original template.
    Note: this template uses $order->orderItems (not $order->items) per the original.
--}}
@extends('emails.layouts.jjs-base')

@section('title', 'Order #' . $order->order_number . ' Cancelled')
@section('header_badge', 'Cancelled')
@section('header_icon', '⚠️')
@section('header_title', 'Order #' . $order->order_number . ' Cancelled')

@section('content')
    <p>Hi {{ $order->address->full_name ?? ($order->user->name ?? 'Customer') }}, your order has been cancelled.</p>

    @if(($order->loyalty_points ?? 0) > 0)
        <p style="color: #b91c1c;">Note: {{ $order->loyalty_points }} loyalty points and any associated entries for this order have been retracted.</p>
    @endif

    @include('emails.partials.order-summary', ['order' => $order, 'itemsRelation' => 'orderItems'])

    @include('emails.partials.contact-block', [
        'address' => $order->address,
        'userEmail' => optional($order->user)->email,
        'title' => 'Billing address',
    ])
@endsection
