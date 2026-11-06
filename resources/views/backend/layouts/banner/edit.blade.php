@extends('backend.master')

@section('title')
    Banner - Edit
@endsection

@section('body')
    <x-admin.page-header title="Banner Edit"
        :crumbs="['Banner' => null, 'Edit' => null]" />

<div class="row row-deck">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Edit Banner</h3>
                </div>
                <div class="card-body">
                    <form action="{{ route('banner.update', $banner->id) }}" method="post" enctype="multipart/form-data">
                        @csrf
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label class="form-label">Title</label>
                                    <input type="text" class="form-control" name="title" placeholder="Enter Title" value="{{ $banner->title }}">
                                </div>
                            </div>

                            <div class="col-md-12">
                                <div class="form-group">
                                    <label class="form-label">Sub Title</label>
                                    <input type="text" class="form-control" name="sub_title" placeholder="Enter Sub Title" value="{{ $banner->sub_title }}">
                                </div>
                            </div>

                            <div class="col-md-12">
                                <div class="form-group">
                                    <label class="form-label">Image</label>
                                    <input type="file" class="form-control dropify" name="image"
                                    @isset($banner->image)
                                                data-default-file="{{ asset($banner->image) }}"
                                    @endisset>
                            @error('image')
                                <div class="text-danger">{{ $message }}</div>
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
