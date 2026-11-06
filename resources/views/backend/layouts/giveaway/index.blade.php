@extends('backend.master')

@section('title', 'Giveaway List')

@section('body')

<!-- PAGE-HEADER -->
<div class="page-header">
    <div>
        <h1 class="page-title">Giveaway List</h1>
    </div>
    <div class="ms-auto pageheader-btn">
        <a href="{{ route('giveaways.create') }}" class="btn btn-danger">
            <i class="fa fa-plus"></i> Add Giveaway
        </a>
    </div>
</div>
<!-- PAGE-HEADER END -->

<!-- =============================== -->
<!-- 🔵 UPCOMING GIVEAWAYS TABLE -->
<!-- =============================== -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title">Upcoming Giveaway List</h3>
    </div>
    <div class="card-body">
        <table class="table table-bordered" id="upcoming-table">
            <thead>
                <tr>
                    <th>SL</th>
                    <th>Prize Name</th>
                    <th>Prize Value</th>
                    <th>Image</th>
                    <th>Draw Date</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
        </table>
    </div>
</div>

<!-- =============================== -->
<!-- 🟢 DRAWN GIVEAWAYS TABLE -->
<!-- =============================== -->
<div class="card mt-4">
    <div class="card-header">
        <h3 class="card-title">Drawn Giveaway List</h3>
    </div>
    <div class="card-body">
        <table class="table table-bordered" id="drawn-table">
            <thead>
                <tr>
                    <th>SL</th>
                    <th>Prize Name</th>
                    <th>Prize Value</th>
                    <th>Image</th>
                    <th>Draw Date</th>
                    <th>Winner Name</th>
                </tr>
            </thead>
        </table>
    </div>
</div>

<!-- ================================= -->
<!-- 🏆 WINNER MODAL -->
<!-- ================================= -->
<div class="modal fade" id="winnerModal" tabindex="-1" aria-labelledby="winnerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">🎉 Winner Selected!</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <p>Winner:</p>
                <h5 id="winnerName">-</h5>
                <p><strong>Prize:</strong> <span id="winnerPrize">-</span></p>
                <p><strong>Email:</strong> <span id="winnerEmail">-</span></p>
                <p><strong>Phone:</strong> <span id="winnerPhone">-</span></p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-dark" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<meta name="csrf-token" content="{{ csrf_token() }}">

<script>
$(document).ready(function () {

    let csrfToken = $('meta[name="csrf-token"]').attr('content');

    /* ====================================
        🔵 UPCOMING GIVEAWAYS DATATABLE
    ==================================== */
    let upcomingTable = $('#upcoming-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route('giveaways.upcoming') }}',
            error: function(xhr, error, code) {
                console.error('DataTable Error:', xhr.responseText);
                alert('Error loading data. Check console for details.');
            }
        },
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'prize_name' },
            { data: 'prize_value' },
            { data: 'image', orderable: false, searchable: false },
            { data: 'draw_date' },
            { data: 'status', orderable: false, searchable: false },
            { data: null,
                    defaultContent: '', orderable: false, searchable: false }
        ]
    });

    /* ====================================
        🟢 DRAWN GIVEAWAYS DATATABLE
    ==================================== */
    let drawnTable = $('#drawn-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: '{{ route('giveaways.drawn') }}',
            error: function(xhr, error, code) {
                console.error('DataTable Error:', xhr.responseText);
                alert('Error loading data. Check console for details.');
            }
        },
        columns: [
            { data: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'prize_name' },
            { data: 'prize_value' },
            { data: 'image', orderable: false, searchable: false },
            { data: 'draw_date' },
            { data: 'winner_name' }
        ]
    });

    /* ================================
        ⭐ DRAW WINNER BUTTON
    ================================ */
    $(document).on('click', '.draw-winner', function () {
        let id = $(this).data('id');

        if (!confirm("Are you sure you want to draw a winner?")) return;

        // Show loading
        $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Drawing...');
        let btn = $(this);

        $.ajax({
            url: '{{ url("/giveaways/draw-winner") }}/' + id,
            type: 'POST',
            data: { _token: csrfToken },
            success: function (res) {
                btn.prop('disabled', false).html('<i class="fa fa-gift"></i> Draw');

                if (!res.success) {
                    toastr.error(res.message || 'Failed to draw winner');
                    return;
                }

                toastr.success(res.message || "Winner selected successfully!");

                // Update modal
                $('#winnerName').text(res.user.name);
                $('#winnerPrize').text(res.prize_name || '-');
                $('#winnerEmail').text(res.user.email);
                $('#winnerPhone').text(res.user.phone || 'N/A');

                // Show modal
                let modal = new bootstrap.Modal(document.getElementById('winnerModal'));
                modal.show();

                // Refresh tables
                upcomingTable.ajax.reload(null, false);
                drawnTable.ajax.reload(null, false);
            },
            error: function(xhr) {
                btn.prop('disabled', false).html('<i class="fa fa-gift"></i> Draw');

                let errorMsg = 'Something went wrong!';
                if (xhr.responseJSON && xhr.responseJSON.message) {
                    errorMsg = xhr.responseJSON.message;
                }
                toastr.error(errorMsg);
                console.error('Error:', xhr.responseText);
            }
        });
    });

    /* ================================
        🔁 STATUS TOGGLE
    ================================ */
    $(document).on('change', '.toggle-status', function () {
        let id = $(this).data('id');
        let status = $(this).is(':checked') ? 1 : 0;
        let checkbox = $(this);

        $.ajax({
            url: '{{ url("/giveaways") }}/' + id + '/status',
            type: 'POST',
            data: {
                _token: csrfToken,
                status: status
            },
            success: function (res) {
                if (res.success) {
                    toastr.success(res.message || "Status updated successfully");
                } else {
                    toastr.error(res.message || "Failed to update status");
                    checkbox.prop('checked', !status);
                }
            },
            error: function(xhr) {
                toastr.error("Failed to update status");
                checkbox.prop('checked', !status);
                console.error('Error:', xhr.responseText);
            }
        });
    });

});
</script>

<style>
img.table-thumb {
    width: 50px;
    height: 50px;
    object-fit: cover;
    border-radius: 6px;
}

/* Toggle Switch */
.switch {
    position: relative;
    display: inline-block;
    width: 46px;
    height: 24px;
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
    background: #dc3545;
    transition: .3s;
    border-radius: 24px;
}
.slider:before {
    position: absolute;
    content: "";
    height: 18px;
    width: 18px;
    left: 3px;
    bottom: 3px;
    background: white;
    transition: .3s;
    border-radius: 50%;
}
input:checked + .slider {
    background: #28a745;
}
input:checked + .slider:before {
    transform: translateX(22px);
}
</style>

@endpush
