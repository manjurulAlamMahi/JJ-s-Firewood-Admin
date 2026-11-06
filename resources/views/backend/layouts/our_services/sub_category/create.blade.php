@extends('backend.master')

@section('title')
    Service Sub Category - Create
@endsection

@section('body')
    <x-admin.page-header title="Service Sub Category Create"
        :crumbs="['Service Sub Category' => null, 'Create' => null]" />

<div class="col-md-12">
        <div class="card">
            <div class="card-header border-bottom">
                <h3 class="card-title">Create New Service Sub Category</h3>
            </div>

            <div class="card-body">
                <form action="{{ route('services_sub_category.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf

                    <div class="row">

                        <!-- Parent Category -->
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">Select Service Category</label>
                                <select name="service_category_id"
                                    class="form-control @error('service_category_id') is-invalid @enderror" required>
                                    <option value="">-- Select Category --</option>
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

                        <!-- Sub Category Name -->
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">Sub Category Name</label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror"
                                    name="name" placeholder="Enter Sub Category Name" value="{{ old('name') }}"
                                    required>
                                @error('name')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <!-- In App Name -->
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">In App Name</label>
                                <input type="text" class="form-control @error('inapp_name') is-invalid @enderror"
                                    name="inapp_name" placeholder="Enter In App Name" value="{{ old('inapp_name') }}">
                                @error('inapp_name')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <!-- Description -->
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control" placeholder="Enter Description">{{ old('description') }}</textarea>
                            </div>
                        </div>

                        <!-- Image -->
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">Image</label>
                                <input type="file" class="form-control dropify @error('image') is-invalid @enderror"
                                    name="image">
                                @error('image')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <!-- Features -->
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">Features</label>
                                <div id="feature-wrapper"></div>
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
    </script>
@endpush
