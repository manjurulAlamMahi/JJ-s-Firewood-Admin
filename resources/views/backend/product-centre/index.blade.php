@extends('backend.master')

@section('title', 'Product Centre')

@push('styles')
<!-- jQuery UI CSS -->
<link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
@endpush

@section('body')
<style>
    /* Product Centre — uses design system tokens for theme compatibility */
    .product-centre-header {
        background: var(--av-surface);
        color: var(--av-text);
        padding: 1.25rem;
        border-radius: var(--av-r);
        margin-bottom: 1.25rem;
        border: 1px solid var(--av-border);
    }

    .stat-card {
        background: var(--av-surface);
        border-radius: var(--av-r-sm);
        padding: 1.25rem;
        border: 1px solid var(--av-border);
        box-shadow: var(--av-shadow-xs);
    }

    .stat-icon {
        width: 50px;
        height: 50px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
        margin-bottom: 1rem;
    }

    /* Tab Styling — aligned to design system */
    .nav-tabs-custom {
        border-bottom: 1px solid var(--av-border);
        margin-bottom: 1.25rem;
        background: transparent;
        padding: 0;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
        flex-wrap: nowrap;
        white-space: nowrap;
    }
    .nav-tabs-custom::-webkit-scrollbar { display: none; }

    .nav-tabs-custom .nav-link {
        border: none;
        color: var(--av-muted);
        padding: .75rem 1rem;
        font-weight: 500;
        position: relative;
        white-space: nowrap;
    }

    .nav-tabs-custom .nav-link:hover {
        color: var(--av-primary);
        border: none;
    }

    .nav-tabs-custom .nav-link.active {
        color: var(--av-primary);
        border: none;
        background: transparent;
    }

    .nav-tabs-custom .nav-link.active::after {
        content: '';
        position: absolute;
        bottom: -1px;
        left: 0;
        right: 0;
        height: 2px;
        background: var(--av-primary);
    }

    /* Table Styling — inherits from design system */
    .modern-table {
        background: var(--av-surface);
        border-radius: var(--av-r-sm);
        overflow: hidden;
        border: 1px solid var(--av-border);
    }

    .modern-table thead {
        background: var(--av-surface-2);
    }

    .modern-table th {
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.5px;
        color: var(--av-muted);
        padding: .85rem 1rem;
        border: none;
    }

    .modern-table td {
        padding: .85rem 1rem;
        vertical-align: middle;
        border-bottom: 1px solid var(--av-border);
        color: var(--av-text);
    }

    .modern-table tbody tr:hover {
        background: var(--av-surface-2);
    }

    /* Filter Section */
    .filter-section {
        background: var(--av-surface);
        padding: 1.25rem;
        border-radius: var(--av-r-sm);
        margin-bottom: 1rem;
        border: 1px solid var(--av-border);
    }

    /* Bulk Actions */
    .bulk-actions-bar {
        background: var(--av-surface-2);
        padding: 1rem;
        border-radius: var(--av-r-sm);
        margin-bottom: 1rem;
        display: none;
    }

    .bulk-actions-bar.active {
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 8px;
    }

    /* Action Buttons */
    .action-btn {
        width: 32px;
        height: 32px;
        padding: 0;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 6px;
        margin: 0 2px;
    }

    /* Drag Handle */
    .drag-handle {
        cursor: move;
        color: var(--av-faint);
        font-size: 18px;
    }

    .drag-handle:hover {
        color: var(--av-primary);
    }
    
    /* Image Preview */
    .img-preview {
        width: 50px;
        height: 50px;
        object-fit: cover;
        border-radius: 6px;
    }
    
    /* Status Toggle */
    .status-toggle {
        position: relative;
        display: inline-block;
        width: 44px;
        height: 24px;
    }
    
    .status-toggle input {
        opacity: 0;
        width: 0;
        height: 0;
    }
    
    .toggle-slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #ccc;
        transition: .3s;
        border-radius: 24px;
    }
    
    .toggle-slider:before {
        position: absolute;
        content: "";
        height: 18px;
        width: 18px;
        left: 3px;
        bottom: 3px;
        background-color: white;
        transition: .3s;
        border-radius: 50%;
    }
    
    input:checked + .toggle-slider {
        background-color: #28a745;
    }
    
    input:checked + .toggle-slider:before {
        transform: translateX(20px);
    }
    
    .custom-badge {
        display: inline-block;
        padding: .25em .4em;
        font-size: 75%;
        font-weight: 700;
        line-height: 1;
        text-align: center;
        white-space: nowrap;
        vertical-align: baseline;
        border-radius: .25rem;
    }

    /* Badge colors */
    .badge-info {
        background-color: #17a2b8 !important;
        color: white !important;
    }
    
    .badge-secondary {
        background-color: #6c757d !important;
        color: white !important;
    }
    
    .badge-success {
        background-color: #28a745 !important;
        color: white !important;
    }
    
    .badge-danger {
        background-color: #dc3545 !important;
        color: white !important;
    }
