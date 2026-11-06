@extends('backend.master')

@section('title')
    Bank Transfer - List
@endsection

@section('body')
    <x-admin.page-header title="Bank Transfer List"
        :crumbs="['Bank Transfer' => null, 'List' => null]" />

<!-- TABLE -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0">Bank Transfer List</h3>
            <!-- <a href="{{ route('bank_transfer.create') }}" class="btn btn-danger">
                <i class="fa fa-plus"></i> Add Bank Transfer
            </a> -->
        </div>

        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="bank-transfers-table">
                    <thead>
                        <tr>
                            <th>SL No</th>
                            <th>Account Name</th>
                            <th>Account Number</th>
                            <th>BSB</th>
                            <th>PAY ID</th>
                            <th>Note</th>
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

        let table = $('#bank-transfers-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: '{{ route('bank_transfer.data') }}', 
            columns: [
                { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false, width: '5%' },
                { data: 'account_holder_name', name: 'account_holder_name' },
                { data: 'account_number', name: 'account_number' },
                { data: 'bank_name', name: 'bank_name' },
                { data: 'branch_name', name: 'branch_name' },
                { data: 'note', name: 'note' },
                {
                    data: 'status',
                    name: 'status',
                    render: function(data, type, row) {
                        let checked = parseInt(data) === 1 ? 'checked' : '';
                        return `
                            <label class="switch">
                                <input type="checkbox" class="toggle-status" data-id="${row.id}" ${checked}>
                                <span class="slider"></span>
                            </label>
                        `;
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
                        let editUrl = `/bank_transfer/edit/${row.id}`;
                        let deleteUrl = `/bank_transfer/${row.id}`;
                        return `
                            <a href="${editUrl}" class="btn btn-sm btn-icon btn-warning me-1" title="Edit">
                                <i class="fa fa-edit"></i>
                            </a>

                            <!---<form action="${deleteUrl}" method="POST" style="display:inline-block;" onsubmit="return confirm('Delete this bank transfer?')">
                                <input type="hidden" name="_token" value="${csrfToken}">
                                <input type="hidden" name="_method" value="DELETE">
                                <button class="btn btn-sm btn-icon btn-danger" title="Delete">
                                    <i class="fa fa-trash"></i>
                                </button>
                            </form> --->
                        `;
                    }
                }
            ],
            order: [[0, 'desc']],
        });

        // Toggle status
        $(document).on('change', '.toggle-status', function() {
            let checkbox = $(this);
            let id = checkbox.data('id');
            let newStatus = checkbox.is(':checked') ? 1 : 0;

            checkbox.prop('disabled', true);

            $.ajax({
                url: `/bank_transfer/${id}/status`,
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
