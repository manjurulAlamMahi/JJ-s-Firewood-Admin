@extends('backend.master')

@section('title')
Bundles - Create
@endsection

@section('body')
    <x-admin.page-header title="Add Bundle"
        :crumbs="['Bundles' => null, 'Create' => null]" />

<div class="card">
    <div class="card-body">
        <form method="post" action="{{ route('bundles.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="row">
                <div class="col-md-8">
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" class="form-control" name="name" value="{{ old('name') }}" placeholder="Enter Bundle Name">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">Base Product</label>
                        <select class="form-control" name="base_product_id">
                            <option value="">Select Base Product</option>
                            @foreach($products as $product)
                                <option value="{{ $product->id }}">{{ $product->name }} (${{ $product->price }})</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="col-md-12">
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="3">{{ old('description') }}</textarea>
                    </div>
                </div>

                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">Base Price Override (optional)</label>
                        <input type="number" step="0.01" min="0" class="form-control" name="base_price" value="{{ old('base_price') }}" placeholder="Leave blank to use the base product's own price">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">Bundle Discount</label>
                        <div class="input-group">
                            <input type="number" step="0.01" min="0" max="100" class="form-control" name="discount_percentage" value="{{ old('discount_percentage', 0) }}">
                            <span class="input-group-text">%</span>
                        </div>
                        <small class="form-text text-muted">Applied to base + selected extras at checkout. 0 = no discount.</small>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="mb-3">
                        <label class="form-label">Image</label>
                        <input type="file" class="form-control" name="image">
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Optional Extras</label>
                        <select class="form-control" name="extras[]" multiple size="8">
                            @foreach($products as $product)
                                <option value="{{ $product->id }}">{{ $product->name }} (${{ $product->price }})</option>
                            @endforeach
                        </select>
                        <small class="form-text text-muted">Hold Ctrl/Cmd to select multiple. Customer can toggle these on/off.</small>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Required Extras</label>
                        <select class="form-control" name="required_extras[]" multiple size="8">
                            @foreach($products as $product)
                                <option value="{{ $product->id }}">{{ $product->name }} (${{ $product->price }})</option>
                            @endforeach
                        </select>
                        <small class="form-text text-muted">Hold Ctrl/Cmd to select multiple. Always included, customer cannot remove.</small>
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Create</button>
        </form>
    </div>
</div>
@endsection
