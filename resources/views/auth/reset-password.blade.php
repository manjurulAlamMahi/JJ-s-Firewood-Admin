@extends('auth.layout')

@section('title', 'Choose a new password')
@section('heading', 'New password')
@section('lede', 'Pick something you have not used before.')

@section('form')
    <form method="POST" action="{{ route('password.update') }}" class="av-stack">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div>
            <label class="form-label" for="email">Email</label>
            <input id="email" name="email" type="email" class="form-control"
                   value="{{ old('email', $request->email) }}" inputmode="email"
                   autocomplete="username" autocapitalize="none" spellcheck="false" required autofocus>
        </div>

        <div>
            <label class="form-label" for="password">New password</label>
            <input id="password" name="password" type="password" class="form-control"
                   autocomplete="new-password" required>
        </div>

        <div>
            <label class="form-label" for="password_confirmation">Confirm password</label>
            <input id="password_confirmation" name="password_confirmation" type="password"
                   class="form-control" autocomplete="new-password" required>
        </div>

        <button type="submit" class="btn btn-primary btn-lg">Reset password</button>
    </form>
@endsection
