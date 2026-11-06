@extends('backend.master')

@section('title')
    Manual Notifications
@endsection

@section('body')
    <div class="page-header">
        <div>
            <h1 class="page-title">Manual Notifications</h1>
        </div>
        <div class="ms-auto">
            <a href="{{ route('notifications.create') }}" class="btn btn-danger">
                <i class="fa fa-plus"></i> Create Notification
            </a>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <!-- All Notifications Table -->
    <div class="card mb-4">
        <div class="card-header">
            <h4>All Notifications</h4>
        </div>
        <div class="card-body">
            <table class="table table-bordered" id="notificationTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Title</th>
                        <th>Message</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th width="20%">Action</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>

    <!-- Sent Notifications Table -->
    <div class="card">
        <div class="card-header">
            <h4>Sent Notifications</h4>
        </div>
        <div class="card-body">
            <table class="table table-bordered" id="sentNotificationTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Title</th>
                        <th>Message</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th width="20%">Action</th>
                    </tr>
                </thead>
            </table>
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
            let csrf = $('meta[name="csrf-token"]').attr('content');

            // All Notifications Table
            let table = $('#notificationTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: "{{ route('notifications.data') }}",
                columns: [{
                        data: 'DT_RowIndex',
                        orderable: false
                    },
                    {
                        data: 'title'
                    },
                    {
                        data: 'message'
                    },
                    {
                        data: 'category',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'status',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: null,
                    defaultContent: '',
                        orderable: false,
                        searchable: false
                    },
                ]
            });

            // Sent Notifications Table
            let sentTable = $('#sentNotificationTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: "{{ route('notifications.sent.data') }}",
                columns: [{
                        data: 'DT_RowIndex',
                        orderable: false
                    },
                    {
                        data: 'title'
                    },
                    {
                        data: 'message'
                    },
                    {
                        data: 'category',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'status',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: null,
                    defaultContent: '',
                        orderable: false,
                        searchable: false
                    },
                ]
            });

            // Toggle status
            $(document).on('change', '.toggle-status', function() {
                let checkbox = $(this);
                let id = checkbox.data('id');
                let newStatus = checkbox.is(':checked') ? 1 : 0;

                checkbox.prop('disabled', true);

                $.ajax({
                    url: `/notifications/${id}/status`,
                    type: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': csrf
                    },
                    data: {
                        status: newStatus
                    },
                    success: function(response) {
                        toastr.success(response.success || 'Status updated successfully.');
                        checkbox.prop('disabled', false);
                        table.ajax.reload(null, false);
                        sentTable.ajax.reload(null, false);
                    },
                    error: function(xhr) {
                        console.log('Error response:', xhr);
                        let errorMsg = 'Failed to update status.';
                        if (xhr.responseJSON && xhr.responseJSON.error) {
                            errorMsg = xhr.responseJSON.error;
                        }
                        toastr.error(errorMsg);
                        checkbox.prop('disabled', false);
                        checkbox.prop('checked', !checkbox.is(':checked'));
                    }
                });
            });
        });
    </script>
@endpush