</style>

<div class="product-centre-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h2 class="mb-1">📦 Product Centre</h2>
            <p class="mb-0 opacity-75">Manage all your products, categories, and subcategories in one place</p>
        </div>
        <div>
            <button class="btn btn-light" onclick="location.reload()">
                <i class="fe fe-refresh-cw"></i> Refresh
            </button>
        </div>
    </div>
    
    <div class="row mt-4">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(102, 126, 234, 0.1); color: #667eea;">
                    <i class="fe fe-grid"></i>
                </div>
                <h3 class="mb-0">{{ $stats['categories'] }}</h3>
                <small class="text-muted">Categories</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(52, 211, 153, 0.1); color: #34d399;">
                    <i class="fe fe-layers"></i>
                </div>
                <h3 class="mb-0">{{ $stats['subcategories'] }}</h3>
                <small class="text-muted">SubCategories</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(251, 146, 60, 0.1); color: #fb923c;">
                    <i class="fe fe-package"></i>
                </div>
                <h3 class="mb-0">{{ $stats['products'] }}</h3>
                <small class="text-muted">Total Products</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(34, 197, 94, 0.1); color: #22c55e;">
                    <i class="fe fe-check-circle"></i>
                </div>
                <h3 class="mb-0">{{ $stats['active_products'] }}</h3>
                <small class="text-muted">Active Products</small>
            </div>
        </div>
    </div>
</div>

<!-- Tabs -->
<ul class="nav nav-tabs nav-tabs-custom" id="productCentreTabs" role="tablist">
    <li class="nav-item">
        <a class="nav-link active" id="categories-tab" data-toggle="tab" href="#categories" role="tab">
            <i class="fe fe-grid mr-2"></i> Categories
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="subcategories-tab" data-toggle="tab" href="#subcategories" role="tab">
            <i class="fe fe-layers mr-2"></i> SubCategories
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="products-tab" data-toggle="tab" href="#products" role="tab">
            <i class="fe fe-package mr-2"></i> Products
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="bundles-tab" data-toggle="tab" href="#bundles" role="tab">
            <i class="fe fe-box mr-2"></i> Bundles
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="coupons-tab" data-toggle="tab" href="#coupons" role="tab">
            <i class="fe fe-tag mr-2"></i> Coupons
        </a>
    </li>
</ul>

<!-- Tab Content -->
<div class="tab-content" id="productCentreTabContent">
    <!-- Categories Tab -->
    <div class="tab-pane fade show active" id="categories" role="tabpanel">
        @include('backend.product-centre.tabs.categories')
    </div>
    
    <!-- SubCategories Tab -->
    <div class="tab-pane fade" id="subcategories" role="tabpanel">
        @include('backend.product-centre.tabs.subcategories')
    </div>
    
    <!-- Products Tab -->
    <div class="tab-pane fade" id="products" role="tabpanel">
        @include('backend.product-centre.tabs.products')
    </div>

    <!-- Bundles Tab -->
    <div class="tab-pane fade" id="bundles" role="tabpanel">
        @include('backend.product-centre.tabs.bundles')
    </div>

    <!-- Coupons Tab -->
    <div class="tab-pane fade" id="coupons" role="tabpanel">
        @include('backend.product-centre.tabs.coupons')
    </div>
</div>

@endsection

@push('scripts')
<meta name="csrf-token" content="{{ csrf_token() }}">

{{-- jQuery is already loaded by the shell (same 3.6.0). Re-loading it here
     replaced the global instance *after* DataTables had registered against the
     first one, so $.fn.DataTable was undefined on this page only. jQuery UI is
     still needed for the drag-to-reorder handles and now binds to the shell's
     jQuery. --}}
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js" integrity="sha256-lSjKY0/srUM9BE3dPm+c4fBo1dky2v27Gdjm2uoZaL0=" crossorigin="anonymous"></script>

<!-- Product Centre JavaScript -->
<script src="{{ asset('js/product-centre.js') }}"></script>

