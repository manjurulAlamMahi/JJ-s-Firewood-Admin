@extends('backend.master')

@section('title')
    Tips and Guides - List
@endsection

@section('body')
    <x-admin.page-header title="Tips and Guides List"
        :crumbs="['Tips and Guides' => null, 'List' => null]">
        <x-slot:actions>
            <a href="{{ route('tips-and-guides.create') }}" class="btn btn-primary"> <i class="fa fa-plus"></i> Add Tip or Guide </a>
        </x-slot:actions>
    </x-admin.page-header>

<!-- TABLE -->
    <div class="card">
<div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="tips-and-guides-table">
                    <thead>
                        <tr>
                            <th>SL No</th>
                            <th>Title</th>
                            <th>Short Description</th>
                            <th>Category</th>
                            <th>Image</th>
                            <th>Featured</th>
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
            // Show session flash messages via toastr
            @if(session('success'))
                toastr.success('{{ session('success') }}');
            @endif
            @if(session('error'))
                toastr.error('{{ session('error') }}');
            @endif

            let csrfToken = $('meta[name="csrf-token"]').attr('content');

            let table = $('#tips-and-guides-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: '{{ route('tips-and-guides.data') }}',
                columns: [{
                        data: 'DT_RowIndex',
                        name: 'DT_RowIndex',
                        orderable: false,
                        searchable: false,
                        width: '5%'
                    },
                    {
                        data: 'title',
                        name: 'title'
                    },
                    {
                        data: 'short_description',
                        name: 'short_description'
                    },
                    {
                        data: 'category',
                        name: 'category',
                        defaultContent: 'None'
                    },
                    {
                        data: 'image',
                        name: 'image',
                        render: function(data) {
                            return data ?
                                `<img src="/uploads/tips_and_guides/${data}" class="img-thumbnail" style="width:60px;height:60px;">` :
                                `<img src="${AdminV2.imageSrc(null)}" class="img-thumbnail" style="width:60px;height:60px;">`;
                        },
                        orderable: false,
                        searchable: false,
                        width: '15%'
                    },
                    {
                        data: 'is_featured',
                        name: 'is_featured',
                        render: function(data) {
                            return parseInt(data) === 1 
                                ? '<span class="badge bg-success">Featured</span>' 
                                : '<span class="badge bg-secondary">No</span>';
                        },
                        orderable: false,
                        searchable: false,
                        width: '10%'
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
                            let editUrl = `/tips-and-guides/${row.id}/edit`;
                            return `
                        <a href="${editUrl}" class="btn btn-sm btn-icon btn-warning me-1" title="Edit">
                            <i class="fa fa-edit"></i>
                        </a>
                        <button class="btn btn-sm btn-icon btn-danger btn-delete" data-id="${row.id}" title="Delete">
                            <i class="fa fa-trash"></i>
                        </button>
                    `;
                        }
                    }
                ],
                order: [
                    [0, 'desc']
                ],
            });

            // SweetAlert2 Delete
            $(document).on('click', '.btn-delete', function() {
                let id = $(this).data('id');
                Swal.fire({
                    title: 'Are you sure?',
                    text: 'This tip/guide will be permanently deleted!',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, delete it!',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        $.ajax({
                            url: `/tips-and-guides/${id}`,
                            type: 'POST',
                            data: {
                                _token: csrfToken,
                                _method: 'DELETE'
                            },
                            success: function(response) {
                                toastr.success(response.success || 'Deleted successfully.');
                                table.ajax.reload(null, false);
                            },
                            error: function(xhr) {
                                toastr.error('Failed to delete.');
                            }
                        });
                    }
                });
            });

            // Toggle status
            $(document).on('change', '.toggle-status', function() {
                let checkbox = $(this);
                let id = checkbox.data('id');
                let newStatus = checkbox.is(':checked') ? 1 : 0;

                checkbox.prop('disabled', true);

                $.ajax({
                    url: `/tips-and-guides/${id}/status`,
                    type: 'POST',
                    data: {
                        _token: csrfToken,
                        status: newStatus
                    },
                    success: function(response) {
                        toastr.success(response.success || 'Status updated successfully.');
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
