@extends('backend.master')

@section('title', 'Edit Customer')

@section('body')

<style>
    /* CRITICAL: Fix all text visibility */
    .table td, .table th, .card-body, .form-group label, .form-control {
        color: #1a1a1a !important;
    }
</style>
<div class="page-header">
    <div>
        <h2 class="main-content-title tx-24 mg-b-5">Edit Customer</h2>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('customer-management.index') }}">Customers</a></li>
            <li class="breadcrumb-item active" aria-current="page">Edit</li>
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
                <form action="{{ route('customer-management.update', $customer->id) }}" method="POST">
                    @csrf
                    @method('PUT')

                    <div class="row">
                        <!-- Personal Information -->
                        <div class="col-md-6">
                            <h5 class="mb-3">Personal Information</h5>

                            <div class="form-group">
                                <label for="name">Full Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror" 
                                       id="name" name="name" value="{{ old('name', $customer->name) }}" required>
                                @error('name')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label for="email">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control @error('email') is-invalid @enderror" 
                                       id="email" name="email" value="{{ old('email', $customer->email) }}" required>
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label for="phone">Phone</label>
                                <input type="text" class="form-control @error('phone') is-invalid @enderror" 
                                       id="phone" name="phone" value="{{ old('phone', $customer->phone) }}">
                                @error('phone')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label for="role">Role <span class="text-danger">*</span></label>
                                <select class="form-control @error('role') is-invalid @enderror" 
                                        id="role" name="role" required>
                                    <option value="customer" {{ old('role', $customer->role) === 'customer' ? 'selected' : '' }}>Customer</option>
                                    <option value="business" {{ old('role', $customer->role) === 'business' ? 'selected' : '' }}>Business</option>
                                    <option value="admin" {{ old('role', $customer->role) === 'admin' ? 'selected' : '' }}>Admin</option>
                                </select>
                                @error('role')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <hr class="my-4">

                            <h5 class="mb-3">Change Password (Optional)</h5>
                            <p class="text-muted">Leave blank to keep current password</p>

                            <div class="form-group">
                                <label for="password">New Password</label>
                                <input type="password" class="form-control @error('password') is-invalid @enderror" 
                                       id="password" name="password">
                                @error('password')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group">
                                <label for="password_confirmation">Confirm New Password</label>
                                <input type="password" class="form-control" 
                                       id="password_confirmation" name="password_confirmation">
                            </div>
                        </div>

                        <!-- Address Information -->
                        <div class="col-md-6">
                            <h5 class="mb-3">Address Information</h5>
                            
                            @if($customer->shippingAddresses->count() > 0)
                                @foreach($customer->shippingAddresses as $index => $address)
                                    <div class="card mb-3">
                                        <div class="card-header">
                                            <strong>{{ $address->label }}</strong>
                                            @if($address->is_default)
                                                <span class="badge badge-primary ml-2">Default</span>
                                            @endif
                                        </div>
                                        <div class="card-body">
                                            <p class="mb-1"><strong>{{ $address->full_name }}</strong></p>
                                            <p class="mb-1">{{ $address->address }}</p>
                                            @if($address->suburb)
                                                <p class="mb-1">{{ $address->suburb }}</p>
                                            @endif
                                            <p class="mb-1">{{ $address->city }}, {{ $address->state }} {{ $address->zip_code }}</p>
                                            <p class="mb-1">{{ $address->phone }}</p>
                                            <p class="mb-0"><small class="text-muted">{{ $address->email }}</small></p>
                                        </div>
                                    </div>
                                @endforeach
                            @else
                                <div class="alert alert-info">
                                    <i class="fe fe-info mr-2"></i> No addresses on file.
                                    Customer can add addresses from their account.
                                </div>
                            @endif

                            <hr class="my-4">

                            <h5 class="mb-3">Account Statistics</h5>
                            <div class="list-group">
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <span>Total Orders:</span>
                                        <strong>{{ $customer->orders_count }}</strong>
                                    </div>
                                </div>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <span>Total Spent:</span>
                                        <strong>${{ number_format($customer->orders->sum('total'), 2) }}</strong>
                                    </div>
                                </div>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <span>Giveaway Entries:</span>
                                        <strong>{{ $activeEntries ?? 0 }} <small class="text-muted">active</small></strong>
                                    </div>
                                </div>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <span>Loyalty Points:</span>
                                        <strong>{{ $customer->loyalty_points }}</strong>
                                    </div>
                                </div>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <span>Account Created:</span>
                                        <strong>{{ $customer->created_at->format('M d, Y') }}</strong>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-3">
                                <a href="{{ url('/manage-entries?user_id=' . $customer->id) }}" class="btn btn-info btn-block">
                                    <i class="fe fe-gift mr-2"></i> Manage Giveaway Entries
                                </a>
                                <small class="form-text text-muted text-center">Add or remove entries for this customer</small>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fe fe-save mr-2"></i> Update Customer
                        </button>
                        <a href="{{ route('customer-management.show', $customer->id) }}" class="btn btn-secondary">
                            <i class="fe fe-x mr-2"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Entry Management Section -->
<div class="row">
    <div class="col-12">
        @include('backend.customers.partials.entry-management', [
            'customer' => $customer, 
            'entries' => $entries, 
            'totalEntries' => $totalEntries, 
            'rewardEntries' => $rewardEntries, 
            'purchaseEntries' => $purchaseEntries, 
            'entryHistory' => $entryHistory
        ])
    </div>
</div>

@endsection