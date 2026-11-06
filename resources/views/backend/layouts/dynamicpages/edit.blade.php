@extends('backend.master')

@section('title')
    Dynamic Pages - Edit
@endsection

@section('body')
    <x-admin.page-header title="Dynamic Pages Edit"
        :crumbs="['Dynamic Pages' => null, 'Edit' => null]" />

<div class="col-md-12">
        <div class="card">
<div class="card-body">
                <p class="text-muted">Update the details of the Dynamic Page.</p>

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

                <form class="form-horizontal" method="POST" action="{{ route('dynamicpage.update',$dynamicpage->id) }}">
                    @csrf
                    @method('PUT')

                    <div class="mb-4">
                        <label for="title" class="form-label">Page Title</label>
                        <input type="text" id="title" name="title" class="form-control" placeholder="Enter Title"
                            value="{{ old('title', $dynamicpage->title) }}" >
                    </div>

                    <div class="mb-4">
                        <label for="page_content" class="form-label">Page Content</label>
                        <textarea id="page_content" name="page_content" class="form-control" placeholder="Enter Page Content">{{ old('page_content', $dynamicpage->page_content) }}</textarea>
                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-danger">Update Page</button>
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
