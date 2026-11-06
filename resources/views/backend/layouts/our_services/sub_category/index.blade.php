@extends('backend.master')

@section('title')
    Service Sub Category - List
@endsection

@section('body')
    <x-admin.page-header title="Service Sub Category List"
        :crumbs="['Service Sub Category' => null, 'List' => null]">
        <x-slot:actions>
            <a href="{{ route('services_sub_category.create') }}" class="btn btn-primary"> <i class="fa fa-plus"></i> Add </a>
        </x-slot:actions>
    </x-admin.page-header>

<!-- TABLE -->

    <div class="card">
```
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="subcategories-table">
                    <thead>
                        <tr>
                            <th>SL No</th>
                            <th>Sub Category Name</th>
                            <th>In App Name</th>
                            <th>Category Name</th>
                            <th>Image</th>
                            <th>Status</th>
                            <th width="15%">Action</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
        ```

    </div>
    <!-- TABLE END -->
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

            let table = $('#subcategories-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: '{{ route('services_sub_category.data') }}',
                columns: [{
                        data: 'DT_RowIndex',
                        name: 'DT_RowIndex',
                        orderable: false,
                        searchable: false,
                        width: '5%'
                    },
                    {
                        data: 'name',
                        name: 'name'
                    },
                    {
                        data: 'inapp_name',
                        name: 'inapp_name'
                    },
                    {
                        data: 'category_name',
                        name: 'category_name'
                    },
                    {
                        data: 'image',
                        name: 'image',
                        orderable: false,
                        searchable: false,
                        width: '15%'
                    },
                    {
                        data: 'status',
                        name: 'status',
                        render: function(data, type, row) {
                            let checked = parseInt(data) === 1 ? 'checked' : '';
                            return `<label class="switch"><input type="checkbox" class="toggle-status" data-id="${row.id}" ${checked}><span class="slider"></span></label>`;
                        },
                        orderable: false,
                        searchable: false,
                        width: '10%'
                    },
                    {
                        data: null,
                        name: 'action',
                        orderable: false,
                        searchable: false,
                        width: '10%',
                        render: function(data, type, row) {
                            let editUrl = `/services/sub_category/${row.id}/edit`;
                            let deleteUrl = `/services/sub_category/${row.id}`;
                            return `
                                <a href="${editUrl}" class="btn btn-sm btn-warning me-1" title="Edit">
                                    <i class="fa fa-edit"></i>
                                </a>
                                <form action="${deleteUrl}" method="POST" style="display:inline-block;" onsubmit="return confirm('Delete this SubCategory?')">
                                    <input type="hidden" name="_token" value="${csrfToken}">
                                    <input type="hidden" name="_method" value="DELETE">
                                    <button class="btn btn-sm btn-danger" title="Delete">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </form>
                            `;
                        }
                    }
                ]
            });

            $(document).on('change', '.toggle-status', function() {
                let checkbox = $(this);
                let id = checkbox.data('id');
                let newStatus = checkbox.is(':checked') ? 1 : 0;

                checkbox.prop('disabled', true);

                $.ajax({
                    url: `/services/sub_category/${id}/status`,
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
                        checkbox.prop('disabled', false);
                    }
                });
            });



        });
    </script>
@endpush
