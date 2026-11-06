@extends('backend.master')

@section('title')
    Info - Edit
@endsection

@section('body')
    <x-admin.page-header title="Edit Info"
        :crumbs="['Info' => null, 'Edit' => null]" />

<div class="col-md-12">
        <div class="card">
<div class="card-body">

                <!-- Success/Error Message -->
                @if (session('success'))
                    <div class="alert alert-success">{{ session('success') }}</div>
                @endif
                @if (session('error'))
                    <div class="alert alert-danger">{{ session('error') }}</div>
                @endif

                <form action="{{ route('info.update', $info->id) }}" method="POST">
                    @csrf
                    <div class="row">

                        <!-- Title -->
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">Title</label>
                                <input type="text" class="form-control @error('title') is-invalid @enderror"
                                    name="title" placeholder="Enter Title" value="{{ old('title', $info->title) }}">
                                @error('title')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <!-- Phone -->
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">Phone</label>
                                <input type="text" class="form-control @error('phone') is-invalid @enderror"
                                    name="phone" placeholder="Enter Phone" value="{{ old('phone', $info->phone) }}">
                                @error('phone')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <!-- Email -->
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control @error('email') is-invalid @enderror"
                                    name="email" placeholder="Enter Email" value="{{ old('email', $info->email) }}">
                                @error('email')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <!-- Website -->
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">Website</label>
                                <input type="text" class="form-control @error('website') is-invalid @enderror"
                                    name="website" placeholder="Enter Website URL"
                                    value="{{ old('website', $info->website) }}">
                                @error('website')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-danger">Update Info</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