<script>
$(function () {
    'use strict';

    var csrfToken = $('meta[name="csrf-token"]').attr('content');

    // Bundles and Coupons reuse their existing server-side endpoints rather
    // than duplicating query logic in the Product Centre controller.
    var bundles = $('#bundles-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route('bundles.data') }}',
        order: [[0, 'desc']],
        // The tab header already provides a search box; drop DataTables' own
        // so the tab does not show two.
        dom: 'lrtip',
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'name', name: 'name' },
            { data: 'base_product', name: 'base_product', orderable: false },
            { data: 'price', name: 'price' },
            { data: 'image', name: 'image', orderable: false, searchable: false },
            {
                // bundles.data returns a raw 0/1 here, so the switch is built
                // client-side (same as the standalone Bundles page did).
                data: 'status',
                name: 'status',
                orderable: false,
                searchable: false,
                render: function (data, type, row) {
                    var checked = parseInt(data, 10) === 1 ? 'checked' : '';
                    return '<label class="switch">' +
                        '<input type="checkbox" class="toggle-status" data-id="' + row.id + '" ' + checked + '>' +
                        '<span class="slider"></span></label>';
                }
            },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ]
    });

    var coupons = $('#coupons-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: '{{ route('coupon.data') }}',
        order: [[0, 'desc']],
        dom: 'lrtip',
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'code', name: 'code' },
            { data: 'type', name: 'type' },
            { data: 'value', name: 'value' },
            { data: 'min_order_amount', name: 'min_order_amount' },
            { data: 'max_discount', name: 'max_discount' },
            { data: 'usage_info', name: 'usage_info', orderable: false, searchable: false },
            { data: 'is_active', name: 'is_active', orderable: false, searchable: false },
            {
                // coupon.data returns no `action` column — unlike bundles.data,
                // this endpoint leaves the row controls to the client. Asking
                // for data:'action' produced "Requested unknown parameter
                // 'action' for row 0, column 8".
                data: null,
                name: 'action',
                defaultContent: '',
                orderable: false,
                searchable: false,
                render: function (data, type, row) {
                    return '' +
                        '<div class="av-actions">' +
                            '<a href="/coupon/' + row.id + '/edit" class="btn btn-sm btn-secondary" title="Edit">' +
                                '<i class="fa fa-edit"></i>' +
                            '</a>' +
                            '<form action="/coupon/' + row.id + '" method="POST" class="pc-coupon-delete">' +
                                '<input type="hidden" name="_token" value="' + csrfToken + '">' +
                                '<input type="hidden" name="_method" value="DELETE">' +
                                '<button type="button" class="btn btn-sm btn-outline-danger" title="Delete">' +
                                    '<i class="fa fa-trash"></i>' +
                                '</button>' +
                            '</form>' +
                        '</div>';
                }
            }
        ]
    });

    // Deleting a coupon is destructive and the row buttons are small; confirm
    // through SweetAlert rather than the browser's native dialog.
    $(document).on('click', '.pc-coupon-delete button', function () {
        var form = $(this).closest('form');

        Swal.fire({
            title: 'Delete this coupon?',
            text: 'This cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Delete',
            confirmButtonColor: '#c02626'
        }).then(function (result) {
            if (result.isConfirmed) form.submit();
        });
    });

    // These tabs are hidden at load, so DataTables measures their columns
    // against a zero-width container. Re-measure once the tab is shown.
    $('#productCentreTabs a').on('shown.bs.tab click', function () {
        setTimeout(function () {
            bundles.columns.adjust();
            coupons.columns.adjust();
        }, 60);
    });

    // The tab headers carry their own search boxes.
    $('#bundleSearch').on('keyup', function () { bundles.search(this.value).draw(); });
    $('#couponSearch').on('keyup', function () { coupons.search(this.value).draw(); });

    // Status switches. Both tables use `.toggle-status`, so the handlers are
    // scoped per table — they post to different endpoints.
    function wireToggle(tableSelector, urlFor) {
        $(document).on('change', tableSelector + ' .toggle-status', function () {
            var checkbox = $(this).prop('disabled', true);

            $.ajax({
                url: urlFor(checkbox.data('id')),
                type: 'POST',
                data: { _token: csrfToken, status: checkbox.is(':checked') ? 1 : 0 },
                success: function (response) {
                    toastr.success((response && response.message) || 'Status updated');
                },
                error: function () {
                    toastr.error('Failed to update status.');
                    // Put the switch back so it stops showing a state the
                    // server never accepted.
                    checkbox.prop('checked', !checkbox.is(':checked'));
                },
                complete: function () {
                    checkbox.prop('disabled', false);
                }
            });
        });
    }

    wireToggle('#bundles-table', function (id) { return '/bundles/' + id + '/status'; });
    wireToggle('#coupons-table', function (id) { return '/coupon/' + id + '/status'; });
});
</script>
@endpush