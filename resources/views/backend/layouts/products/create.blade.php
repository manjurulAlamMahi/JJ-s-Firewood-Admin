@extends('backend.master')
@section('title')
    Product - Create
@endsection
@section('body')
    <!-- PAGE-HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">Product Create</h1>
        </div>
        <div class="ms-auto pageheader-btn"></div>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="javascript:void(0);">Product</a></li>
            <li class="breadcrumb-item active" aria-current="page">Create</li>
        </ol>
    </div>
    <!-- PAGE-HEADER END -->
    <div class=" col-md-12">
        <div class="card">
            <div class="card-header border-bottom">
                <h3 class="card-title">Create New Product</h3>
            </div>
            <div class="card-body">
                @if ($errors->any())
                    <div class="alert alert-danger mb-3">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                <form action="{{ route('products.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Category</label>
                                <select name="category_id" id="category_id" class="form-control">
                                    <option value="">Select Category</option>
                                    @foreach ($categories as $category)
                                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">SubCategory</label>
                                <select name="subcategory_id" id="subcategory_id" class="form-control">
                                    <option value="">Select SubCategory</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Product Name</label>
                                <input type="text" class="form-control" name="name" placeholder="Enter Product Name">
                            </div>
                        </div>

                        

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Short Description</label>
                                <textarea class="form-control" name="short_title" id="short_title" rows="4" placeholder="Enter Short Description" maxlength="200"></textarea>
                                <small class="text-muted"><span id="CharCount">0</span>/200 characters</small>
                            </div>
                        </div>


                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Price</label>
                                <input type="text" class="form-control" name="price" placeholder="Enter Price">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Quantity</label>
                                <input type="text" class="form-control" name="quantity" placeholder="Enter Quantity">
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea id="description" class="form-control" name="description" rows="4" placeholder="Enter Description"></textarea>
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">Image</label>
                                <input type="file" class="form-control dropify" name="image">
                            </div>
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Other Image</label>
                            <div class="row" id="other-images-container">
                                <div class="col-md-4 mb-2">
                                    <input type="file" class="form-control dropify" name="other_image[]">
                                </div>
                            </div>
                            <button type="button" class="btn btn-primary btn-sm mb-3" id="add-more-images">Add More Image</button>
                        </div>

                        {{-- <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Size Info</label>
                                <input type="text" class="form-control" name="size_info" placeholder="Enter Size Info">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Burn Quantity Info</label>
                                <input type="text" class="form-control" name="burn_quantity_info"
                                    placeholder="Enter Burn Quantity Info">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Delivery Info</label>
                                <input type="text" class="form-control" name="delivery_info"
                                    placeholder="Enter Delivery Info">
                            </div>
                        </div> --}}



                    </div>

                    <!-- Upsell UI -->
                    @include('backend.layouts.products.upsell_ui')

                    <button type="submit" class="btn btn-danger">Submit</button>
                </form>
            </div>

        </div>
    </div>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        $('#description').summernote({
            height: 100,
            placeholder: 'Enter Description',
            toolbar: [
                ['style', ['bold', 'italic', 'underline', 'clear']],
                ['font', ['strikethrough', 'superscript', 'subscript']],
                ['fontsize', ['fontsize']],
                ['color', ['color']],
                ['para', ['ul', 'ol', 'paragraph']],
                ['insert', ['link', 'picture', 'video']],
                ['view', ['fullscreen', 'codeview', 'help']]
            ]
        });
    });
</script>
<script>
$(document).ready(function () {
    $('#category_id').on('change', function () {
        var categoryId = $(this).val();
        $('#subcategory_id').empty().append('<option value="">Select SubCategory</option>');

        if (categoryId) {
            $.ajax({
                url: "{{ url('/get-subcategories') }}/" + categoryId,
                type: "GET",
                dataType: "json",
                success: function (data) {
                    $.each(data, function (key, value) {
                        $('#subcategory_id').append('<option value="' + value.id + '">' + value.name + '</option>');
                    });
                }
            });
        }
    });
});
</script>

    <script>
        $(document).ready(function() {
            $('#add-more-images').click(function() {
                var newField = `
                    <div class="col-md-3 mb-2 row-container">
                        <input type="file" class="form-control dropify" name="other_image[]">
                        <button type="button" class="btn btn-danger btn-sm mt-1 remove-image">Remove</button>
                    </div>`;
                $('#other-images-container').append(newField);
                $('.dropify').dropify();
            });

            $(document).on('click', '.remove-image', function() {
                $(this).closest('.row-container').remove();
            });
        });
    </script>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const description = document.getElementById('short_title');
        const charCount = document.getElementById('CharCount');
        const maxLength = 200;
        
        description.addEventListener('input', function() {
            charCount.textContent = this.value.length;
            
            if (this.value.length >= maxLength) {
                charCount.parentNode.classList.add('text-danger');
                charCount.parentNode.classList.remove('text-muted');
            } else {
                charCount.parentNode.classList.remove('text-danger');
                charCount.parentNode.classList.add('text-muted');
            }
        });
    });
</script>

@endpush

