@extends('backend.master')

@section('title')
    Selling Point - Edit
@endsection

@section('body')
    <x-admin.page-header title="Selling Point Edit"
        :crumbs="['Selling Point' => null, 'Edit' => null]" />

<div class="row">
        <div class="col-lg-12">
            <div class="card">
<div class="card-body">

                    <form method="post" action="{{ route('selling-point.update', $selling_point->id) }}" enctype="multipart/form-data">
                        @csrf

                        <div class="row">

                            <!-- Name -->
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Name</label>
                                    <input type="text" class="form-control" name="name"
                                           value="{{ $selling_point->name }}"
                                           placeholder="Enter Name">
                                    @error('name')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>
                            </div>

                            <!-- Description -->
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea class="form-control" name="description" rows="4"
                                              placeholder="Enter Description">{{ $selling_point->description }}</textarea>
                                    @error('description')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>
                            </div>

                            <!-- Image -->
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Image</label>

                                    <input type="file" class="form-control dropify" name="image"
                                           @if($selling_point->image)
                                               data-default-file="{{ asset($selling_point->image) }}"
                                           @endif
                                    >

                                    @error('image')
                                        <small class="text-danger">{{ $message }}</small>
                                    @enderror
                                </div>
                            </div>

                        </div>

                        <button type="submit" class="btn btn-primary">Update</button>

                    </form>

                </div>
            </div>
        </div>
    </div>
@endsection
