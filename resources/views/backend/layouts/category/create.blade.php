@extends('backend.master')

@section('title')
    Category - Create
@endsection

@section('body')
    <x-admin.page-header title="Category Create"
        :crumbs="['Category' => null, 'Create' => null]" />

<div class="col-md-12">
        <div class="card">
            <div class="card-header border-bottom">
                <h3 class="card-title">Create New Category</h3>
            </div>
            <div class="card-body">
                <form action="{{ route('category.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="row">

                        <!-- Category Name -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Category Name</label>
                                <input type="text" class="form-control" name="name" placeholder="Enter Category Name"
                                    value="{{ old('name') }}">
                            </div>
                        </div>

                        <!-- Inapp Name -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">In App Name</label>
                                <input type="text" class="form-control" name="inapp_name" placeholder="Enter In App Name"
                                    value="{{ old('inapp_name') }}">
                            </div>
                        </div>

                        <!-- Description -->
                        {{-- <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" rows="4" placeholder="Enter Description">{{ old('description') }}</textarea>
                            </div>
                        </div> --}}

                        <!-- Image -->
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">Image</label>
                                <input type="file" class="form-control dropify" name="image">
                            </div>
                        </div>



                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-danger">Create Category</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
