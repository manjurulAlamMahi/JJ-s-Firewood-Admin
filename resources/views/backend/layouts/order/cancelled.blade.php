{{-- resources/views/backend/layouts/order/cancelled.blade.php --}}

@extends('backend.master')

@section('title') Cancelled Orders - Refund Management @endsection

@section('body')
<div class="page-header">
    <h1>Cancelled Orders - Refund Management</h1>
</div>

<div class="card">
    <div class="card-body table-responsive">
        <table class="table table-bordered" id="cancelled-orders-table">
            <thead>
                <tr>
                    <th>SL No</th>
                    <th>Order #</th>
                    <th>Customer</th>
                    <th>Total Amount</th>
                    <th>Delivery Fee</th>
                    <th>Refunded</th>
                    <th>Balance</th>
                    <th>Cancelled At</th>
                    <th>Payment ID</th>
                    <th>Refund Status</th>
                    <th>Action</th>
                </tr>
            </thead>
        </table>
    </div>
</div>

<!-- Refund Modal -->
<div class="modal fade" id="refundModal" tabindex="-1" aria-labelledby="refundModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-warning">
                <h5 class="modal-title" id="refundModalLabel">
                    <i class="fas fa-pound-sign"></i> Process Refund
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="refundForm">
                <div class="modal-body">
                    <input type="hidden" id="refundOrderId">

                    <div class="alert alert-info">
                        <strong>Order Total:</strong> <span id="orderTotal"></span>
                    </div>

                    <div class="mb-3">
                        <label for="refundType" class="form-label">Refund Type</label>
                        <select class="form-select" id="refundType" name="refund_type" required>
                            <option value="">Select refund type</option>
                            <option value="full">Full Refund</option>
                            <option value="partial">Partial Refund</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="refundAmount" class="form-label">Refund Amount ($)</label>
                        <input type="number" class="form-control" id="refundAmount"
                               name="refund_amount" step="0.01" min="0.01" required>
                        <small class="text-muted">Enter amount to refund</small>
                    </div>

                    <div class="mb-3">
                        <label for="refundReason" class="form-label">Reason (Optional)</label>
                        <textarea class="form-control" id="refundReason" name="reason"
                                  rows="3" placeholder="Enter reason for refund..."></textarea>
                    </div>

                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Warning:</strong> This action will process a refund through Square.
                        Make sure the amount is correct.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-check"></i> Process Refund
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<meta name="csrf-token" content="{{ csrf_token() }}">
<script>
$(function() {
    let csrfToken = $('meta[name="csrf-token"]').attr('content');

    // DataTable initialization
    let table = $('#cancelled-orders-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route("order.cancelled.data") }}',
        columns: [
            {data: 'DT_RowIndex', orderable: false, searchable: false},
            {data: 'order_number'},
            {
                data: null,
                render: function(data) {
                    return `${data.user_name}<br><small>${data.user_email}</small>`;
                }
            },
            {
                data: 'total',
                render: function(d) {
                    return '$' + parseFloat(d).toFixed(2);
                }
            },
            {
                data: 'delivery_fee',
                render: function(d) {
                    return '$' + parseFloat(d || 0).toFixed(2);
                }
            },
            {
                data: 'refunded_amount',
                render: function(d) {
                    return d > 0 ? '<span class="text-danger">$' + parseFloat(d).toFixed(2) + '</span>' : '$0.00';
                }
            },
            {
                data: 'remaining_balance',
                render: function(d, type, row) {
                    // Balance excluding delivery fee
                    let deliveryFee = parseFloat(row.delivery_fee || 0);
                    let balance = parseFloat(d || 0);
                    let actualBalance = balance - deliveryFee;
                    if(actualBalance < 0.01) actualBalance = 0;

                    return `<span class="text-danger fw-bold">$${actualBalance.toFixed(2)}</span>`;
                }
            },
            {data: 'cancelled_at'},
            {
                data: 'payment_id',
                render: function(d) {
                    return d ? `<small>${d}</small>` : 'Manual/N/A';
                }
            },
            {
                data: 'refund_status',
                render: function(d, type, row) {
                    // Calculate actual balance excluding delivery fee
                    let deliveryFee = parseFloat(row.delivery_fee || 0);
                    let balance = parseFloat(row.remaining_balance || 0);
                    let actualBalance = balance - deliveryFee;

                    // If effectively fully refunded (excluding delivery fee), force status to 'full'
                    let status = d;
                    if (actualBalance <= 0.01) {
                        status = 'full';
                    }

                    let badges = {
                        'none': 'secondary',
                        'pending': 'warning',
                        'partial': 'info',
                        'full': 'success'
                    };
                    let color = badges[status] || 'secondary';
                    let label = status === 'none' ? 'No Refund' : status.charAt(0).toUpperCase() + status.slice(1);
                    return `<span class="badge bg-${color}">${label}</span>`;
                }
            },
            {
                data: null,
                name: 'action',
                orderable: false,
                searchable: false,
                width: '10%',
                render: function(data, type, row) {
                    // Check if fully refunded (excluding delivery fee)
                    let deliveryFee = parseFloat(row.delivery_fee || 0);
                    let balance = parseFloat(row.remaining_balance || 0);
                    let actualBalance = balance - deliveryFee;
                    
                    if (row.refund_status === 'full' || actualBalance <= 0.01) {
                        return '<span class="badge bg-success">Fully Refunded</span>';
                    }
                    
                    let balanceHtml = actualBalance > 0
                        ? `<span class="text-success fw-bold">$${actualBalance.toFixed(2)}</span>`
                        : '$0.00';
                        
                    return `
                        <button class="btn btn-sm btn-warning refund-btn"
                                data-order-id="${row.id}"
                                data-total="${row.total}"
                                data-delivery-fee="${row.delivery_fee || 0}"
                                data-balance="${row.remaining_balance}"
                                title="Process Refund">
                            <i class="fa fa-undo"></i> Refund
                        </button>
                    `;
                }
            }
        ],
        order: [[0, 'desc']]
    });

    // Open refund modal
    $(document).on('click', '.refund-btn', function() {
        let orderId = $(this).data('order-id');
        let total = parseFloat($(this).data('total'));
        let deliveryFee = parseFloat($(this).data('delivery-fee')) || 0;
        let balance = parseFloat($(this).data('balance'));

        // Calculate max refundable amount (Total - Delivery Fee)
        let maxRefundableTotal = total - deliveryFee;
        
        // Calculate current refundable (Max Refundable - Already Refunded)
        // Already Refunded = Total - Balance
        let alreadyRefunded = total - balance;
        let currentRefundable = maxRefundableTotal - alreadyRefunded;
        
        // Ensure we don't go below zero if somehow we already refunded more than allowed
        currentRefundable = Math.max(0, currentRefundable);

        $('#refundOrderId').val(orderId);
        
        // Update Modal message to show breakdown
        let infoHtml = `
            <div class="d-flex justify-content-between mb-1">
                <span>Order Total:</span>
                <strong>$${total.toFixed(2)}</strong>
            </div>
            <div class="d-flex justify-content-between mb-1 text-danger">
                <span>Less Delivery Fee:</span>
                <strong>-$${deliveryFee.toFixed(2)}</strong>
            </div>
            <div class="d-flex justify-content-between mb-1 border-top pt-1">
                <span>Max Refundable Amount:</span>
                <strong>$${maxRefundableTotal.toFixed(2)}</strong>
            </div>
        `;

        if (alreadyRefunded > 0.01) {
            infoHtml += `
                <div class="d-flex justify-content-between mb-1 text-warning">
                    <span>Already Refunded:</span>
                    <strong>-$${alreadyRefunded.toFixed(2)}</strong>
                </div>
            `;
        }

        infoHtml += `
            <div class="d-flex justify-content-between mt-2 pt-2 border-top">
                <span class="h6 mb-0">Available for Refund:</span>
                <strong class="h6 mb-0 text-success">$${currentRefundable.toFixed(2)}</strong>
            </div>
        `;

        // Find or create info div
        if ($('#refund-info-display').length === 0) {
            $('#refundForm').prepend('<div id="refund-info-display" class="alert alert-light border mb-3"></div>');
        }
        $('#refund-info-display').html(infoHtml);

        $('#refundAmount').attr('max', currentRefundable).val(currentRefundable.toFixed(2));
        $('#refundType').val('');
        $('#refundReason').val('');

        $('#refundModal').modal('show');
    });

    // Auto-fill amount based on refund type
    $('#refundType').change(function() {
        let type = $(this).val();
        let balance = parseFloat($('#refundAmount').attr('max'));

        if (type === 'full') {
            $('#refundAmount').val(balance.toFixed(2));
        } else if (type === 'partial') {
            $('#refundAmount').val((balance * 0.5).toFixed(2));
        }
    });

    // Submit refund form
    $('#refundForm').submit(function(e) {
        e.preventDefault();

        let orderId = $('#refundOrderId').val();
        let formData = {
            refund_amount: $('#refundAmount').val(),
            refund_type: $('#refundType').val(),
            reason: $('#refundReason').val(),
            _token: csrfToken
        };

        // Disable submit button
        let $submitBtn = $(this).find('button[type="submit"]');
        $submitBtn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Processing...');

        $.ajax({
            url: `/order/${orderId}/refund`,
            method: 'POST',
            data: formData,
            success: function(response) {
                if (response.success) {
                    $('#refundModal').modal('hide');
                    table.ajax.reload(null, false);

                    toastr.success(response.message);
                }
            },
            error: function(xhr) {
                let message = xhr.responseJSON?.message || 'Refund failed';
                toastr.error(message);
            },
            complete: function() {
                $submitBtn.prop('disabled', false).html('<i class="fas fa-check"></i> Process Refund');
            }
        });
    });
});
</script>
@endpush
