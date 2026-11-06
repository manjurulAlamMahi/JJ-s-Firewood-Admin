@extends('backend.master')

@section('title')
    Product - List
@endsection

@section('body')
    <x-admin.page-header title="Product List"
        :crumbs="['Product' => null, 'List' => null]">
        <x-slot:actions>
            <a href="{{ route('products.create') }}" class="btn btn-primary"> <i class="fa fa-plus"></i> Add Product </a>
        </x-slot:actions>
    </x-admin.page-header>

<!-- TABLE -->
    <div class="card">
<div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="products-table">
                    <thead>
                        <tr>
                            <th>SL No</th>
                            <th>Category</th>
                            <th>SubCategory</th>
                            <th>Product Name</th>
                            <th>Quantity</th>
                            <th>Price</th>
                            <th>Image</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <style>
        .switch {
            position: relative;
            display: inline-block;
            width: 50px;
            height: 26px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #dc3545;
            transition: .4s;
            border-radius: 34px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked+.slider {
            background-color: #28a745;
        }

        input:checked+.slider:before {
            transform: translateX(24px);
        }
    </style>

    <script>
        $(document).ready(function() {
            let csrfToken = $('meta[name="csrf-token"]').attr('content');

            let table = $('#products-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{{ route('products.data') }}',
                    type: 'GET',
                    error: function(xhr) {
                        console.log('DataTables error:', xhr.responseText);
                        alert('Error loading data. Check console for details.');
                    }
                },
                columns: [{
                        data: 'DT_RowIndex',
                        name: 'DT_RowIndex',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'category_name',
                        name: 'category_name',
                    },
                    {
                        data: 'subcategory_name',
                        name: 'subcategory_name',
                    },
                    {
                        data: 'name',
                        name: 'name',
                    },
                    {
                        data: 'quantity',
                        name: 'quantity',
                    },
                    {
                        data: 'price',
                        name: 'price',
                    },
                    {
                        data: 'image',
                        name: 'image',
                        orderable: false,
                        searchable: false,
                        render: function(data, type, row) {
                            return `<img src="${data}" class="img-thumbnail" style="width:60px;height:60px;">`;
                        }
                    },
                    {
                        data: 'status',
                        name: 'status',
                        orderable: false,
                        searchable: false,
                        width: '10%',
                        render: function(data, type, row) {
                            let checked = parseInt(data) === 1 ? 'checked' : '';
                            return `
                        <label class="switch">
                            <input type="checkbox" class="toggle-status" data-id="${row.id}" ${checked}>
                            <span class="slider"></span>
                        </label>`;
                        }
                    },
                    {
                        data: null,
                        name: 'action',
                        orderable: false,
                        searchable: false,
                        width: '10%',
                        render: function(data, type, row) {
                            let editUrl = `/products/${row.id}/edit`;
                            let deleteUrl = `/products/${row.id}`;
                            return `
                        <div style="display:flex; align-items:center;">
                            <a href="${editUrl}" class="btn btn-sm btn-warning me-1" title="Edit">
                                <i class="fa fa-edit"></i>
                            </a>
                            <form action="/products/${row.id}/duplicate" method="POST" style="display:inline-block;" class="me-1 duplicate-form">
                                <input type="hidden" name="_token" value="${csrfToken}">
                                <button type="button" class="btn btn-sm btn-info btn-duplicate" title="Duplicate">
                                    <i class="fa fa-copy"></i>
                                </button>
                            </form>
                            <form action="${deleteUrl}" method="POST" style="display:inline-block;" onsubmit="return confirm('Delete this Product?')">
                                <input type="hidden" name="_token" value="${csrfToken}">
                                <input type="hidden" name="_method" value="DELETE">
                                <button class="btn btn-sm btn-danger" title="Delete">
                                    <i class="fa fa-trash"></i>
                                </button>
                            </form>
                        </div>`;
                        }
                    }
                ],
                order: [
                    [0, 'desc']
                ]
            });

            // Duplicate Confirmation
            $(document).on('click', '.btn-duplicate', function(e) {
                e.preventDefault();
                let form = $(this).closest('form');

                Swal.fire({
                    title: 'Duplicate this product?',
                    text: "All details including images will be cloned.",
                    icon: 'info',
                    showCancelButton: true,
                    confirmButtonColor: '#3085d6',
                    cancelButtonColor: '#d33',
                    confirmButtonText: 'Yes, duplicate it!'
                }).then((result) => {
                    if (result.isConfirmed) {
                        form.submit();
                    }
                })
            });

            // Toggle status
            $(document).on('change', '.toggle-status', function() {
                let checkbox = $(this);
                let id = checkbox.data('id');
                let newStatus = checkbox.is(':checked') ? 1 : 0;

                checkbox.prop('disabled', true);

                $.ajax({
                    url: `/products/${id}/status`,
                    type: 'POST',
                    data: {
                        _token: csrfToken,
                        status: newStatus
                    },
                    success: function(response) {
                        toastr.success(response.message);
                        checkbox.prop('disabled', false);
                    },
                    error: function(xhr) {
                        toastr.error('Failed to update status.');
                        console.log(xhr.responseText);
                        checkbox.prop('disabled', false);
                        checkbox.prop('checked', !newStatus);
                    }
                });
            });
        });
    </script>
@endpush
