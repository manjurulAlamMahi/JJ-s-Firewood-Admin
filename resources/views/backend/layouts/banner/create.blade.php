@extends('backend.master')

@section('title')
    Banner - Create
@endsection

@section('body')
    <x-admin.page-header title="Banner Create"
        :crumbs="['Banner' => null, 'Create' => null]" />

<div class="row">
        <div class="col-lg-12">
            <div class="card">
<div class="card-body">
                    <form method="post" action="{{ route('banner.store') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Title</label>
                                    <input type="text" class="form-control" name="title" placeholder="Enter Title">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Sub Title</label>
                                    <input type="text" class="form-control" name="sub_title" placeholder="Enter Sub Title">
                                </div>
                            </div>

                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Image</label>
                                    <input type="file" class="form-control dropify" name="image">
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary">Create</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
