@extends('backend.master')

@section('title')
    Category - Edit
@endsection

@section('body')
    <x-admin.page-header title="Category Edit"
        :crumbs="['Category' => null, 'Edit' => null]" />

<div class="col-md-12">
        <div class="card">
            <div class="card-header border-bottom">
                <h3 class="card-title">Edit Category</h3>
            </div>
            <div class="card-body">
                <form action="{{ route('category.update', $category->id) }}" method="POST" enctype="multipart/form-data">
                    @csrf

                    <div class="row">
                        <!-- Category Name -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Category Name</label>
                                <input type="text" class="form-control" name="name"
                                    value="{{ old('name', $category->name) }}" placeholder="Enter Category Name">
                            </div>
                        </div>

                        <!-- Inapp Name -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">In App Name</label>
                                <input type="text" class="form-control" name="inapp_name"
                                    value="{{ old('inapp_name', $category->inapp_name) }}" placeholder="Enter In App Name">
                            </div>
                        </div>




                        <!-- New Image -->
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label for="image" class="form-label">Image</label>
                                <input class="form-control dropify" type="file" name="image"
                                    @isset($category->image)
                                                data-default-file="{{ asset($category->image) }}"
                                    @endisset>
                                @error('image')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>




                        <div class="text-end">
                            <button type="submit" class="btn btn-danger">Update Category</button>
                        </div>
                </form>
            </div>
        </div>
    </div>
@endsection
