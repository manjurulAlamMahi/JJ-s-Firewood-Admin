@extends('auth.layout')

@section('title', 'Sign in')
@section('heading', 'Sign in')

@section('form')
    <form method="POST" action="{{ route('login') }}" class="av-stack">
        @csrf

        <div>
            <label class="form-label" for="email">Email</label>
            {{-- inputmode + autocapitalize give phone keyboards the right layout --}}
            <input id="email" name="email" type="email" class="form-control"
                   value="{{ old('email') }}" inputmode="email" autocomplete="username"
                   autocapitalize="none" spellcheck="false" required autofocus>
        </div>

        <div>
            <label class="form-label" for="password">Password</label>
            <input id="password" name="password" type="password" class="form-control"
                   autocomplete="current-password" required>
        </div>

        <div class="av-row av-row--between">
            <label class="form-check-label av-row" style="gap:8px">
                <input type="checkbox" name="remember" class="form-check-input" style="margin:0">
                Keep me signed in
            </label>

            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}">Forgot password?</a>
            @endif
        </div>

        <button type="submit" class="btn btn-primary btn-lg">Sign in</button>
    </form>
@endsection
