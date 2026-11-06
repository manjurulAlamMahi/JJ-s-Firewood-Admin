@extends('auth.layout')

@section('title', 'Reset password')
@section('heading', 'Forgot password')
@section('lede', 'We will email you a link to choose a new one.')

@section('form')
    <form method="POST" action="{{ route('password.email') }}" class="av-stack">
        @csrf

        <div>
            <label class="form-label" for="email">Email</label>
            <input id="email" name="email" type="email" class="form-control"
                   value="{{ old('email') }}" inputmode="email" autocomplete="username"
                   autocapitalize="none" spellcheck="false" required autofocus>
        </div>

        <button type="submit" class="btn btn-primary btn-lg">Email reset link</button>

        <p class="av-auth__sub" style="margin:0">
            <a href="{{ route('login') }}">Back to sign in</a>
        </p>
    </form>
@endsection
