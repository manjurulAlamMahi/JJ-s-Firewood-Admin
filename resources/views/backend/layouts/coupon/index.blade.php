@extends('backend.master')

@section('title')
    Coupon - List
@endsection

@section('body')
    <x-admin.page-header title="Coupon List"
        :crumbs="['Coupon' => null, 'List' => null]">
        <x-slot:actions>
            <a href="{{ route('coupon.create') }}" class="btn btn-primary"> <i class="fa fa-plus"></i> Add Coupon </a>
        </x-slot:actions>
    </x-admin.page-header>

<!-- TABLE -->
    <div class="card">
<div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="coupons-table">
                    <thead>
                        <tr>
                            <th>SL No</th>
                            <th>Code</th>
                            <th>Type</th>
                            <th>Value</th>
                            <th>Min Order</th>
                            <th>Max Discount</th>
                            <th>Usage</th>
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
    top: 0; left: 0; right: 0; bottom: 0;
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
input:checked + .slider {
    background-color: #28a745;
}
input:checked + .slider:before {
    transform: translateX(24px);
}
</style>

<script>
$(document).ready(function() {
    let csrfToken = $('meta[name="csrf-token"]').attr('content');

    let table = $('#coupons-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route("coupon.data") }}',
        columns: [
            {data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false},
            {data: 'code', name: 'code'},
            {data: 'type', name: 'type'},
            {data: 'value', name: 'value'},
            {data: 'min_order_amount', name: 'min_order_amount'},
            {data: 'max_discount', name: 'max_discount'},
            {data: 'usage_info', name: 'usage_info', orderable: false, searchable: false},
            {data: 'is_active', name: 'is_active', orderable: false, searchable: false},
            {
                data: null,
                name: 'action',
                orderable: false,
                searchable: false,
                render: function(data, type, row) {
                    let editUrl = `/coupon/${row.id}/edit`;
                    let deleteUrl = `/coupon/${row.id}`;
                    return `
                        <a href="${editUrl}" class="btn btn-sm btn-warning me-1" title="Edit">
                            <i class="fa fa-edit"></i>
                        </a>
                        <form action="${deleteUrl}" method="POST" style="display:inline-block;" onsubmit="return confirm('Delete this Coupon?')">
                            <input type="hidden" name="_token" value="${csrfToken}">
                            <input type="hidden" name="_method" value="DELETE">
                            <button class="btn btn-sm btn-danger" title="Delete">
                                <i class="fa fa-trash"></i>
                            </button>
                        </form>`;
                }
            }
        ],
        order: [[0, 'desc']]
    });

    // Toggle status
    $(document).on('change', '.toggle-status', function() {
        let checkbox = $(this);
        let id = checkbox.data('id');
        let newStatus = checkbox.is(':checked') ? 1 : 0;

        checkbox.prop('disabled', true);

        $.ajax({
            url: `/coupon/${id}/status`,
            type: 'POST',
            data: {_token: csrfToken, status: newStatus},
            success: function(response) {
                if(response.success) {
                    toastr.success(response.message);
                } else {
                    toastr.error('Failed to update status');
                    checkbox.prop('checked', !newStatus);
                }
                checkbox.prop('disabled', false);
            },
            error: function(xhr) {
                toastr.error('Failed to update status.');
                checkbox.prop('disabled', false);
                checkbox.prop('checked', !newStatus);
            }
        });
    });
});
</script>
@endpush
