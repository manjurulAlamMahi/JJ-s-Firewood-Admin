@extends('backend.master')

@section('title')
    Payment Methods - List
@endsection

@section('body')
    <x-admin.page-header title="Payment Method List"
        :crumbs="['Payment Methods' => null, 'List' => null]" />

<!-- TABLE -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0">Manage Payment Methods</h3>
        </div>

        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered text-nowrap border-bottom" id="payment-methods-table">
                    <thead>
                        <tr>
                            <th class="wd-5p border-bottom-0">SL No</th>
                            <th class="wd-25p border-bottom-0">Name</th>
                            <th class="wd-25p border-bottom-0">Slug</th>
                            <th class="wd-15p border-bottom-0">Status</th>
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

        let table = $('#payment-methods-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: '{{ route('payment-methods.index') }}',
            columns: [
                {
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
                    data: 'slug',
                    name: 'slug'
                },
                {
                    data: 'is_active',
                    name: 'is_active',
                    orderable: false,
                    searchable: false,
                    width: '10%',
                    render: function(data, type, row) {
                        let checked = (data == 1 || data == true) ? 'checked' : '';
                        return `
                        <label class="switch">
                            <input type="checkbox" class="update-status" data-id="${row.id}" ${checked}>
                            <span class="slider"></span>
                        </label>`;
                    }
                }
            ],
            order: [[0, 'asc']],
        });

        // Toggle status
        $(document).on('change', '.update-status', function() {
            let checkbox = $(this);
            let id = checkbox.data('id');
            let isChecked = checkbox.is(':checked') ? 1 : 0;

            checkbox.prop('disabled', true);

            // Use the route helper but replace the placeholder ID
            let url = '{{ route('payment-methods.updateStatus', ':id') }}';
            url = url.replace(':id', id);

            $.ajax({
                url: url,
                type: 'POST',
                data: {
                    _token: csrfToken,
                    is_active: isChecked
                },
                success: function(response) {
                    if(response.success) {
                        toastr.success(response.message);
                    } else {
                        toastr.error(response.message);
                        checkbox.prop('checked', !checkbox.is(':checked'));
                    }
                    checkbox.prop('disabled', false);
                },
                error: function(xhr) {
                    toastr.error('Failed to update status.');
                    checkbox.prop('checked', !checkbox.is(':checked'));
                    checkbox.prop('disabled', false);
                }
            });
        });
    });
</script>
@endpush
