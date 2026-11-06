@extends('backend.master')

@section('title')
    {{ $group->name }} - Postcodes
@endsection

@section('body')
    <x-admin.page-header title="{{ $group->name }}"
        :crumbs="['Postcodes' => null, '{{ $group->name }}' => null]" />

<!-- GROUP INFO & STATUS -->
    <div class="card mb-3">
        <div class="card-body py-3">
            <div class="row align-items-center">
                <div class="col-md-4">
                    <h5 class="mb-1">{{ $group->name }}</h5>
                    <span class="text-muted">Zone Status:</span>
                    <label class="switch ms-2" style="vertical-align: middle;">
                        <input type="checkbox" id="group-status-toggle" {{ $group->status ? 'checked' : '' }}>
                        <span class="slider"></span>
                    </label>
                    <span id="group-status-text" class="{{ $group->status ? 'text-success' : 'text-danger' }} fw-bold ms-1">
                        {{ $group->status ? 'Active' : 'Inactive' }}
                    </span>
                </div>
                <div class="col-md-8 text-end">
                    <a href="{{ route('zip-code.edit-group', $group->id) }}" class="btn btn-warning btn-sm me-1">
                        <i class="fa fa-edit"></i> Edit Zone Name
                    </a>
                    <a href="{{ route('zip-code.index') }}" class="btn btn-secondary btn-sm">
                        <i class="fa fa-arrow-left"></i> All Zones
                    </a>
                </div>
            </div>
        </div>
    </div>

    @if(!$group->status)
        <div class="alert alert-warning">
            <i class="fa fa-exclamation-triangle"></i>
            <strong>This zone is disabled.</strong> All postcodes in this zone are inactive for delivery regardless of their individual status.
        </div>
    @endif

    <!-- BULK ADD POSTCODES -->
    <div class="card mb-3">
        <div class="card-header border-bottom d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0"><i class="fa fa-plus-circle text-primary"></i> Add Postcodes</h3>
            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#bulkAddSection">
                <i class="fa fa-chevron-down"></i>
            </button>
        </div>
        <div class="collapse show" id="bulkAddSection">
            <div class="card-body">
                <div class="mb-3">
                    <textarea class="form-control" id="bulk-zip-input" rows="3"
                              placeholder="Enter postcodes: comma-separated, one per line, or ranges (e.g. 6100-6150, 6200, 6305)"></textarea>
                </div>
                <div class="d-flex justify-content-between align-items-center">
                    <small class="text-muted">
                        <i class="fa fa-info-circle"></i>
                        Supports: commas, new lines, ranges (6100-6150). Duplicates auto-skipped.
                    </small>
                    <button class="btn btn-primary" id="btn-bulk-add">
                        <i class="fa fa-plus"></i> Add Postcodes
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- ZIP CODES TABLE -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0">
                Postcodes
                <span class="badge bg-primary ms-2" id="zip-count">0</span>
            </h3>
            <div>
                <button class="btn btn-sm btn-outline-danger" id="btn-bulk-delete" style="display:none;">
                    <i class="fa fa-trash"></i> Delete Selected (<span id="selected-count">0</span>)
                </button>
            </div>
        </div>

        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="zip-codes-table">
                    <thead>
                        <tr>
                            <th width="5%">
                                <input type="checkbox" id="select-all">
                            </th>
                            <th width="8%">#</th>
                            <th>Postcode</th>
                            <th width="12%">Status</th>
                            <th width="10%">Action</th>
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
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider {
            position: absolute; cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: #dc3545;
            transition: .4s; border-radius: 34px;
        }
        .slider:before {
            position: absolute; content: "";
            height: 20px; width: 20px; left: 3px; bottom: 3px;
            background-color: white; transition: .4s; border-radius: 50%;
        }
        input:checked + .slider { background-color: #28a745; }
        input:checked + .slider:before { transform: translateX(24px); }
    </style>

    <script>
        $(document).ready(function() {
            let csrfToken = $('meta[name="csrf-token"]').attr('content');
            let groupId = {{ $group->id }};
            let selectedIds = [];

            // ==================== ZIP CODES DATATABLE ====================
            let zipTable = $('#zip-codes-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: `/zip-code/group/${groupId}/zip-data`,
                    type: 'GET',
                },
                columns: [
                    {
                        data: null,
                        orderable: false,
                        searchable: false,
                        render: function(data, type, row) {
                            return `<input type="checkbox" class="zip-checkbox" value="${row.id}">`;
                        }
                    },
                    { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
                    { data: 'zip_code', name: 'zip_code' },
                    {
                        data: 'status',
                        name: 'status',
                        orderable: false,
                        searchable: false,
                        render: function(data, type, row) {
                            let checked = parseInt(data) === 1 ? 'checked' : '';
                            return `
                                <label class="switch">
                                    <input type="checkbox" class="toggle-zip-status" data-id="${row.id}" ${checked}>
                                    <span class="slider"></span>
                                </label>`;
                        }
                    },
                    {
                        data: null,
                        orderable: false,
                        searchable: false,
                        render: function(data, type, row) {
                            return `
                                <button class="btn btn-sm btn-danger delete-zip" data-id="${row.id}" data-code="${row.zip_code}" title="Delete">
                                    <i class="fa fa-trash"></i>
                                </button>`;
                        }
                    }
                ],
                order: [[2, 'asc']],
                pageLength: 25,
                drawCallback: function(settings) {
                    let total = settings._iRecordsTotal || 0;
                    $('#zip-count').text(total);
                    // Reset select all
                    $('#select-all').prop('checked', false);
                    updateBulkDeleteBtn();
                }
            });

            // ==================== GROUP STATUS TOGGLE ====================
            $('#group-status-toggle').on('change', function() {
                let newStatus = $(this).is(':checked') ? 1 : 0;
                $.ajax({
                    url: `/zip-code/group/${groupId}/status`,
                    type: 'POST',
                    data: { _token: csrfToken, status: newStatus },
                    success: function(response) {
                        toastr.success(response.message);
                        $('#group-status-text')
                            .text(newStatus ? 'Active' : 'Inactive')
                            .toggleClass('text-success', !!newStatus)
                            .toggleClass('text-danger', !newStatus);
                        // Reload page to show/hide warning
                        if (!newStatus) location.reload();
                    },
                    error: function() {
                        toastr.error('Failed to update zone status.');
                    }
                });
            });

            // ==================== BULK ADD POSTCODES ====================
            $('#btn-bulk-add').on('click', function() {
                let input = $('#bulk-zip-input').val().trim();
                if (!input) {
                    toastr.warning('Please enter postcodes to add.');
                    return;
                }

                let btn = $(this);
                btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Adding...');

                $.ajax({
                    url: `/zip-code/group/${groupId}/add-zips`,
                    type: 'POST',
                    data: {
                        _token: csrfToken,
                        zip_codes_input: input
                    },
                    success: function(response) {
                        toastr.success(response.message);
                        $('#bulk-zip-input').val('');
                        zipTable.ajax.reload(null, false);
                    },
                    error: function(xhr) {
                        let msg = xhr.responseJSON?.message || 'Failed to add postcodes.';
                        toastr.error(msg);
                    },
                    complete: function() {
                        btn.prop('disabled', false).html('<i class="fa fa-plus"></i> Add Postcodes');
                    }
                });
            });

            // ==================== TOGGLE INDIVIDUAL ZIP STATUS ====================
            $(document).on('change', '.toggle-zip-status', function() {
                let checkbox = $(this);
                let id = checkbox.data('id');
                let newStatus = checkbox.is(':checked') ? 1 : 0;
                checkbox.prop('disabled', true);

                $.ajax({
                    url: `/zip-code/group/${groupId}/zip/${id}/status`,
                    type: 'POST',
                    data: { _token: csrfToken, status: newStatus },
                    success: function(response) {
                        toastr.success(response.message);
                        checkbox.prop('disabled', false);
                    },
                    error: function() {
                        toastr.error('Failed to update status.');
                        checkbox.prop('checked', !newStatus);
                        checkbox.prop('disabled', false);
                    }
                });
            });

            // ==================== DELETE INDIVIDUAL ZIP ====================
            $(document).on('click', '.delete-zip', function() {
                let id = $(this).data('id');
                let code = $(this).data('code');

                if (!confirm(`Delete postcode ${code}?`)) return;

                $.ajax({
                    url: `/zip-code/group/${groupId}/zip/${id}`,
                    type: 'DELETE',
                    data: { _token: csrfToken },
                    success: function(response) {
                        toastr.success(response.success);
                        zipTable.ajax.reload(null, false);
                    },
                    error: function() {
                        toastr.error('Failed to delete postcode.');
                    }
                });
            });

            // ==================== SELECT ALL / BULK DELETE ====================
            $('#select-all').on('change', function() {
                let checked = $(this).is(':checked');
                $('.zip-checkbox').prop('checked', checked);
                updateBulkDeleteBtn();
            });

            $(document).on('change', '.zip-checkbox', function() {
                updateBulkDeleteBtn();
            });

            function updateBulkDeleteBtn() {
                let checked = $('.zip-checkbox:checked');
                let count = checked.length;
                $('#selected-count').text(count);
                $('#btn-bulk-delete').toggle(count > 0);
            }

            $('#btn-bulk-delete').on('click', function() {
                let ids = [];
                $('.zip-checkbox:checked').each(function() {
                    ids.push(parseInt($(this).val()));
                });

                if (ids.length === 0) return;

                if (!confirm(`Delete ${ids.length} selected postcode(s)?`)) return;

                $.ajax({
                    url: `/zip-code/group/${groupId}/bulk-delete`,
                    type: 'POST',
                    data: { _token: csrfToken, ids: ids },
                    success: function(response) {
                        toastr.success(response.message);
                        zipTable.ajax.reload(null, false);
                    },
                    error: function() {
                        toastr.error('Failed to delete postcodes.');
                    }
                });
            });
        });
    </script>
@endpush