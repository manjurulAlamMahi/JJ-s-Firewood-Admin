@extends('backend.master')

@section('title')
Tip Categories - List
@endsection

@section('body')
    <x-admin.page-header title="Tip Categories"
        :crumbs="['Tip' => null, 'Categories' => null]">
        <x-slot:actions>
            <a href="{{ route('tip-categories.create') }}" class="btn btn-primary"> <i class="fa fa-plus"></i> Add Category </a>
        </x-slot:actions>
    </x-admin.page-header>

<div class="card">
<div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" id="tip-categories-table">
                <thead>
                    <tr>
                        <th>SL No</th>
                        <th>Name</th>
                        <th>Sort Order</th>
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
    .switch { position: relative; display: inline-block; width: 50px; height: 26px; }
    .switch input { opacity: 0; width: 0; height: 0; }
    .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #dc3545; transition: .4s; border-radius: 34px; }
    .slider:before { position: absolute; content: ""; height: 20px; width: 20px; left: 3px; bottom: 3px; background-color: white; transition: .4s; border-radius: 50%; }
    input:checked+.slider { background-color: #28a745; }
    input:checked+.slider:before { transform: translateX(24px); }
</style>
<script>
    $(document).ready(function() {
        let csrfToken = $('meta[name="csrf-token"]').attr('content');

        @if(session('success'))
            toastr.success('{{ session('success') }}');
        @endif
        @if(session('error'))
            toastr.error('{{ session('error') }}');
        @endif

        $('#tip-categories-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: '{{ route('tip-categories.data') }}',
            columns: [
                { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false, width: '5%' },
                { data: 'name', name: 'name' },
                { data: 'sort_order', name: 'sort_order', width: '10%' },
                {
                    data: 'status',
                    name: 'status',
                    render: function(data, type, row) {
                        let checked = parseInt(data) === 1 ? 'checked' : '';
                        return `<label class="switch"><input type="checkbox" class="toggle-status" data-id="${row.id}" ${checked}><span class="slider"></span></label>`;
                    },
                    orderable: false, searchable: false, width: '10%'
                },
                {
                    data: null, defaultContent: '', name: 'action', orderable: false, searchable: false, width: '15%',
                    render: function(data, type, row) {
                        let editUrl = `/tip-categories/${row.id}/edit`;
                        let deleteUrl = `/tip-categories/${row.id}`;
                        return `
                        <a href="${editUrl}" class="btn btn-sm btn-icon btn-warning me-1" title="Edit"><i class="fa fa-edit"></i></a>
                        <form action="${deleteUrl}" method="POST" style="display:inline-block;" class="delete-form">
                            <input type="hidden" name="_token" value="${csrfToken}">
                            <input type="hidden" name="_method" value="DELETE">
                            <button type="button" class="btn btn-sm btn-icon btn-danger btn-delete" title="Delete"><i class="fa fa-trash"></i></button>
                        </form>`;
                    }
                }
            ],
            order: [[2, 'asc']],
        });

        $(document).on('change', '.toggle-status', function() {
            let checkbox = $(this);
            let id = checkbox.data('id');
            let newStatus = checkbox.is(':checked') ? 1 : 0;
            checkbox.prop('disabled', true);
            $.ajax({
                url: `/tip-categories/${id}/status`,
                type: 'POST',
                data: { _token: csrfToken, status: newStatus },
                success: function(response) {
                    toastr.success(response.success || 'Status updated successfully.');
                    checkbox.prop('disabled', false);
                },
                error: function() {
                    toastr.error('Failed to update status.');
                    checkbox.prop('disabled', false);
                }
            });
        });

        $(document).on('click', '.btn-delete', function(e) {
            e.preventDefault();
            let form = $(this).closest('form');
            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) form.submit();
            });
        });
    });
</script>
@endpush
