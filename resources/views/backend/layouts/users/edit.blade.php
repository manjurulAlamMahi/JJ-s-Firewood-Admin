@extends('backend.master')

@section('title', 'Edit Admin')

@section('body')
    <x-admin.page-header title="Edit Admin" :crumbs="['Admin Users' => route('users.index'), 'Edit' => null]" />

    <div class="col-md-12">
        <div class="card">
            <div class="card-header border-bottom">
                <h3 class="card-title">{{ $user->name }}</h3>
            </div>
            <div class="card-body">
                @if ($errors->any())
                    <div class="alert alert-danger mb-3">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                        </ul>
                    </div>
                @endif

                <form class="form-horizontal" method="POST" action="{{ route('users.update', $user->id) }}" autocomplete="off">
                    @csrf
                    @method('PUT')
                    <div class="row mb-4">
                        <label for="name" class="col-md-3 form-label">Name</label>
                        <div class="col-md-9">
                            <input type="text" id="name" name="name" class="form-control" value="{{ old('name', $user->name) }}" required>
                        </div>
                    </div>
                    <div class="row mb-4">
                        <label for="email" class="col-md-3 form-label">Email (used to log in)</label>
                        <div class="col-md-9">
                            <input type="email" id="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" required>
                        </div>
                    </div>
                    <div class="row mb-4">
                        <label for="password" class="col-md-3 form-label">New password</label>
                        <div class="col-md-9">
                            <input type="password" id="password" name="password" class="form-control" autocomplete="new-password" placeholder="Leave blank to keep the current password">
                        </div>
                    </div>
                    <div class="row mb-4">
                        <label for="password_confirmation" class="col-md-3 form-label">Confirm new password</label>
                        <div class="col-md-9">
                            <input type="password" id="password_confirmation" name="password_confirmation" class="form-control" autocomplete="new-password">
                        </div>
                    </div>
                    <div class="text-end">
                        <a href="{{ route('users.index') }}" class="btn btn-light me-2">Cancel</a>
                        <button type="submit" class="btn btn-danger">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
