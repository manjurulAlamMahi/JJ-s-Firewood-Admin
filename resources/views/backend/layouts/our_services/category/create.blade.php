@extends('backend.master')

@section('title')
   Service Category - Create
@endsection

@section('body')
    <x-admin.page-header title="Service Category Create"
        :crumbs="['Service Category' => null, 'Create' => null]" />

<div class="col-md-12">
        <div class="card">
            <div class="card-header border-bottom">
                <h3 class="card-title">Create New Service Category</h3>
            </div>

            <div class="card-body">
                <form action="{{ route('services.category.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf

                    <div class="row">

                        <!-- Category Name -->
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">Category Name</label>
                                <input
                                    type="text"
                                    class="form-control @error('name') is-invalid @enderror"
                                    name="name"
                                    placeholder="Enter Category Name"
                                    value="{{ old('name') }}"
                                >
                                @error('name')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                        <!-- In App Name -->
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">In App Name</label>
                                <input
                                    type="text"
                                    class="form-control @error('inapp_name') is-invalid @enderror"
                                    name="inapp_name"
                                    placeholder="Enter In App Name"
                                    value="{{ old('inapp_name') }}"
                                >
                                @error('inapp_name')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>

                         <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">URL</label>
                                <input
                                    type="text"
                                    class="form-control @error('url') is-invalid @enderror"
                                    name="url"
                                    placeholder="Enter URL"
                                    value="{{ old('url') }}"
                                >
                                @error('url')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
                            </div>
                        </div>



                        <!-- Image -->
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">Image</label>
                                <input
                                    type="file"
                                    class="form-control dropify @error('image') is-invalid @enderror"
                                    name="image"
                                >
                                @error('image')
                                    <span class="text-danger">{{ $message }}</span>
                                @enderror
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
