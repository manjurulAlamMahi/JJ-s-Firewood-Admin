@extends('backend.master')

@section('title', 'Customer Details')

@section('body')


<div class="page-header">
    <div>
        <h2 class="main-content-title tx-24 mg-b-5">Customer Details</h2>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('dashboard') }}">Dashboard</a></li>
            <li class="breadcrumb-item"><a href="{{ route('customer-management.index') }}">Customers</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $customer->name }}</li>
        </ol>
    </div>
    <div class="d-flex flex-wrap gap-2">
        <a href="{{ route('customer-management.edit', $customer->id) }}" class="btn btn-primary my-2 btn-icon-text">
            <i class="fe fe-edit me-2"></i> Edit Customer
        </a>
        <button type="button" class="btn btn-info my-2 btn-icon-text" data-bs-toggle="modal" data-bs-target="#notificationModal">
            <i class="fe fe-bell me-2"></i> Send Notification
        </button>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif

<!-- Customer Overview Cards -->
<div class="row">
    <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="mr-3">
                        <span class="avatar avatar-md brround bg-primary-transparent d-flex align-items-center justify-content-center">
                            <i class="fe fe-shopping-cart tx-primary"></i>
                        </span>
                    </div>
                    <div>
                        <h6 class="mb-1">Total Orders</h6>
                        <h3 class="mb-0">{{ $stats['total_orders'] }}</h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="mr-3">
                        <span class="avatar avatar-md brround bg-success-transparent d-flex align-items-center justify-content-center">
                            <i class="fe fe-dollar-sign tx-success"></i>
                        </span>
                    </div>
                    <div>
                        <h6 class="mb-1">Total Spent</h6>
                        <h3 class="mb-0">${{ number_format($stats['total_spent'], 2) }}</h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="mr-3">
                        <span class="avatar avatar-md brround bg-warning-transparent d-flex align-items-center justify-content-center">
                            <i class="fe fe-gift tx-warning"></i>
                        </span>
                    </div>
                    <div>
                        <h6 class="mb-1">Giveaway Entries</h6>
                        <h3 class="mb-0">{{ $stats['total_entries'] }}</h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-lg-6 col-md-6">
        <div class="card">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="mr-3">
                        <span class="avatar avatar-md brround bg-{{ $stats['is_active'] ? 'success' : 'danger' }}-transparent d-flex align-items-center justify-content-center">
                            <i class="fe fe-{{ $stats['is_active'] ? 'check' : 'x' }}-circle tx-{{ $stats['is_active'] ? 'success' : 'danger' }}"></i>
                        </span>
                    </div>
                    <div>
                        <h6 class="mb-1">Status</h6>
                        <h3 class="mb-0">{{ $stats['is_active'] ? 'Active' : 'Inactive' }}</h3>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Customer Information -->
    <div class="col-lg-4">
        <div class="card custom-card">
            <div class="card-header">
                <h5 class="card-title mb-0">Customer Information</h5>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <small class="text-muted">Name</small>
                    <h6>{{ $customer->name }}</h6>
                </div>
                <div class="mb-3">
                    <small class="text-muted">Email</small>
                    <h6><a href="mailto:{{ $customer->email }}">{{ $customer->email }}</a></h6>
                </div>
                <div class="mb-3">
                    <small class="text-muted">Phone</small>
                    <h6>{{ $customer->phone ?? 'N/A' }}</h6>
                </div>
                <div class="mb-3">
                    <small class="text-muted">Role</small>
                    <h6>
                        <span class="badge badge-{{ $customer->role === 'admin' ? 'primary' : ($customer->role === 'business' ? 'info' : 'secondary') }}">
                            {{ ucfirst($customer->role ?? 'customer') }}
                        </span>
                    </h6>
                </div>
                <div class="mb-3">
                    <small class="text-muted">Loyalty Points</small>
                    <h6>{{ $customer->loyalty_points }}</h6>
                </div>
                <div class="mb-3">
                    <small class="text-muted">Account Age</small>
                    <h6>{{ $stats['account_age_days'] }} days</h6>
                </div>
                <div class="mb-3">
                    <small class="text-muted">Joined</small>
                    <h6>{{ $customer->created_at->format('M d, Y') }}</h6>
                </div>
                <div class="mb-0">
                    <small class="text-muted">Last Updated</small>
                    <h6>{{ $customer->updated_at->diffForHumans() }}</h6>
                </div>
            </div>
        </div>

        <!-- Addresses -->
        <div class="card custom-card">
            <div class="card-header">
                <h5 class="card-title mb-0">Addresses</h5>
            </div>
            <div class="card-body">
                @forelse($customer->shippingAddresses as $address)
                    <div class="mb-3 pb-3 {{ !$loop->last ? 'border-bottom' : '' }}">
                        <div class="d-flex justify-content-between mb-2">
                            <strong>{{ $address->label }}</strong>
                            @if($address->is_default)
                                <span class="badge badge-primary">Default</span>
                            @endif
                        </div>
                        <p class="mb-1">{{ $address->full_name }}</p>
                        <p class="mb-1">{{ $address->address }}</p>
                        @if($address->suburb)
                            <p class="mb-1">{{ $address->suburb }}</p>
                        @endif
                        <p class="mb-1">{{ $address->city }}, {{ $address->state }} {{ $address->zip_code }}</p>
                        <p class="mb-1">{{ $address->phone }}</p>
                    </div>
                @empty
                    <p class="text-muted mb-0">No addresses on file</p>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Orders and Entries -->
    <div class="col-lg-8">
        <!-- Orders -->
        <div class="card custom-card">
            <div class="card-header">
                <h5 class="card-title mb-0">Order History</h5>
            </div>
            <div class="card-body">
                @if($customer->orders->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Items</th>
                                    <th>Total</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($customer->orders->take(10) as $order)
                                    <tr>
                                        <td>
                                            <a href="{{ route('order.show', $order->id) }}">
                                                {{ $order->order_number }}
                                            </a>
                                        </td>
                                        <td>{{ $order->created_at->format('M d, Y') }}</td>
                                        <td>
                                            <span class="badge badge-{{ $order->order_status === 'delivered' ? 'success' : 'warning' }}">
                                                {{ ucfirst($order->order_status) }}
                                            </span>
                                        </td>
                                        <td>{{ $order->items->count() }}</td>
                                        <td>${{ number_format($order->total, 2) }}</td>
                                        <td>
                                            <a href="{{ route('order.show', $order->id) }}" class="btn btn-sm btn-info">
                                                View
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-muted mb-0">No orders yet</p>
                @endif
            </div>
        </div>

        <!-- Giveaway Entries -->
        <div class="card custom-card">
            <div class="card-header">
                <h5 class="card-title mb-0">Giveaway Entries</h5>
            </div>
            <div class="card-body">
                @if($customer->entries->count() > 0)
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Giveaway</th>
                                    <th>Entries</th>
                                    <th>Type</th>
                                    <th>Amount</th>
                                    <th>Date</th>
                                    <th>Drawn</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($customer->entries->take(10) as $entry)
                                    <tr>
                                        <td>
                                            @if($entry->giveaway)
                                                <a href="{{ route('giveaways.show', $entry->giveaway_id) }}">
                                                    {{ $entry->giveaway->prize_name }}
                                                </a>
                                            @else
                                                <em>General Entry</em>
                                            @endif
                                        </td>
                                        <td>{{ $entry->entry_count }}</td>
                                        <td>
                                            <span class="av-badge badge-{{ $entry->purchase_type === 'reward' ? 'success' : 'primary' }}">
                                                {{ ucfirst($entry->purchase_type) }}
                                            </span>
                                        </td>
                                        <td>${{ number_format($entry->amount_paid, 2) }}</td>
                                        <td>{{ $entry->created_at->format('M d, Y') }}</td>
                                        <td>
                                            @if($entry->drawn)
                                                <span class="av-badge badge-success">Yes</span>
                                            @else
                                                <span class="av-badge badge-secondary">No</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <p class="text-muted mb-0">No giveaway entries yet</p>
                @endif
            </div>
        </div>
    </div>
</div>

<!-- Send Notification Modal -->
<div class="modal fade" id="notificationModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Send Notification to {{ $customer->name }}</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <form action="{{ route('customer-management.send-notification', $customer->id) }}" method="POST">
                @csrf
                <div class="modal-body">
                    <div class="form-group">
                        <label>Channel</label>
                        <select name="channel" class="form-control" required>
                            <option value="push">Push Notification</option>
                            <option value="email">Email</option>
                            <option value="sms">SMS</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Title (for Push only)</label>
                        <input type="text" name="title" class="form-control" placeholder="Notification title">
                    </div>
                    <div class="form-group">
                        <label>Message</label>
                        <textarea name="message" class="form-control" rows="4" required placeholder="Enter your message..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Send Notification</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Send Notification Section -->
<div class="row">
    <div class="col-12">
        @include('backend.customers.partials.notification-form', ['customer' => $customer])
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