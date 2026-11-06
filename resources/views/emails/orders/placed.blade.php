{{--
    Order Placed — extends the JJ-branded base layout.
    All wording preserved verbatim from the original template.
--}}
@extends('emails.layouts.jjs-base')

@section('title', 'Order #' . $order->order_number)
@section('header_badge', 'Order placed')
@section('header_icon', '🪵')
@section('header_title', 'New order: #' . $order->order_number)
@section('header_subtitle', 'Thanks for your order')

@section('content')
    <p>You've received a new order from {{ $order->address->full_name ?? ($order->user->name ?? 'Customer') }}:</p>

    @include('emails.partials.order-summary', ['order' => $order, 'itemsRelation' => 'items'])

    @include('emails.partials.contact-block', [
        'address' => $order->address,
        'userEmail' => optional($order->user)->email,
        'title' => 'Billing address',
    ])
@endsection
