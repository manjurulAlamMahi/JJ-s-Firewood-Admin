@extends('backend.master')

@section('title')
    Tips and Guides - Edit
@endsection

@section('body')
    <x-admin.page-header title="Tips and Guides Edit"
        :crumbs="['Tips and Guides' => null, 'Edit' => null]" />

<div class="row">
        <div class="col-lg-12">
            <div class="card">
<div class="card-body">
                    <form method="post" action="{{ route('tips-and-guides.update', $tipsAndGuide->id) }}" enctype="multipart/form-data">
                        @csrf
                        <div class="row">
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Title</label>
                                    <input type="text" class="form-control" name="title" placeholder="Enter Title" value="{{ $tipsAndGuide->title }}">
                                </div>
                            </div>

                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Short Description</label>
                                    <textarea class="form-control" name="short_description" placeholder="Enter Short Description">{{ $tipsAndGuide->short_description }}</textarea>
                                </div>
                            </div>

                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Long Description</label>
                                    <textarea class="form-control summernote" name="long_description" placeholder="Enter Long Description">{{ $tipsAndGuide->long_description }}</textarea>
                                </div>
                            </div>

                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Image</label>
                                    <input type="file" class="form-control dropify" name="image" data-default-file="{{ $tipsAndGuide->image ? asset('uploads/tips_and_guides/' . $tipsAndGuide->image) : '' }}">
                                </div>
                            </div>

                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Is Featured</label>
                                    <select class="form-control" name="is_featured">
                                        <option value="0" @if($tipsAndGuide->is_featured == 0) selected @endif>No</option>
                                        <option value="1" @if($tipsAndGuide->is_featured == 1) selected @endif>Yes</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Category</label>
                                    <select class="form-control" name="category">
                                        <option value="">None</option>
                                        @foreach($categories as $category)
                                            <option value="{{ $category }}" {{ $tipsAndGuide->category == $category ? 'selected' : '' }}>{{ $category }}</option>
                                        @endforeach
                                    </select>
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

@push('scripts')
<script>
    $(document).ready(function() {
        // Show session flash messages via toastr
        @if(session('success'))
            toastr.success('{{ session('success') }}');
        @endif
        @if(session('error'))
            toastr.error('{{ session('error') }}');
        @endif

        $('.summernote').summernote({
            height: 300,
        });

        // Summernote textarea content sync kore submit er age
        $('form').on('submit', function() {
            $('.summernote').each(function() {
                var content = $(this).summernote('code');
                $(this).val(content);
            });
        });
    });
</script>
@endpush