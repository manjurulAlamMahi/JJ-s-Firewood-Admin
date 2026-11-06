@extends('backend.master')

@section('title')
    Blog - Create
@endsection

@section('body')
    <!-- PAGE-HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">Create Blog</h1>
        </div>
        <div class="ms-auto pageheader-btn"></div>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ route('blog.index') }}">Blog</a></li>
            <li class="breadcrumb-item active" aria-current="page">Create</li>
        </ol>
    </div>
    <!-- PAGE-HEADER END -->

    <div class="col-md-12">
        <div class="card">
            <div class="card-header border-bottom">
                <h3 class="card-title">Create New Blog</h3>
            </div>
            <div class="card-body">
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

                <form action="{{ route('blog.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="row">
                        <!-- Blog Title -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Blog Title <span class="text-danger">*</span></label>
                            <input type="text" id="title" name="title" class="form-control" placeholder="Enter blog title" value="{{ old('title') }}" required>
                        </div>

                        <!-- Published At -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Published At</label>
                            <input type="date" name="published_at" class="form-control" value="{{ old('published_at') }}">
                        </div>

                        <!-- Short Description -->
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Short Description <span class="text-danger">*</span></label>
                            <textarea name="short_description" class="form-control" rows="3" placeholder="Enter short description" required>{{ old('short_description') }}</textarea>
                        </div>

                        <!-- Long Description -->
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Long Description</label>
                            <textarea id="long_description" name="long_description" class="form-control summernote" placeholder="Enter long description">{{ old('long_description') }}</textarea>
                        </div>

                        <!-- Image -->
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Image</label>
                            <input type="file" name="image" class="dropify" accept="image/*" data-height="200">
                        </div>

                    </div>

                    <button type="submit" class="btn btn-primary mt-3">Save Blog</button>
                    <a href="{{ route('blog.index') }}" class="btn btn-secondary mt-3">Cancel</a>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<link href="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.18/dist/summernote-lite.min.js"></script>
<script>
    $(document).ready(function() {
        $('.summernote').summernote({
            height: 300,
            toolbar: [
                ['style', ['style']],
                ['font', ['bold', 'underline', 'clear']],
                ['color', ['color']],
                ['para', ['ul', 'ol', 'paragraph']],
                ['table', ['table']],
                ['insert', ['link', 'picture', 'video']],
                ['view', ['fullscreen', 'codeview', 'help']]
            ]
        });

        $('.dropify').dropify();
    });
</script>
@endpush
