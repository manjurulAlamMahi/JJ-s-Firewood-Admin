@extends('backend.master')

@section('title') Reviews - List @endsection

@section('body')
<div class="page-header">
    <h1>Review List</h1>
</div>

<div class="card">
    <div class="card-body table-responsive">
        <table class="table table-bordered" id="reviews-table">
            <thead>
                <tr>
                    <th>SL No</th>
                    <th>User</th>
                    <th>Product</th>
                    <th>Rating</th>
                    <th>Comment</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
        </table>
    </div>
</div>
@endsection

@push('scripts')
<meta name="csrf-token" content="{{ csrf_token() }}">
<script>
$(function(){
    let csrfToken = $('meta[name="csrf-token"]').attr('content');

    let statusColors = {
        pending: 'warning',
        approved: 'success',
        rejected: 'danger'
    };

    let table = $('#reviews-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route("review.data") }}',
        columns: [
            {data: 'DT_RowIndex', orderable: false, searchable: false},
            {data: null, render: d => `${d.user.name}<br><small>${d.user.email}</small>`},
            {data: 'product.name'},
            {data: 'rating'},
            {data: 'comment'},
            {data: 'status', orderable: false, searchable: false},
            {data: null,
                    defaultContent: '', orderable: false, searchable: false}
        ]
    });

    $(document).on('change', '.update-status', function() {
        let id = $(this).data('id');
        let status = $(this).val();

        $.post(`/review/${id}/status`, {_token: csrfToken, status: status}, function(res) {
            toastr.success(res.message);
            table.ajax.reload(null, false);
        }).fail(function(xhr) {
            toastr.error(xhr.responseJSON?.message || 'Failed');
            table.ajax.reload(null, false);
        });
    });

    $(document).on('click', '.delete-review', function() {
        let id = $(this).data('id');
        if(confirm('Are you sure you want to delete this review?')){
            $.ajax({
                url: `/review/${id}`,
                type: 'DELETE',
                data: {_token: csrfToken},
                success: function(res) {
                    if(res.status) {
                        toastr.success(res.message);
                        table.ajax.reload(null, false);
                    } else {
                        toastr.error(res.message);
                    }
                },
                error: function(xhr) {
                    toastr.error(xhr.responseJSON?.message || 'Failed to delete review');
                }
            });
        }
    });
});
</script>
@endpush
