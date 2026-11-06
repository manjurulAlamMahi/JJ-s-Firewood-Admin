{{--
    Order Delivered — extends the JJ-branded base layout.
    All wording preserved verbatim from the original template.
--}}
@extends('emails.layouts.jjs-base')

@section('title', 'Order #' . $order->order_number . ' Delivered')
@section('header_badge', 'Delivered')
@section('header_icon', '✅')
@section('header_title', 'Order Delivered! #' . $order->order_number)
@section('header_subtitle', 'Thanks for choosing JJ\'s Firewood')

@section('content')
    <p>Hi {{ optional($order->address)->full_name ?? ($order->user->name ?? 'Customer') }}, your order has been successfully delivered. We hope you enjoy your purchase!</p>

    @include('emails.partials.order-summary', ['order' => $order, 'itemsRelation' => 'items'])

    @include('emails.partials.contact-block', [
        'address' => $order->address,
        'userEmail' => optional($order->user)->email,
        'title' => 'Delivery address',
    ])
@endsection
