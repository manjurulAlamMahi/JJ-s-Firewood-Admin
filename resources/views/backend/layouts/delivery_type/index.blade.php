@extends('backend.master')

@section('title')
    Delivery Type - List
@endsection

@section('body')
    <x-admin.page-header title="Delivery Type List"
        :crumbs="['Delivery Type' => null, 'List' => null]">
        <x-slot:actions>
            <a href="{{ route('delivery-type.create') }}" class="btn btn-primary"> <i class="fa fa-plus"></i> Add Delivery Type </a>
        </x-slot:actions>
    </x-admin.page-header>

<div class="card">
<div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="deliveryTypeTable">
                    <thead>
                        <tr>
                            <th>SL No</th>
                            <th>Name</th>
                            <th>Fee</th>
                            <th>Days</th>
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

<!-- Switch CSS -->
<style>
.switch {
    position: relative;
    display: inline-block;
    width: 50px;
    height: 26px;
}
.switch input {opacity: 0;width: 0;height: 0;}
.slider {position: absolute;cursor: pointer;top: 0;left: 0;right: 0;bottom: 0;
    background-color: #dc3545; transition: .4s; border-radius: 34px;}
.slider:before {position: absolute; content: ""; height: 20px; width: 20px; left: 3px; bottom: 3px;
    background-color: white; transition: .4s; border-radius: 50%;}
input:checked+.slider {background-color: #28a745;}
input:checked+.slider:before {transform: translateX(24px);}
</style>

<script>
$(document).ready(function() {
    let csrfToken = $('meta[name="csrf-token"]').attr('content');

    let table = $('#deliveryTypeTable').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route('delivery-type.data') }}',
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false, width: '5%' },
            { data: 'name', name: 'name' },
            { data: 'fee', name: 'fee' },
            { data: 'days', name: 'days' },
            {
                data: 'status',
                name: 'status',
                render: function(data, type, row) {
                    let checked = parseInt(data) === 1 ? 'checked' : '';
                    return `
                        <span class="d-none">Status</span>
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
                render: function(data, type, row) {
                    let editUrl = `/delivery-type/${row.id}/edit`;
                    let deleteUrl = `/delivery-type/${row.id}`;
                    return `
                        <div class="d-flex gap-2 justify-content-start">
                            <a href="${editUrl}" class="btn btn-sm btn-icon btn-warning" aria-label="Edit">
                                <i class="fa fa-edit"></i>
                            </a>
                            <form action="${deleteUrl}" method="POST" style="margin:0;"
                                  onsubmit="return confirm('Delete this delivery type?')">
                                <input type="hidden" name="_token" value="${csrfToken}">
                                <input type="hidden" name="_method" value="DELETE">
                                <button class="btn btn-sm btn-icon btn-danger" aria-label="Delete">
                                    <i class="fa fa-trash"></i>
                                </button>
                            </form>
                        </div>
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
            url: `/delivery-type/${id}/status`,
            type: 'POST',
            data: {_token: csrfToken, status: newStatus},
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
