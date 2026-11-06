@extends('backend.master')

@section('title')
    Service Category - Edit
@endsection

@section('body')
    <x-admin.page-header title="Edit Service Category"
        :crumbs="['Service Category' => null, 'Edit' => null]" />

<div class="col-md-12">
        <div class="card">
            <div class="card-header border-bottom">
                <h3 class="card-title">Update Service Category</h3>
            </div>

            <div class="card-body">
                <form action="{{ route('services.category.update', $category->id) }}" method="POST"
                    enctype="multipart/form-data">
                    @csrf

                    <div class="row">

                        <!-- Category Name -->
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">Category Name</label>
                                <input type="text" class="form-control @error('name') is-invalid @enderror"
                                    name="name" value="{{ old('name', $category->name) }}"
                                    placeholder="Enter Category Name">
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
                                    name="inapp_name" value="{{ old('inapp_name', $category->inapp_name) }}"
                                    placeholder="Enter In App Name">
                                @error('inapp_name')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>


                        <!-- URL -->                        
                         <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">URL</label>
                                <input type="text" class="form-control @error('url') is-invalid @enderror"
                                    name="url" value="{{ old('url', $category->url) }}"
                                    placeholder="Enter URL">
                                @error('url')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <!-- Existing Image -->
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Current Image</label><br>

                            @if ($category->image && file_exists(public_path($category->image)))
                                <img src="{{ asset($category->image) }}" width="120" class="img-thumbnail">
                            @else
                                <img src="{{ asset('no_image/no_image.jpg') }}" width="120" class="img-thumbnail">
                            @endif
                        </div>

                        <!-- Upload New Image -->
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">Change Image</label>
                                <input type="file" class="form-control dropify @error('image') is-invalid @enderror"
                                    name="image">
                                @error('image')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-primary">Update Category</button>
                        <a href="{{ route('services.category.index') }}" class="btn btn-secondary">Back</a>
                    </div>

                </form>
            </div>
        </div>
    </div>
@endsection
