@extends('backend.master')
@section('title')
    Order - Edit
@endsection

@section('body')
    <!-- PAGE-HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">Edit Order</h1>
        </div>
        <div class="ms-auto pageheader-btn"></div>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('order.index') }}">Orders</a></li>
            <li class="breadcrumb-item active" aria-current="page">Edit</li>
        </ol>
    </div>
    <!-- PAGE-HEADER END -->

    <div class="col-md-12">
        <div class="card">
            <div class="card-header border-bottom">
                <h3 class="card-title">Edit Order Details</h3>
            </div>
            <div class="card-body">
                <p class="text-muted">Update the information for this order.</p>

                {{-- Show validation errors --}}
                @if ($errors->any())
                    <div class="alert alert-danger mb-3">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('order.update', $order->id) }}" method="POST">
                    @csrf

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Order Number</label>
                                <input type="text" class="form-control" value="{{ $order->order_number }}" disabled>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Customer</label>
                                <select name="user_id" class="form-control">
                                    <option value="">Select Customer</option>
                                    @foreach ($users as $user)
                                        <option value="{{ $user->id }}"
                                            {{ $order->user_id == $user->id ? 'selected' : '' }}>
                                            {{ $user->name }} ({{ $user->email }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Subtotal</label>
                                <input type="number" step="0.01" name="subtotal" class="form-control"
                                    value="{{ old('subtotal', $order->subtotal) }}" placeholder="Enter Subtotal">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Delivery Fee</label>
                                <input type="number" step="0.01" name="delivery_fee" class="form-control"
                                    value="{{ old('delivery_fee', $order->delivery_fee) }}"
                                    placeholder="Enter Delivery Fee">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Discount</label>
                                <input type="number" step="0.01" name="discount" class="form-control"
                                    value="{{ old('discount', $order->discount) }}" placeholder="Enter Discount">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Tax</label>
                                <input type="number" step="0.01" name="tax" class="form-control"
                                    value="{{ old('tax', $order->tax) }}" placeholder="Enter Tax">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Total</label>
                                <input type="number" step="0.01" name="total" class="form-control"
                                    value="{{ old('total', $order->total) }}" placeholder="Enter Total Amount">
                            </div>
                        </div>



                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Payment Status</label>
                                <select name="payment_status" class="form-control">
                                    <option value="">Select Payment Status</option>
                                    <option value="pending" {{ $order->payment_status == 'pending' ? 'selected' : '' }}>
                                        Pending</option>
                                    <option value="paid" {{ $order->payment_status == 'paid' ? 'selected' : '' }}>Paid
                                    </option>
                                    <option value="failed" {{ $order->payment_status == 'failed' ? 'selected' : '' }}>
                                        Failed</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Order Status</label>
                                <select name="order_status" class="form-control">
                                    <option value="">Select Order Status</option>
                                    <option value="pending" {{ $order->order_status == 'pending' ? 'selected' : '' }}>
                                        Pending</option>
                                    <option value="processing"
                                        {{ $order->order_status == 'processing' ? 'selected' : '' }}>Processing</option>
                                    <option value="shipped" {{ $order->order_status == 'shipped' ? 'selected' : '' }}>
                                        Shipped</option>
                                    <option value="delivered" {{ $order->order_status == 'delivered' ? 'selected' : '' }}>
                                        Delivered</option>
                                    <option value="cancelled" {{ $order->order_status == 'cancelled' ? 'selected' : '' }}>
                                        Cancelled</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Preferred Delivery Date</label>
                                <input type="date" name="preferred_delivery_date" class="form-control"
                                    value="{{ old('preferred_delivery_date', $order->preferred_delivery_date ? $order->preferred_delivery_date->format('Y-m-d') : '') }}">
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">Notes</label>
                                <textarea class="form-control" name="notes" rows="4" placeholder="Enter Notes">{{ old('notes', $order->notes) }}</textarea>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="mb-3">
                                <button type="submit" class="btn btn-danger">
                                    <i class="fa fa-save me-1"></i> Update Order
                                </button>
                            </div>
                        </div>
                    </div>
                </form>

            </div>
        </div>
    </div>
@endsection
