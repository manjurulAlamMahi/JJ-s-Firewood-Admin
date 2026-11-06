@extends('backend.master')

@section('title')
    Service Sub Category - Edit
@endsection

@section('body')

    <x-admin.page-header title="Service Sub Category Edit"
        :crumbs="['Service Sub Category' => null, 'Edit' => null]" />

<div class="col-md-12">
        <div class="card">
            <div class="card-header border-bottom">
                <h3 class="card-title">Edit Service Sub Category</h3>
            </div>


            <div class="card-body">
                <form action="{{ route('services_sub_category.update', $data->id) }}" method="POST"
                    enctype="multipart/form-data">
                    @csrf

                    <div class="row">

                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">Select Service Category</label>
                                <select name="service_category_id"
                                    class="form-control @error('service_category_id') is-invalid @enderror" required>
                                    <option value="">-- Select Category --</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}"
                                            {{ $data->service_category_id == $category->id ? 'selected' : '' }}>
                                            {{ $category->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('service_category_id')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>


                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">Sub Category Name</label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror"
                                    name="name" placeholder="Enter Sub Category Name"
                                    value="{{ old('name', $data->name) }}" required>
                                @error('name')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>


                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">In App Name</label>
                                <input type="text" class="form-control @error('inapp_name') is-invalid @enderror"
                                    name="inapp_name" placeholder="Enter In App Name"
                                    value="{{ old('inapp_name', $data->inapp_name) }}">
                                @error('inapp_name')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>


                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control" placeholder="Enter Description">{{ old('description', $data->description) }}</textarea>
                            </div>
                        </div>


                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">Image</label>
                                <input type="file" class="form-control dropify @error('image') is-invalid @enderror"
                                    name="image" data-default-file="{{ $data->image ? asset($data->image) : '' }}">
                                @error('image')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>


                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">Features</label>
                                <div id="feature-wrapper">
                                    @if ($data->features && $data->features->count())
                                        @foreach ($data->features as $feature)
                                            <div class="feature-item mb-2 d-flex">
                                                <input type="text" name="features[]" class="form-control me-2"
                                                    value="{{ $feature->name }}" required>
                                                <button type="button" class="btn btn-danger remove-feature-btn">X</button>
                                            </div>
                                        @endforeach
                                    @endif
                                </div>
                                <button type="button" id="add-feature-btn" class="btn btn-primary btn-sm mt-2">Add
                                    Feature</button>
                            </div>
                        </div>

                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-success">Update</button>
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
                this.parentElement.remove();
            });
        });
    </script>
@endpush
