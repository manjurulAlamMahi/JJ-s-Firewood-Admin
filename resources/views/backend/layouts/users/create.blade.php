@extends('backend.master')

@section('title', 'Add Admin')

@section('body')
    <x-admin.page-header title="Add Admin" :crumbs="['Admin Users' => route('users.index'), 'Add' => null]" />

    <div class="col-md-12">
        <div class="card">
            <div class="card-header border-bottom">
                <h3 class="card-title">Add an admin</h3>
            </div>
            <div class="card-body">
                <p class="text-muted">
                    Gives someone their own login to this admin panel.
                    If the email already belongs to an app customer, that account is given admin access instead
                    (leave the password blank to keep their current one).
                </p>

                @if ($errors->any())
                    <div class="alert alert-danger mb-3">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                        </ul>
                    </div>
                @endif

                <form class="form-horizontal" method="POST" action="{{ route('users.store') }}" autocomplete="off">
                    @csrf
                    <div class="row mb-4">
                        <label for="name" class="col-md-3 form-label">Name</label>
                        <div class="col-md-9">
                            <input type="text" id="name" name="name" class="form-control" value="{{ old('name') }}" required>
                        </div>
                    </div>
                    <div class="row mb-4">
                        <label for="email" class="col-md-3 form-label">Email (used to log in)</label>
                        <div class="col-md-9">
                            <input type="email" id="email" name="email" class="form-control" value="{{ old('email') }}" required>
                        </div>
                    </div>
                    <div class="row mb-4">
                        <label for="password" class="col-md-3 form-label">Password</label>
                        <div class="col-md-9">
                            <input type="password" id="password" name="password" class="form-control" autocomplete="new-password" placeholder="At least 8 characters">
                        </div>
                    </div>
                    <div class="row mb-4">
                        <label for="password_confirmation" class="col-md-3 form-label">Confirm password</label>
                        <div class="col-md-9">
                            <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" autocomplete="new-password">
                        </div>
                    </div>
                    <div class="text-end">
                        <a href="{{ route('users.index') }}" class="btn btn-light me-2">Cancel</a>
                        <button type="submit" class="btn btn-danger">Add Admin</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
