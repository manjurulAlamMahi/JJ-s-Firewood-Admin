@extends('backend.master')

@section('title')
    Notification - Edit
@endsection

@section('body')

    <x-admin.page-header title="Notification Edit"
        :crumbs="['Notification' => null, 'Edit' => null]" />

@if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row">
        <div class="col-lg-12">
            <div class="card">

                <div class="card-header">
                    <h3 class="card-title">Edit Manual Notification</h3>
                </div>

                <div class="card-body">
                    <form method="POST" action="{{ route('notifications.update', $notification->id) }}">
                        @csrf

                        <div class="row">

                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Title</label>
                                    <input type="text" name="title" class="form-control"
                                        value="{{ old('title', $notification->title) }}" required>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Category</label>
                                    <select name="category" class="form-control" required>
                                        <option value="">-- Select Category --</option>
                                        <option value="transactional" {{ old('category', $notification->category) == 'transactional' ? 'selected' : '' }}>Transactional (Orders, Payments)</option>
                                        <option value="helpful" {{ old('category', $notification->category) == 'helpful' ? 'selected' : '' }}>Helpful (Tips, Recommendations)</option>
                                        <option value="updates" {{ old('category', $notification->category) == 'updates' ? 'selected' : '' }}>Updates & Newsletters</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Link to Product (optional)</label>
                                    <select name="product_id" class="form-control">
                                        <option value="">-- None --</option>
                                        @foreach ($products as $product)
                                            <option value="{{ $product->id }}" {{ old('product_id', $notification->product_id) == $product->id ? 'selected' : '' }}>{{ $product->name }}</option>
                                        @endforeach
                                    </select>
                                    <small class="form-text text-muted">Tapping the notification opens this product's page in the app.</small>
                                </div>
                            </div>

                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Message</label>
                                    <textarea name="message" class="form-control" rows="5" required>{{ old('message', $notification->message) }}</textarea>
                                </div>
                            </div>

                        </div>

                        <button type="submit" class="btn btn-primary">
                            Update
                        </button>

                        <a href="{{ route('notifications.index') }}" class="btn btn-secondary">
                            Back
                        </a>
                    </form>
                </div>

            </div>
        </div>
    </div>

@endsection
