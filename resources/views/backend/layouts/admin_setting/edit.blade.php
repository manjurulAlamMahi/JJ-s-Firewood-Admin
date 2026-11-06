@extends('backend.master')

@section('title')
    System Settings
@endsection

@section('body')
    <x-admin.page-header title="Admin Settings"
        :crumbs="['Settings' => null, 'Admin Settings' => null]" />

<div class="page">
        <div class="page-main">
            <div class="row">
                <div class="col-md-12">

                    {{-- Success Message --}}
                    @if(session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    {{-- Error Message --}}
                    @if(session('error'))
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            {{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    {{-- Validation Errors --}}
                    @if($errors->any())
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <ul class="mb-0">
                                @foreach($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    <div class="card">
<div class="card-body">
                            <form action="{{ route('admin.settings.update') }}" method="POST" enctype="multipart/form-data">
                                @csrf
                                <div class="row">

                                    {{-- Admin Logo --}}
                                    <div class="col-md-6 mb-3">
                                        <label for="admin_logo" class="form-label">Admin Logo</label>
                                        @if($settings->admin_logo)
                                            <div class="mb-2">
                                                <img src="{{ asset($settings->admin_logo) }}" alt="Admin Logo" class="img-thumbnail" style="height: 60px;">
                                            </div>
                                        @endif
                                        <input type="file" 
                                            name="admin_logo" 
                                            id="admin_logo" 
                                            class="form-control @error('admin_logo') is-invalid @enderror"
                                            accept="image/jpeg,image/jpg,image/png,image/svg+xml">
                                        @error('admin_logo')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        <small class="form-text text-muted">Accepted formats: JPG, PNG, SVG. Max size: 2MB</small>
                                    </div>

                                    {{-- Admin Favicon --}}
                                    <div class="col-md-6 mb-3">
                                        <label for="admin_favicon" class="form-label">Admin Favicon</label>
                                        @if($settings->admin_favicon)
                                            <div class="mb-2">
                                                <img src="{{ asset($settings->admin_favicon) }}" alt="Admin Favicon" class="img-thumbnail" style="height: 40px;">
                                            </div>
                                        @endif
                                        <input type="file" 
                                            name="admin_favicon" 
                                            id="admin_favicon" 
                                            class="form-control @error('admin_favicon') is-invalid @enderror"
                                            accept="image/png,image/ico,image/jpeg,image/jpg">
                                        @error('admin_favicon')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        <small class="form-text text-muted">Accepted formats: PNG, ICO, JPG. Max size: 1MB</small>
                                    </div>

                                    {{-- Admin Title --}}
                                    <div class="col-md-6 mb-3">
                                        <label for="admin_title" class="form-label">Admin Title</label>
                                        <input type="text" 
                                            name="admin_title" 
                                            id="admin_title"
                                            value="{{ old('admin_title', $settings->admin_title) }}"
                                            class="form-control @error('admin_title') is-invalid @enderror"
                                            placeholder="Enter admin title">
                                        @error('admin_title')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    {{-- Admin Short Title --}}
                                    <div class="col-md-6 mb-3">
                                        <label for="short_title" class="form-label">Admin Short Title</label>
                                        <input type="text" 
                                            name="short_title" 
                                            id="short_title"
                                            value="{{ old('short_title', $settings->short_title) }}"
                                            class="form-control @error('short_title') is-invalid @enderror"
                                            placeholder="Enter short title">
                                        @error('short_title')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    {{-- Copyright Text --}}
                                    <div class="col-md-12 mb-3">
                                        <label for="admin_copyright_text" class="form-label">Admin Copyright Text</label>
                                        <textarea name="admin_copyright_text"
                                                id="admin_copyright_text"
                                                class="form-control @error('admin_copyright_text') is-invalid @enderror"
                                                rows="3"
                                                placeholder="Enter copyright text">{{ old('admin_copyright_text', $settings->admin_copyright_text) }}</textarea>
                                        @error('admin_copyright_text')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    {{-- Submit Button --}}
                                    <div class="col-md-12 text-end">
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-save me-2"></i>Update Admin Settings
                                        </button>
                                    </div>

                                </div>
                            </form>

                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    function previewImage(input, imgContainer) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                let existingImg = imgContainer.querySelector('.mb-2 img');
                if (existingImg) {
                    existingImg.src = e.target.result;
                } else {
                    const div = document.createElement('div');
                    div.className = 'mb-2';
                    const img = document.createElement('img');
                    img.className = 'img-thumbnail';
                    img.style.height = input.id === 'admin_favicon' ? '40px' : '60px';
                    img.src = e.target.result;
                    img.alt = 'Preview';
                    div.appendChild(img);
                    input.parentElement.insertBefore(div, input);
                }
            };
            reader.readAsDataURL(input.files[0]);
        }
    }
    
    const logoInput = document.getElementById('admin_logo');
    logoInput.addEventListener('change', function() {
        previewImage(this, this.parentElement);
    });
    
    const faviconInput = document.getElementById('admin_favicon');
    faviconInput.addEventListener('change', function() {
        previewImage(this, this.parentElement);
    });
});
</script>
@endpush