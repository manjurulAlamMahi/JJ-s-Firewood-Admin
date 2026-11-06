@extends('backend.master')

@section('title')
    SubCategory - Create
@endsection

@section('body')
    <x-admin.page-header title="SubCategory Create"
        :crumbs="['SubCategory' => null, 'Create' => null]" />

<div class=" col-md-12">
        <div class="card">
            <div class="card-header border-bottom">
                <h3 class="card-title">Create New SubCategory</h3>
            </div>
            <div class="card-body">
                <form action="{{ route('subcategory.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="row">
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">Category</label>
                                <select name="category_id" class="form-control">
                                    <option value="">Select Category</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">SubCategory Name</label>
                                <input type="text" class="form-control" name="name" placeholder="Enter SubCategory Name">
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" id="description" rows="4" placeholder="Enter Description" maxlength="130"></textarea>
                                <small class="text-muted"><span id="charCount">0</span>/130 characters</small>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">Image</label>
                                <input type="file" class="form-control dropify" name="image">
                            </div>
                        </div>



                    </div>
                    <div class="text-end">
                        <button type="submit" class="btn btn-danger">Create SubCategory</button>
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
