@extends('auth.layout')

@section('title', 'Create account')
@section('heading', 'Create account')
@section('lede', 'Set up a new login.')

@section('form')
    <form method="POST" action="{{ route('register') }}" class="av-stack">
        @csrf

        <div>
            <label class="form-label" for="name">Name</label>
            <input id="name" name="name" type="text" class="form-control"
                   value="{{ old('name') }}" autocomplete="name" required autofocus>
        </div>

        <div>
            <label class="form-label" for="email">Email</label>
            <input id="email" name="email" type="email" class="form-control"
                   value="{{ old('email') }}" inputmode="email" autocomplete="username"
                   autocapitalize="none" spellcheck="false" required>
        </div>

        <div>
            <label class="form-label" for="password">Password</label>
            <input id="password" name="password" type="password" class="form-control"
                   autocomplete="new-password" required>
        </div>

        <div>
            <label class="form-label" for="password_confirmation">Confirm password</label>
            <input id="password_confirmation" name="password_confirmation" type="password"
                   class="form-control" autocomplete="new-password" required>
        </div>

        @if (Laravel\Jetstream\Jetstream::hasTermsAndPrivacyPolicyFeature())
            <label class="form-check-label av-row" style="gap:8px">
                <input type="checkbox" name="terms" id="terms" class="form-check-input"
                       style="margin:0" required>
                <span>
                    I agree to the
                    <a href="{{ route('terms.show') }}" target="_blank">terms</a> and
                    <a href="{{ route('policy.show') }}" target="_blank">privacy policy</a>
                </span>
            </label>
        @endif

        <button type="submit" class="btn btn-primary btn-lg">Create account</button>

        <p class="av-auth__sub" style="margin:0">
            Already have an account? <a href="{{ route('login') }}">Sign in</a>
        </p>
    </form>
@endsection
