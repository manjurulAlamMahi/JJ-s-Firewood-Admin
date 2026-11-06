@extends('backend.master')

@section('title')
    Dynamic Pages - Create
@endsection

@section('body')
    <x-admin.page-header title="Dynamic Pages Create"
        :crumbs="['Dynamic Pages' => null, 'Create' => null]" />

<div class="col-md-12">
        <div class="card">
<div class="card-body">
                <p class="text-muted">Fill the form to create a new Dynamic Page.</p>

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

                <form class="form-horizontal" method="POST" action="{{ route('dynamicpage.store') }}">
                    @csrf


                    <div class="mb-4">
                        <label for="title" class="form-label">Page Title</label>
                        <input type="text" id="title" name="title" class="form-control" placeholder="Enter Title" value="{{ old('title') }}" >
                    </div>

                    <div class="mb-4">
                        <label for="page_content" class="form-label">Page Content</label>
                        <textarea id="page_content" name="page_content" class="form-control" placeholder="Enter Page Content" >{{ old('page_content') }}</textarea>
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-danger">Create User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>


@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        $('#page_content').summernote({
            placeholder: 'Enter Page Content',
            tabsize: 2,
            height: 300
        });
    });
</script>
@endpush
