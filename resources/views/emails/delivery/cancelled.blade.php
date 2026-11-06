{{--
    Delivery Cancelled — cancellation notice for non-app bookings (phone / WC)
    whose Setmore appointment was deleted. Matches order-email styling.
    The cancellation webhook carries no date, so none is referenced.

    Expected variables (all optional):
        $customerName, $addressLine, $suburb, $state, $postcode,
        $customerPhone, $customerEmail
--}}
@extends('emails.layouts.jjs-base')

@section('title', 'Delivery Cancelled')
@section('header_bg', '#7a1f1f')
@section('header_bg_dark', '#5e1414')
@section('header_badge', 'Delivery cancelled')
@section('header_icon', '✕')

@section('header_title', 'Delivery Cancelled')
@section('header_subtitle', 'Your delivery booking has been cancelled')

@section('content')
    <p>Hi {{ $customerName ?? 'there' }},</p>

    <p>We're writing to let you know that your <strong>JJ's Firewood</strong> delivery booking has been cancelled.</p>

    <p>If this was a mistake, or you'd like to rebook your delivery, just reply to this email and we'll get it sorted for you straight away.</p>

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

