{{--
    OTP / Login code — extends the JJ-branded base layout.
    All wording preserved verbatim from the original template.
--}}
@extends('emails.layouts.jjs-base')

@section('title', 'Your Login Code')
@section('header_badge', 'Login code')
@section('header_icon', '🔐')
@section('header_title', 'Your Login Code')
@section('header_subtitle', 'Use this code to sign in')

@section('content')
    <p>Hi{{ $userName ? ' ' . $userName : '' }},</p>

    <p>Your one-time login code for {{ config('app.name') }} is:</p>

    <div style="background: #faf6ec; border: 2px dashed #c89538; border-radius: 12px; padding: 24px; text-align: center; margin: 24px 0;">
        <div style="font-size: 11px; color: #9b2d2d; text-transform: uppercase; letter-spacing: 2px; font-weight: 700; margin-bottom: 10px;">
            Your one-time code
        </div>
        <div style="font-size: 36px; font-weight: 800; letter-spacing: 8px; color: #9b2d2d; font-family: 'Courier New', Courier, monospace;">
            {{ $otp }}
        </div>
    </div>

    <p>This code will expire in 15 minutes.</p>

    <p>For your security, please do not share this code with anyone.</p>

    <p>If you didn't request this login code, you can safely ignore this email.</p>

    <div class="signoff">
        Thanks,<br>
        <strong>{{ config('app.name') }}</strong><br>
        <a href="mailto:admin@jjsfirewoodperth.com.au" style="color: #9b2d2d; text-decoration: none;">admin@jjsfirewoodperth.com.au</a>
    </div>
@endsection

@section('signoff', '')
