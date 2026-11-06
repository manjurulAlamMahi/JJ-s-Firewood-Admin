@extends('backend.master')

@section('title')
    Weather Campaigns
@endsection

@section('body')
    <div class="page-header">
        <div>
            <h1 class="page-title">Weather-Triggered Campaigns</h1>
        </div>
        <div class="ms-auto">
            <a href="{{ route('weather-campaigns.create') }}" class="btn btn-danger">
                <i class="fa fa-plus"></i> Create Campaign
            </a>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card">
        <div class="card-header">
            <h4>All Weather Campaigns</h4>
        </div>
        <div class="card-body">
            <table class="table table-bordered" id="weatherCampaignTable">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Target Area</th>
                        <th>Channels</th>
                        <th>Active</th>
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

            let table = $('#weatherCampaignTable').DataTable({
                processing: true,
                serverSide: true,
                ajax: "{{ route('weather-campaigns.data') }}",
                columns: [
                    { data: 'DT_RowIndex', orderable: false },
                    { data: 'name' },
                    { data: 'target_area', orderable: false, searchable: false },
                    { data: 'channels', orderable: false, searchable: false },
                    { data: 'status_badge', name: 'status', orderable: false, searchable: false },
                    { data: 'action', orderable: false, searchable: false },
                ]
            });

            $(document).on('change', '.status-dropdown', function() {
                let dropdown = $(this);
                let id = dropdown.data('id');
                let newStatus = dropdown.val();

                dropdown.prop('disabled', true);

                $.ajax({
                    url: `/weather-campaigns/${id}/status`,
                    type: 'POST',
                    headers: { 'X-CSRF-TOKEN': csrf },
                    data: { status: newStatus },
                    success: function(response) {
                        toastr.success(response.success || 'Status updated successfully.');
                        dropdown.prop('disabled', false);
                        table.ajax.reload(null, false);
                    },
                    error: function(xhr) {
                        let errorMsg = 'Failed to update status.';
                        if (xhr.responseJSON && xhr.responseJSON.error) {
                            errorMsg = xhr.responseJSON.error;
                        }
                        toastr.error(errorMsg);
                        dropdown.prop('disabled', false);
                        table.ajax.reload(null, false); // reload to revert to actual status
                    }
                });
            });
        });
    </script>
@endpush
