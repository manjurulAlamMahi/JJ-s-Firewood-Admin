@extends('backend.master')

@section('title')
    Selling Point - List
@endsection

@section('body')
    <x-admin.page-header title="Selling Point List"
        :crumbs="['Selling Point' => null, 'List' => null]">
        <x-slot:actions>
            <a href="{{ route('selling-point.create') }}" class="btn btn-primary"> <i class="fa fa-plus"></i> Add Selling Point </a>
        </x-slot:actions>
    </x-admin.page-header>

<!-- TABLE -->
    <div class="card">
<div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="selling_points-table">
                    <thead>
                        <tr>
                            <th>SL No</th>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Image</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
    <!-- TABLE END -->
@endsection

@push('scripts')
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Switch CSS -->
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

            let table = $('#selling_points-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: '{{ route('selling-point.data') }}',
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
                        data: 'description',
                        name: 'description'
                    },
                    {
                        data: 'image',
                        name: 'image',
                        render: function(data) {
                            return `<img src="${AdminV2.imageSrc(data)}" class="img-thumbnail" style="width:80px;height:80px;">`;
                        },
                        orderable: false,
                        searchable: false,
                        width: '15%'
                    },
                    {
                        data: 'status',
                        name: 'status',
                        render: function(data, type, row) {
                            let checked = parseInt(data) === 1 ? 'checked' : '';
                            return `
                        <label class="switch">
                            <input type="checkbox" class="toggle-status" data-id="${row.id}" ${checked}>
                            <span class="slider"></span>
                        </label>`;
                        },
                        orderable: false,
                        searchable: false,
                        width: '10%'
                    },
                    {
                        data: null,
                    defaultContent: '',
                        name: 'action',
                        orderable: false,
                        searchable: false,
                        width: '15%',
                        render: function(data, type, row) {
                            let editUrl = `/selling-point/${row.id}/edit`;
                            let deleteUrl = `/selling-point/${row.id}`;
                            return `
                        <a href="${editUrl}" class="btn btn-sm btn-icon btn-warning me-1" title="Edit">
                            <i class="fa fa-edit"></i>
                        </a>

                        <form action="${deleteUrl}" method="POST" style="display:inline-block;" onsubmit="return confirm('Delete this Selling Point?')">
                            <input type="hidden" name="_token" value="${csrfToken}">
                            <input type="hidden" name="_method" value="DELETE">
                            <button class="btn btn-sm btn-icon btn-danger" title="Delete">
                                <i class="fa fa-trash"></i>
                            </button>
                        </form>

                    `;
                        }
                    }
                ],
                order: [
                    [0, 'desc']
                ],
            });

            // Toggle status
            $(document).on('change', '.toggle-status', function() {
                let checkbox = $(this);
                let id = checkbox.data('id');
                let newStatus = checkbox.is(':checked') ? 1 : 0;

                checkbox.prop('disabled', true);

                $.ajax({
                    url: `/selling-point/${id}/status`,
                    type: 'POST',
                    data: {
                        _token: csrfToken,
                        status: newStatus
                    },
                    success: function(response) {
                        toastr.success(response.message);
                        checkbox.prop('disabled', false);
                        // Optional: reload table if needed
                        // table.ajax.reload(null, false);
                    },
                    error: function(xhr) {
                        toastr.error('Failed to update status.');
                        // console.log(xhr.responseText);
                        checkbox.prop('disabled', false);
                    }
                });
            });
        });
    </script>
@endpush
