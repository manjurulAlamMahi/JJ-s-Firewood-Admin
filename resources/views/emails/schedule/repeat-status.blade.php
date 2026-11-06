@extends('emails.layouts.jjs-base')

@section('title', $heading)
@section('header_badge', 'Repeat Delivery')
@section('header_icon', '🗓️')
@section('header_title', $heading)
@section('header_subtitle', '')

@section('content')
    <p>Hi {{ $schedule->user->name ?? 'Customer' }},</p>
    <p>{{ $body }}</p>
@endsection
