@extends('backend.master')

@section('title')
    Order Management
@endsection

@push('styles')
    <style>
        /* Premium Look & Feel Overrides */
        :root {
            --primary-color: #4F46E5;
            /* Indigo 600 */
            --bg-light: #F3F4F6;
            --text-muted: #6B7280;
        }

        body {
            background-color: #F9FAFB;
        }

        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            transition: all 0.2s ease;
        }

        .card-stats {
            cursor: pointer;
        }

        .card-stats:hover,
        .card-stats.active {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
            border-bottom: 3px solid var(--primary-color);
        }

        .badge-soft-primary {
            background-color: #E0E7FF;
            color: #4338CA !important;
        }

        .badge-soft-success {
            background-color: #D1FAE5;
            color: #065F46 !important;
        }

        .badge-soft-warning {
            background-color: #FEF3C7;
            color: #92400E !important;
        }

        .badge-soft-danger {
            background-color: #FEE2E2;
            color: #B91C1C !important;
        }

        .badge-soft-info {
            background-color: #DBEAFE;
            color: #1E40AF !important;
        }

        .badge-soft-secondary {
            background-color: #F3F4F6;
            color: #374151 !important;
        }

        .table thead th {
            background-color: #F9FAFB;
            color: #6B7280;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
            border-bottom: 1px solid #E5E7EB;
        }

        .customer-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
        }

        /* Right Sidebar for Order Details */
        .order-sidebar-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.3);
            z-index: 1040;
            display: none;
            backdrop-filter: blur(2px);
        }

        .order-sidebar {
            position: fixed;
            top: 0;
            right: -450px;
            width: 450px;
            height: 100vh;
            background: white;
            z-index: 1050;
            transition: right 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: -4px 0 24px rgba(0, 0, 0, 0.1);
            overflow-y: auto;
        }

        .order-sidebar.active {
            right: 0;
        }

        .filter-group-title {
            font-size: 0.85rem;
            font-weight: 600;
            color: #374151;
            margin-bottom: 10px;
            margin-top: 20px;
        }
    </style>
@endpush

