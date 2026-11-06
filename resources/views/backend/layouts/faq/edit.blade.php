@extends('backend.master')

@section('title')
    FAQ - Edit
@endsection

@section('body')
    <x-admin.page-header title="FAQ Edit"
        :crumbs="['FAQ' => null, 'Edit' => null]" />

<div class="row">
        <div class="col-lg-12">
            <div class="card">
<div class="card-body">
                    <form method="post" action="{{ route('faqs.update', $faq->id) }}" enctype="multipart/form-data">
                        @csrf
                        <div class="row">
                            <div class="col-md-8">
                                <div class="mb-3">
                                    <label class="form-label">Question</label>
                                    <input type="text" class="form-control" name="question" value="{{ $faq->question }}" placeholder="Enter Question">
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="mb-3">
                                    <label class="form-label">Category</label>
                                    <select class="form-control" name="category">
                                        <option value="">None</option>
                                        @foreach($categories as $category)
                                            <option value="{{ $category }}" {{ $faq->category == $category ? 'selected' : '' }}>{{ $category }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-2">
                                <div class="mb-3">
                                    <label class="form-label">Sort Order</label>
                                    <input type="number" class="form-control" name="sort_order" value="{{ $faq->sort_order }}">
                                </div>
                            </div>

                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label class="form-label">Answer</label>
                                    <textarea class="form-control summernote" name="answer" placeholder="Enter Answer">{{ $faq->answer }}</textarea>
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

        // Summernote textarea-te content sync kore submit er age
        $('form').on('submit', function() {
            $('.summernote').each(function() {
                var content = $(this).summernote('code');
                $(this).val(content);
            });
        });
    });
</script>
@endpush
