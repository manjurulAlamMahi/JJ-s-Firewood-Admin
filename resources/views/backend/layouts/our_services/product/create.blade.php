@extends('backend.master')

@section('title')
    Service Product - Create
@endsection

@section('body')    <x-admin.page-header title="Service Product Create"
        :crumbs="['Service Product' => null, 'Create' => null]" />

<div class="col-md-12">
        <div class="card">
            <div class="card-header border-bottom">
                <h3 class="card-title">Create New Service Product</h3>
            </div>
            <div class="card-body">
                <form action="{{ route('services.product.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="row">

                        <!-- Service Category -->
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">Service Category</label>
                                <select name="service_category_id"
                                    class="form-control @error('service_category_id') is-invalid @enderror">
                                    <option value="">Select Service Category</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}"
                                            {{ old('service_category_id') == $category->id ? 'selected' : '' }}>
                                            {{ $category->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('service_category_id')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <!-- Service SubCategory -->
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">Service SubCategory</label>
                                <select name="service_sub_category_id"
                                    class="form-control @error('service_sub_category_id') is-invalid @enderror">
                                    <option value="">Select Service SubCategory</option>
                                    @foreach ($subcategories as $subcategory)
                                        <option value="{{ $subcategory->id }}"
                                            {{ old('service_sub_category_id') == $subcategory->id ? 'selected' : '' }}>
                                            {{ $subcategory->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('service_sub_category_id')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <!-- Product Name -->
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">Product Name</label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror"
                                    name="name" placeholder="Enter Product Name" value="{{ old('name') }}">
                                @error('name')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <!-- Price -->
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">Price</label>
                                <input type="text"
                                    class="form-control @error('price') is-invalid @enderror" name="price"
                                    placeholder="Enter Price" value="{{ old('price') }}">
                                @error('price')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <!-- Installation Included -->
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">Installation Included</label>
                                <select name="installation_included"
                                    class="form-control @error('installation_included') is-invalid @enderror">
                                    <option value="1" {{ old('installation_included') == '1' ? 'selected' : '' }}>Yes
                                    </option>
                                    <option value="0" {{ old('installation_included') == '0' ? 'selected' : '' }}>No
                                    </option>
                                </select>
                                @error('installation_included')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <!-- Features -->
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">Features</label>
                                <div id="feature-wrapper">
                                    @if (old('features'))
                                        @foreach (old('features') as $feature)
                                            <div class="feature-item mb-2 d-flex">
                                                <input type="text" name="features[]" class="form-control me-2"
                                                    value="{{ $feature }}" placeholder="Enter feature" required>
                                                <button type="button" class="btn btn-danger remove-feature-btn">X</button>
                                            </div>
                                        @endforeach
                                    @endif
                                </div>
                                @error('features.*')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                                <button type="button" id="add-feature-btn" class="btn btn-primary btn-sm mt-2">Add
                                    Feature</button>
                            </div>
                        </div>

                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-danger">Create</button>
                    </div>
                </form>
            </div>
        </div>
    </div>


@endsection

@push('scripts')
    <script>
        let featureWrapper = document.getElementById('feature-wrapper');
        let addFeatureBtn = document.getElementById('add-feature-btn');

        addFeatureBtn.addEventListener('click', function() {
            let div = document.createElement('div');
            div.classList.add('feature-item', 'mb-2', 'd-flex');

            div.innerHTML = `
            <input type="text" name="features[]" class="form-control me-2" placeholder="Enter feature" required>
            <button type="button" class="btn btn-danger remove-feature-btn">X</button>
        `;

            featureWrapper.appendChild(div);

            div.querySelector('.remove-feature-btn').addEventListener('click', function() {
                featureWrapper.removeChild(div);
            });
        });


        document.querySelectorAll('.remove-feature-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                btn.parentElement.remove();
            });
        });
    </script>
@endpush