@section('body')
    <div class="container-fluid p-4">

        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 fw-bold text-dark mb-0">Order Management</h1>
                <p class="text-muted small">Manage and track all your orders here.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('customers.export') }}" class="btn btn-outline-secondary btn-sm"><i
                        class="fa fa-file-export me-1"></i> Export</a>
                <!-- <button class="btn btn-primary btn-sm"><i class="fa fa-plus me-1"></i> Create Order</button> -->
            </div>
        </div>

        <!-- Stats Tabs -->
        <div class="row g-3 mb-4">
            @foreach ($tabs as $key => $count)
                <div class="col-6 col-md-2">
                    <div class="card card-stats h-100 p-3 text-center {{ $loop->first ? 'active' : '' }}"
                        data-tab="{{ $key }}">
                        <h6 class="text-muted small text-uppercase fw-bold mb-1">{{ str_replace('_', ' ', $key) }}</h6>
                        <h3 class="fw-bold mb-0 text-dark">{{ $count }}</h3>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="row">
            <!-- Filters Sidebar -->
            <div class="col-lg-3 mb-4">
                <div class="card h-100">
                    <div
                        class="card-header bg-white border-bottom-0 pt-4 pb-0 d-flex justify-content-between align-items-center">
                        <h5 class="card-title fw-bold mb-0"><i class="fa fa-filter me-2 text-primary"></i> Filters</h5>
                        <button class="btn btn-sm btn-link text-muted text-decoration-none" id="clear-filters">Clear
                            All</button>
                    </div>
                    <div class="card-body">

                        <!-- Search -->
                        <div class="mb-4">
                            <label class="form-label text-muted small fw-bold">SEARCH</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0"><i
                                        class="fa fa-search text-muted"></i></span>
                                <input type="text" class="form-control border-start-0 ps-0" id="global-search"
                                    placeholder="Order ID, Customer, Suburb...">
                            </div>
                        </div>

                        <!-- Payment Status -->
                        <div class="mb-4">
                            <label class="filter-group-title">PAYMENT STATUS</label>
                            <div class="d-flex flex-column gap-2 ps-4">
                                <label class="form-check-label d-flex align-items-center cursor-pointer">
                                    <input type="checkbox" class="form-check-input me-2 filter-payment" value="paid">
                                    Paid
                                </label>
                                <label class="form-check-label d-flex align-items-center cursor-pointer">
                                    <input type="checkbox" class="form-check-input me-2 filter-payment" value="pending">
                                    Pending
                                </label>
                                <label class="form-check-label d-flex align-items-center cursor-pointer">
                                    <input type="checkbox" class="form-check-input me-2 filter-payment" value="refunded">
                                    Refunded
                                </label>
                                <label class="form-check-label d-flex align-items-center cursor-pointer">
                                    <input type="checkbox" class="form-check-input me-2 filter-payment" value="failed">
                                    Failed
                                </label>
                            </div>
                        </div>

                        <!-- Date Filter -->
                        <div class="mb-4">
                            <label class="filter-group-title">DATE RANGE</label>
                            <div class="row g-2">
                                <div class="col-12">
                                    <input type="date" class="form-control text-muted" id="start-date"
                                        placeholder="Start Date">
                                    <small class="text-muted" style="font-size: 0.75rem;">Start Date</small>
                                </div>
                                <div class="col-12 mt-2">
                                    <input type="date" class="form-control text-muted" id="end-date"
                                        placeholder="End Date">
                                    <small class="text-muted" style="font-size: 0.75rem;">End Date</small>
                                </div>
                            </div>
                        </div>

                        <!-- Order Value -->
                        <div class="mb-2">
                            <label class="filter-group-title">ORDER VALUE</label>
                            <div class="row g-2">
                                <div class="col-6">
                                    <input type="number" class="form-control form-control-sm" id="min-value"
                                        placeholder="Min">
                                </div>
                                <div class="col-6">
                                    <input type="number" class="form-control form-control-sm" id="max-value"
                                        placeholder="Max">
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Main Table -->
            <div class="col-lg-9">
                <div class="card">
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0" id="orders-table" style="width:100%">
                                <thead>
                                    <tr>
                                        <th width="5%">#</th>
                                        <th>Order #</th>
                                        <th>Customer</th>
                                        <th>Suburb</th>
                                        <th>Total</th>

                                        <th>Payment</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Right Sidebar for Order Details -->
    <div class="order-sidebar-overlay"></div>
    <div class="order-sidebar p-0">
        <!-- Header -->
        <div class="p-4 border-bottom d-flex justify-content-between align-items-start bg-light">
            <div>
                <h5 class="fw-bold mb-1" id="detail-order-number">#JJS00000</h5>
                <small class="text-muted" id="detail-created-at">Created on --</small>
            </div>
            <button class="btn-close" id="close-sidebar"></button>
        </div>

        <!-- Customer Info -->
        <div class="p-4 border-bottom">
            <div class="d-flex align-items-center mb-3">
                <img src="" id="detail-customer-avatar" class="rounded-circle me-3" width="48"
                    height="48">
                <div>
                    <h6 class="fw-bold mb-0 text-dark" id="detail-customer-name">User Name</h6>
                    <a href="#" class="small text-decoration-none" id="detail-customer-email">email@example.com</a>
                    <div class="small text-muted" id="detail-customer-phone">+123456789</div>
                </div>
            </div>
            <div class="bg-gray-50 p-3 rounded border">
                <div class="d-flex align-items-start">
                    <i class="fa fa-map-marker-alt text-muted mt-1 me-2"></i>
                    <div>
                        <span class="d-block fw-bold text-dark mb-1">Shipping Address</span>
                        <span class="text-muted small" id="detail-address">123 Street, Suburb, City</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="p-4 border-bottom">
            <h6 class="fw-bold text-dark mb-3">Manage Order</h6>

            <div class="mb-3">
                <label class="form-label text-muted small fw-bold">ORDER STATUS</label>
                <select class="form-select" id="detail-status-select">
                    <option value="pending">Pending</option>
                    <option value="confirmed">Confirmed</option>
                    <option value="processing">Processing</option>
                    <option value="shipped">Shipped</option>
                    <option value="delivered">Delivered</option>
                    <option value="cancelled">Cancelled</option>
                </select>
                <button class="btn btn-primary btn-sm mt-2 w-100" id="btn-update-status">Update Status</button>
            </div>

            <h6 class="fw-bold text-dark mb-3 mt-4">Payment & Actions</h6>
            <div class="d-grid">
                <button class="btn btn-success btn-sm w-100" id="btn-mark-paid"><i class="fa fa-check me-1"></i> Mark
                    Paid</button>
            </div>
        </div>

        <!-- Line Items -->
        <div class="p-4 pb-2">
            <h6 class="fw-bold text-dark mb-3">Items</h6>
            <div id="detail-items-list">
                <!-- Items populated by JS -->
            </div>
            <div class="border-top pt-3 mt-2">
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-muted">Subtotal</span>
                    <span class="fw-bold" id="detail-subtotal">$0.00</span>
                </div>
                <div class="d-flex justify-content-between mb-1">
                    <span class="text-muted">Discount</span>
                    <span class="text-success" id="detail-discount">-$0.00</span>
                </div>
                <div class="d-flex justify-content-between mt-2 pt-2 border-top">
                    <span class="fw-bold text-dark">Total</span>
                    <span class="fw-bold text-primary fs-5" id="detail-total">$0.00</span>
                </div>
            </div>
        </div>

    </div>

