@extends('backend.master')

@section('title', 'Create Customer')

@section('body')

<style>
    /* CRITICAL: Fix all text visibility */
    .table td, .table th, .card-body, .form-group label, .form-control {
        color: #1a1a1a !important;
    }
</style>
<div class="page-header">
    <div>
        <h2 class="main-content-title tx-24 mg-b-5">Create Customer</h2>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('customer-management.index') }}">Customers</a></li>
            <li class="breadcrumb-item active" aria-current="page">Create</li>
        </ol>
    </div>
</div>

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ session('error') }}
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
@endif

<div class="row">
    <div class="col-lg-12">
        <div class="card custom-card">
            <div class="card-body">
                <form action="{{ route('customer-management.store') }}" method="POST">
                    @csrf

                    <div class="row">
                        <!-- Personal Information -->
                        <div class="col-md-6">
                            <h5 class="mb-3">Personal Information</h5>

                            <div class="form-group">
                                <label for="name">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                       id="name" name="name" value="{{ old('name') }}" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label for="email">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control @error('email') is-invalid @enderror" 
                                       id="email" name="email" value="{{ old('email') }}" required>
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label for="phone">Phone</label>
                                <input type="text" class="form-control @error('phone') is-invalid @enderror" 
                                       id="phone" name="phone" value="{{ old('phone') }}">
                                @error('phone')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label for="role">Role <span class="text-danger">*</span></label>
                                <select class="form-control @error('role') is-invalid @enderror" 
                                        id="role" name="role" required>
                                    <option value="customer" {{ old('role') === 'customer' ? 'selected' : '' }}>Customer</option>
                                    <option value="business" {{ old('role') === 'business' ? 'selected' : '' }}>Business</option>
                                    <option value="admin" {{ old('role') === 'admin' ? 'selected' : '' }}>Admin</option>
                                </select>
                                @error('role')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label for="password">Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control @error('password') is-invalid @enderror" 
                                       id="password" name="password" required>
                                @error('password')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label for="password_confirmation">Confirm Password <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" 
                                       id="password_confirmation" name="password_confirmation" required>
                            </div>
                        </div>

                        <!-- Address Information -->
                        <div class="col-md-6">
                            <h5 class="mb-3">Address Information (Optional)</h5>

                            <div class="form-group">
                                <label for="address">Street Address</label>
                                <input type="text" class="form-control @error('address') is-invalid @enderror" 
                                       id="address" name="address" value="{{ old('address') }}">
                                @error('address')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label for="suburb">Suburb</label>
                                <input type="text" class="form-control @error('suburb') is-invalid @enderror" 
                                       id="suburb" name="suburb" value="{{ old('suburb') }}">
                                @error('suburb')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label for="city">City</label>
                                <input type="text" class="form-control @error('city') is-invalid @enderror" 
                                       id="city" name="city" value="{{ old('city') }}">
                                @error('city')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label for="state">State</label>
                                <select class="form-control @error('state') is-invalid @enderror" 
                                        id="state" name="state">
                                    <option value="">Select State</option>
                                    <option value="NSW" {{ old('state') === 'NSW' ? 'selected' : '' }}>New South Wales</option>
                                    <option value="VIC" {{ old('state') === 'VIC' ? 'selected' : '' }}>Victoria</option>
                                    <option value="QLD" {{ old('state') === 'QLD' ? 'selected' : '' }}>Queensland</option>
                                    <option value="SA" {{ old('state') === 'SA' ? 'selected' : '' }}>South Australia</option>
                                    <option value="WA" {{ old('state') === 'WA' ? 'selected' : '' }}>Western Australia</option>
                                    <option value="TAS" {{ old('state') === 'TAS' ? 'selected' : '' }}>Tasmania</option>
                                    <option value="NT" {{ old('state') === 'NT' ? 'selected' : '' }}>Northern Territory</option>
                                    <option value="ACT" {{ old('state') === 'ACT' ? 'selected' : '' }}>Australian Capital Territory</option>
                                </select>
                                @error('state')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label for="zip_code">Postcode</label>
                                <input type="text" class="form-control @error('zip_code') is-invalid @enderror" 
                                       id="zip_code" name="zip_code" value="{{ old('zip_code') }}">
                                @error('zip_code')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fe fe-save mr-2"></i> Create Customer
                        </button>
                        <a href="{{ route('customer-management.index') }}" class="btn btn-secondary">
                            <i class="fe fe-x mr-2"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
