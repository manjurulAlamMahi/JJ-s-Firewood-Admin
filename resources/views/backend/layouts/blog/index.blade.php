@extends('backend.master')

@section('title')
Blog - List
@endsection

@section('body')
    <x-admin.page-header title="Blog List"
        :crumbs="['Blog' => null, 'List' => null]">
        <x-slot:actions>
            <a href="{{ route('blog.create') }}" class="btn btn-primary"> <i class="fa fa-plus"></i> Add New Blog </a>
        </x-slot:actions>
    </x-admin.page-header>

<!-- TABLE -->
<div class="card">
<div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" id="blog-table">
                <thead>
                    <tr>
                        <th>SL No</th>
                        <th>Title</th>
                        <th>Short Description</th>
                        <th>Published At</th>
                        <th>Image</th>
                        <th>Status</th>
                        <th>Featured</th>
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

        // Show session flash messages via toastr
        @if(session('success'))
        toastr.success(@json(session('success')));
        @endif
        @if(session('error'))
        toastr.error(@json(session('error')));
        @endif

        let table = $('#blog-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: '{{ route('blog.data') }}',
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
                    data: 'published_at',
                    name: 'published_at',
                    render: function(data) {
                        return data ? new Date(data).toLocaleDateString() : '-';
                    }
                },
                {
                    data: 'image',
                    name: 'image',
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
                    data: 'featured',
                    name: 'featured',
                    render: function(data, type, row) {
                        let checked = parseInt(data) === 1 ? 'checked' : '';
                        return `
                        <label class="switch">
                            <input type="checkbox" class="toggle-featured" data-id="${row.id}" ${checked}>
                            <span class="slider"></span>
                        </label>`;
                    },
                    orderable: false,
                    searchable: false,
                    width: '10%'
                },
                {
                    data: 'action',
                    name: 'action',
                    orderable: false,
                    searchable: false,
                    width: '15%'
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
                url: `/blogs/${id}/status`,
                type: 'POST',
                data: {
                    _token: csrfToken,
                    status: newStatus
                },
                success: function(response) {
                    toastr.success(response.success || 'Status updated successfully.');
                    checkbox.prop('disabled', false);
                },
                error: function(xhr) {
                    toastr.error('Failed to update status.');
                    checkbox.prop('disabled', false);
                    checkbox.prop('checked', !newStatus);
                }
            });
        });

        // Toggle featured (exclusive — server unsets all others when one is set)
        $(document).on('change', '.toggle-featured', function() {
            let checkbox = $(this);
            let id = checkbox.data('id');
            let newFeatured = checkbox.is(':checked') ? 1 : 0;

            checkbox.prop('disabled', true);

            $.ajax({
                url: `/blogs/${id}/featured`,
                type: 'POST',
                data: {
                    _token: csrfToken,
                    featured: newFeatured
                },
                success: function(response) {
                    toastr.success(response.success || 'Featured status updated successfully.');
                    checkbox.prop('disabled', false);
                    table.ajax.reload(null, false);
                },
                error: function(xhr) {
                    toastr.error('Failed to update featured status.');
                    checkbox.prop('disabled', false);
                    checkbox.prop('checked', !newFeatured);
                }
            });
        });

        // SweetAlert Delete Confirmation
        $(document).on('click', '.delete-blog', function(e) {
            e.preventDefault();
            let id = $(this).data('id');

            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: `/blogs/${id}`,
                        type: 'POST',
                        data: {
                            _token: csrfToken,
                            _method: 'DELETE'
                        },
                        success: function(response) {
                            if (response.success) {
                                toastr.success(response.success);
                                table.ajax.reload(null, false);
                            } else {
                                toastr.error(response.error || 'Failed to delete blog.');
                            }
                        },
                        error: function() {
                            toastr.error('Failed to delete blog.');
                        }
                    });
                }
            })
        });
    });
</script>
@endpush