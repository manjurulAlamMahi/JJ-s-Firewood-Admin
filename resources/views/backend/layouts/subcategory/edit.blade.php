@extends('backend.master')

@section('title')
    SubCategory - Edit
@endsection

@section('body')
    <x-admin.page-header title="SubCategory Edit"
        :crumbs="['SubCategory' => null, 'Edit' => null]" />

<div class=" col-md-12">
        <div class="card">
            <div class="card-header border-bottom">
                <h3 class="card-title">Edit SubCategory</h3>
            </div>
            <div class="card-body">
                <p class="text-muted">Update the details of this SubCategory.</p>

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

                <form action="{{ route('subcategory.update', $subcategory->id) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="row">
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">Category</label>
                                <select name="category_id" class="form-control">
                                    <option value="">Select Category</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}" {{ $subcategory->category_id == $category->id ? 'selected' : '' }}>
                                            {{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">SubCategory Name</label>
                                <input type="text" class="form-control" name="name" value="{{ $subcategory->name }}" placeholder="Enter SubCategory Name">
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" id="description" rows="4" placeholder="Enter Description" maxlength="130">{{ $subcategory->description }}</textarea>
                                <small class="text-muted"><span id="charCount">{{ strlen($subcategory->description ?? '') }}</span>/130 characters</small>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="mb-3">
                            <label for="image" class="form-label">Image</label>
                            <input class="form-control dropify" type="file" name="image"
                                @isset($subcategory->image)
                                                data-default-file="{{ asset($subcategory->image) }}"
                                    @endisset>
                            @error('image')
                                <div class="text-danger">{{ $message }}</div>
                            @enderror
                        </div>
                        </div>





                        <div class="col-md-12">
                            <div class="mb-3">
                                <button type="submit" class="btn btn-danger">Update SubCategory</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>


@endsection

@push('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const description = document.getElementById('description');
        const charCount = document.getElementById('charCount');
        const maxLength = 130;
        
        // Check on load
        if (description.value.length >= maxLength) {
            charCount.parentNode.classList.add('text-danger');
            charCount.parentNode.classList.remove('text-muted');
        }
        
        description.addEventListener('input', function() {
            charCount.textContent = this.value.length;
            
            if (this.value.length >= maxLength) {
                charCount.parentNode.classList.add('text-danger');
                charCount.parentNode.classList.remove('text-muted');
            } else {
                charCount.parentNode.classList.remove('text-danger');
                charCount.parentNode.classList.add('text-muted');
            }
        });
    });
</script>
@endpush
