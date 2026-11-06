@extends('backend.master')
@section('title')
    Order {{ $order->order_number }}
@endsection

@section('body')
    <x-admin.page-header title="Order {{ $order->order_number }}"
        :crumbs="['Orders' => null, '{{ $order->order_number }}' => null]" />

<div class="row">
        <!-- Order Summary -->
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header border-bottom d-flex justify-content-between align-items-center">
                    <h3 class="card-title mb-0">Order Details</h3>
                    <div>
                        {!! $order->status_badge !!}
                        <span class="badge bg-{{ $order->payment_status === 'paid' ? 'success' : ($order->payment_status === 'failed' ? 'danger' : 'warning') }} ms-1">
                            {{ ucfirst($order->payment_status) }}
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    @if($order->notes)
                        <div class="alert alert-warning mb-4" role="alert">
                            <h4 class="alert-heading mb-1"><i class="fas fa-sticky-note me-2"></i>Customer Note</h4>
                            <p class="mb-0">{{ $order->notes }}</p>
                        </div>
                    @endif
                    <div class="row mb-3">
                        <div class="col-sm-3"><strong>Order Number</strong></div>
                        <div class="col-sm-9">{{ $order->order_number }}</div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-3"><strong>Date Placed</strong></div>
                        <div class="col-sm-9">{{ $order->created_at->format('M d, Y \a\t g:i A') }}</div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-sm-3"><strong>Payment Method</strong></div>
                        <div class="col-sm-9">{{ ucwords(str_replace('_', ' ', $order->payment_method ?? 'N/A')) }}</div>
                    </div>
                    @if($order->preferred_delivery_date)
                        <div class="row mb-3">
                            <div class="col-sm-3"><strong>Delivery Date</strong></div>
                            <div class="col-sm-9">{{ $order->preferred_delivery_date->format('M d, Y') }}</div>
                        </div>
                    @endif
                    @if($order->deliveryType)
                        <div class="row mb-3">
                            <div class="col-sm-3"><strong>Delivery Type</strong></div>
                            <div class="col-sm-9">{{ $order->deliveryType->name }}</div>
                        </div>
                    @endif
                    @if($order->transaction_id)
                        <div class="row mb-3">
                            <div class="col-sm-3"><strong>Transaction ID</strong></div>
                            <div class="col-sm-9"><code>{{ $order->transaction_id }}</code></div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Order Items -->
            <div class="card">
                <div class="card-header border-bottom">
                    <h3 class="card-title mb-0">Items</h3>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-bordered mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th>Product</th>
                                    <th class="text-center" width="10%">Qty</th>
                                    <th class="text-end" width="15%">Price</th>
                                    <th class="text-end" width="15%">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($order->orderItems as $item)
                                    <tr>
                                        <td>
                                            @if($item->product)
                                                {{ $item->product->name }}
                                            @else
                                                <em class="text-muted">Product removed</em>
                                            @endif
                                        </td>
                                        <td class="text-center">{{ $item->quantity }}</td>
                                        <td class="text-end">${{ number_format($item->price, 2) }}</td>
                                        <td class="text-end">${{ number_format($item->quantity * $item->price, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="3" class="text-end"><strong>Subtotal</strong></td>
                                    <td class="text-end">${{ number_format($order->subtotal, 2) }}</td>
                                </tr>
                                @if($order->delivery_fee > 0)
                                    <tr>
                                        <td colspan="3" class="text-end">Delivery Fee</td>
                                        <td class="text-end">${{ number_format($order->delivery_fee, 2) }}</td>
                                    </tr>
                                @endif
                                @if($order->discount > 0)
                                    <tr>
                                        <td colspan="3" class="text-end text-success">Discount</td>
                                        <td class="text-end text-success">-${{ number_format($order->discount, 2) }}</td>
                                    </tr>
                                @endif
                                @if($order->tax > 0)
                                    <tr>
                                        <td colspan="3" class="text-end">Tax</td>
                                        <td class="text-end">${{ number_format($order->tax, 2) }}</td>
                                    </tr>
                                @endif
                                <tr class="fw-bold">
                                    <td colspan="3" class="text-end"><strong>Total</strong></td>
                                    <td class="text-end"><strong>${{ number_format($order->total, 2) }}</strong></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Sidebar -->
        <div class="col-lg-4">
            <!-- Customer -->
            <div class="card">
                <div class="card-header border-bottom">
                    <h3 class="card-title mb-0">Customer</h3>
                </div>
                <div class="card-body">
                    @if($order->user)
                        <h6 class="mb-1">{{ $order->user->name }}</h6>
                        <p class="mb-1 text-muted">{{ $order->user->email }}</p>
                        @if($order->user->phone)
                            <p class="mb-2 text-muted">{{ $order->user->phone }}</p>
                        @endif
                        <a href="{{ route('customer-management.show', $order->user->id) }}" class="btn btn-sm btn-outline-primary">
                            <i class="fe fe-user"></i> View Customer
                        </a>
                    @else
                        <p class="text-muted mb-0">Guest order</p>
                    @endif
                </div>
            </div>

            <!-- Shipping Address -->
            <div class="card">
                <div class="card-header border-bottom">
                    <h3 class="card-title mb-0">Shipping Address</h3>
                </div>
                <div class="card-body">
                    @if($order->address)
                        <p class="mb-1">{{ $order->address->full_name ?? $order->address->name ?? '' }}</p>
                        <p class="mb-1">{{ $order->address->address }}</p>
                        @if($order->address->suburb)
                            <p class="mb-1">{{ $order->address->suburb }}</p>
                        @endif
                        <p class="mb-1">{{ $order->address->city ?? '' }}{{ $order->address->state ? ', '.$order->address->state : '' }} {{ $order->address->zip_code ?? '' }}</p>
                        @if($order->address->phone)
                            <p class="mb-0">{{ $order->address->phone }}</p>
                        @endif
                    @else
                        <p class="text-muted mb-0">No shipping address</p>
                    @endif
                </div>
            </div>

            <!-- Actions -->
            <div class="card">
                <div class="card-header border-bottom">
                    <h3 class="card-title mb-0">Actions</h3>
                </div>
                <div class="card-body">
                    <a href="{{ route('order.edit', $order->id) }}" class="btn btn-warning btn-sm w-100 mb-2">
                        <i class="fa fa-edit"></i> Edit Order
                    </a>
                    <a href="{{ route('order.index') }}" class="btn btn-secondary btn-sm w-100">
                        <i class="fa fa-arrow-left"></i> Back to Orders
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection