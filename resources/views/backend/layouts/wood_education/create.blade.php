@extends('backend.master')

@section('title')
Wood Education - Create
@endsection

@section('body')
    <x-admin.page-header title="Add Wood Education Content"
        :crumbs="['Wood Education' => null, 'Create' => null]" />

<div class="card">
    <div class="card-body">
        <form method="post" action="{{ route('wood-education.store') }}" enctype="multipart/form-data">
            @csrf
            <div class="row">
                <div class="col-md-8">
                    <div class="mb-3">
                        <label class="form-label">Title</label>
                        <input type="text" class="form-control" name="title" value="{{ old('title') }}" placeholder="Enter Title">
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="mb-3">
                        <label class="form-label">Category</label>
                        <select class="form-control" name="category_id">
                            <option value="">Select Category</option>
                            @foreach($categories as $category)
                                <option value="{{ $category->id }}">{{ $category->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-2">
                    <div class="mb-3">
                        <label class="form-label">Sub-Category (optional)</label>
                        <select class="form-control" name="sub_category_id">
                            <option value="">None</option>
                            @foreach($subCategories as $subCategory)
                                <option value="{{ $subCategory->id }}">{{ $subCategory->name }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div class="col-md-12">
                    <div class="mb-3">
                        <label class="form-label">Short Description</label>
                        <textarea name="short_description" class="form-control" rows="3" placeholder="Enter short description (shown on the category screen card)">{{ old('short_description') }}</textarea>
                    </div>
                </div>

                <div class="col-md-12">
                    <div class="mb-3">
                        <label class="form-label">Long Description (full content)</label>
                        <textarea id="long_description" name="long_description" class="form-control summernote" placeholder="Enter full content">{{ old('long_description') }}</textarea>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Image</label>
                        <input type="file" class="form-control" name="image">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label class="form-label">Published At</label>
                        <input type="date" class="form-control" name="published_at" value="{{ old('published_at') }}">
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Create</button>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>
<script>
    $(document).ready(function() {
        $('.summernote').summernote({
            height: 250
        });
    });
</script>
@endpush