@endsection

@push('scripts')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script>
        $(function() {
            let table;
            const csrfToken = $('meta[name="csrf-token"]').attr('content');

            // Status Badges
            const statusBadges = {
                pending: '<span class="badge badge-soft-warning">Pending</span>',
                confirmed: '<span class="badge badge-soft-primary">Confirmed</span>',
                processing: '<span class="badge badge-soft-info">Processing</span>',
                shipped: '<span class="badge badge-soft-secondary">Shipped</span>',
                delivered: '<span class="badge badge-soft-success">Delivered</span>',
                cancelled: '<span class="badge badge-soft-danger">Cancelled</span>'
            };

            const paymentBadges = {
                pending: '<span class="badge badge-soft-warning">Pending</span>',
                paid: '<span class="badge badge-soft-success">Paid</span>',
                failed: '<span class="badge badge-soft-danger">Failed</span>',
                refunded: '<span class="badge badge-soft-danger">Refunded</span>',
                partially_refunded: '<span class="badge badge-soft-info">Partially Refunded</span>'
            };

            // --- DataTable Initialization ---
            table = $('#orders-table').DataTable({
                processing: true,
                serverSide: true,
                dom: 'rtip', // Hide default search/length
                pageLength: 20,
                ajax: {
                    url: '{{ route('order.data') }}',
                    data: function(d) {
                        d.payment_status = $('.filter-payment:checked').map(function() {
                            return $(this).val();
                        }).get().join(',');
                        d.order_value_min = $('#min-value').val();
                        d.order_value_max = $('#max-value').val();
                        d.start_date = $('#start-date').val();
                        d.end_date = $('#end-date').val();
                        // Global search via custom Input
                        d.search = {
                            value: $('#global-search').val(),
                            regex: false
                        };
                        // Tab filter
                        d.tab_status = $('.card-stats.active').data('tab') === 'all' ? '' : $(
                            '.card-stats.active').data('tab');
                    }
                },
                columns: [{
                        data: 'DT_RowIndex',
                        orderable: false,
                        searchable: false
                    },
                    {
                        data: 'order_number',
                        name: 'order_number',
                        render: function(d) {
                            return `<span class="fw-bold text-primary">#${d}</span>`;
                        }
                    },
                    {
                        data: 'customer',
                        name: 'user.name',
                        render: function(d) {
                            return `
                    <div class="d-flex align-items-center">
                        <img src="${d.avatar}" class="customer-avatar me-2">
                        <div>
                            <div class="fw-bold text-dark" style="font-size:0.9rem">${d.name}</div>
                            <div class="text-muted small">${d.email}</div>
                        </div>
                    </div>
                `;
                        }
                    },
                    {
                        data: 'suburb',
                        name: 'address.suburb'
                    },
                    {
                        data: 'total_formatted',
                        name: 'total',
                        render: d => `<span class="fw-bold text-dark">$${d}</span>`
                    },

                    {
                        data: 'payment_status',
                        name: 'payment_status',
                        render: d => paymentBadges[d] || d
                    },
                    {
                        data: 'order_status',
                        name: 'order_status',
                        render: d => statusBadges[d] || d
                    },
                    {
                        data: 'created_at_human',
                        name: 'created_at'
                    },
                    {
                        data: 'id',
                        orderable: false,
                        render: function(d, type, row) {
                            let editUrl = '{{ route('order.edit', ':id') }}'.replace(':id', d);

                            return `
                                <div class="d-flex justify-content-start gap-2">
                                    <button class="btn btn-sm btn-outline-info border btn-view-details rounded" data-id="${d}" title="View Details">
                                        <i class="fa fa-eye"></i>
                                    </button>
                                    <a href="${editUrl}" class="btn btn-sm btn-outline-primary border rounded" title="Edit Order">
                                        <i class="fa fa-pencil"></i>
                                    </a>
                                </div>
                            `;
                        }
                    }
                ]
            });

            // --- Filter Events ---
            $('#global-search').on('keyup', function() {
                table.ajax.reload();
            });
            $('.filter-payment, #start-date, #end-date').on('change', function() {
                table.ajax.reload();
            });
            $('#min-value, #max-value').on('keyup change', function() {
                table.ajax.reload();
            });
            $('#clear-filters').on('click', function() {
                $('#global-search').val('');
                $('.filter-payment').prop('checked', false);
                $('#start-date, #end-date').val('');
                $('#min-value, #max-value').val('');
                table.ajax.reload();
            });

            // --- Tab Switching ---
            $('.card-stats').on('click', function() {
                $('.card-stats').removeClass('active');
                $(this).addClass('active');
                table.ajax.reload();
            });

            // --- Right Sidebar Logic ---
            $(document).on('click', '.btn-view-details', function() {
                let id = $(this).data('id');
                fetchDetails(id);
            });

            $('#close-sidebar, .order-sidebar-overlay').on('click', function() {
                $('.order-sidebar').removeClass('active');
                $('.order-sidebar-overlay').fadeOut();
            });

            let currentOrderId = null;

            function fetchDetails(id) {
                currentOrderId = id; // Store ID
                $.get(`/order/${id}/details`, function(data) {
                    // Populate Sidebar
                    $('#detail-order-number').text('#' + data.order_number);
                    $('#detail-created-at').text('Created ' + (data.created_at || ''));

                    // Set stats dropdown
                    $('#detail-status-select').val(data.order_status);

                    // User
                    if (data.user) {
                        $('#detail-customer-name').text(data.user.name);
                        $('#detail-customer-email').text(data.user.email);
                        $('#detail-customer-phone').text(data.user.phone || '');
                        $('#detail-customer-avatar').attr('src', data.user.avatar_url ||
                            '{{ asset('uploads/avatars/default.png') }}');
                    } else {
                        $('#detail-customer-name').text('Guest');
                        $('#detail-customer-avatar').attr('src',
                            '{{ asset('uploads/avatars/default.png') }}');
                    }

                    // Address
                    let addr = data.address;
                    if (addr) {
                        $('#detail-address').text(`${addr.address}, ${addr.suburb}, ${addr.city}`);
                    } else {
                        $('#detail-address').text('No shipping address');
                    }

                    // Totals
                    $('#detail-subtotal').text('$' + data.subtotal);
                    $('#detail-discount').text('-$' + data.discount);
                    $('#detail-total').text('$' + data.total);



                    // Items
                    let itemsHtml = '';
                    if (data.order_items) {
                        data.order_items.forEach(item => {
                            itemsHtml += `
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="d-flex align-items-center">
                                <div class="bg-light rounded p-1 me-2" style="width:40px;height:40px;display:flex;align-items:center;justify-content:center;">
                                    <i class="fa fa-box text-muted"></i>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark small">${item.product?.name || 'Product'}</div>
                                    <div class="text-muted small">Qty: ${item.quantity}</div>
                                </div>
                            </div>
                            <span class="fw-bold small">$${item.price}</span>
                        </div>
                    `;
                        });
                    }
                    $('#detail-items-list').html(itemsHtml);

                    // Open Sidebar
                    $('.order-sidebar-overlay').fadeIn();
                    $('.order-sidebar').addClass('active');
                });
            }

            // Load Stats Function
            function loadStats() {
                $.get('{{ route('order.stats') }}', function(data) {
                    Object.keys(data).forEach(key => {
                        $(`.card-stats[data-tab="${key}"] h3`).text(data[key]);
                    });
                });
            }

            // Initial load
            loadStats();

            // Action Handlers
            $('#btn-update-status').on('click', function() {
                if (!currentOrderId) return;
                let newStatus = $('#detail-status-select').val();

                $.post(`/order/${currentOrderId}/status`, {
                    _token: csrfToken,
                    order_status: newStatus
                }, function(res) {
                    toastr.success(res.message);
                    table.ajax.reload(null, false);
                    loadStats();
                }).fail(function(xhr) {
                    toastr.error(xhr.responseJSON?.message || 'Failed');
                });
            });

            $('#btn-mark-paid').on('click', function() {
                if (!currentOrderId) return;
                if (!confirm('Mark this order as Paid?')) return;

                $.post(`/order/${currentOrderId}/payment`, {
                    _token: csrfToken,
                    payment_status: 'paid'
                }, function(res) {
                    toastr.success(res.message);
                    $('.order-sidebar').removeClass('active'); // Close sidebar
                    $('.order-sidebar-overlay').fadeOut();
                    table.ajax.reload(null, false);
                    loadStats();
                }).fail(function(xhr) {
                    toastr.error(xhr.responseJSON?.message || 'Failed');
                });
            });

            $('#btn-cancel-order').on('click', function() {
                if (!currentOrderId) return;
                if (!confirm('Are you sure you want to cancel this order?')) return;

                $.post(`/order/${currentOrderId}/cancel`, {
                    _token: csrfToken
                }, function(res) {
                    toastr.success(res.message || 'Order cancelled');
                    $('.order-sidebar').removeClass('active');
                    $('.order-sidebar-overlay').fadeOut();
                    table.ajax.reload(null, false);
                    loadStats();
                }).fail(function(xhr) {
                    toastr.error(xhr.responseJSON?.message || 'Failed');
                });
            });

            // --- Refund Modal Logic ---
            let refundMaxAmount = 0;
            let currentRefundMethod = '';
            let currentTransactionId = '';



            // Initialize Flatpickr
            $("#start-date, #end-date").flatpickr({
                dateFormat: "Y-m-d", // Value sent to server
                altInput: true, // Show a different input to user
                altFormat: "d/m/Y", // format: Date/Month/Year
                allowInput: true
            });
        });
    </script>

    <!-- Flatpickr -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <style>
        /* Fix Flatpickr width in Bootstrap grid */
        .flatpickr-wrapper {
            width: 100%;
        }
    </style>
@endpush
