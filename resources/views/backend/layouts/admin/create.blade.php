@extends('backend.master')

@section('title')
    Notification - Create
@endsection

@section('body')
    <x-admin.page-header title="Notification Create"
        :crumbs="['Notification' => null, 'Create' => null]" />

@if (session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Create Manual Notification</h3>
                </div>

                <div class="card-body">
                    <form method="POST" action="{{ route('notifications.store') }}">
                        @csrf

                        <div class="row">

                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Title</label>
                                    <input type="text" name="title" class="form-control" placeholder="Enter  title"
                                        required>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Category</label>
                                    <select name="category" class="form-control" required>
                                        <option value="">-- Select Category --</option>
                                        <option value="transactional">Transactional (Orders, Payments)</option>
                                        <option value="helpful">Helpful (Tips, Recommendations)</option>
                                        <option value="updates">Updates & Newsletters</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Link to Product (optional)</label>
                                    <select name="product_id" class="form-control">
                                        <option value="">-- None --</option>
                                        @foreach ($products as $product)
                                            <option value="{{ $product->id }}">{{ $product->name }}</option>
                                        @endforeach
                                    </select>
                                    <small class="form-text text-muted">Tapping the notification opens this product's page in the app.</small>
                                </div>
                            </div>

                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Message</label>
                                    <textarea name="message" class="form-control" rows="5" placeholder="Enter  message" required></textarea>
                                </div>
                            </div>

                        </div>

                        <button type="submit" class="btn btn-primary">
                            Create
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
