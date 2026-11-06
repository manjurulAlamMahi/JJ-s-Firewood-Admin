@extends('backend.master')

@section('title', 'Order Centre')

@push('page-styles')
{{-- Page CSS lives in a real stylesheet now (see public/admin/assets/css/
     order-centre.css). It replaces ~500 lines of inline styles that hardcoded
     an indigo palette and a desktop-only layout. Pushed to `page-styles` so it
     loads after the design system. --}}
<link rel="stylesheet"
      href="{{ asset('admin/assets/css/order-centre.css') }}?v={{ @filemtime(public_path('admin/assets/css/order-centre.css')) ?: 1 }}">
@endpush

@section('body')

<x-admin.page-header
    title="Order Centre"
    subtitle="Everything that has been ordered, and everything still waiting on you.">
    <x-slot:actions>
        <button type="button" class="btn btn-secondary" id="oc-refresh">
            <i class="fa fa-sync"></i> Refresh
        </button>
    </x-slot:actions>
</x-admin.page-header>

{{-- Four numbers, tappable: each jumps to the list it summarises. --}}
<div class="av-stats">
    <a class="av-stat" href="#all-orders" data-oc-tab="all-orders-tab">
        <div class="av-stat__top">
            <span class="av-stat__icon av-stat__icon--primary"><i class="fa fa-shopping-bag"></i></span>
            <span class="av-stat__label">Today's orders</span>
        </div>
        <div class="av-stat__value" id="stat-today-orders">{{ $stats['today_orders'] }}</div>
    </a>

    <div class="av-stat">
        <div class="av-stat__top">
            <span class="av-stat__icon av-stat__icon--success"><i class="fa fa-dollar-sign"></i></span>
            <span class="av-stat__label">Today's revenue</span>
        </div>
        <div class="av-stat__value" id="stat-today-revenue">${{ number_format($stats['today_revenue'], 2) }}</div>
    </div>

    <a class="av-stat" href="#needs-action" data-oc-tab="needs-action-tab">
        <div class="av-stat__top">
            <span class="av-stat__icon av-stat__icon--warning"><i class="fa fa-exclamation-circle"></i></span>
            <span class="av-stat__label">Needs action</span>
        </div>
        <div class="av-stat__value" id="stat-needs-action">{{ $stats['needs_action'] }}</div>
    </a>

    <a class="av-stat" href="#needs-action" data-oc-tab="needs-action-tab">
        <div class="av-stat__top">
            <span class="av-stat__icon av-stat__icon--danger"><i class="fa fa-clock"></i></span>
            <span class="av-stat__label">Overdue</span>
        </div>
        <div class="av-stat__value" id="stat-overdue">{{ $stats['overdue'] }}</div>
    </a>
</div>

{{-- Tab strip. Scrolls horizontally on a phone rather than wrapping into three
     cramped rows. The active tab is scrolled into view by the page script. --}}
<ul class="nav nav-tabs oc-tabs" id="orderCentreTabs" role="tablist">
    <li class="nav-item"><a class="nav-link" id="needs-action-tab" data-bs-toggle="tab" href="#needs-action" role="tab"><i class="fa fa-bolt"></i> Needs Action</a></li>
    <li class="nav-item"><a class="nav-link active" id="all-orders-tab" data-bs-toggle="tab" href="#all-orders" role="tab"><i class="fa fa-mobile-alt"></i> App Orders</a></li>
    <li class="nav-item"><a class="nav-link" id="website-orders-tab" data-bs-toggle="tab" href="#website-orders" role="tab"><i class="fa fa-globe"></i> Website Orders</a></li>
    <li class="nav-item"><a class="nav-link" id="refunds-queue-tab" data-bs-toggle="tab" href="#refunds-queue" role="tab"><i class="fa fa-undo"></i> Refunds Queue</a></li>
    <li class="nav-item"><a class="nav-link" id="analytics-tab" data-bs-toggle="tab" href="#analytics" role="tab"><i class="fa fa-chart-line"></i> Analytics</a></li>
    <li class="nav-item"><a class="nav-link" id="settings-tab" data-bs-toggle="tab" href="#settings" role="tab"><i class="fa fa-cog"></i> Settings</a></li>
</ul>

<div class="tab-content" id="orderCentreTabContent">

    {{-- ============ NEEDS ACTION ============ --}}
    <div class="tab-pane fade" id="needs-action" role="tabpanel">

        <div class="oc-table-card">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <div>
                    <h5 class="mb-0"><i class="fa fa-bolt text-warning me-2"></i> Needs Your Attention</h5>
                    <small class="text-muted">Pending payment, awaiting scheduling, or overdue.</small>
                </div>
                <div class="d-flex align-items-center gap-3">
                    {{-- Show dismissed toggle — when on, the table includes dismissed
                         orders (greyed out with a Restore button). When off (default),
                         dismissed orders are hidden. --}}
                    <div class="form-check form-switch m-0">
                        <input class="form-check-input" type="checkbox" id="oc-na-show-dismissed">
                        <label class="form-check-label small text-muted" for="oc-na-show-dismissed">
                            Show dismissed
                        </label>
                    </div>
                    <div class="text-muted small" id="oc-na-summary">Loading…</div>
                </div>
            </div>

            {{-- Bulk action bar — appears when at least one row is selected --}}
            <div class="oc-bulk-bar mb-3 p-2 rounded border bg-light d-none" id="oc-na-bulk-bar" style="border-color:#fbbf24!important;">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <strong class="me-2"><span id="oc-na-bulk-count">0</span> selected</strong>
                    <button class="btn btn-sm btn-primary oc-na-bulk-btn" data-action="update">
                        <i class="fa fa-pencil-alt me-1"></i> Update Status / Payment
                    </button>
                    <button class="btn btn-sm btn-success oc-na-bulk-btn" data-action="mark-delivered" title="One-click: marks all selected as Delivered + Paid">
                        <i class="fa fa-check-double me-1"></i> Mark as Delivered
                    </button>
                    <button class="btn btn-sm btn-info oc-na-bulk-btn" data-action="reschedule">
                        <i class="fa fa-calendar me-1"></i> Reschedule
                    </button>
                    <button class="btn btn-sm btn-outline-warning oc-na-bulk-btn" data-action="dismiss" title="Hide selected from Needs Action (returns automatically if customer/order state changes)">
                        <i class="fa fa-eye-slash me-1"></i> Dismiss
                    </button>
                    <button class="btn btn-sm btn-outline-secondary ms-auto" id="oc-na-bulk-clear">
                        <i class="fa fa-times me-1"></i> Clear
                    </button>
                </div>
            </div>

            <div class="table-responsive">
                <table class="oc-table table table-hover align-middle mb-0" id="oc-na-table" style="width:100%">
                    <thead>
                        <tr>
                            <th style="width: 40px;"><input type="checkbox" id="oc-na-check-all" title="Select all on this page"></th>
                            <th>Reason</th>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Suburb</th>
                            <th>Total</th>
                            <th>Delivery Date</th>
                            <th>Created</th>
                            <th style="width: 110px;">Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ============ ALL ORDERS ============ --}}
    <div class="tab-pane fade show active" id="all-orders" role="tabpanel">
        <div class="row">
            {{-- Filters: a rail on desktop, a bottom sheet on a phone. Same
                 markup and same element IDs either way — only the presentation
                 changes, so none of the filtering JS knows the difference. --}}
            <div class="col-lg-3 mb-3">
                <div class="oc-filter-card av-card av-sheet" id="oc-filters">
                    <div class="av-sheet__handle"></div>

                    <div class="av-sheet__head">
                        <h2 class="av-sheet__title">Filters</h2>
                        <button type="button" class="av-iconbtn" data-sheet-close aria-label="Close filters">
                            <i class="fa fa-times"></i>
                        </button>
                    </div>

                    <div class="av-sheet__body">
                    <div class="d-flex justify-content-between align-items-center mb-2 av-only-desktop">
                        <strong>Filters</strong>
                        <button class="btn btn-sm btn-link text-decoration-none p-0" id="oc-clear-filters">Clear all</button>
                    </div>

                    <div class="oc-filter-group">
                        <h6>Search</h6>
                        <input type="text" id="oc-search" class="form-control form-control-sm" placeholder="Order #, name, email, phone…">
                    </div>

                    <div class="oc-filter-group">
                        <h6>Date Range</h6>
                        <input type="date" id="oc-date-from" class="form-control form-control-sm mb-2" placeholder="From">
                        <input type="date" id="oc-date-to" class="form-control form-control-sm" placeholder="To">
                    </div>

                    <div class="oc-filter-group">
                        <h6>Order Status</h6>
                        @foreach(['pending','confirmed','processing','shipped','delivered','cancelled'] as $s)
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input oc-filter-status" value="{{ $s }}" id="oc-st-{{ $s }}">
                                <label class="form-check-label small" for="oc-st-{{ $s }}">{{ ucfirst($s) }}</label>
                            </div>
                        @endforeach
                    </div>

                    <div class="oc-filter-group">
                        <h6>Payment Status</h6>
                        @foreach(['pending','paid','failed','refunded','partially_refunded'] as $p)
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input oc-filter-payment" value="{{ $p }}" id="oc-pay-{{ $p }}">
                                <label class="form-check-label small" for="oc-pay-{{ $p }}">{{ ucwords(str_replace('_',' ', $p)) }}</label>
                            </div>
                        @endforeach
                    </div>

                    <div class="oc-filter-group">
                        <h6>Order Value</h6>
                        <div class="row g-2">
                            <div class="col-6"><input type="number" id="oc-value-min" class="form-control form-control-sm" placeholder="Min"></div>
                            <div class="col-6"><input type="number" id="oc-value-max" class="form-control form-control-sm" placeholder="Max"></div>
                        </div>
                    </div>

                    <div class="oc-filter-group">
                        <h6>Tags</h6>
                        @foreach($tags as $tag)
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input oc-filter-tag" value="{{ $tag->id }}" id="oc-tag-{{ $tag->id }}">
                                <label class="form-check-label small" for="oc-tag-{{ $tag->id }}">
                                    <span class="oc-tag-chip" style="background: {{ $tag->colour }}">{{ $tag->name }}</span>
                                </label>
                            </div>
                        @endforeach
                    </div>

                    <div class="oc-filter-group">
                        <h6>Suburb</h6>
                        <input type="text" id="oc-suburb" class="form-control form-control-sm" placeholder="e.g. Wanneroo">
                    </div>

                    <div>
                        <h6>Source</h6>
                        <select id="oc-source" class="form-select form-select-sm">
                            <option value="">All</option>
                            <option value="native">Native (this site)</option>
                            <option value="woocommerce">WooCommerce</option>
                        </select>
                    </div>
                    </div>{{-- /av-sheet__body --}}

                    <div class="av-sheet__foot">
                        <button type="button" class="btn btn-secondary" id="oc-clear-filters-sheet">Clear all</button>
                        <button type="button" class="btn btn-primary" data-sheet-close>Show results</button>
                    </div>
                </div>
            </div>

            {{-- Master table --}}
            <div class="col-lg-9">
                {{-- Top toolbar: count + filters + export --}}
                <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                    <div class="text-muted small av-grow" id="oc-result-summary">Loading orders…</div>
                    <div class="av-row">
                        <button class="btn btn-sm btn-secondary" data-sheet-open="oc-filters">
                            <i class="fa fa-filter"></i> Filters
                        </button>
                        <button class="btn btn-sm btn-outline-secondary" id="oc-export-csv">
                            <i class="fa fa-download"></i> <span class="av-only-desktop">Export CSV</span>
                        </button>
                    </div>
                </div>

                <div class="oc-bulk-bar" id="oc-bulk-bar">
                    <div><strong><span id="oc-bulk-count">0</span> selected</strong></div>
                    <div>
                        <button class="btn-bulk" id="oc-bulk-update"><i class="fa fa-pencil-alt me-1"></i> Update Status / Payment</button>
                        <button class="btn-bulk" id="oc-bulk-reschedule">Reschedule</button>
                        <button class="btn-bulk" id="oc-bulk-notify">Email Customers</button>
                        <button class="btn-bulk" id="oc-bulk-clear">Clear</button>
                    </div>
                </div>

                <div class="oc-table-card">
                    <div class="table-responsive">
                        <table class="oc-table table table-hover align-middle mb-0" id="oc-orders-table" style="width:100%">
                            <thead>
                                <tr>
                                    <th width="32"><input type="checkbox" id="oc-check-all"></th>
                                    <th>Order #</th>
                                    <th>Customer</th>
                                    <th>Suburb</th>
                                    <th>Total</th>
                                    <th>Payment</th>
                                    <th>Status</th>
                                    <th>Tags</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ============ WEBSITE ORDERS (Phase 7) ============ --}}
    <div class="tab-pane fade" id="website-orders" role="tabpanel">
        <div class="oc-table-card">
            <div class="d-flex justify-content-between align-items-start mb-3 flex-wrap gap-2">
                <div>
                    <h5 class="mb-0"><i class="fa fa-globe text-primary me-2"></i> Website Orders</h5>
                    <small class="text-muted">Synced from <strong id="oc-wo-site-url">(configure in Settings)</strong> via <code class="small">/wp-json/wc/v3/orders</code></small>
                    <div class="text-muted small mt-1" id="oc-wo-last-sync">Last sync: never</div>
                </div>
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <div class="form-check form-switch m-0">
                        <input class="form-check-input" type="checkbox" id="oc-wo-full-sync">
                        <label class="form-check-label small" for="oc-wo-full-sync" title="Pull all orders, ignoring last sync time">Full sync</label>
                    </div>
                    <button class="btn btn-primary btn-sm" id="oc-wo-sync-btn">
                        <i class="fa fa-sync me-1"></i> Sync Now
                    </button>
                </div>
            </div>

            {{-- Search bar --}}
            <div class="row g-2 mb-3 align-items-center">
                <div class="col-md-6">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-white"><i class="fa fa-search text-muted"></i></span>
                        <input type="text" class="form-control" id="oc-wo-search" placeholder="Search by order #, name, email, or phone…">
                        <button class="btn btn-outline-secondary" type="button" id="oc-wo-search-clear" title="Clear">
                            <i class="fa fa-times"></i>
                        </button>
                    </div>
                </div>
                <div class="col-md-3">
                    <select class="form-select form-select-sm" id="oc-wo-status-filter">
                        <option value="">All statuses</option>
                        <option value="pending">Pending</option>
                        <option value="on-hold">On hold</option>
                        <option value="processing">Processing</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                        <option value="refunded">Refunded</option>
                        <option value="failed">Failed</option>
                    </select>
                </div>
                <div class="col-md-3 text-end">
                    <span class="text-muted small" id="oc-wo-result-count"></span>
                </div>
            </div>

            <div id="oc-wo-status" class="alert alert-light border d-none mb-3 small"></div>

            <div class="table-responsive">
                <table class="oc-table table table-hover align-middle mb-0" id="oc-wo-table" style="width:100%; cursor: pointer;">
                    <thead>
                        <tr>
                            <th>WC #</th>
                            <th>Customer</th>
                            <th>Suburb</th>
                            <th>Total</th>
                            <th>WC Status</th>
                            <th>Payment</th>
                            <th>Delivery</th>
                            <th>Created</th>
                            <th width="80"></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ============ REFUNDS QUEUE ============ --}}
    <div class="tab-pane fade" id="refunds-queue" role="tabpanel">
        <div class="oc-table-card">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <div>
                    <h5 class="mb-0"><i class="fa fa-undo text-danger me-2"></i> Refunds Queue</h5>
                    <small class="text-muted">Cancelled-but-paid + partially refunded orders. Dismissed items hidden by default.</small>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="oc-rq-include-dismissed">
                        <label class="form-check-label small" for="oc-rq-include-dismissed">Show dismissed</label>
                    </div>
                    <span class="text-muted small" id="oc-rq-summary">Loading…</span>
                </div>
            </div>
            <div class="table-responsive">
                <table class="oc-table table table-hover align-middle mb-0" id="oc-rq-table" style="width:100%">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Total</th>
                            <th>Refunded</th>
                            <th>Remaining</th>
                            <th>Refund Status</th>
                            <th>Method</th>
                            <th>Cancelled</th>
                            <th width="180">Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- ============ ANALYTICS ============ --}}
    <div class="tab-pane fade" id="analytics" role="tabpanel">
        <div class="oc-table-card">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <h5 class="mb-0"><i class="fa fa-chart-line text-primary me-2"></i> Analytics</h5>
                <div class="btn-group btn-group-sm" role="group">
                    <button type="button" class="btn btn-outline-secondary oc-an-range" data-days="7">7 days</button>
                    <button type="button" class="btn btn-outline-secondary oc-an-range active" data-days="30">30 days</button>
                    <button type="button" class="btn btn-outline-secondary oc-an-range" data-days="90">90 days</button>
                    <button type="button" class="btn btn-outline-secondary oc-an-range" data-days="365">1 year</button>
                </div>
            </div>

            {{-- KPI cards --}}
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted small text-uppercase">Total Revenue</div>
                            <div class="h3 mb-0 text-success" id="oc-an-kpi-revenue">$0</div>
                            <div class="small text-muted">paid orders</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted small text-uppercase">Total Orders</div>
                            <div class="h3 mb-0 text-primary" id="oc-an-kpi-orders">0</div>
                            <div class="small text-muted">all statuses</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted small text-uppercase">Avg Order Value</div>
                            <div class="h3 mb-0" id="oc-an-kpi-aov">$0</div>
                            <div class="small text-muted">paid only</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <div class="text-muted small text-uppercase">New Customers</div>
                            <div class="h3 mb-0" id="oc-an-kpi-customers">0</div>
                            <div class="small text-muted">in period</div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Charts row 1: revenue + delivery --}}
            <div class="row g-3 mb-4">
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h6 class="mb-3">Revenue Trend</h6>
                            <canvas id="oc-an-chart-revenue" style="max-height: 280px;"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h6 class="mb-3">Delivery Performance</h6>
                            <canvas id="oc-an-chart-delivery" style="max-height: 280px;"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Charts row 2: top customers + top products --}}
            <div class="row g-3">
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h6 class="mb-3">Top Customers (revenue)</h6>
                            <canvas id="oc-an-chart-customers" style="max-height: 320px;"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h6 class="mb-3">Top Products (qty)</h6>
                            <canvas id="oc-an-chart-products" style="max-height: 320px;"></canvas>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    {{-- ============ SETTINGS ============ --}}
    <div class="tab-pane fade" id="settings" role="tabpanel">
        {{-- Settings sub-nav (mirrors WooCommerce's settings pattern) --}}
        <div class="oc-settings-nav-wrap mb-4">
            <ul class="nav nav-tabs oc-settings-nav" id="oc-settings-subtabs" role="tablist">
                <li class="nav-item">
                    <a class="nav-link active" data-section="connection" href="#">
                        <i class="fa fa-plug me-1"></i> Connection
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-section="notifications" href="#">
                        <i class="fa fa-envelope me-1"></i> Notifications
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-section="tags" href="#">
                        <i class="fa fa-tags me-1"></i> Order Tags
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-section="automation" href="#">
                        <i class="fa fa-robot me-1"></i> Automation
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" data-section="maintenance" href="#">
                        <i class="fa fa-tools me-1"></i> Maintenance
                    </a>
                </li>
            </ul>
        </div>

        {{-- ===== Connection sub-pane ===== --}}
        <div class="oc-settings-pane" data-section="connection">
            {{-- Header row with title + connection status --}}
            <div class="d-flex justify-content-between align-items-center mb-3" style="max-width: 720px;">
                <div>
                    <h4 class="mb-1" style="font-weight:600;"><i class="fa fa-plug me-2 text-primary"></i> WooCommerce Integration</h4>
                    <p class="text-muted small mb-0">
                        Connect your WooCommerce store at <strong>jjsfirewoodperth.com.au</strong>. Credentials are encrypted at rest.
                    </p>
                </div>
                <div class="oc-status-pill not-configured" id="oc-wc-status-pill">
                    <span class="oc-status-dot"></span>
                    <span id="oc-wc-status-label">Checking…</span>
                </div>
            </div>

            <form id="oc-wc-form">
                {{-- Site URL --}}
                <div class="oc-settings-section">
                    <div class="oc-settings-section-title">Store</div>
                    <div class="mb-0">
                        <label class="form-label fw-semibold">Site URL</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fa fa-globe text-muted"></i></span>
                            <input type="url" class="form-control" id="oc-wc-url" placeholder="https://jjsfirewoodperth.com.au">
                        </div>
                        <div class="form-text">Root URL of your WordPress / WooCommerce site.</div>
                    </div>
                </div>

                {{-- API credentials --}}
                <div class="oc-settings-section">
                    <div class="oc-settings-section-title">API Credentials</div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Consumer Key</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fa fa-key text-muted"></i></span>
                            <input type="text" class="form-control font-monospace" id="oc-wc-key" placeholder="ck_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" style="font-size:.85rem;">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Consumer Secret</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fa fa-lock text-muted"></i></span>
                            <input type="password" class="form-control font-monospace" id="oc-wc-secret" placeholder="cs_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" style="font-size:.85rem;">
                        </div>
                        <div class="form-text">Leave the masked value to keep the existing secret.</div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-semibold">
                            Webhook Secret <span class="text-muted fw-normal">(optional)</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fa fa-shield-alt text-muted"></i></span>
                            <input type="password" class="form-control font-monospace" id="oc-wc-webhook" placeholder="(used to verify incoming webhooks)" style="font-size:.85rem;">
                        </div>
                    </div>
                </div>

                {{-- Sync behaviour --}}
                <div class="oc-settings-section">
                    <div class="oc-settings-section-title">Sync Behaviour</div>
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" id="oc-wc-enabled" role="switch">
                        <label class="form-check-label fw-semibold" for="oc-wc-enabled">Enable write mode</label>
                        <div class="form-text">Push status changes and refunds back to WooCommerce. Off = read-only mirror.</div>
                    </div>
                </div>

                {{-- Action footer --}}
                <div class="d-flex gap-2 align-items-center" style="max-width: 720px;">
                    <button type="button" class="btn btn-primary" id="oc-wc-save">
                        <i class="fa fa-save me-1"></i> Save Settings
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="oc-wc-test">
                        <i class="fa fa-plug me-1"></i> Test Connection
                    </button>
                    <span class="text-muted small ms-auto" id="oc-wc-last-sync"></span>
                </div>
                <div id="oc-wc-test-result" class="mt-3" style="max-width: 720px;"></div>
            </form>
        </div>{{-- ===== END Connection sub-pane ===== --}}

        {{-- ===== Notifications sub-pane ===== --}}
        <div class="oc-settings-pane d-none" data-section="notifications">
            <div class="mb-3" style="max-width: 720px;">
                <h4 class="mb-1" style="font-weight:600;"><i class="fa fa-envelope me-2 text-primary"></i> Notifications</h4>
                <p class="text-muted small mb-0">
                    Control which automatic emails and push notifications go to customers when their app order status changes.
                    These settings only affect <strong>App Orders</strong> — WooCommerce sends its own emails for website orders.
                </p>
            </div>

            <div id="oc-notif-loading" class="text-muted small mb-3">Loading settings…</div>

            <div id="oc-notif-form" class="d-none">
                {{-- Customer events --}}
                <div class="oc-settings-section">
                    <div class="oc-settings-section-title">Customer Events</div>
                    <table class="table align-middle mb-0" style="--bs-table-bg: transparent;">
                        <thead>
                            <tr style="border-bottom: 2px solid #e5e7eb;">
                                <th class="border-0 text-muted small fw-semibold text-uppercase" style="font-size:.7rem; letter-spacing:.05em;">Event</th>
                                <th class="border-0 text-center text-muted small fw-semibold text-uppercase" width="110" style="font-size:.7rem; letter-spacing:.05em;"><i class="fa fa-envelope me-1"></i> Email</th>
                                <th class="border-0 text-center text-muted small fw-semibold text-uppercase" width="110" style="font-size:.7rem; letter-spacing:.05em;"><i class="fa fa-bell me-1"></i> Push</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><div class="fw-semibold">Order Placed</div><div class="text-muted small">When a new order is created in the app</div></td>
                                <td class="text-center"><div class="form-check form-switch d-inline-block m-0"><input class="form-check-input" type="checkbox" role="switch" id="oc-notif-email-placed"></div></td>
                                <td class="text-center"><div class="form-check form-switch d-inline-block m-0"><input class="form-check-input" type="checkbox" role="switch" id="oc-notif-push-placed"></div></td>
                            </tr>
                            <tr>
                                <td><div class="fw-semibold">Order Delivered</div><div class="text-muted small">When an order is marked as delivered</div></td>
                                <td class="text-center"><div class="form-check form-switch d-inline-block m-0"><input class="form-check-input" type="checkbox" role="switch" id="oc-notif-email-delivered"></div></td>
                                <td class="text-center"><div class="form-check form-switch d-inline-block m-0"><input class="form-check-input" type="checkbox" role="switch" id="oc-notif-push-delivered"></div></td>
                            </tr>
                            <tr>
                                <td><div class="fw-semibold">Order Cancelled</div><div class="text-muted small">When an order is cancelled</div></td>
                                <td class="text-center"><div class="form-check form-switch d-inline-block m-0"><input class="form-check-input" type="checkbox" role="switch" id="oc-notif-email-cancelled"></div></td>
                                <td class="text-center"><div class="form-check form-switch d-inline-block m-0"><input class="form-check-input" type="checkbox" role="switch" id="oc-notif-push-cancelled"></div></td>
                            </tr>
                            <tr>
                                <td><div class="fw-semibold">Delivery Date Updated</div><div class="text-muted small">When you change the preferred delivery date</div></td>
                                <td class="text-center"><div class="form-check form-switch d-inline-block m-0"><input class="form-check-input" type="checkbox" role="switch" id="oc-notif-email-delivery"></div></td>
                                <td class="text-center"><div class="form-check form-switch d-inline-block m-0"><input class="form-check-input" type="checkbox" role="switch" id="oc-notif-push-delivery"></div></td>
                            </tr>
                            <tr>
                                <td><div class="fw-semibold">Refund Processed</div><div class="text-muted small">When a refund is issued for an order</div></td>
                                <td class="text-center"><div class="form-check form-switch d-inline-block m-0"><input class="form-check-input" type="checkbox" role="switch" id="oc-notif-email-refund"></div></td>
                                <td class="text-center"><div class="form-check form-switch d-inline-block m-0"><input class="form-check-input" type="checkbox" role="switch" id="oc-notif-push-refund"></div></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                {{-- Admin BCC --}}
                <div class="oc-settings-section">
                    <div class="oc-settings-section-title">Admin BCC</div>
                    <p class="text-muted small mb-3">When customer emails go out, also BCC a copy to your admin address so you can keep an eye on outgoing communication.</p>
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="oc-notif-bcc-enabled">
                        <label class="form-check-label fw-semibold" for="oc-notif-bcc-enabled">Send BCC copies</label>
                    </div>
                    <div class="mb-0">
                        <label class="form-label fw-semibold">BCC Address</label>
                        <div class="input-group">
                            <span class="input-group-text bg-white"><i class="fa fa-envelope text-muted"></i></span>
                            <input type="email" class="form-control" id="oc-notif-bcc-address" placeholder="admin@Jjsfirewoodperth.com.au">
                        </div>
                    </div>
                </div>

                {{-- Setmore Delivery Emails (phone / WooCommerce bookings) --}}
                <div class="oc-settings-section">
                    <div class="oc-settings-section-title">Delivery Emails — Phone &amp; WooCommerce Bookings</div>
                    <p class="text-muted small mb-3">
                        Server-sent booked / rescheduled / cancelled emails for bookings that <strong>don't match an app order</strong>
                        (app orders keep their own emails above — no double-sends). Replaces the Zapier email steps.
                    </p>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="oc-setmore-master">
                        <label class="form-check-label fw-semibold" for="oc-setmore-master">
                            Master switch — turn the whole system on/off
                        </label>
                    </div>

                    <div style="margin-left: 1rem; padding-left: 1rem; border-left: 3px solid #eef0f3;">
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" role="switch" id="oc-setmore-booked">
                            <label class="form-check-label" for="oc-setmore-booked">Send <strong>booked</strong> emails</label>
                        </div>
                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" role="switch" id="oc-setmore-rescheduled">
                            <label class="form-check-label" for="oc-setmore-rescheduled">Send <strong>rescheduled</strong> emails</label>
                        </div>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" role="switch" id="oc-setmore-cancelled">
                            <label class="form-check-label" for="oc-setmore-cancelled">Send <strong>cancelled</strong> emails</label>
                        </div>

                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" role="switch" id="oc-setmore-bcc">
                            <label class="form-check-label" for="oc-setmore-bcc">
                                <strong>BCC admin</strong> on every delivery email (watch real emails go out)
                            </label>
                        </div>

                        <div class="mb-3" style="max-width: 360px;">
                            <label class="form-label fw-semibold">Send via</label>
                            <select class="form-select" id="oc-setmore-mailer">
                                <option value="brevo">Brevo</option>
                                <option value="gmail">Google (Gmail SMTP)</option>
                            </select>
                            <div class="form-text">Applies to all scheduling emails (these and app-order delivery emails).</div>
                        </div>
                    </div>

                    {{-- Send a test email on demand --}}
                    <div class="mt-2 p-3" style="background:#f9fafb; border:1px solid #eef0f3; border-radius:8px; max-width: 560px;">
                        <div class="fw-semibold mb-2"><i class="fa fa-paper-plane me-1 text-primary"></i> Send a test email</div>
                        <p class="text-muted small mb-2">Fires a sample email to the address below so you can preview what customers receive. Doesn't affect the toggles above.</p>
                        <div class="row g-2 align-items-end">
                            <div class="col-12 col-sm-5">
                                <label class="form-label small mb-1">Email type</label>
                                <select class="form-select form-select-sm" id="oc-setmore-test-type">
                                    <option value="booked">Booked</option>
                                    <option value="rescheduled">Rescheduled</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </div>
                            <div class="col-12 col-sm-5">
                                <label class="form-label small mb-1">Send to</label>
                                <input type="email" class="form-control form-control-sm" id="oc-setmore-test-email" placeholder="admin@jjsfirewoodperth.com.au">
                            </div>
                            <div class="col-12 col-sm-2">
                                <button type="button" class="btn btn-sm btn-outline-primary w-100" id="oc-setmore-test-btn">Send</button>
                            </div>
                        </div>
                        <span class="small d-block mt-2" id="oc-setmore-test-status"></span>
                    </div>

                    <div class="mt-3">
                        <a href="{{ route('setmore-reviews.delivery-email-log') }}" class="btn btn-sm btn-outline-secondary">
                            <i class="fa fa-list me-1"></i> View delivery email activity log
                        </a>
                    </div>
                </div>

                <div class="d-flex gap-2 align-items-center" style="max-width: 720px;">
                    <button type="button" class="btn btn-primary" id="oc-notif-save">
                        <i class="fa fa-save me-1"></i> Save Notification Settings
                    </button>
                    <span class="text-muted small ms-auto" id="oc-notif-status"></span>
                </div>
            </div>
        </div>{{-- ===== END Notifications sub-pane ===== --}}

        {{-- ===== Automation sub-pane ===== --}}
        <div class="oc-settings-pane d-none" data-section="automation">
            <div class="mb-3" style="max-width: 720px;">
                <h4 class="mb-1" style="font-weight:600;"><i class="fa fa-robot me-2 text-primary"></i> Automation</h4>
                <p class="text-muted small mb-0">
                    Configure end-of-day automated tasks that reduce manual work.
                </p>
            </div>

            @php
                $hasAutoDeliverCol = \Schema::hasColumn('system_settings', 'auto_deliver_enabled');
                $autoDeliverEnabled = false;
                if ($hasAutoDeliverCol) {
                    try {
                        $autoDeliverEnabled = (bool) \DB::table('system_settings')
                            ->orderBy('id')->value('auto_deliver_enabled');
                    } catch (\Throwable $e) {}
                }
            @endphp

            {{-- Auto-delivery card --}}
            <div class="oc-settings-section" style="max-width: 720px;">
                <div class="card" style="border-left: 4px solid #667eea;">
                    <div class="card-body p-4">
                        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <h5 class="mb-0">End-of-day auto-delivery</h5>
                                    <span class="badge {{ $autoDeliverEnabled ? 'bg-success' : 'bg-secondary' }}"
                                          id="oc-auto-deliver-status">
                                        {{ $autoDeliverEnabled ? 'ON' : 'OFF' }}
                                    </span>
                                </div>
                                <p class="text-muted small mb-3">
                                    Runs <strong>automatically at 6:00 PM Perth time</strong> every day.
                                    Marks orders as <strong>Delivered</strong> if their scheduled delivery date is today (or earlier)
                                    AND they were paid via <strong>Square, PayPal, or card</strong> (electronically verified).
                                    <br>
                                    <strong>Skipped (kept in Needs Action):</strong> unpaid orders, cash payments, bank transfers,
                                    and any other payment method that can't be auto-verified.
                                </p>

                                @if(!$hasAutoDeliverCol)
                                    <div class="alert alert-warning small mb-0">
                                        <i class="fa fa-exclamation-triangle me-1"></i>
                                        Run migrations to enable this feature: <code>php artisan migrate</code>
                                    </div>
                                @else
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox"
                                               id="oc-auto-deliver-toggle"
                                               style="width: 3rem; height: 1.6rem; cursor:pointer;"
                                               {{ $autoDeliverEnabled ? 'checked' : '' }}>
                                        <label class="form-check-label" for="oc-auto-deliver-toggle">
                                            <strong>Enable automatic delivery marking</strong>
                                        </label>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <hr class="my-3">

                        <h6 class="small text-muted text-uppercase mb-2">Manual controls</h6>
                        <div class="d-flex gap-2 flex-wrap">
                            <button class="btn btn-outline-secondary" id="oc-auto-deliver-preview"
                                    title="See what would happen if the sweep ran now — no changes made">
                                <i class="fa fa-eye me-1"></i> Preview tonight's sweep
                            </button>
                            <button class="btn btn-primary" id="oc-auto-deliver-runnow"
                                    title="Run the sweep right now instead of waiting for 6 PM">
                                <i class="fa fa-play me-1"></i> Run sweep now
                            </button>
                        </div>
                        <p class="text-muted small mt-2 mb-0">
                            <strong>Preview</strong> shows what the sweep would do without making changes.
                            <strong>Run now</strong> performs the sweep immediately.
                        </p>
                    </div>
                </div>
            </div>
        </div>{{-- ===== END Automation sub-pane ===== --}}

        {{-- ===== Maintenance sub-pane ===== --}}
        <div class="oc-settings-pane d-none" data-section="maintenance">
            <div class="mb-3" style="max-width: 720px;">
                <h4 class="mb-1" style="font-weight:600;"><i class="fa fa-tools me-2 text-primary"></i> Maintenance</h4>
                <p class="text-muted small mb-0">
                    Tools for resetting your local WooCommerce mirror. None of these touch your live website.
                </p>
            </div>

            <div class="oc-settings-section">
                <div class="oc-settings-section-title">Reset &amp; Pull Latest Orders</div>
                <p class="text-muted small mb-3">
                    Wipe all locally-stored WooCommerce orders and pull only the most recent N orders from your store.
                    The sync watermark is set to <strong>now</strong>, so future syncs will only pick up orders created or modified
                    from this point forward — they won't re-import old orders.
                </p>
                <div class="alert alert-warning small mb-3" role="alert" style="background: #fef3c7; border-color: #fcd34d; color: #92400e;">
                    <i class="fa fa-exclamation-triangle me-1"></i>
                    <strong>This deletes:</strong> all WC orders, line items, customers, notes, audit logs, refunds, and tag links currently in the panel.
                    It does <strong>not</strong> touch your live WooCommerce store — only the local mirror.
                </div>
                <div class="row g-2 align-items-end">
                    <div class="col-md-4">
                        <label class="form-label fw-semibold small mb-1">How many recent orders?</label>
                        <input type="number" class="form-control" id="oc-wc-reset-count" value="100" min="1" max="500">
                    </div>
                    <div class="col-md-8">
                        <button type="button" class="btn btn-warning" id="oc-wc-reset-btn">
                            <i class="fa fa-sync-alt me-1"></i> Reset &amp; Pull Latest
                        </button>
                        <span class="text-muted small ms-2" id="oc-wc-reset-status"></span>
                    </div>
                </div>
            </div>
        </div>{{-- ===== END Maintenance sub-pane ===== --}}

        {{-- ===== Order Tags sub-pane ===== --}}
        <div class="oc-settings-pane d-none" data-section="tags">
            <div class="d-flex justify-content-between align-items-end mb-3" style="max-width: 720px;">
                <div>
                    <h4 class="mb-1" style="font-weight:600;"><i class="fa fa-tags me-2 text-primary"></i> Order Tags</h4>
                    <p class="text-muted small mb-0">
                        Create your own tags to categorise orders. Tags appear in the drawer's Tags section, on every order row, and apply to both App and WooCommerce orders.
                    </p>
                </div>
                <button type="button" class="btn btn-primary" id="oc-tag-new-btn">
                    <i class="fa fa-plus me-1"></i> New Tag
                </button>
            </div>

            <div class="oc-settings-section">
                {{-- Inline create/edit form (hidden by default) --}}
                <div id="oc-tag-form" class="d-none mb-3 p-3" style="background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 8px;">
                    <div class="oc-settings-section-title mb-3" style="border-bottom:none; padding-bottom:0;">New / Edit Tag</div>
                    <input type="hidden" id="oc-tag-form-id" value="">
                    <div class="row g-2 align-items-end">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold small mb-1">Tag Name</label>
                            <input type="text" class="form-control" id="oc-tag-form-name" placeholder="e.g. Bulk Order, Christmas, Pickup" maxlength="64">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold small mb-1">Colour</label>
                            <input type="color" class="form-control form-control-color" id="oc-tag-form-colour" value="#6B7280" style="width: 100%; height: 38px;">
                        </div>
                        <div class="col-md-3 d-flex gap-1">
                            <button type="button" class="btn btn-success flex-fill" id="oc-tag-form-save">Save</button>
                            <button type="button" class="btn btn-light" id="oc-tag-form-cancel">Cancel</button>
                        </div>
                    </div>
                </div>

                <div id="oc-tag-list">
                    <div class="text-muted small">Loading…</div>
                </div>
            </div>
        </div>{{-- ===== END Order Tags sub-pane ===== --}}
    </div>{{-- end #settings tab-pane --}}

</div>{{-- end .tab-content --}}

{{-- =============== RIGHT-SIDE DRAWER =============== --}}
<div class="oc-drawer-overlay" id="oc-drawer-overlay"></div>
<div class="oc-drawer" id="oc-drawer">
    <div class="oc-drawer-header">
        <div>
            <h5 class="fw-bold mb-1" id="oc-d-order-number">#JJS00000</h5>
            <small class="text-muted" id="oc-d-created">Created --</small>
            <div class="mt-1">
                <span class="badge badge-soft-warning" id="oc-d-status-badge">Pending</span>
                <span class="badge badge-soft-warning" id="oc-d-payment-badge">Payment</span>
            </div>
            {{-- Tag pills + confirmed indicator. Populated by loadDrawerOrder().
                 Tags are pure provenance labels — they don't drive workflow. --}}
            <div class="mt-2" id="oc-d-tags-row" style="display: none;">
                <span class="text-muted small me-1">Tags:</span>
                <span id="oc-d-tags"></span>
            </div>
            <div class="mt-1" id="oc-d-confirmed-row" style="display: none;">
                <small class="text-success">
                    <i class="fa fa-check-circle me-1"></i>
                    Date confirmed <span id="oc-d-confirmed-at"></span>
                </small>
            </div>
            <div class="mt-1" id="oc-d-auto-delivered-row" style="display: none;">
                <span class="badge bg-info text-dark" style="font-size:.75rem;">
                    <i class="fa fa-robot me-1"></i> Auto-delivered
                </span>
                <small class="text-muted ms-1" id="oc-d-auto-delivered-at"></small>
            </div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button class="btn btn-success btn-sm" id="oc-drawer-done" title="Close this drawer and refresh the list">
                <i class="fa fa-check me-1"></i> Done
            </button>
            <button class="btn-close" id="oc-drawer-close"></button>
        </div>
    </div>

    <div class="oc-drawer-tabs">
        <button class="active" data-section="items"><i class="fa fa-box me-1"></i> Items</button>
        <button data-section="payment"><i class="fa fa-credit-card me-1"></i> Payment</button>
        <button data-section="edit"><i class="fa fa-pencil me-1"></i> Edit</button>
        <button data-section="notes"><i class="fa fa-sticky-note me-1"></i> Notes <span class="badge badge-soft-secondary ms-1" id="oc-d-notes-count">0</span></button>
        <button data-section="timeline"><i class="fa fa-history me-1"></i> Timeline</button>
        <button data-section="tags"><i class="fa fa-tags me-1"></i> Tags</button>
        <button data-section="actions"><i class="fa fa-bolt me-1"></i> Actions</button>
    </div>

    {{-- Customer card (always shown above sections) --}}
    <div style="padding: 1rem 1.25rem 0;">
        <div class="oc-customer-card">
            <img id="oc-d-cust-avatar" src="">
            <div>
                <div class="fw-bold text-dark oc-customer-link" id="oc-d-cust-name" title="View customer profile">--</div>
                <div class="small"><a href="#" id="oc-d-cust-email" class="text-decoration-none">--</a></div>
                <div class="small text-muted" id="oc-d-cust-phone">--</div>
            </div>
        </div>

        {{-- Delivery details card — populated by JS with full address + contact + delivery method --}}
        <div class="oc-delivery-card" id="oc-d-delivery-card">
            <div class="dc-header"><i class="fa fa-truck me-1"></i> Delivery Address</div>
            <div class="dc-address" id="oc-d-delivery-address">—</div>
            <div class="dc-contact" id="oc-d-delivery-contact"></div>
            <div class="dc-method" id="oc-d-delivery-method"></div>
            <div class="dc-date" id="oc-d-delivery-date"></div>
        </div>

        {{-- WooCommerce origin banner (Phase 7) — populated by drawer load if source=woocommerce --}}
        <div class="alert alert-light border-start border-primary border-3 small d-none mb-3" id="oc-d-wc-banner">
            <div class="d-flex align-items-center justify-content-between gap-2">
                <div>
                    <i class="fa fa-globe text-primary me-1"></i>
                    <strong>From WooCommerce</strong>
                    <span class="text-muted">— WC #<span id="oc-d-wc-id">—</span></span>
                </div>
                <div class="d-flex gap-1">
                    <a href="#" target="_blank" class="btn btn-sm btn-outline-primary" id="oc-d-wc-link" title="Open in WP admin">
                        <i class="fa fa-external-link-alt me-1"></i> Open
                    </a>
                    <button type="button" class="btn btn-sm btn-outline-success d-none" id="oc-d-wc-push" title="Push current status back to WooCommerce">
                        <i class="fa fa-cloud-upload-alt me-1"></i> Push
                    </button>
                </div>
            </div>
            <div class="text-muted small mt-1" id="oc-d-wc-push-hint">
                Read-only mode. Enable "Sync enabled" in Settings to push updates.
            </div>
        </div>
    </div>

    {{-- Items section --}}
    <div class="oc-drawer-section active" data-section="items">
        <div id="oc-d-items"></div>

        {{-- Totals — professional summary card design with icons & gradient grand total --}}
        <div class="oc-totals-card">
            <div class="label-row">
                <span class="h-label"><i class="fa fa-receipt me-1"></i> Order Summary</span>
                <span class="h-meta" id="oc-d-totals-meta">—</span>
            </div>
            <div class="oc-totals-row">
                <span class="label-l"><i class="fa fa-shopping-basket"></i> Subtotal</span>
                <span class="val" id="oc-d-subtotal">$0.00</span>
            </div>
            <div class="oc-totals-row">
                <span class="label-l"><i class="fa fa-truck"></i> Delivery</span>
                <span class="val" id="oc-d-delivery">$0.00</span>
            </div>
            <div class="oc-totals-row discount" id="oc-d-discount-row" style="display: none;">
                <span class="label-l"><i class="fa fa-tag"></i> Discount</span>
                <span class="val" id="oc-d-discount">-$0.00</span>
            </div>
            <div class="oc-totals-row" id="oc-d-tax-row" style="display: none;">
                <span class="label-l"><i class="fa fa-percent"></i> Tax</span>
                <span class="val" id="oc-d-tax">$0.00</span>
            </div>
            <div class="oc-totals-row refund" id="oc-d-refund-row" style="display: none;">
                <span class="label-l"><i class="fa fa-undo"></i> Refunded</span>
                <span class="val" id="oc-d-refunded">-$0.00</span>
            </div>
            <div class="oc-totals-row grand">
                <span class="label-l">Total</span>
                <span class="val" id="oc-d-total">$0.00</span>
            </div>
        </div>
    </div>

    {{-- Payment section — shows payment record + cancel/refund actions
         that delegate to the EXISTING legacy endpoints (/order/{id}/refund,
         /order/{id}/cancel). No new controller methods needed — we reuse
         what's already tested and live. --}}
    <div class="oc-drawer-section" data-section="payment">
        <div id="oc-d-payment-info"></div>

        <div id="oc-d-payment-actions" class="mt-3"></div>
    </div>

    {{-- Edit section (full-form manual edit, mirrors legacy /order/{id}/edit) --}}
    <div class="oc-drawer-section" data-section="edit">
        <div class="alert alert-light border small mb-3">
            <i class="fa fa-info-circle me-1"></i>
            Edit any field below and click <strong>Save Changes</strong>. Changes are logged to the timeline and fire the same events the legacy edit form does.
        </div>
        <form id="oc-d-edit-form">
            <div class="row g-2 mb-2">
                <div class="col-12">
                    <label class="form-label small mb-1">Order Number</label>
                    <input type="text" class="form-control form-control-sm" id="oc-d-edit-order-number" disabled>
                </div>
                <div class="col-12">
                    <label class="form-label small mb-1">Customer</label>
                    <input type="text" class="form-control form-control-sm" id="oc-d-edit-customer" disabled>
                </div>
            </div>

            <hr>

            <div class="row g-2 mb-2">
                <div class="col-6">
                    <label class="form-label small mb-1">Subtotal</label>
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm" id="oc-d-edit-subtotal">
                </div>
                <div class="col-6">
                    <label class="form-label small mb-1">Delivery Fee</label>
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm" id="oc-d-edit-delivery-fee">
                </div>
                <div class="col-6">
                    <label class="form-label small mb-1">Discount</label>
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm" id="oc-d-edit-discount">
                </div>
                <div class="col-6">
                    <label class="form-label small mb-1">Tax</label>
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm" id="oc-d-edit-tax">
                </div>
                <div class="col-12">
                    <label class="form-label small mb-1">Total</label>
                    <input type="number" step="0.01" min="0" class="form-control form-control-sm fw-bold" id="oc-d-edit-total">
                    <button type="button" class="btn btn-link btn-sm p-0 mt-1" id="oc-d-edit-recalc">
                        <i class="fa fa-calculator me-1"></i>Recalculate from sub + delivery + tax − discount
                    </button>
                </div>
            </div>

            <hr>

            <div class="row g-2 mb-2">
                <div class="col-6">
                    <label class="form-label small mb-1">Order Status</label>
                    <select class="form-select form-select-sm" id="oc-d-edit-order-status">
                        <option value="pending">Pending</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="processing">Processing</option>
                        <option value="shipped">Shipped / Out for Delivery</option>
                        <option value="delivered">Delivered</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                <div class="col-6">
                    <label class="form-label small mb-1">Payment Status</label>
                    <select class="form-select form-select-sm" id="oc-d-edit-payment-status">
                        <option value="pending">Pending</option>
                        <option value="paid">Paid</option>
                        <option value="failed">Failed</option>
                        <option value="refunded">Refunded</option>
                        <option value="partially_refunded">Partially Refunded</option>
                    </select>
                </div>
                <div class="col-12">
                    <label class="form-label small mb-1">Preferred Delivery Date</label>
                    <input type="date" class="form-control form-control-sm" id="oc-d-edit-delivery-date">
                </div>
                <div class="col-12">
                    <label class="form-label small mb-1">Order Notes (customer-visible)</label>
                    <textarea class="form-control form-control-sm" id="oc-d-edit-notes" rows="3" placeholder="(optional)"></textarea>
                </div>
            </div>

            <button type="button" class="btn btn-success w-100 mt-2" id="oc-d-edit-save">
                <i class="fa fa-save me-1"></i> Save Changes
            </button>
        </form>
    </div>

    {{-- Notes section --}}
    <div class="oc-drawer-section" data-section="notes">
        <textarea class="form-control mb-2" id="oc-d-note-input" rows="3" placeholder="Add a staff-only note…"></textarea>
        <button class="btn btn-primary btn-sm w-100 mb-3" id="oc-d-note-add">Post Note</button>
        <div id="oc-d-notes-list"></div>
    </div>

    {{-- Timeline section --}}
    <div class="oc-drawer-section" data-section="timeline">
        <div id="oc-d-timeline-list"></div>
    </div>

    {{-- Tags section --}}
    <div class="oc-drawer-section" data-section="tags">
        <div class="text-muted small mb-2">Click a tag to attach. Click ✕ on an attached tag to remove it.</div>
        <div class="mb-3">
            <strong class="d-block small mb-1">Attached</strong>
            <div class="oc-tag-input" id="oc-d-tags-attached"></div>
        </div>
        <div>
            <strong class="d-block small mb-1">Available</strong>
            <div class="oc-tag-input" id="oc-d-tags-available"></div>
        </div>
    </div>

    {{-- Quick Actions section --}}
    <div class="oc-drawer-section" data-section="actions">
        <div class="oc-quick-actions">
            <div>
                <label class="form-label">Order Status</label>
                <select class="form-select form-select-sm" id="oc-d-act-status">
                    <option value="pending">Pending</option>
                    <option value="confirmed">Confirmed</option>
                    <option value="processing">Processing</option>
                    <option value="shipped">Shipped</option>
                    <option value="delivered">Delivered</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            <div>
                <label class="form-label">&nbsp;</label>
                <button class="btn btn-primary btn-sm w-100" id="oc-d-act-status-btn">Update Status</button>
            </div>

            <div>
                <label class="form-label">Payment Status</label>
                <select class="form-select form-select-sm" id="oc-d-act-payment">
                    <option value="pending">Pending</option>
                    <option value="paid">Paid</option>
                    <option value="failed">Failed</option>
                </select>
            </div>
            <div>
                <label class="form-label">&nbsp;</label>
                <button class="btn btn-success btn-sm w-100" id="oc-d-act-payment-btn">Update Payment</button>
            </div>

            <div>
                <label class="form-label">Delivery Date</label>
                <input type="date" class="form-control form-control-sm" id="oc-d-act-date">
            </div>
            <div>
                <label class="form-label">&nbsp;</label>
                <button class="btn btn-info btn-sm w-100" id="oc-d-act-date-btn">Reschedule</button>
            </div>
        </div>

        <hr>
        <div class="d-grid gap-2">
            <a href="#" class="btn btn-outline-primary btn-sm" id="oc-d-act-edit-link">
                <i class="fa fa-pencil me-1"></i> Open Full Edit Form (legacy)
            </a>
        </div>
    </div>

</div>

{{-- =============================================================
     PHASE 3: BULK-ACTION MODALS
     ============================================================= --}}

{{-- Combined Status + Payment modal (replaces the previous two separate modals) --}}
<div class="modal fade" id="oc-modal-update" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header" style="background: linear-gradient(135deg,#667eea,#764ba2); color: #fff;">
        <h5 class="modal-title"><i class="fa fa-pencil-alt me-2"></i> Bulk Update Status &amp; Payment</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="mb-3">Updating <strong><span class="oc-modal-count">0</span> order(s)</strong>. Leave a field on <em>"— No change —"</em> to skip it.</p>

        <div class="mb-3">
          <label class="form-label">Order Status</label>
          <select id="oc-modal-update-status" class="form-select">
            <option value="">— No change —</option>
            <option value="pending">Pending</option>
            <option value="confirmed">Confirmed</option>
            <option value="processing">Processing</option>
            <option value="shipped">Shipped / Out for Delivery</option>
            <option value="delivered">Delivered</option>
            <option value="cancelled">Cancelled</option>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label">Payment Status</label>
          <select id="oc-modal-update-payment" class="form-select">
            <option value="">— No change —</option>
            <option value="pending">Pending</option>
            <option value="paid">Paid</option>
            <option value="failed">Failed</option>
            <option value="refunded">Refunded</option>
            <option value="partially_refunded">Partially Refunded</option>
          </select>
        </div>

        {{-- Conditional: appears when "Delivered" is picked above. Saves
             the admin from a separate payment-status edit. --}}
        <div class="mb-3 d-none" id="oc-modal-also-paid-wrap">
          <div class="alert alert-info small mb-2">
            <i class="fa fa-info-circle me-1"></i>
            You're marking orders as <strong>delivered</strong>. If the customer paid on delivery (most common case),
            we should also mark them as <strong>paid</strong> so they receive their loyalty points and giveaway entry.
          </div>
          <div class="form-check form-switch">
            <input class="form-check-input" type="checkbox" id="oc-modal-also-paid" checked>
            <label class="form-check-label" for="oc-modal-also-paid">
              <strong>Also mark as paid</strong> (recommended)
            </label>
          </div>
          <div class="form-text">Only applies to orders whose payment is currently pending — orders already paid are unchanged. Loyalty points and giveaway entries will be awarded automatically.</div>
        </div>

        <div class="alert alert-warning small mb-0">
          <i class="fa fa-info-circle me-1"></i>
          Both changes are logged to each order's audit trail. Setting <strong>cancelled</strong> or <strong>delivered</strong> fires the relevant system events (refund flows, customer emails). Marking <strong>paid</strong> auto-promotes pending orders to <em>processing</em>.
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" id="oc-modal-update-submit">
          <i class="fa fa-check me-1"></i> Apply
        </button>
      </div>
    </div>
  </div>
</div>

{{-- Mark Delivered + Paid? modal (single order) --}}
<div class="modal fade" id="oc-modal-deliver-paid" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header" style="background: linear-gradient(135deg,#667eea,#764ba2); color: #fff;">
        <h5 class="modal-title"><i class="fa fa-check-circle me-2"></i> Mark Delivered</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="mb-3">Marking order <strong id="oc-deliver-paid-order-number">#</strong> as delivered.</p>
        <div class="alert alert-warning small mb-3">
          <i class="fa fa-exclamation-triangle me-1"></i>
          This order's payment is currently <strong>pending</strong>.
          If the customer paid on delivery (most common case for COD or bank transfer), tick the box below so they receive their loyalty points and giveaway entry.
        </div>
        <div class="form-check form-switch">
          <input class="form-check-input" type="checkbox" id="oc-deliver-paid-also" checked>
          <label class="form-check-label" for="oc-deliver-paid-also">
            <strong>Also mark this order as paid</strong> (recommended)
          </label>
        </div>
        <div class="form-text">Leave unchecked only if you've delivered on credit or are awaiting payment. You can flip payment to paid later via the order detail page.</div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-success" id="oc-deliver-paid-submit">
          <i class="fa fa-check me-1"></i> Confirm
        </button>
      </div>
    </div>
  </div>
</div>

{{-- Reschedule modal --}}
<div class="modal fade" id="oc-modal-reschedule" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header" style="background: linear-gradient(135deg,#667eea,#764ba2); color: #fff;">
        <h5 class="modal-title"><i class="fa fa-calendar me-2"></i> Bulk Reschedule Delivery Date</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="mb-3">Rescheduling <strong><span class="oc-modal-count">0</span> order(s)</strong>.</p>
        <label class="form-label">New Delivery Date</label>
        <input type="date" id="oc-modal-reschedule-value" class="form-control">
        <div class="form-text">All selected orders will share this delivery date.</div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" id="oc-modal-reschedule-submit">
          <i class="fa fa-check me-1"></i> Apply
        </button>
      </div>
    </div>
  </div>
</div>

{{-- Notify modal --}}
<div class="modal fade" id="oc-modal-notify" tabindex="-1">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <div class="modal-header" style="background: linear-gradient(135deg,#667eea,#764ba2); color: #fff;">
        <h5 class="modal-title"><i class="fa fa-envelope me-2"></i> Email Selected Customers</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p class="mb-3">Sending an email to <strong><span class="oc-modal-count">0</span> customer(s)</strong>.</p>
        <div class="mb-3">
          <label class="form-label">Subject</label>
          <input type="text" id="oc-modal-notify-subject" class="form-control" maxlength="200" placeholder="Update on your firewood order">
        </div>
        <div class="mb-2">
          <label class="form-label">Message</label>
          <textarea id="oc-modal-notify-body" class="form-control" rows="8" maxlength="5000" placeholder="Hi {customer_name},&#10;&#10;Your order #{order_number} is scheduled for delivery on {delivery_date}…"></textarea>
        </div>
        <div class="small text-muted">
          <strong>Available placeholders:</strong>
          <code>{customer_name}</code>
          <code>{order_number}</code>
          <code>{order_total}</code>
          <code>{delivery_date}</code>
        </div>
        <div class="alert alert-warning mt-3 small mb-0">
          <i class="fa fa-info-circle me-1"></i>
          Orders without a customer email address will be skipped. Each send is recorded in the order's audit trail.
        </div>
      </div>
      <div class="modal-footer">
        <button class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
        <button class="btn btn-primary" id="oc-modal-notify-submit">
          <i class="fa fa-paper-plane me-1"></i> Send Emails
        </button>
      </div>
    </div>
  </div>
</div>

{{-- Generic toast container --}}
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11000;">
  <div id="oc-toast" class="toast align-items-center text-bg-success border-0" role="alert">
    <div class="d-flex">
      <div class="toast-body" id="oc-toast-body">Done.</div>
      <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
    </div>
  </div>
</div>

{{-- =============== Phase 10: WC ORDER DRAWER (separate from native drawer) =============== --}}
<div class="oc-drawer-overlay" id="oc-wc-drawer-overlay"></div>
<div class="oc-drawer" id="oc-wc-drawer">
    <div class="oc-drawer-header">
        <div>
            <h5 class="fw-bold mb-1" id="oc-wcd-number">#WC0000</h5>
            <small class="text-muted" id="oc-wcd-created"></small>
            <div class="mt-1">
                <span class="badge" id="oc-wcd-status-badge">—</span>
                <span class="badge ms-1" id="oc-wcd-payment-badge">—</span>
                <span class="badge bg-light text-dark ms-1"><i class="fa fa-globe me-1"></i>WooCommerce</span>
            </div>
        </div>
        <button class="btn-close" id="oc-wc-drawer-close"></button>
    </div>

    <div class="oc-drawer-body">
        {{-- Customer card (clickable → WC customer profile) --}}
        <div class="oc-customer-card mb-2" style="cursor: pointer;" id="oc-wcd-customer">
            <div style="width:48px; height:48px; border-radius:50%; background:#e0e7ff; color:#667eea; font-weight:bold; font-size:1.2rem; display:flex; align-items:center; justify-content:center;" id="oc-wcd-cust-initials">?</div>
            <div>
                <div class="fw-bold text-dark oc-customer-link" id="oc-wcd-cust-name" title="View customer profile">--</div>
                <div class="small"><a href="#" id="oc-wcd-cust-email" class="text-decoration-none">--</a></div>
                <div class="small text-muted" id="oc-wcd-cust-phone">--</div>
            </div>
        </div>
        <div class="text-muted small mb-3" id="oc-wcd-address"><i class="fa fa-map-marker-alt me-1"></i>--</div>

        {{-- WP admin link banner --}}
        <div class="alert alert-light border-start border-primary border-3 small d-flex align-items-center justify-content-between mb-3">
            <div>
                <i class="fa fa-link text-primary me-1"></i>
                <span class="text-muted">Last synced <span id="oc-wcd-last-sync">—</span></span>
            </div>
            <a href="#" target="_blank" class="btn btn-sm btn-outline-primary" id="oc-wcd-wp-link">
                <i class="fa fa-external-link-alt me-1"></i> Open in WP Admin
            </a>
        </div>
    </div>

    {{-- Tabs --}}
    <div class="oc-drawer-tabs">
        <button class="active" data-section="items"><i class="fa fa-box me-1"></i> Items</button>
        <button data-section="edit"><i class="fa fa-pencil me-1"></i> Edit</button>
        <button data-section="notes"><i class="fa fa-sticky-note me-1"></i> Notes <span class="badge badge-soft-secondary ms-1" id="oc-wcd-notes-count">0</span></button>
        <button data-section="timeline"><i class="fa fa-history me-1"></i> Timeline</button>
        <button data-section="tags"><i class="fa fa-tags me-1"></i> Tags</button>
        <button data-section="refunds"><i class="fa fa-undo me-1"></i> Refunds <span class="badge badge-soft-secondary ms-1" id="oc-wcd-refunds-count">0</span></button>
    </div>

    {{-- Items + Totals --}}
    <div class="oc-drawer-section active" data-section="items">
        <div id="oc-wcd-items"></div>
        <div style="padding: 0.75rem 0; border-top: 1px solid #e5e7eb; margin-top: 0.5rem;">
            <div class="oc-totals-row"><span class="text-muted">Subtotal</span><span id="oc-wcd-subtotal">$0.00</span></div>
            <div class="oc-totals-row"><span class="text-muted">Shipping</span><span id="oc-wcd-shipping">$0.00</span></div>
            <div class="oc-totals-row"><span class="text-muted">Tax</span><span id="oc-wcd-tax">$0.00</span></div>
            <div class="oc-totals-row"><span class="text-muted">Discount</span><span class="text-success" id="oc-wcd-discount">−$0.00</span></div>
            <div class="oc-totals-row fw-bold" style="border-top: 1px solid #e5e7eb; padding-top: 0.5rem;">
                <span>Total</span><span id="oc-wcd-total">$0.00</span>
            </div>
            <div class="oc-totals-row text-danger d-none" id="oc-wcd-refund-row">
                <span>Refunded</span><span id="oc-wcd-refunded">−$0.00</span>
            </div>
        </div>
    </div>

    {{-- Edit (status, dates, addresses — within WC API limits) --}}
    <div class="oc-drawer-section" data-section="edit">
        <div class="alert alert-light border small mb-3">
            <i class="fa fa-info-circle me-1"></i>
            All edits within the WC REST API spec. If write mode is on, changes are pushed to WooCommerce live.
            Otherwise they're saved locally and overwritten on the next sync.
        </div>
        <form id="oc-wcd-edit-form">

            {{-- Order details --}}
            <div class="card border-0 bg-light mb-3">
                <div class="card-body p-3">
                    <h6 class="small fw-bold text-uppercase text-muted mb-2">Order</h6>
                    <div class="mb-2">
                        <label class="form-label small mb-1">WC Status</label>
                        <select class="form-select form-select-sm" id="oc-wcd-edit-status">
                            <option value="pending">Pending</option>
                            <option value="on-hold">On hold</option>
                            <option value="processing">Processing</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                            <option value="refunded">Refunded</option>
                            <option value="failed">Failed</option>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small mb-1">Preferred Delivery Date <span class="text-muted">(local)</span></label>
                        <input type="date" class="form-control form-control-sm" id="oc-wcd-edit-delivery">
                    </div>
                    <div class="mb-0">
                        <label class="form-label small mb-1">Customer Note</label>
                        <textarea class="form-control form-control-sm" id="oc-wcd-edit-note" rows="2"></textarea>
                    </div>
                </div>
            </div>

            {{-- Billing address (also has email + phone) --}}
            <div class="card border-0 bg-light mb-3">
                <div class="card-body p-3">
                    <h6 class="small fw-bold text-uppercase text-muted mb-2">Billing</h6>
                    <div class="row g-2 mb-2">
                        <div class="col-6"><input type="text" class="form-control form-control-sm" id="oc-wcd-edit-bill-first" placeholder="First name"></div>
                        <div class="col-6"><input type="text" class="form-control form-control-sm" id="oc-wcd-edit-bill-last" placeholder="Last name"></div>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-7"><input type="email" class="form-control form-control-sm" id="oc-wcd-edit-bill-email" placeholder="Email"></div>
                        <div class="col-5"><input type="text" class="form-control form-control-sm" id="oc-wcd-edit-bill-phone" placeholder="Phone"></div>
                    </div>
                    <div class="mb-2"><input type="text" class="form-control form-control-sm" id="oc-wcd-edit-bill-addr1" placeholder="Address line 1"></div>
                    <div class="mb-2"><input type="text" class="form-control form-control-sm" id="oc-wcd-edit-bill-addr2" placeholder="Address line 2"></div>
                    <div class="row g-2 mb-0">
                        <div class="col-5"><input type="text" class="form-control form-control-sm" id="oc-wcd-edit-bill-city" placeholder="Suburb / city"></div>
                        <div class="col-3"><input type="text" class="form-control form-control-sm" id="oc-wcd-edit-bill-state" placeholder="State" maxlength="10"></div>
                        <div class="col-2"><input type="text" class="form-control form-control-sm" id="oc-wcd-edit-bill-postcode" placeholder="Postcode" maxlength="10"></div>
                        <div class="col-2"><input type="text" class="form-control form-control-sm" id="oc-wcd-edit-bill-country" placeholder="AU" maxlength="3"></div>
                    </div>
                </div>
            </div>

            {{-- Shipping address --}}
            <div class="card border-0 bg-light mb-3">
                <div class="card-body p-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="small fw-bold text-uppercase text-muted mb-0">Shipping</h6>
                        <button type="button" class="btn btn-link btn-sm p-0" id="oc-wcd-edit-copy-billing" title="Copy billing → shipping">
                            <i class="fa fa-copy"></i> Same as billing
                        </button>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6"><input type="text" class="form-control form-control-sm" id="oc-wcd-edit-ship-first" placeholder="First name"></div>
                        <div class="col-6"><input type="text" class="form-control form-control-sm" id="oc-wcd-edit-ship-last" placeholder="Last name"></div>
                    </div>
                    <div class="mb-2"><input type="text" class="form-control form-control-sm" id="oc-wcd-edit-ship-addr1" placeholder="Address line 1"></div>
                    <div class="mb-2"><input type="text" class="form-control form-control-sm" id="oc-wcd-edit-ship-addr2" placeholder="Address line 2"></div>
                    <div class="row g-2 mb-0">
                        <div class="col-5"><input type="text" class="form-control form-control-sm" id="oc-wcd-edit-ship-city" placeholder="Suburb / city"></div>
                        <div class="col-3"><input type="text" class="form-control form-control-sm" id="oc-wcd-edit-ship-state" placeholder="State" maxlength="10"></div>
                        <div class="col-2"><input type="text" class="form-control form-control-sm" id="oc-wcd-edit-ship-postcode" placeholder="Postcode" maxlength="10"></div>
                        <div class="col-2"><input type="text" class="form-control form-control-sm" id="oc-wcd-edit-ship-country" placeholder="AU" maxlength="3"></div>
                    </div>
                </div>
            </div>

            <button type="button" class="btn btn-success w-100" id="oc-wcd-edit-save">
                <i class="fa fa-save me-1"></i> Save Changes
            </button>
            <div class="text-muted small text-center mt-2" id="oc-wcd-edit-push-hint"></div>
        </form>
    </div>

    {{-- Notes --}}
    <div class="oc-drawer-section" data-section="notes">
        <textarea class="form-control mb-2" id="oc-wcd-note-input" rows="3" placeholder="Add a staff-only note…"></textarea>
        <button class="btn btn-primary btn-sm w-100 mb-3" id="oc-wcd-note-add">Post Note</button>
        <div id="oc-wcd-notes-list"></div>
    </div>

    {{-- Timeline --}}
    <div class="oc-drawer-section" data-section="timeline">
        <div id="oc-wcd-timeline-list"></div>
    </div>

    {{-- Tags --}}
    <div class="oc-drawer-section" data-section="tags">
        <div class="small text-muted mb-2">Click a tag to attach. Click ✕ on an attached tag to remove it.</div>
        <div class="mb-3">
            <strong class="small">Attached</strong>
            <div id="oc-wcd-tags-attached" class="mt-1">
                <span class="text-muted small">None attached.</span>
            </div>
        </div>
        <div>
            <strong class="small">Available</strong>
            <div id="oc-wcd-tags-available" class="mt-1"></div>
        </div>
    </div>

    {{-- Refunds --}}
    <div class="oc-drawer-section" data-section="refunds">
        <div class="alert alert-light border small mb-3">
            <i class="fa fa-info-circle me-1"></i>
            Refunds use the WooCommerce REST API and are processed through the original payment gateway (Stripe / PayPal / etc.). Write mode must be enabled in Settings.
        </div>
        <div class="card border-0 bg-light mb-3">
            <div class="card-body p-3">
                <div class="d-flex justify-content-between small">
                    <span class="text-muted">Order Total</span><span id="oc-wcd-rf-total">$0.00</span>
                </div>
                <div class="d-flex justify-content-between small">
                    <span class="text-muted">Already Refunded</span><span class="text-danger" id="oc-wcd-rf-refunded">$0.00</span>
                </div>
                <div class="d-flex justify-content-between small fw-bold">
                    <span>Remaining</span><span class="text-success" id="oc-wcd-rf-remaining">$0.00</span>
                </div>
            </div>
        </div>
        <button type="button" class="btn btn-danger w-100 mb-3" id="oc-wcd-rf-new" disabled>
            <i class="fa fa-undo me-1"></i> Issue Refund
        </button>
        <div id="oc-wcd-rf-history"></div>
    </div>
</div>

{{-- =============== Phase 10: WC REFUND MODAL =============== --}}
<div class="modal fade" id="oc-wc-refund-modal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg,#dc2626,#ef4444); color: white;">
                <h5 class="modal-title"><i class="fa fa-undo me-2"></i> Issue WooCommerce Refund</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-warning small mb-3">
                    <i class="fa fa-exclamation-triangle me-1"></i>
                    This calls the WooCommerce API and processes the refund through the original payment gateway. The refund is real and will not be undoable from this panel.
                </div>
                <div class="mb-3">
                    <label class="form-label small">Order</label>
                    <div class="form-control-plaintext fw-semibold" id="oc-wc-rf-modal-order">—</div>
                </div>
                <div class="row g-2 mb-3">
                    <div class="col-6">
                        <label class="form-label small">Total</label>
                        <div class="form-control-plaintext" id="oc-wc-rf-modal-total">$0.00</div>
                    </div>
                    <div class="col-6">
                        <label class="form-label small">Remaining</label>
                        <div class="form-control-plaintext fw-bold text-success" id="oc-wc-rf-modal-remaining">$0.00</div>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Refund Amount</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" step="0.01" min="0.01" class="form-control" id="oc-wc-rf-modal-amount">
                        <button type="button" class="btn btn-outline-secondary" id="oc-wc-rf-modal-fill-max">Use Max</button>
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label">Reason <span class="text-muted small">(optional, sent to WC)</span></label>
                    <textarea class="form-control" id="oc-wc-rf-modal-reason" rows="2" maxlength="500" placeholder="e.g. Customer reported wrong product delivered"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-danger" id="oc-wc-rf-modal-submit">
                    <i class="fa fa-check me-1"></i> Process Refund
                </button>
            </div>
        </div>
    </div>
</div>

{{-- =============== Phase 9: TODAY'S DELIVERIES FLOATING WIDGET =============== --}}
<button class="oc-today-fab" id="oc-today-fab" title="Today's deliveries">
    <i class="fa fa-truck"></i>
    <span class="oc-today-count d-none" id="oc-today-count">0</span>
</button>

<div class="oc-today-panel" id="oc-today-panel">
    <div class="oc-today-panel-header">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h6 class="mb-0"><i class="fa fa-truck me-2"></i> Today's Deliveries</h6>
                <small id="oc-today-date" style="opacity: 0.85;">—</small>
            </div>
            <button type="button" class="btn btn-sm text-white" id="oc-today-close" style="background: rgba(255,255,255,0.15);">
                <i class="fa fa-times"></i>
            </button>
        </div>
    </div>
    <div class="oc-today-panel-body" id="oc-today-list">
        <div class="oc-empty">Loading…</div>
    </div>
</div>

{{-- =============== Phase 9: CUSTOMER PROFILE MODAL =============== --}}
<div class="modal fade" id="oc-customer-modal" tabindex="-1">
  <div class="modal-dialog modal-xl modal-dialog-scrollable modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header" style="background: linear-gradient(135deg,#667eea,#764ba2); color: white; padding: 1.25rem 1.5rem;">
        <h4 class="modal-title mb-0" style="font-size: 1.4rem;"><i class="fa fa-user me-2"></i> Customer Profile</h4>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body oc-cust-modal-body" id="oc-customer-modal-body" style="padding: 1.75rem 1.75rem; font-size: 1rem;">
        <div class="text-center text-muted py-5"><i class="fa fa-spinner fa-spin"></i> Loading…</div>
      </div>
      <div class="modal-footer" style="padding: 1rem 1.5rem;">
        <a href="#" target="_blank" class="btn btn-outline-primary" id="oc-customer-full-link">
            <i class="fa fa-external-link-alt me-1"></i> Open Full Customer Page
        </a>
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<style>
    /* Customer profile modal — bigger text + spacing for readability on large screens */
    .oc-cust-modal-body .h-customer-name { font-size: 1.6rem; font-weight: 700; line-height: 1.2; margin-bottom: .35rem; }
    .oc-cust-modal-body .h-customer-contact { font-size: 1rem; color: #555; }
    .oc-cust-modal-body .h-customer-contact i { width: 18px; }
    .oc-cust-modal-body .stat-card { border-radius: 10px; padding: 1.1rem .75rem; text-align: center; background: #f8f9fa; }
    .oc-cust-modal-body .stat-card .label { font-size: .75rem; text-transform: uppercase; color: #6c757d; letter-spacing: .5px; margin-bottom: .25rem; }
    .oc-cust-modal-body .stat-card .value { font-size: 1.8rem; font-weight: 700; line-height: 1; }
    .oc-cust-modal-body .section-h { font-size: 1.05rem; font-weight: 600; margin: 1.5rem 0 .75rem; color: #1a1a1a; border-bottom: 1px solid #e5e7eb; padding-bottom: .5rem; }
    .oc-cust-modal-body .section-h i { color: #667eea; }
    .oc-cust-modal-body .addr-card { border: 1px solid #e5e7eb; border-radius: 8px; padding: .85rem 1rem; height: 100%; }
    .oc-cust-modal-body .addr-card.is-default { border-color: #667eea; background: linear-gradient(to bottom right, #f5f7ff, #fff); }
    .oc-cust-modal-body .addr-card .addr-label { font-size: .8rem; font-weight: 700; text-transform: uppercase; color: #667eea; letter-spacing: .5px; }
    .oc-cust-modal-body .addr-card .addr-lines { font-size: 1rem; line-height: 1.5; margin-top: .25rem; }
    .oc-cust-modal-body .addr-card .addr-contact { font-size: .9rem; color: #555; margin-top: .5rem; padding-top: .5rem; border-top: 1px dashed #e5e7eb; }
    .oc-cust-modal-body .addr-card .addr-contact i { color: #888; }
    .oc-cust-modal-body table.oc-recent { font-size: .95rem; }
    .oc-cust-modal-body table.oc-recent th { font-size: .8rem; text-transform: uppercase; color: #6c757d; font-weight: 600; }
    .oc-cust-modal-body table.oc-recent td { vertical-align: middle; }
    .oc-cust-modal-body table.oc-recent a { font-weight: 600; font-size: 1rem; }
</style>

@endsection

@push('scripts')
<meta name="csrf-token" content="{{ csrf_token() }}">
<script>
$(function () {
    'use strict';
    var csrfToken = $('meta[name="csrf-token"]').attr('content');
    $.ajaxSetup({ headers: { 'X-CSRF-TOKEN': csrfToken } });

    // -------- Status / Payment badge maps --------
    var statusBadges = {
        pending:    '<span class="badge badge-soft-warning">Pending</span>',
        confirmed:  '<span class="badge badge-soft-primary">Confirmed</span>',
        processing: '<span class="badge badge-soft-info">Processing</span>',
        shipped:    '<span class="badge badge-soft-secondary">Shipped</span>',
        delivered:  '<span class="badge badge-soft-success">Delivered</span>',
        cancelled:  '<span class="badge badge-soft-danger">Cancelled</span>'
    };
    var paymentBadges = {
        pending: '<span class="badge badge-soft-warning">Pending</span>',
        paid:    '<span class="badge badge-soft-success">Paid</span>',
        failed:  '<span class="badge badge-soft-danger">Failed</span>',
        refunded: '<span class="badge badge-soft-danger">Refunded</span>',
        partially_refunded: '<span class="badge badge-soft-info">Partial Refund</span>'
    };

    // -------- Live-refresh stat cards every 60s --------
    function refreshStats() {
        $.get('{{ route('order-centre.stats') }}').done(function (d) {
            $('#stat-today-orders').text(d.today_orders);
            $('#stat-today-revenue').text('$' + Number(d.today_revenue).toFixed(2));
            $('#stat-needs-action').text(d.needs_action);
            $('#stat-overdue').text(d.overdue);
        });
    }
    setInterval(refreshStats, 60000);

    // -------- All Orders DataTable --------
    var selected = new Set();

    var table = $('#oc-orders-table').DataTable({
        processing: true,
        serverSide: true,
        dom: 'rtip',
        pageLength: 25,
        autoWidth: false,
        ajax: {
            url: '{{ route('order-centre.data.all-orders') }}',
            data: function (d) {
                d.search_text     = $('#oc-search').val();
                d.date_from       = $('#oc-date-from').val();
                d.date_to         = $('#oc-date-to').val();
                d.value_min       = $('#oc-value-min').val();
                d.value_max       = $('#oc-value-max').val();
                d.suburb          = $('#oc-suburb').val();
                d.source          = $('#oc-source').val();
                d.order_status    = $('.oc-filter-status:checked').map(function(){return this.value;}).get().join(',');
                d.payment_status  = $('.oc-filter-payment:checked').map(function(){return this.value;}).get().join(',');
                d.tags            = $('.oc-filter-tag:checked').map(function(){return this.value;}).get().join(',');
            },
            dataSrc: function (json) {
                // Diagnostic: log first response to console for visibility
                if (!window._ocFirstResponseLogged) {
                    console.log('[Order Centre] First all-orders response:', json);
                    window._ocFirstResponseLogged = true;
                }
                if (json && json.error) {
                    console.error('[Order Centre] Server error:', json.error);
                    $('#oc-result-summary').html('<span class="text-danger"><i class="fa fa-exclamation-triangle me-1"></i> Error loading orders. Check browser console.</span>');
                }
                return (json && json.data) ? json.data : [];
            },
            error: function (xhr, status, err) {
                console.error('[Order Centre] AJAX error:', status, err, xhr.responseText);
                $('#oc-result-summary').html('<span class="text-danger"><i class="fa fa-exclamation-triangle me-1"></i> Failed to load orders (' + status + '). Open browser console for details.</span>');
            }
        },
        order: [],
        columns: [
            {
                data: 'id', orderable: false, searchable: false,
                render: function (id) {
                    var checked = selected.has(String(id)) ? 'checked' : '';
                    return '<input type="checkbox" class="oc-row-check" value="' + id + '" ' + checked + '>';
                }
            },
            { data: 'order_number', render: function (d) { return '<span class="fw-bold text-primary">#' + d + '</span>'; } },
            { data: 'customer', render: function (d) {
                return '<div class="d-flex align-items-center">'
                     + '<img src="' + d.avatar + '" class="oc-customer-avatar me-2">'
                     + '<div><div class="fw-bold oc-customer-link" style="font-size:.9rem" data-customer-id="' + (d.id || '') + '">' + escapeHtml(d.name) + '</div>'
                     + '<div class="text-muted small">' + escapeHtml(d.email) + '</div></div></div>';
            }},
            { data: 'suburb' },
            { data: 'total_formatted', render: function (d) { return '<span class="fw-bold">$' + d + '</span>'; } },
            { data: 'payment_status', render: function (d) { return paymentBadges[d] || d; } },
            { data: 'order_status',   render: function (d) { return statusBadges[d] || d; } },
            { data: 'tags', render: function (tags) {
                if (!tags || !tags.length) return '<span class="text-muted small">—</span>';
                return tags.map(function (t) {
                    return '<span class="oc-tag-chip" style="background:' + (t.colour || '#6B7280') + '">' + escapeHtml(t.name) + '</span>';
                }).join('');
            }},
            { data: 'created_at_human' }
        ],
        rowCallback: function (row, data) {
            // make whole row clickable to open drawer (except checkbox column)
            $(row).attr('data-order-id', data.id);
        },
        drawCallback: function (settings) {
            var info = this.api().page.info();
            $('#oc-result-summary').text(
                'Showing ' + (info.recordsDisplay > 0 ? (info.start + 1) : 0)
                + '–' + Math.min(info.end, info.recordsDisplay)
                + ' of ' + info.recordsDisplay + ' orders'
                + (info.recordsTotal !== info.recordsDisplay ? ' (filtered from ' + info.recordsTotal + ')' : '')
            );
        },
        initComplete: function () {
            // Fix for DataTables initialised inside Bootstrap tabs:
            // recalculate column widths after the tab is fully visible.
            var api = this.api();
            setTimeout(function () { api.columns.adjust(); }, 100);

            // Also re-adjust whenever the All Orders tab becomes visible
            $('a[data-bs-toggle="tab"][href="#all-orders"]').on('shown.bs.tab', function () {
                api.columns.adjust();
            });
        }
    });

    function escapeHtml(s) {
        if (s === null || s === undefined) return '';
        return String(s).replace(/[&<>"']/g, function(c) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
        });
    }

    // -------- Filter wiring (live reload) --------
    var filterTimer = null;
    function reloadDebounced() {
        clearTimeout(filterTimer);
        filterTimer = setTimeout(function () { table.ajax.reload(null, false); }, 250);
    }
    $('#oc-search, #oc-suburb').on('keyup change', reloadDebounced);
    $('#oc-date-from, #oc-date-to, #oc-value-min, #oc-value-max, #oc-source').on('change', reloadDebounced);
    $('.oc-filter-status, .oc-filter-payment, .oc-filter-tag').on('change', reloadDebounced);

    $('#oc-clear-filters').on('click', function () {
        $('#oc-search, #oc-suburb, #oc-date-from, #oc-date-to, #oc-value-min, #oc-value-max').val('');
        $('#oc-source').val('');
        $('.oc-filter-status, .oc-filter-payment, .oc-filter-tag').prop('checked', false);
        table.ajax.reload();
    });

    // -------- Bulk-selection --------
    $('#oc-orders-table tbody').on('click', '.oc-row-check', function (e) {
        e.stopPropagation();
        var id = String($(this).val());
        if (this.checked) selected.add(id); else selected.delete(id);
        updateBulkBar();
    });
    $('#oc-check-all').on('click', function () {
        var on = this.checked;
        $('#oc-orders-table tbody .oc-row-check').each(function () {
            this.checked = on;
            var id = String($(this).val());
            if (on) selected.add(id); else selected.delete(id);
        });
        updateBulkBar();
    });
    $('#oc-bulk-clear').on('click', function () {
        selected.clear();
        $('.oc-row-check, #oc-check-all').prop('checked', false);
        updateBulkBar();
    });
    function updateBulkBar() {
        $('#oc-bulk-count').text(selected.size);
        $('#oc-bulk-bar').toggleClass('active', selected.size > 0);
    }

    // -------- Drawer open --------
    $('#oc-orders-table tbody').on('click', 'tr', function (e) {
        if ($(e.target).is('input[type=checkbox]')) return;
        var id = $(this).data('order-id');
        if (id) openDrawer(id);
    });
    $('#oc-drawer-close, #oc-drawer-overlay').on('click', closeDrawer);

    var currentOrderId = null;

    function openDrawer(id) {
        currentOrderId = id;
        $('#oc-drawer').addClass('active');
        $('#oc-drawer-overlay').fadeIn(150);
        loadDrawerOrder(id);
    }
    function closeDrawer() {
        $('#oc-drawer').removeClass('active');
        $('#oc-drawer-overlay').fadeOut(150);
        currentOrderId = null;
    }

    // -------- Drawer tab switching --------
    $('.oc-drawer-tabs button').on('click', function () {
        var section = $(this).data('section');
        $('.oc-drawer-tabs button').removeClass('active');
        $(this).addClass('active');
        $('.oc-drawer-section').removeClass('active');
        $('.oc-drawer-section[data-section="' + section + '"]').addClass('active');

        // lazy-load some sections
        if (section === 'notes')    loadNotes(currentOrderId);
        if (section === 'timeline') loadTimeline(currentOrderId);
        if (section === 'tags')     loadTagsForOrder(currentOrderId);
    });

    // -------- Drawer load --------
    function loadDrawerOrder(id) {
        $.get('{{ url('order-centre/orders') }}/' + id + '/details').done(function (d) {
            $('#oc-d-order-number').text('#' + d.order_number);
            $('#oc-d-created').text('Created ' + (d.created_at || ''));
            $('#oc-d-status-badge').replaceWith(
                $(statusBadges[d.order_status] || ('<span class="badge">' + d.order_status + '</span>')).attr('id','oc-d-status-badge')
            );
            $('#oc-d-payment-badge').replaceWith(
                $(paymentBadges[d.payment_status] || ('<span class="badge">' + d.payment_status + '</span>')).attr('id','oc-d-payment-badge')
            );

            // Tag pills — render from d.tags array (slug/name/colour objects)
            var $tagsRow = $('#oc-d-tags-row');
            var $tagsHost = $('#oc-d-tags');
            $tagsHost.empty();
            if (Array.isArray(d.tags) && d.tags.length) {
                d.tags.forEach(function (t) {
                    var colour = t.colour || '#6B7280';
                    $tagsHost.append(
                        '<span class="badge me-1" style="background:' + escapeHtml(colour) + '; color:#fff; font-size:.8rem;">'
                        + escapeHtml(t.name || t.slug) +
                        '</span>'
                    );
                });
                $tagsRow.show();
            } else {
                $tagsRow.hide();
            }

            // Confirmed indicator — small green tick + timestamp when admin has
            // confirmed the delivery date. NULL means it's still pending review.
            if (d.delivery_date_confirmed_at) {
                $('#oc-d-confirmed-at').text(d.delivery_date_confirmed_at);
                $('#oc-d-confirmed-row').show();
            } else {
                $('#oc-d-confirmed-row').hide();
            }

            // Auto-delivered badge — shown when the daily 6pm job marked this
            // order delivered automatically (vs manual admin click).
            if (d.auto_delivered_at) {
                $('#oc-d-auto-delivered-at').text('at ' + d.auto_delivered_at);
                $('#oc-d-auto-delivered-row').show();
            } else {
                $('#oc-d-auto-delivered-row').hide();
            }

            // customer
            var u = d.user || {};
            $('#oc-d-cust-name').text(u.name || 'Guest').attr('data-user-id', u.id || '');
            $('#oc-d-cust-email').text(u.email || '').attr('href', u.email ? 'mailto:' + u.email : '#');
            $('#oc-d-cust-phone').text(u.phone || '');
            $('#oc-d-cust-avatar').attr('src', u.avatar_url || '{{ asset('uploads/avatars/default.png') }}');

            // Delivery details card — full address + contact + delivery method
            var a = d.address;
            var dt = d.delivery_type;

            var addressLines = [];
            if (a && a.address) addressLines.push(escapeHtml(a.address));
            if (a) {
                var l2 = [a.suburb, a.city, a.state, a.zip].filter(Boolean);
                if (l2.length) addressLines.push(escapeHtml(l2.join(', ')));
            }
            $('#oc-d-delivery-address').html(addressLines.length ? addressLines.join('<br>') : '<em class="text-muted">No address on file</em>');

            // Contact line — name, phone, email pulled from the shipping_addresses row.
            // Each on its own line for readability (especially when names are long).
            var contactBits = [];
            if (a && a.full_name) contactBits.push('<div><i class="fa fa-user"></i> ' + escapeHtml(a.full_name) + '</div>');
            if (a && a.phone)     contactBits.push('<div><i class="fa fa-phone"></i> ' + escapeHtml(a.phone) + '</div>');
            if (a && a.email)     contactBits.push('<div><i class="fa fa-envelope"></i> ' + escapeHtml(a.email) + '</div>');
            $('#oc-d-delivery-contact').html(contactBits.length ? contactBits.join('') : '');

            // Delivery method line
            if (dt && dt.name) {
                var feeLabel = dt.fee > 0 ? (' — $' + Number(dt.fee).toFixed(2)) : ' — Free';
                var daysLabel = dt.days ? ' (' + escapeHtml(dt.days) + ')' : '';
                $('#oc-d-delivery-method').html('<strong>Delivery Method:</strong> ' + escapeHtml(dt.name) + feeLabel + daysLabel);
            } else {
                $('#oc-d-delivery-method').html('');
            }

            // Preferred delivery date — falls back to gracefully if missing
            if (d.preferred_delivery_date) {
                var prettyDate = new Date(d.preferred_delivery_date).toLocaleDateString(undefined, { day: 'numeric', month: 'short', year: 'numeric' });
                $('#oc-d-delivery-date').html('<i class="fa fa-calendar"></i><strong>Preferred Date:</strong> ' + escapeHtml(prettyDate));
            } else {
                $('#oc-d-delivery-date').html('');
            }

            // Items — enriched rendering shows product thumbnail, category,
            // subtitle and size info when available, with a graceful fallback
            // for older API responses that only have product_name/quantity/price.
            var $items = $('#oc-d-items').empty();
            if (!d.items.length) $items.append('<div class="oc-empty">No items.</div>');
            d.items.forEach(function (it) {
                // Image — only show if we have one (controller may or may not
                // populate it depending on which OrderCentreController version
                // is deployed). Falls back gracefully if missing.
                var imgHtml = '';
                if (it.image) {
                    var imgSrc = it.image;
                    if (it.image.indexOf('/') === -1 && it.image.indexOf('http') !== 0) {
                        imgSrc = '{{ asset("uploads/products") }}/' + it.image;
                    }
                    imgHtml =
                        '<img src="' + escapeHtml(imgSrc) + '" onerror="this.style.display=\'none\'" ' +
                        'style="width: 64px; height: 64px; object-fit: cover; border-radius: 6px; border: 1px solid #e5e7eb; flex-shrink: 0;">';
                }
                var subInfo = [];
                if (it.category)    subInfo.push('<span class="badge" style="background:#fee2e2;color:#b91c1c;font-weight:600;font-size:0.65rem;">' + escapeHtml(it.category) + '</span>');
                if (it.subcategory) subInfo.push('<span class="text-muted small">' + escapeHtml(it.subcategory) + '</span>');
                if (it.size_info)   subInfo.push('<span class="text-muted small"><i class="fa fa-box me-1"></i>' + escapeHtml(it.size_info) + '</span>');
                var subInfoHtml = subInfo.length ? '<div class="d-flex gap-2 flex-wrap mt-1 align-items-center">' + subInfo.join('') + '</div>' : '';
                var shortTitleHtml = it.short_title ? '<div class="text-muted small fst-italic">' + escapeHtml(it.short_title) + '</div>' : '';

                $items.append(
                    '<div class="oc-line-item">' +
                        imgHtml +
                        '<div style="flex: 1; min-width: 0;">' +
                            '<div class="fw-semibold">' + escapeHtml(it.product_name) + '</div>' +
                            shortTitleHtml +
                            subInfoHtml +
                            '<div class="text-muted small mt-1">' + Number(it.quantity) + ' × $' + Number(it.price).toFixed(2) + '</div>' +
                        '</div>' +
                        '<div class="fw-bold" style="flex-shrink: 0;">$' + Number(it.subtotal).toFixed(2) + '</div>' +
                    '</div>'
                );
            });

            // Totals card population — hide rows with zero/null values for a
            // cleaner look (e.g. orders without a discount won't show "-$0.00")
            $('#oc-d-subtotal').text('$' + Number(d.subtotal).toFixed(2));
            $('#oc-d-delivery').text('$' + Number(d.delivery_fee).toFixed(2));

            var discountVal = parseFloat(d.discount) || 0;
            if (discountVal > 0) {
                $('#oc-d-discount').text('-$' + discountVal.toFixed(2));
                $('#oc-d-discount-row').show();
            } else { $('#oc-d-discount-row').hide(); }

            var taxVal = parseFloat(d.tax) || 0;
            if (taxVal > 0) {
                $('#oc-d-tax').text('$' + taxVal.toFixed(2));
                $('#oc-d-tax-row').show();
            } else { $('#oc-d-tax-row').hide(); }

            // Refunded row — only shows when there's actually been a refund
            var refundedVal = parseFloat(d.latest_payment?.refund_amount || 0);
            if (refundedVal > 0) {
                $('#oc-d-refunded').text('-$' + refundedVal.toFixed(2));
                $('#oc-d-refund-row').show();
            } else { $('#oc-d-refund-row').hide(); }

            $('#oc-d-total').text('$' + Number(d.total).toFixed(2));

            // Meta — small label like "3 items · paid via Square"
            var itemCount = (d.items || []).length;
            var metaBits = [];
            if (itemCount) metaBits.push(itemCount + ' item' + (itemCount !== 1 ? 's' : ''));
            if (d.payment_status === 'paid' && d.payment_method) {
                var methodLabel = (d.payment_method === 'cash_on_delivery') ? 'COD' : d.payment_method;
                metaBits.push('paid via ' + methodLabel);
            } else if (d.payment_status) {
                metaBits.push(d.payment_status);
            }
            $('#oc-d-totals-meta').text(metaBits.join(' · ') || '—');

            // -------- Edit tab: pre-fill all fields from current state --------
            $('#oc-d-edit-order-number').val(d.order_number || '');
            $('#oc-d-edit-customer').val(((u.name || 'Guest') + (u.email ? ' (' + u.email + ')' : '')));
            $('#oc-d-edit-subtotal').val(Number(d.subtotal || 0).toFixed(2));
            $('#oc-d-edit-delivery-fee').val(Number(d.delivery_fee || 0).toFixed(2));
            $('#oc-d-edit-discount').val(Number(d.discount || 0).toFixed(2));
            $('#oc-d-edit-tax').val(Number(d.tax || 0).toFixed(2));
            $('#oc-d-edit-total').val(Number(d.total || 0).toFixed(2));
            $('#oc-d-edit-order-status').val(d.order_status || 'pending');
            $('#oc-d-edit-payment-status').val(d.payment_status || 'pending');
            $('#oc-d-edit-delivery-date').val(d.preferred_delivery_date || '');
            $('#oc-d-edit-notes').val(d.notes || '');

            // -------- Payment tab rendering --------
            // Three-card layout (visual consistency with totals card):
            //   1. Refund Breakdown card  — Order Total / Delivery / Refunded / Available for Refund
            //   2. Transaction Info card  — Method, Amount, Status pill, Transaction ID, Receipt
            //   3. Action card            — Cancel Order OR Process Refund (state-dependent)
            var p = d.latest_payment;
            var $pay = $('#oc-d-payment-info').empty();

            var orderTotal_v = parseFloat(d.total) || 0;
            var deliveryFee_v = parseFloat(d.delivery_fee) || 0;
            var alreadyRefunded_v = p ? (parseFloat(p.refund_amount) || 0) : 0;
            var refundable_v = Math.max(0, orderTotal_v - alreadyRefunded_v);
            var isFullyRefunded_v = p && p.refund_status === 'full';

            // 1. Breakdown card — shows what's available to refund at a glance
            var statusBadgeHtml = isFullyRefunded_v
                ? '<span class="badge bg-secondary">Fully Refunded</span>'
                : (alreadyRefunded_v > 0
                    ? '<span class="badge bg-warning text-dark">Partially Refunded</span>'
                    : (d.payment_status === 'paid'
                        ? '<span class="badge bg-success">Refundable</span>'
                        : '<span class="badge bg-light text-dark">Not Paid</span>'));

            var breakdownHtml =
                '<div class="pay-card">' +
                    '<div class="pay-card-header">' +
                        '<span class="h-label"><i class="fa fa-undo"></i> Refund Breakdown</span>' +
                        statusBadgeHtml +
                    '</div>' +
                    '<div class="pay-row">' +
                        '<span class="label-l"><i class="fa fa-shopping-basket"></i> Order Total</span>' +
                        '<span class="val">$' + orderTotal_v.toFixed(2) + '</span>' +
                    '</div>' +
                    (deliveryFee_v > 0
                        ? '<div class="pay-row">' +
                            '<span class="label-l"><i class="fa fa-truck"></i> Less Delivery Fee</span>' +
                            '<span class="val text-muted">-$' + deliveryFee_v.toFixed(2) + '</span>' +
                          '</div>'
                        : '') +
                    (alreadyRefunded_v > 0
                        ? '<div class="pay-row is-refund">' +
                            '<span class="label-l"><i class="fa fa-history"></i> Already Refunded</span>' +
                            '<span class="val">-$' + alreadyRefunded_v.toFixed(2) + '</span>' +
                          '</div>'
                        : '') +
                    '<div class="pay-row is-available' + (refundable_v <= 0.01 ? ' zero' : '') + '">' +
                        '<span class="label-l">Available for Refund</span>' +
                        '<span class="val">$' + refundable_v.toFixed(2) + '</span>' +
                    '</div>' +
                '</div>';

            // 2. Transaction Info card — only if there's actually a payment record
            var transactionHtml = '';
            if (p) {
                var statusClass = (p.status || '').toLowerCase().replace(/[^a-z]/g, '');
                var methodLabel = p.payment_method || d.payment_method || 'manual';
                var methodDisplay = methodLabel === 'cash_on_delivery' ? 'Cash on Delivery' :
                                    methodLabel.charAt(0).toUpperCase() + methodLabel.slice(1);

                transactionHtml =
                    '<div class="pay-trans-card">' +
                        '<div class="pay-trans-row"><span class="lbl">Method</span><span class="vl">' + escapeHtml(methodDisplay) + '</span></div>' +
                        '<div class="pay-trans-row"><span class="lbl">Amount Paid</span><span class="vl">$' + Number(p.amount).toFixed(2) + '</span></div>' +
                        '<div class="pay-trans-row"><span class="lbl">Status</span><span class="vl"><span class="status-pill ' + statusClass + '">' + escapeHtml(p.status || '—') + '</span></span></div>' +
                        (p.payment_id ? '<div class="pay-trans-row"><span class="lbl">Transaction ID</span><span class="vl"><code>' + escapeHtml(p.payment_id) + '</code></span></div>' : '') +
                        (alreadyRefunded_v > 0 ? '<div class="pay-trans-row"><span class="lbl text-danger">Refunded</span><span class="vl text-danger">$' + alreadyRefunded_v.toFixed(2) + ' (' + escapeHtml(p.refund_status || '') + ')</span></div>' : '') +
                        (p.refunded_at ? '<div class="pay-trans-row"><span class="lbl">Refunded On</span><span class="vl text-muted">' + escapeHtml(p.refunded_at) + '</span></div>' : '') +
                        (p.refund_reason ? '<div class="pay-trans-row"><span class="lbl">Refund Reason</span><span class="vl text-muted" style="font-style:italic;">' + escapeHtml(p.refund_reason) + '</span></div>' : '') +
                        (p.receipt_url ? '<div class="pay-trans-row pt-2"><a href="' + p.receipt_url + '" target="_blank" class="btn btn-sm btn-outline-secondary"><i class="fa fa-external-link-alt me-1"></i>View Receipt</a></div>' : '') +
                    '</div>';
            } else {
                transactionHtml = '<div class="alert alert-light border mb-3 small"><i class="fa fa-info-circle me-1"></i> No payment record on file.</div>';
            }

            $pay.append(breakdownHtml + transactionHtml);

            // -------- Cancel & Refund actions (action card, state-dependent) --------
            // These buttons POST to the EXISTING legacy endpoints — same code path
            // the old admin uses, so retraction/notification flows behave identically.
            var $actions = $('#oc-d-payment-actions').empty();
            var orderTotal = parseFloat(d.total) || 0;
            var alreadyRefunded = p ? (parseFloat(p.refund_amount) || 0) : 0;
            var refundable = Math.max(0, orderTotal - alreadyRefunded);
            var isCancelled = d.order_status === 'cancelled';
            var isPaid = d.payment_status === 'paid';
            var isFullyRefunded = p && p.refund_status === 'full';
            var hasRegisteredPayment = !!(p && p.payment_id);
            var canRefund = isPaid && !isFullyRefunded && refundable > 0.01;
            var gatewayName = (d.payment_method === 'stripe' || (d.transaction_id || '').indexOf('pi_') === 0 || (d.transaction_id || '').indexOf('ch_') === 0)
                ? 'Stripe'
                : (hasRegisteredPayment ? 'Square' : 'manual');

            if (isFullyRefunded) {
                $actions.append(
                    '<div class="alert alert-secondary mb-0">' +
                        '<i class="fa fa-check-circle me-1"></i> ' +
                        '<strong>Fully refunded.</strong> No further actions available.' +
                    '</div>'
                );
            } else if (!isCancelled) {
                // ORDER IS LIVE — show Cancel Order with optional refund toggle
                var refundToggleHtml = canRefund
                    ? ('<div class="form-check form-switch mt-3 mb-2">' +
                           '<input class="form-check-input" type="checkbox" role="switch" id="oc-d-also-refund">' +
                           '<label class="form-check-label" for="oc-d-also-refund">' +
                               '<strong>Also refund $<span id="oc-d-refundable-display">' + refundable.toFixed(2) + '</span></strong> via ' + gatewayName +
                           '</label>' +
                       '</div>' +
                       '<div id="oc-d-refund-amount-row" class="mt-2" style="display: none;">' +
                           '<label class="form-label small mb-1 fw-semibold">Refund Amount</label>' +
                           '<div class="input-group input-group-sm">' +
                               '<span class="input-group-text">$</span>' +
                               '<input type="number" step="0.01" min="0.01" max="' + refundable.toFixed(2) + '" value="' + refundable.toFixed(2) + '" class="form-control" id="oc-d-cancel-refund-amount">' +
                           '</div>' +
                           '<small class="text-muted d-block mt-1">Up to <strong>$' + refundable.toFixed(2) + '</strong>. Full refund will retract loyalty + entries.</small>' +
                       '</div>')
                    : '';

                $actions.append(
                    '<div class="pay-action-card danger">' +
                        '<h6 class="text-danger"><i class="fa fa-ban me-1"></i> Cancel Order</h6>' +
                        '<div class="helper">Marks the order as cancelled. <strong>Entries and loyalty points awarded for this order will be retracted automatically.</strong></div>' +
                        '<label class="form-label small mb-1 fw-semibold">Reason (optional)</label>' +
                        '<textarea class="form-control form-control-sm" rows="2" id="oc-d-cancel-reason" placeholder="e.g. Customer requested cancellation"></textarea>' +
                        refundToggleHtml +
                        '<button type="button" class="btn btn-danger mt-3 w-100" id="oc-d-cancel-btn">' +
                            '<i class="fa fa-ban me-1"></i> Cancel Order' +
                        '</button>' +
                    '</div>'
                );

                $('#oc-d-also-refund').off('change').on('change', function () {
                    $('#oc-d-refund-amount-row').toggle(this.checked);
                });
            } else if (canRefund) {
                // ORDER ALREADY CANCELLED, payment still refundable — refund-only form
                $actions.append(
                    '<div class="pay-action-card warn">' +
                        '<h6 class="text-warning"><i class="fa fa-undo me-1"></i> Process Refund</h6>' +
                        '<div class="helper">' +
                            'Order is cancelled. <strong>$' + refundable.toFixed(2) + '</strong> remaining to refund via ' + gatewayName + '.' +
                            '<span class="d-block mt-1"><small>Full refund will retract loyalty + entries (partial refunds keep them).</small></span>' +
                        '</div>' +
                        '<label class="form-label small mb-1 fw-semibold">Refund Amount</label>' +
                        '<div class="input-group input-group-sm mb-2">' +
                            '<span class="input-group-text">$</span>' +
                            '<input type="number" step="0.01" min="0.01" max="' + refundable.toFixed(2) + '" value="' + refundable.toFixed(2) + '" class="form-control" id="oc-d-refund-only-amount">' +
                        '</div>' +
                        '<label class="form-label small mb-1 fw-semibold">Reason (optional)</label>' +
                        '<textarea class="form-control form-control-sm mb-3" rows="2" id="oc-d-refund-only-reason"></textarea>' +
                        '<button type="button" class="btn btn-warning w-100" id="oc-d-refund-only-btn">' +
                            '<i class="fa fa-undo me-1"></i> Process Refund' +
                        '</button>' +
                    '</div>'
                );
            } else {
                $actions.append(
                    '<div class="alert alert-light border mb-0 small">' +
                        '<i class="fa fa-info-circle me-1"></i> Order is cancelled. No refundable balance.' +
                    '</div>'
                );
            }

            // counts
            $('#oc-d-notes-count').text(d.notes_count || 0);

            // pre-fill quick action selects
            $('#oc-d-act-status').val(d.order_status);
            $('#oc-d-act-payment').val(d.payment_status === 'paid' ? 'paid' : (d.payment_status === 'failed' ? 'failed' : 'pending'));
            $('#oc-d-act-date').val(d.preferred_delivery_date || '');
            $('#oc-d-act-edit-link').attr('href', '/order/' + d.id + '/edit');

            // -------- Phase 7: WooCommerce origin banner --------
            var $wcBanner = $('#oc-d-wc-banner');
            if (d.source === 'woocommerce' && d.woocommerce_order_id) {
                $wcBanner.removeClass('d-none');
                $('#oc-d-wc-id').text(d.woocommerce_order_id);

                // Build link from saved WC site_url (fetched on first banner show)
                if (window.ocWcSiteUrl) {
                    $('#oc-d-wc-link').attr('href', window.ocWcSiteUrl.replace(/\/$/, '') + '/wp-admin/post.php?post=' + d.woocommerce_order_id + '&action=edit');
                } else {
                    // Lazy-fetch WC site url
                    $.get('{{ route('order-centre.woocommerce.get') }}').done(function (s) {
                        window.ocWcSiteUrl = s.site_url || '';
                        window.ocWcSyncEnabled = !!s.sync_enabled;
                        if (window.ocWcSiteUrl) {
                            $('#oc-d-wc-link').attr('href', window.ocWcSiteUrl.replace(/\/$/, '') + '/wp-admin/post.php?post=' + d.woocommerce_order_id + '&action=edit');
                        }
                        if (window.ocWcSyncEnabled) {
                            $('#oc-d-wc-push').removeClass('d-none');
                            $('#oc-d-wc-push-hint').text('Write mode active — pushing will overwrite the WooCommerce order status.');
                        }
                    });
                }
                if (window.ocWcSyncEnabled) {
                    $('#oc-d-wc-push').removeClass('d-none');
                    $('#oc-d-wc-push-hint').text('Write mode active — pushing will overwrite the WooCommerce order status.');
                }
            } else {
                $wcBanner.addClass('d-none');
            }
        }).fail(function () {
            alert('Failed to load order details.');
        });
    }

    // -------- Notes --------
    function loadNotes(id) {
        if (!id) return;
        $.get('{{ url('order-centre/orders') }}/' + id + '/notes').done(function (resp) {
            var $list = $('#oc-d-notes-list').empty();
            if (!resp.data.length) {
                $list.append('<div class="oc-empty">No notes yet.</div>');
                return;
            }
            resp.data.forEach(function (n) { $list.append(renderNote(n)); });
        });
    }

    function renderNote(n) {
        return '<div class="oc-note" data-note-id="' + n.id + '">' +
            '<button class="delete-btn" title="Delete"><i class="fa fa-times"></i></button>' +
            '<div class="author">' + escapeHtml(n.author) + '</div>' +
            '<div class="meta">' + escapeHtml(n.created_at) + ' · ' + escapeHtml(n.created_at_full) + '</div>' +
            '<div class="body">' + escapeHtml(n.body) + '</div>' +
            '</div>';
    }

    $('#oc-d-note-add').on('click', function () {
        var body = $('#oc-d-note-input').val().trim();
        if (!body || !currentOrderId) return;
        $(this).prop('disabled', true);
        $.post('{{ url('order-centre/orders') }}/' + currentOrderId + '/notes', { body: body })
            .done(function (resp) {
                $('#oc-d-note-input').val('');
                $('#oc-d-notes-list').prepend(renderNote(resp.note));
                $('#oc-d-notes-list .oc-empty').remove();
                var c = parseInt($('#oc-d-notes-count').text(), 10) || 0;
                $('#oc-d-notes-count').text(c + 1);
            })
            .fail(function () { alert('Failed to post note.'); })
            .always(function () { $('#oc-d-note-add').prop('disabled', false); });
    });

    $('#oc-d-notes-list').on('click', '.delete-btn', function () {
        if (!confirm('Delete this note?')) return;
        var $note = $(this).closest('.oc-note');
        var noteId = $note.data('note-id');
        $.ajax({
            url: '{{ url('order-centre/orders') }}/' + currentOrderId + '/notes/' + noteId,
            type: 'DELETE'
        }).done(function () {
            $note.remove();
            var c = parseInt($('#oc-d-notes-count').text(), 10) || 1;
            $('#oc-d-notes-count').text(Math.max(0, c - 1));
            if (!$('#oc-d-notes-list .oc-note').length) {
                $('#oc-d-notes-list').append('<div class="oc-empty">No notes yet.</div>');
            }
        });
    });

    // -------- Cancel & Refund (Payment tab) --------
    // These reuse EXISTING endpoints — no new server-side code added:
    //   - Cancel:  POST /order-centre/bulk/status  (with ids=[id], order_status=cancelled)
    //              fires OrderCancelled event → RewardRetractionService retracts entries+loyalty
    //   - Refund:  POST /order/{id}/refund         (legacy processRefund — handles Square/Stripe routing)
    //              fires PaymentRefunded event → retracts on FULL refund only
    //
    // Both event flows are already battle-tested. We just add UI to call them.

    $(document).on('click', '#oc-d-cancel-btn', function () {
        if (!currentOrderId) return;
        var alsoRefund = $('#oc-d-also-refund').is(':checked');
        var reason = $('#oc-d-cancel-reason').val().trim();
        var refundAmount = parseFloat($('#oc-d-cancel-refund-amount').val()) || 0;

        var confirmMsg = 'Cancel this order?';
        confirmMsg += '\n\nEntries and loyalty awarded for this order will be retracted.';
        if (alsoRefund) {
            confirmMsg += '\nAlso processing a $' + refundAmount.toFixed(2) + ' refund. This cannot be undone.';
        }
        if (!confirm(confirmMsg)) return;

        var $btn = $(this);
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> Cancelling…');

        // Step 1: cancel via the bulk status endpoint with a single ID.
        // This is the same code path that runs when you bulk-update orders
        // to 'cancelled' from the Order Centre list, so events + retraction
        // are guaranteed identical to existing behaviour.
        $.ajax({
            url: '{{ url('order-centre/bulk/status') }}',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                ids: [currentOrderId],
                order_status: 'cancelled',
            },
        })
        .done(function (resp) {
            if (!alsoRefund) {
                alert('Order cancelled. Loyalty + entries retracted.');
                openDrawer(currentOrderId);
                return;
            }

            // Step 2: also refund — chain into the legacy refund endpoint
            $btn.html('<i class="fa fa-spinner fa-spin me-1"></i> Refunding…');
            var isFull = Math.abs(refundAmount - parseFloat($('#oc-d-cancel-refund-amount').attr('max'))) < 0.01;
            $.ajax({
                url: '{{ url('order') }}/' + currentOrderId + '/refund',
                type: 'POST',
                data: {
                    _token: '{{ csrf_token() }}',
                    refund_amount: refundAmount,
                    refund_type:   isFull ? 'full' : 'partial',
                    reason:        reason || 'Order cancelled — refund',
                },
            })
            .done(function (refundResp) {
                if (refundResp.success) {
                    alert('Order cancelled + $' + refundAmount.toFixed(2) + ' refund processed.');
                } else {
                    alert('Order cancelled, but refund failed: ' + (refundResp.message || 'unknown error') + '\n\nYou can retry the refund from the Payment tab.');
                }
                openDrawer(currentOrderId);
            })
            .fail(function (xhr) {
                var msg = xhr.responseJSON?.message || ('HTTP ' + xhr.status);
                alert('Order cancelled, but refund failed: ' + msg + '\n\nYou can retry the refund from the Payment tab.');
                openDrawer(currentOrderId);
            });
        })
        .fail(function (xhr) {
            var msg = xhr.responseJSON?.message || ('HTTP ' + xhr.status);
            alert('Cancel failed: ' + msg);
            $btn.prop('disabled', false).html('<i class="fa fa-ban me-1"></i> Cancel Order');
        });
    });

    $(document).on('click', '#oc-d-refund-only-btn', function () {
        if (!currentOrderId) return;
        var amount = parseFloat($('#oc-d-refund-only-amount').val()) || 0;
        var max = parseFloat($('#oc-d-refund-only-amount').attr('max')) || 0;
        var reason = $('#oc-d-refund-only-reason').val().trim();

        if (amount <= 0 || amount > max + 0.01) {
            alert('Refund amount must be between $0.01 and $' + max.toFixed(2));
            return;
        }
        var isFull = Math.abs(amount - max) < 0.01;
        var confirmMsg = 'Refund $' + amount.toFixed(2) + '?';
        if (isFull) confirmMsg += '\n\nFull refund — loyalty + entries will be retracted.';
        else        confirmMsg += '\n\nPartial refund — loyalty + entries kept.';
        confirmMsg += '\n\nThis cannot be undone.';
        if (!confirm(confirmMsg)) return;

        var $btn = $(this);
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> Processing…');

        $.ajax({
            url: '{{ url('order') }}/' + currentOrderId + '/refund',
            type: 'POST',
            data: {
                _token: '{{ csrf_token() }}',
                refund_amount: amount,
                refund_type:   isFull ? 'full' : 'partial',
                reason:        reason,
            },
        })
        .done(function (resp) {
            if (resp.success) {
                alert(resp.message || ('Refund of $' + amount.toFixed(2) + ' processed.'));
                openDrawer(currentOrderId);
            } else {
                alert('Refund failed: ' + (resp.message || 'unknown error'));
                $btn.prop('disabled', false).html('<i class="fa fa-undo me-1"></i> Process Refund');
            }
        })
        .fail(function (xhr) {
            var msg = xhr.responseJSON?.message || ('HTTP ' + xhr.status);
            alert('Refund failed: ' + msg);
            $btn.prop('disabled', false).html('<i class="fa fa-undo me-1"></i> Process Refund');
        });
    });

    // -------- Timeline --------
    function loadTimeline(id) {
        if (!id) return;
        $.get('{{ url('order-centre/orders') }}/' + id + '/audit-log').done(function (resp) {
            var $list = $('#oc-d-timeline-list').empty();
            if (!resp.data.length) {
                $list.append('<div class="oc-empty">No history yet.</div>');
                return;
            }
            resp.data.forEach(function (l) {
                $list.append(
                    '<div class="oc-timeline-item">' +
                        '<div class="oc-timeline-dot"></div>' +
                        '<div class="oc-timeline-content">' +
                            '<div class="desc">' + escapeHtml(l.description || l.action) + '</div>' +
                            '<div class="meta">' + escapeHtml(l.author) + ' · ' + escapeHtml(l.created_at) + '</div>' +
                        '</div>' +
                    '</div>'
                );
            });
        });
    }

    // -------- Tags --------
    var allTags = @json($tags);

    function loadTagsForOrder(id) {
        if (!id) return;
        $.get('{{ url('order-centre/orders') }}/' + id + '/details').done(function (d) {
            var attached = d.tags || [];
            var attachedIds = attached.map(function (t) { return t.id; });

            var $a = $('#oc-d-tags-attached').empty();
            if (!attached.length) $a.append('<span class="text-muted small">None attached.</span>');
            attached.forEach(function (t) {
                $a.append(
                    '<span class="oc-tag-pill" style="background:' + (t.colour || '#6B7280') + '">' +
                    escapeHtml(t.name) +
                    '<span class="remove" data-tag-id="' + t.id + '">✕</span></span>'
                );
            });

            var $av = $('#oc-d-tags-available').empty();
            allTags.forEach(function (t) {
                if (attachedIds.indexOf(t.id) !== -1) return;
                $av.append(
                    '<span class="oc-tag-pill" style="background:' + (t.colour || '#6B7280') + ';cursor:pointer" data-tag-id="' + t.id + '">' +
                    '+ ' + escapeHtml(t.name) + '</span>'
                );
            });
        });
    }

    $('#oc-d-tags-available').on('click', '.oc-tag-pill', function () {
        var tagId = $(this).data('tag-id');
        $.post('{{ url('order-centre/orders') }}/' + currentOrderId + '/tags', { tag_id: tagId })
            .done(function () { loadTagsForOrder(currentOrderId); table.ajax.reload(null, false); });
    });

    $('#oc-d-tags-attached').on('click', '.remove', function () {
        var tagId = $(this).data('tag-id');
        $.ajax({
            url: '{{ url('order-centre/orders') }}/' + currentOrderId + '/tags/' + tagId,
            type: 'DELETE'
        }).done(function () { loadTagsForOrder(currentOrderId); table.ajax.reload(null, false); });
    });

    // -------- Quick Actions (use existing legacy endpoints — observer captures changes) --------
    // Helper: reload whichever DataTable matches the currently visible tab.
    // The drawer can be opened from Needs Action OR App Orders, so updates
    // need to refresh the right table so resolved orders disappear.
    function reloadActiveOrdersTable() {
        var activeTab = $('.nav-tabs .nav-link.active').attr('href') || '#all-orders';
        if (activeTab === '#needs-action' && naTable) {
            naTable.ajax.reload(null, false);
        } else if (activeTab === '#all-orders' && table) {
            table.ajax.reload(null, false);
        } else if (activeTab === '#website-orders' && typeof wcTable !== 'undefined' && wcTable) {
            wcTable.ajax.reload(null, false);
        } else if (activeTab === '#refunds-queue' && typeof rqTable !== 'undefined' && rqTable) {
            rqTable.ajax.reload(null, false);
        }
        // Also refresh stats badges (Needs Action count, etc.)
        if (typeof refreshStats === 'function') refreshStats();
    }

    $('#oc-d-act-status-btn').on('click', function () {
        if (!currentOrderId) return;
        var s = $('#oc-d-act-status').val();
        var $btn = $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> Updating…');
        $.post('/order/' + currentOrderId + '/status', { order_status: s })
            .done(function (r) {
                toast(r.message || 'Status updated');
                loadDrawerOrder(currentOrderId);
                reloadActiveOrdersTable();
            })
            .fail(function (xhr) { alert(xhr.responseJSON?.message || 'Failed'); })
            .always(function () {
                $btn.prop('disabled', false).html('Update Status');
            });
    });
    $('#oc-d-act-payment-btn').on('click', function () {
        if (!currentOrderId) return;
        var s = $('#oc-d-act-payment').val();
        var $btn = $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> Updating…');
        $.post('/order/' + currentOrderId + '/payment', { payment_status: s })
            .done(function (r) {
                toast(r.message || 'Payment updated');
                loadDrawerOrder(currentOrderId);
                reloadActiveOrdersTable();
            })
            .fail(function (xhr) { alert(xhr.responseJSON?.message || 'Failed'); })
            .always(function () {
                $btn.prop('disabled', false).html('Update Payment');
            });
    });
    $('#oc-d-act-date-btn').on('click', function () {
        if (!currentOrderId) return;
        var date = $('#oc-d-act-date').val();
        if (!date) { alert('Pick a date first.'); return; }
        var $btn = $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> Updating…');
        $.post('/order/' + currentOrderId + '/delivery-date', { preferred_delivery_date: date })
            .done(function (r) {
                toast(r.message || 'Date updated');
                loadDrawerOrder(currentOrderId);
                reloadActiveOrdersTable();
            })
            .fail(function (xhr) { alert(xhr.responseJSON?.message || 'Failed'); })
            .always(function () {
                $btn.prop('disabled', false).html('Reschedule');
            });
    });

    var _autoNextAborted = false;       // becomes true if user hits Esc during a Done jump
    var _lastDoneOrderId = null;        // ID admin just finished, skip during next-jump

    // We detect "drawer opened from Needs Action" simply by checking the
    // active tab at Done-click time. This is more reliable than trying to
    // track origin at every openDrawer call site (several exist scattered
    // through the file). Side benefit: if admin switches tabs after opening
    // a drawer, auto-next correctly disables itself.
    function isCurrentlyOnNeedsActionTab() {
        return ($('.nav-tabs .nav-link.active').attr('href') || '') === '#needs-action';
    }

    /**
     * Returns the next order ID to open in Needs Action, skipping the
     * just-Done order. Returns null if there's none in the visible page.
     * Caller is responsible for advancing pages if null is returned.
     */
    function getNextNaIdAfterDone() {
        if (!naTable || !_lastDoneOrderId) return null;

        // Pull current page's row data. DataTables stores it as JSON objects.
        var rows = naTable.rows({ page: 'current' }).data().toArray();
        if (!rows.length) return null;

        // Find the index of the just-Done order
        var doneIdStr = String(_lastDoneOrderId);
        var idx = -1;
        for (var i = 0; i < rows.length; i++) {
            if (String(rows[i].id) === doneIdStr) { idx = i; break; }
        }

        // Look for the first row AFTER the just-Done one whose id isn't the
        // same. If idx === -1 (the row already disappeared from the list
        // because it no longer matches criteria), start from the top.
        var startFrom = (idx === -1) ? 0 : idx + 1;
        for (var j = startFrom; j < rows.length; j++) {
            if (String(rows[j].id) !== doneIdStr) {
                return rows[j].id;
            }
        }
        return null;
    }

    /**
     * Move to the next page of Needs Action and try to open its first row.
     * Returns a promise that resolves with the next id (or null if no more).
     */
    function advanceToNextNaPage() {
        return new Promise(function (resolve) {
            if (!naTable) { resolve(null); return; }
            var info = naTable.page.info();
            if (info.page >= info.pages - 1) {
                // Already on last page; nothing more
                resolve(null);
                return;
            }
            // Advance + wait for the redraw, then return the first row's id
            naTable.one('draw.dt', function () {
                var rows = naTable.rows({ page: 'current' }).data().toArray();
                resolve(rows.length ? rows[0].id : null);
            });
            naTable.page('next').draw('page');
        });
    }

    /**
     * The full Done flow for Needs Action: reload the list, then find next.
     * Returns the next id (via promise) or null if queue is empty.
     */
    function findNextNaOrderAfterDone() {
        return new Promise(function (resolve) {
            if (!naTable) { resolve(null); return; }
            // Reload current page so the just-Done order's state is fresh
            naTable.one('draw.dt', function () {
                var nextId = getNextNaIdAfterDone();
                if (nextId) {
                    resolve(nextId);
                } else {
                    // No more on this page — try advancing
                    advanceToNextNaPage().then(resolve);
                }
            });
            naTable.ajax.reload(null, false);
        });
    }

    // -------- Done button — close drawer + reload active tab + (Needs Action only) auto-jump to next --------
    //
    // Behaviour:
    //   - In Needs Action: clicking Done finds the next order in the visible
    //     list and opens it automatically, turning the tab into a work queue.
    //     If at end of page, advances to next page. When the queue empties,
    //     drawer closes and a toast shows.
    //   - Anywhere else: just closes the drawer + reloads the table.
    //   - Esc key while drawer open cancels auto-next for the current Done.
    $('#oc-drawer-done').on('click', function () {
        var justDoneId = currentOrderId;
        _lastDoneOrderId = justDoneId;

        // Auto-next only applies to Needs Action and only if not aborted
        if (!isCurrentlyOnNeedsActionTab() || _autoNextAborted) {
            closeDrawer();
            reloadActiveOrdersTable();
            _autoNextAborted = false;  // reset for next session
            return;
        }

        // Show a quick "moving on" indicator while we wait for the reload
        var $btn = $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> Next…');

        findNextNaOrderAfterDone().then(function (nextId) {
            $btn.prop('disabled', false).html('<i class="fa fa-check me-1"></i> Done');

            // Re-check abort flag — user may have hit Esc while we were loading
            if (_autoNextAborted) {
                closeDrawer();
                _autoNextAborted = false;
                return;
            }

            if (nextId) {
                // Swap in the next order WITHOUT closing the drawer (smoother)
                openDrawer(nextId);
                toast('Next order loaded');
            } else {
                closeDrawer();
                toast('All caught up — no more orders need action.');
            }
        }).catch(function (err) {
            console.error('[Auto-next] Failed:', err);
            $btn.prop('disabled', false).html('<i class="fa fa-check me-1"></i> Done');
            closeDrawer();
        });
    });

    // Esc key while drawer is open → cancel auto-next for the next Done click.
    // Lets admin stop mid-flow if they want to abandon the queue. The flag
    // is consulted on Done click and reset afterwards, so it doesn't stick.
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape' && $('#oc-drawer').hasClass('active')) {
            _autoNextAborted = true;
        }
    });

    function toast(msg) {
        // Lightweight feedback — replace with your own toast lib if you have one.
        var $t = $('<div style="position:fixed;top:20px;right:20px;background:#22c55e;color:white;padding:10px 16px;border-radius:6px;z-index:9999;box-shadow:0 4px 12px rgba(0,0,0,0.2);font-size:14px;">' + escapeHtml(msg) + '</div>');
        $('body').append($t);
        setTimeout(function () { $t.fadeOut(300, function () { $t.remove(); }); }, 2500);
    }

    // =================================================================
    // PHASE 3: BULK OPERATIONS
    // =================================================================

    function selectedIdsArray() {
        // Source of ids depends on which tab triggered the bulk flow.
        // window._naBulkActive is set just before opening a bulk modal from
        // the Needs Action tab; it's cleared after the request completes.
        if (window._naBulkActive && typeof naSelected !== 'undefined') {
            return Array.from(naSelected).map(function (s) { return parseInt(s, 10); });
        }
        return Array.from(selected).map(function (s) { return parseInt(s, 10); });
    }

    // Helper: clear the Needs Action "bulk active" flag and selected set
    // once the request finishes (successfully or not). Keeps state consistent.
    function clearNaBulkFlag() {
        window._naBulkActive = false;
    }
    function clearNaSelectionAfterBulk() {
        naSelected.clear();
        $('#oc-na-table tbody .oc-na-check').prop('checked', false);
        $('#oc-na-check-all').prop('checked', false).prop('indeterminate', false);
        updateNaBulkBar();
    }

    function setBulkButtonsLoading(on) {
        $('#oc-bulk-bar .btn-bulk').prop('disabled', on);
        $('.modal .btn-primary').prop('disabled', on);
    }

    // ---- Open modals (and inject the count) ----
    function openBulkModal(modalId) {
        if (selected.size === 0) {
            alert('Select at least one order first.');
            return;
        }
        $('#' + modalId).find('.oc-modal-count').text(selected.size);
        new bootstrap.Modal(document.getElementById(modalId)).show();
    }
    $('#oc-bulk-update').on('click',     function () {
        // Reset to "no change" each time
        $('#oc-modal-update-status').val('');
        $('#oc-modal-update-payment').val('');
        $('#oc-modal-also-paid').prop('checked', true);
        $('#oc-modal-also-paid-wrap').addClass('d-none');
        openBulkModal('oc-modal-update');
    });
    $('#oc-bulk-reschedule').on('click', function () { openBulkModal('oc-modal-reschedule'); });
    $('#oc-bulk-notify').on('click',     function () { openBulkModal('oc-modal-notify'); });

    // Show / hide the "Also mark paid" checkbox when status dropdown changes.
    $('#oc-modal-update-status').on('change', function () {
        var isDelivered = $(this).val() === 'delivered';
        // Only show if delivered AND payment-status is left blank (no change)
        var paymentChosen = $('#oc-modal-update-payment').val() !== '';
        $('#oc-modal-also-paid-wrap').toggleClass('d-none', !(isDelivered && !paymentChosen));
    });
    $('#oc-modal-update-payment').on('change', function () {
        // If admin picks an explicit payment status, the auto-flip is redundant
        $('#oc-modal-update-status').trigger('change');
    });

    // ---- Combined Status + Payment submit ----
    $('#oc-modal-update-submit').on('click', function () {
        var newStatus  = $('#oc-modal-update-status').val();
        var newPayment = $('#oc-modal-update-payment').val();
        var alsoPaid   = newStatus === 'delivered' && !newPayment && $('#oc-modal-also-paid').is(':checked');

        if (!newStatus && !newPayment) {
            alert('Pick a new status or payment status — both are currently set to "No change".');
            return;
        }

        // Use the correct selection set's count depending on which tab triggered the bulk
        var activeCount = window._naBulkActive ? naSelected.size : selected.size;

        var parts = [];
        if (newStatus)  parts.push('status → "' + newStatus + '"');
        if (newPayment) parts.push('payment → "' + newPayment + '"');
        if (alsoPaid)   parts.push('also mark paid (where pending)');

        if (!confirm('Update ' + activeCount + ' order(s):\n  ' + parts.join('\n  ') + '\n\nProceed?')) return;

        setBulkButtonsLoading(true);

        // Run both calls if needed; the existing endpoints already exist (Phase 3).
        // We chain promises so events/audit fire correctly per existing logic.
        var ids = selectedIdsArray();
        var p = $.Deferred().resolve();

        if (newStatus) {
            p = p.then(function () {
                return $.post('{{ route('order-centre.bulk.status') }}', { ids: ids, order_status: newStatus, also_mark_paid: alsoPaid ? 1 : 0 });
            });
        }
        if (newPayment) {
            p = p.then(function () {
                return $.post('{{ route('order-centre.bulk.payment') }}', { ids: ids, payment_status: newPayment });
            });
        }

        p.done(function (r) {
            bootstrap.Modal.getInstance(document.getElementById('oc-modal-update')).hide();
            var msg = 'Bulk update complete.';
            if (r && r.auto_paid_count > 0) msg += ' ' + r.auto_paid_count + ' also marked paid (rewards awarded).';
            toast(msg);
            // Clear the appropriate selection + reload the appropriate table
            if (window._naBulkActive) {
                clearNaSelectionAfterBulk();
            } else {
                selected.clear(); updateBulkBar();
            }
            reloadActiveOrdersTable();
        }).fail(function (xhr) {
            alert(xhr.responseJSON?.message || 'Bulk update failed. Check console.');
            console.error(xhr.responseJSON || xhr.responseText);
        }).always(function () {
            setBulkButtonsLoading(false);
            clearNaBulkFlag();
        });
    });

    $('#oc-modal-reschedule-submit').on('click', function () {
        var date = $('#oc-modal-reschedule-value').val();
        if (!date) { alert('Pick a delivery date first.'); return; }
        var activeCount = window._naBulkActive ? naSelected.size : selected.size;
        if (!confirm('Reschedule ' + activeCount + ' order(s) to ' + date + '?')) return;
        setBulkButtonsLoading(true);
        $.post('{{ route('order-centre.bulk.delivery-date') }}', {
            ids: selectedIdsArray(), preferred_delivery_date: date,
        }).done(function (r) {
            bootstrap.Modal.getInstance(document.getElementById('oc-modal-reschedule')).hide();
            toast(r.message || 'Done');
            if (window._naBulkActive) {
                clearNaSelectionAfterBulk();
            } else {
                selected.clear(); updateBulkBar();
            }
            reloadActiveOrdersTable();
        }).fail(function (xhr) {
            alert(xhr.responseJSON?.message || 'Bulk reschedule failed. Check console.');
            console.error(xhr.responseJSON || xhr.responseText);
        }).always(function () {
            setBulkButtonsLoading(false);
            clearNaBulkFlag();
        });
    });

    $('#oc-modal-notify-submit').on('click', function () {
        var subject = $('#oc-modal-notify-subject').val().trim();
        var body    = $('#oc-modal-notify-body').val().trim();
        if (!subject) { alert('Subject is required.'); return; }
        if (!body)    { alert('Message body is required.'); return; }
        if (!confirm('Send this email to ' + selected.size + ' customer(s)?')) return;
        setBulkButtonsLoading(true);
        $.post('{{ route('order-centre.bulk.notify') }}', {
            ids: selectedIdsArray(), subject: subject, body: body,
        }).done(function (r) {
            bootstrap.Modal.getInstance(document.getElementById('oc-modal-notify')).hide();
            toast(r.message || 'Done');
            $('#oc-modal-notify-subject, #oc-modal-notify-body').val('');
            selected.clear(); updateBulkBar();
        }).fail(function (xhr) {
            alert(xhr.responseJSON?.message || 'Bulk send failed. Check console.');
            console.error(xhr.responseJSON || xhr.responseText);
        }).always(function () { setBulkButtonsLoading(false); });
    });

    // =================================================================
    // CSV EXPORT (uses current filters)
    // =================================================================
    $('#oc-export-csv').on('click', function () {
        var params = $.param({
            search_text:    $('#oc-search').val() || '',
            date_from:      $('#oc-date-from').val() || '',
            date_to:        $('#oc-date-to').val() || '',
            value_min:      $('#oc-value-min').val() || '',
            value_max:      $('#oc-value-max').val() || '',
            suburb:         $('#oc-suburb').val() || '',
            source:         $('#oc-source').val() || '',
            order_status:   $('.oc-filter-status:checked').map(function(){return this.value;}).get().join(','),
            payment_status: $('.oc-filter-payment:checked').map(function(){return this.value;}).get().join(','),
            tags:           $('.oc-filter-tag:checked').map(function(){return this.value;}).get().join(','),
        });
        // Open in new window so user stays on page
        window.location.href = '{{ route('order-centre.export.csv') }}?' + params;
        toast('Preparing CSV download…');
    });

    // =================================================================
    // PHASE 4: MANUAL EDIT (drawer Edit tab)
    // =================================================================

    $('#oc-d-edit-recalc').on('click', function () {
        var sub = parseFloat($('#oc-d-edit-subtotal').val()) || 0;
        var del = parseFloat($('#oc-d-edit-delivery-fee').val()) || 0;
        var tax = parseFloat($('#oc-d-edit-tax').val()) || 0;
        var dis = parseFloat($('#oc-d-edit-discount').val()) || 0;
        $('#oc-d-edit-total').val((sub + del + tax - dis).toFixed(2));
    });

    $('#oc-d-edit-save').on('click', function () {
        if (!currentOrderId) return;
        var $btn = $(this);
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> Saving…');

        $.ajax({
            url: '{{ url('order-centre/orders') }}/' + currentOrderId + '/edit-details',
            method: 'POST',
            data: {
                subtotal:                $('#oc-d-edit-subtotal').val(),
                delivery_fee:            $('#oc-d-edit-delivery-fee').val(),
                discount:                $('#oc-d-edit-discount').val(),
                tax:                     $('#oc-d-edit-tax').val(),
                total:                   $('#oc-d-edit-total').val(),
                order_status:            $('#oc-d-edit-order-status').val(),
                payment_status:          $('#oc-d-edit-payment-status').val(),
                preferred_delivery_date: $('#oc-d-edit-delivery-date').val(),
                notes:                   $('#oc-d-edit-notes').val(),
            }
        }).done(function (r) {
            toast(r.message || 'Order updated');
            loadDrawerOrder(currentOrderId);
            table.ajax.reload(null, false);
            refreshStats();
        }).fail(function (xhr) {
            var msg = xhr.responseJSON?.message || 'Update failed';
            if (xhr.responseJSON?.errors) {
                msg += '\n\n' + Object.values(xhr.responseJSON.errors).flat().join('\n');
            }
            alert(msg);
            console.error(xhr.responseJSON || xhr.responseText);
        }).always(function () {
            $btn.prop('disabled', false).html('<i class="fa fa-save me-1"></i> Save Changes');
        });
    });

    // =================================================================
    // PHASE 4: NEEDS ACTION TAB
    // =================================================================

    var naTable = null;
    var naSelected = new Set();  // selected Needs Action order IDs (separate from App Orders' Set)

    // =================================================================
    // Auto-delivery (Settings → Automation tab)
    //
    // Wired at the parent scope (not inside initNeedsActionTable) so they
    // attach immediately on page load — these buttons live on the Settings
    // tab which may be the user's first stop. They'd never wire up if
    // attached inside a tab-init function.
    //
    // Uses event delegation on document so it works even if Bootstrap tabs
    // re-render parts of the DOM.
    // =================================================================
    $(document).on('change', '#oc-auto-deliver-toggle', function () {
        var enabled = this.checked;
        var $t = $(this).prop('disabled', true);
        $.ajax({
            url: '{{ route('order-centre.auto-deliver.toggle') }}',
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
            data: { enabled: enabled ? 1 : 0 }
        })
        .done(function (r) {
            if (r && r.ok) {
                $('#oc-auto-deliver-status')
                    .text(r.enabled ? 'ON' : 'OFF')
                    .removeClass('bg-success bg-secondary')
                    .addClass(r.enabled ? 'bg-success' : 'bg-secondary');
                if (typeof toast === 'function') toast(r.message || 'Setting updated.');
                else alert(r.message || 'Setting updated.');
            } else {
                alert((r && r.message) || 'Failed to update.');
                $t.prop('checked', !enabled);
            }
        })
        .fail(function (xhr) {
            alert(xhr.responseJSON?.message || ('HTTP ' + xhr.status));
            $t.prop('checked', !enabled);
        })
        .always(function () { $t.prop('disabled', false); });
    });

    $(document).on('click', '#oc-auto-deliver-preview', function () {
        var $btn = $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> Loading…');
        $.get('{{ route('order-centre.auto-deliver.preview') }}')
            .done(function (r) {
                if (r && r.ok) {
                    renderAutoDeliverResults('Tonight\'s auto-delivery preview', r.stats, true);
                } else {
                    alert((r && r.message) || 'Preview failed.');
                }
            })
            .fail(function (xhr) {
                alert(xhr.responseJSON?.message || ('HTTP ' + xhr.status));
            })
            .always(function () {
                $btn.prop('disabled', false).html('<i class="fa fa-eye me-1"></i> Preview tonight\'s sweep');
            });
    });

    $(document).on('click', '#oc-auto-deliver-runnow', function () {
        if (!confirm('Run the auto-delivery sweep right now?\n\nThis will mark verified-payment orders (Square / PayPal / card) as Delivered if their delivery date is today or earlier. Cash and unpaid orders will stay in Needs Action.\n\nClick OK to proceed.')) return;
        var $btn = $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> Running…');
        $.ajax({
            url: '{{ route('order-centre.auto-deliver.run') }}',
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
        })
        .done(function (r) {
            if (r && r.ok) {
                renderAutoDeliverResults(r.message || 'Auto-delivery complete', r.stats, false);
                if (naTable) naTable.ajax.reload(null, false);
                if (typeof refreshStats === 'function') refreshStats();
            } else {
                alert((r && r.message) || 'Run failed.');
            }
        })
        .fail(function (xhr) {
            alert(xhr.responseJSON?.message || ('HTTP ' + xhr.status));
        })
        .always(function () {
            $btn.prop('disabled', false).html('<i class="fa fa-play me-1"></i> Run sweep now');
        });
    });

    /**
     * Modal showing what the auto-delivery sweep would do or did.
     * stats = { delivered, skipped, errors, orders: [{order_number, customer, action, reason}, ...] }
     */
    function renderAutoDeliverResults(title, stats, isPreview) {
        // escapeHtml is defined elsewhere in this view's main script block.
        // Inline fallback in case load order changes:
        var esc = (typeof escapeHtml === 'function') ? escapeHtml : function (s) {
            return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
                return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
            });
        };

        var rows = (stats.orders || []).map(function (o) {
            var actionBadge = '<span class="badge bg-secondary">' + esc(o.action || '') + '</span>';
            if (o.action === 'delivered' || o.action === 'would_deliver') {
                actionBadge = '<span class="badge bg-success">' + (isPreview ? 'Would deliver' : 'Delivered') + '</span>';
            } else if (o.action === 'skipped') {
                actionBadge = '<span class="badge bg-warning text-dark">Skipped</span>';
            } else if (o.action === 'error') {
                actionBadge = '<span class="badge bg-danger">Error</span>';
            }
            return '<tr>' +
                '<td><strong>#' + esc(o.order_number || '?') + '</strong></td>' +
                '<td>' + esc(o.customer || '') + '</td>' +
                '<td><small>' + esc(o.payment_method || '-') + ' / ' + esc(o.payment_status || '-') + '</small></td>' +
                '<td>' + actionBadge + '</td>' +
                '<td><small class="text-muted">' + esc(o.reason || '') + '</small></td>' +
            '</tr>';
        }).join('') || '<tr><td colspan="5" class="text-center text-muted py-3">No orders to process today.</td></tr>';

        var html =
            '<div class="modal fade" id="oc-auto-deliver-modal" tabindex="-1">' +
              '<div class="modal-dialog modal-lg modal-dialog-scrollable">' +
                '<div class="modal-content">' +
                  '<div class="modal-header bg-primary text-white">' +
                    '<h5 class="modal-title"><i class="fa fa-robot me-2"></i>' + esc(title) + '</h5>' +
                    '<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>' +
                  '</div>' +
                  '<div class="modal-body">' +
                    '<div class="row text-center mb-3">' +
                      '<div class="col"><div class="h3 mb-0 text-success">' + (stats.delivered || 0) + '</div><small>' + (isPreview ? 'Would deliver' : 'Delivered') + '</small></div>' +
                      '<div class="col"><div class="h3 mb-0 text-warning">' + (stats.skipped || 0) + '</div><small>Skipped (needs admin)</small></div>' +
                      '<div class="col"><div class="h3 mb-0 text-danger">' + (stats.errors || 0) + '</div><small>Errors</small></div>' +
                    '</div>' +
                    '<table class="table table-sm">' +
                      '<thead><tr><th>Order</th><th>Customer</th><th>Payment</th><th>Action</th><th>Reason</th></tr></thead>' +
                      '<tbody>' + rows + '</tbody>' +
                    '</table>' +
                  '</div>' +
                  '<div class="modal-footer">' +
                    '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>' +
                  '</div>' +
                '</div>' +
              '</div>' +
            '</div>';
        $('#oc-auto-deliver-modal').remove();
        $('body').append(html);
        new bootstrap.Modal(document.getElementById('oc-auto-deliver-modal')).show();
    }


    function initNeedsActionTable() {
        if (naTable) return;
        naTable = $('#oc-na-table').DataTable({
            processing: true,
            serverSide: true,
            dom: 'rtip',
            pageLength: 25,
            autoWidth: false,
            ajax: {
                url: '{{ route('order-centre.data.needs-action') }}',
                data: function (d) {
                    // Pass the "Show dismissed" toggle state to the server so
                    // it knows whether to include dismissed rows.
                    d.include_dismissed = $('#oc-na-show-dismissed').is(':checked') ? 1 : 0;
                },
                error: function (xhr, status) {
                    console.error('[Needs Action] AJAX error:', status, xhr.responseText);
                }
            },
            order: [],
            columns: [
                // 0: Checkbox column — not a data field, render only
                { data: null, orderable: false, render: function (data, type, row) {
                    var checked = naSelected.has(String(row.id)) ? ' checked' : '';
                    return '<input type="checkbox" class="oc-na-check" value="' + row.id + '"' + checked + '>';
                }, className: 'oc-na-check-col' },
                { data: 'reason', orderable: false, render: function (reasons, type, row) {
                    if (!Array.isArray(reasons)) reasons = [reasons];
                    var html = reasons.map(function (r) {
                        var cls = 'bg-secondary';
                        if (/Overdue/i.test(r)) cls = 'bg-danger';
                        else if (/Awaiting confirmation/i.test(r)) cls = 'bg-warning text-dark';
                        else if (/Needs scheduling/i.test(r)) cls = 'bg-info text-dark';
                        return '<span class="badge ' + cls + ' me-1">' + escapeHtml(r) + '</span>';
                    }).join('');
                    // Visual cue when this row is currently dismissed
                    if (row.is_dismissed) {
                        html = '<span class="badge bg-dark me-1">Dismissed</span>' + html;
                    }
                    return html;
                }},
                { data: 'order_number', render: function (d) { return '<span class="fw-bold text-primary">#' + d + '</span>'; } },
                { data: 'customer', orderable: false, render: function (d) {
                    return '<div class="d-flex align-items-center"><img src="' + d.avatar + '" class="oc-customer-avatar me-2"><div><div class="fw-semibold oc-customer-link" style="font-size:.9rem" data-customer-id="' + (d.id || '') + '">' + escapeHtml(d.name) + '</div><div class="text-muted small">' + escapeHtml(d.email) + '</div></div></div>';
                }},
                { data: 'suburb' },
                { data: 'total_formatted', render: function (d) { return '<span class="fw-bold">$' + d + '</span>'; } },
                { data: 'delivery_date' },
                { data: 'created_at_human' },
                // Actions: Dismiss for active rows, Restore for already-dismissed
                { data: 'is_dismissed', orderable: false, render: function (dis, type, row) {
                    if (dis) {
                        return '<button class="btn btn-sm btn-outline-success oc-na-restore-btn" data-id="' + row.id + '" title="Restore to Needs Action"><i class="fa fa-undo me-1"></i> Restore</button>';
                    }
                    return '<button class="btn btn-sm btn-outline-warning oc-na-dismiss-btn" data-id="' + row.id + '" title="Hide from Needs Action (returns automatically if state changes)"><i class="fa fa-eye-slash me-1"></i> Dismiss</button>';
                }, className: 'text-end' }
            ],
            rowCallback: function (row, data) {
                $(row).attr('data-order-id', data.id);
                // Dim dismissed rows for visual contrast
                if (data.is_dismissed) {
                    $(row).css({ 'opacity': '0.55', 'background': '#fafafa' });
                }
            },
            drawCallback: function () {
                var info = this.api().page.info();
                $('#oc-na-summary').text(info.recordsDisplay + ' order(s) need action');
                // Re-sync the "select all" checkbox state
                syncNaCheckAll();
            }
        });

        // Row click → drawer (but ignore clicks on the checkbox or action buttons)
        $('#oc-na-table tbody').on('click', 'tr', function (e) {
            if ($(e.target).is('input[type="checkbox"]') ||
                $(e.target).closest('.oc-na-check-col, button').length) return;
            var id = $(this).data('order-id');
            if (id) openDrawer(id);
        });

        // Single-row Dismiss
        $('#oc-na-table tbody').on('click', '.oc-na-dismiss-btn', function (e) {
            e.stopPropagation();
            var orderId = $(this).data('id');
            if (!confirm('Dismiss this order from Needs Action?\n\nIt will reappear automatically if anything changes (payment, status, or delivery date).')) return;
            postDismissOrRestore('dismiss', orderId);
        });

        // Single-row Restore
        $('#oc-na-table tbody').on('click', '.oc-na-restore-btn', function (e) {
            e.stopPropagation();
            var orderId = $(this).data('id');
            postDismissOrRestore('restore', orderId);
        });

        // Show / hide dismissed toggle — reload the table with the flag
        $('#oc-na-show-dismissed').on('change', function () {
            if (naTable) naTable.ajax.reload(null, false);
        });

        // Per-row checkbox toggle
        $('#oc-na-table tbody').on('change', '.oc-na-check', function (e) {
            e.stopPropagation();
            var id = String($(this).val());
            if (this.checked) naSelected.add(id);
            else              naSelected.delete(id);
            updateNaBulkBar();
            syncNaCheckAll();
        });

        // Select-all checkbox — toggles every row currently visible
        $('#oc-na-check-all').on('change', function () {
            var on = this.checked;
            $('#oc-na-table tbody .oc-na-check').each(function () {
                this.checked = on;
                var id = String(this.value);
                if (on) naSelected.add(id);
                else    naSelected.delete(id);
            });
            updateNaBulkBar();
        });
    }

    function syncNaCheckAll() {
        var $boxes = $('#oc-na-table tbody .oc-na-check');
        if (!$boxes.length) {
            $('#oc-na-check-all').prop('checked', false).prop('indeterminate', false);
            return;
        }
        var checkedCount = $boxes.filter(':checked').length;
        $('#oc-na-check-all').prop('checked', checkedCount === $boxes.length)
                             .prop('indeterminate', checkedCount > 0 && checkedCount < $boxes.length);
    }

    function updateNaBulkBar() {
        var n = naSelected.size;
        $('#oc-na-bulk-count').text(n);
        $('#oc-na-bulk-bar').toggleClass('d-none', n === 0);
    }

    // Clear selection button
    $('#oc-na-bulk-clear').on('click', function () {
        naSelected.clear();
        $('#oc-na-table tbody .oc-na-check').prop('checked', false);
        $('#oc-na-check-all').prop('checked', false).prop('indeterminate', false);
        updateNaBulkBar();
    });

    // Bulk action buttons — reuse the existing bulk modals (oc-modal-update,
    // oc-modal-reschedule) so the same well-tested code path handles both
    // App Orders bulk and Needs Action bulk. The modals already POST to
    // /order-centre/bulk/status, /bulk/payment, /bulk/reschedule which fire
    // OrderCancelled / OrderDelivered events and trigger reward retraction
    // and award flows identical to single-order updates.
    $('.oc-na-bulk-btn').on('click', function () {
        if (naSelected.size === 0) return;
        var action = $(this).data('action');
        // Set a flag so the modal-submit handler knows to use naSelected
        // (rather than the App Orders' `selected` Set) when posting.
        window._naBulkActive = true;
        if (action === 'update') {
            $('#oc-modal-update-status').val('');
            $('#oc-modal-update-payment').val('');
            $('#oc-modal-also-paid').prop('checked', true);
            $('#oc-modal-also-paid-wrap').addClass('d-none');
            $('#oc-modal-update').find('.oc-modal-count').text(naSelected.size);
            new bootstrap.Modal(document.getElementById('oc-modal-update')).show();
        } else if (action === 'reschedule') {
            $('#oc-modal-reschedule').find('.oc-modal-count').text(naSelected.size);
            new bootstrap.Modal(document.getElementById('oc-modal-reschedule')).show();
        } else if (action === 'mark-delivered') {
            // One-click bulk Mark as Delivered.
            //
            // Posts to the EXISTING /order-centre/bulk/status endpoint with:
            //   - order_status   = delivered
            //   - also_mark_paid = 1 (so any pending payments flip to paid too)
            //
            // The existing controller already fires OrderDelivered events
            // and (for orders that flip from pending → paid) calls awardRewards()
            // so loyalty points + entries are credited automatically. Same code
            // path as single-order Mark as Delivered, just batched.
            var n = naSelected.size;
            if (!confirm('Mark ' + n + ' order(s) as Delivered + Paid?\n\nLoyalty points and entries will be awarded for any orders flipping to paid.')) {
                window._naBulkActive = false;  // reset; user cancelled
                return;
            }

            var $btn = $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> Marking…');
            setBulkButtonsLoading(true);

            $.post('{{ route('order-centre.bulk.status') }}', {
                ids:            selectedIdsArray(),
                order_status:   'delivered',
                also_mark_paid: 1,
            })
            .done(function (r) {
                var msg = (r && r.message) ? r.message : ('Marked ' + n + ' order(s) as Delivered.');
                if (r && r.auto_paid_count > 0) {
                    msg += ' ' + r.auto_paid_count + ' also marked paid — rewards awarded.';
                }
                toast(msg);
                clearNaSelectionAfterBulk();
                reloadActiveOrdersTable();
            })
            .fail(function (xhr) {
                alert(xhr.responseJSON?.message || 'Bulk mark-delivered failed. Check console.');
                console.error(xhr.responseJSON || xhr.responseText);
            })
            .always(function () {
                $btn.prop('disabled', false).html('<i class="fa fa-check-double me-1"></i> Mark as Delivered');
                setBulkButtonsLoading(false);
                clearNaBulkFlag();
            });
        } else if (action === 'dismiss') {
            // Bulk dismiss — same idea as single dismiss, but for the whole
            // selected set.
            var n = naSelected.size;
            if (!confirm('Dismiss ' + n + ' order(s) from Needs Action?\n\nDismissed orders will reappear automatically if anything changes (payment, status, or delivery date).')) {
                window._naBulkActive = false;
                return;
            }
            var $btn = $(this).prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> Dismissing…');
            setBulkButtonsLoading(true);

            $.post('{{ route('order-centre.bulk.dismiss') }}', {
                ids: selectedIdsArray(),
            })
            .done(function (r) {
                toast((r && r.message) || ('Dismissed ' + n + ' order(s).'));
                clearNaSelectionAfterBulk();
                reloadActiveOrdersTable();
            })
            .fail(function (xhr) {
                alert(xhr.responseJSON?.message || 'Bulk dismiss failed. Check console.');
                console.error(xhr.responseJSON || xhr.responseText);
            })
            .always(function () {
                $btn.prop('disabled', false).html('<i class="fa fa-eye-slash me-1"></i> Dismiss');
                setBulkButtonsLoading(false);
                clearNaBulkFlag();
            });
        }
    });

    /**
     * Shared helper for single-row dismiss / restore. Hits the
     * /orders/{id}/dismiss or /orders/{id}/restore endpoint and
     * reloads the Needs Action table on success.
     */
    function postDismissOrRestore(action, orderId) {
        var base = '{{ url('order-centre/orders') }}/' + orderId + '/';
        var url = base + (action === 'dismiss' ? 'dismiss' : 'restore');

        $.post(url)
            .done(function (r) {
                toast((r && r.message) || (action === 'dismiss' ? 'Dismissed.' : 'Restored.'));
                if (naTable) naTable.ajax.reload(null, false);
                if (typeof refreshStats === 'function') refreshStats();
            })
            .fail(function (xhr) {
                alert(xhr.responseJSON?.message || (action === 'dismiss' ? 'Dismiss' : 'Restore') + ' failed. Check console.');
                console.error(xhr.responseJSON || xhr.responseText);
            });
    }

    // Init when tab first becomes visible
    $('a[data-bs-toggle="tab"][href="#needs-action"]').on('shown.bs.tab', function () {
        initNeedsActionTable();
        if (naTable) naTable.ajax.reload(null, false);
    });

    // =================================================================
    // PHASE 4: REFUNDS QUEUE TAB
    // =================================================================

    var rqTable = null;
    function initRefundsQueueTable() {
        if (rqTable) return;
        rqTable = $('#oc-rq-table').DataTable({
            processing: true,
            serverSide: true,
            dom: 'rtip',
            pageLength: 25,
            autoWidth: false,
            ajax: {
                url: '{{ route('order-centre.data.refunds-queue') }}',
                data: function (d) {
                    d.include_dismissed = $('#oc-rq-include-dismissed').is(':checked') ? 1 : 0;
                },
                error: function (xhr, status) {
                    console.error('[Refunds Queue] AJAX error:', status, xhr.responseText);
                }
            },
            order: [],
            columns: [
                { data: 'order_number', render: function (d) { return '<span class="fw-bold text-primary">#' + d + '</span>'; } },
                { data: 'customer', orderable: false, render: function (d) {
                    return '<div class="d-flex align-items-center"><img src="' + d.avatar + '" class="oc-customer-avatar me-2"><div><div class="fw-semibold oc-customer-link" style="font-size:.9rem" data-customer-id="' + (d.id || '') + '">' + escapeHtml(d.name) + '</div><div class="text-muted small">' + escapeHtml(d.email) + '</div></div></div>';
                }},
                { data: 'total_formatted',     render: function (d) { return '<span>$' + d + '</span>'; } },
                { data: 'refunded_amount',     render: function (d) { return '<span class="text-warning">$' + d + '</span>'; } },
                { data: 'remaining_amount',   render: function (d) { return '<span class="fw-bold text-success">$' + d + '</span>'; } },
                { data: 'refund_status',       render: function (d) {
                    var map = {none:'bg-secondary', partial:'bg-warning text-dark', full:'bg-danger', pending:'bg-info text-dark'};
                    return '<span class="badge ' + (map[d] || 'bg-secondary') + '">' + d + '</span>';
                }},
                { data: 'payment_method' },
                { data: 'cancelled_at' },
                { data: 'is_dismissed', orderable: false, render: function (dis, t, row) {
                    var openLink = '<a href="/order/' + row.id + '/edit" target="_blank" class="btn btn-sm btn-outline-primary me-1" title="Open legacy refund flow"><i class="fa fa-external-link-alt"></i></a>';
                    var btn = dis
                        ? '<button class="btn btn-sm btn-outline-secondary oc-rq-restore" data-id="' + row.id + '" title="Restore to queue"><i class="fa fa-undo"></i> Restore</button>'
                        : '<button class="btn btn-sm btn-outline-secondary oc-rq-dismiss" data-id="' + row.id + '" title="Dismiss"><i class="fa fa-eye-slash"></i> Dismiss</button>';
                    return openLink + btn;
                }}
            ],
            rowCallback: function (row, data) { $(row).attr('data-order-id', data.id); },
            drawCallback: function () {
                var info = this.api().page.info();
                $('#oc-rq-summary').text(info.recordsDisplay + ' eligible order(s)');
            }
        });

        // Row click (excluding action buttons) → drawer
        $('#oc-rq-table tbody').on('click', 'tr', function (e) {
            if ($(e.target).closest('button, a').length) return;
            var id = $(this).data('order-id');
            if (id) openDrawer(id);
        });

        // Dismiss / restore handlers
        $('#oc-rq-table tbody').on('click', '.oc-rq-dismiss', function (e) {
            e.stopPropagation();
            var id = $(this).data('id');
            $.post('{{ url('order-centre/orders') }}/' + id + '/refund-dismiss')
                .done(function (r) { toast(r.message); rqTable.ajax.reload(null, false); })
                .fail(function () { alert('Dismiss failed'); });
        });
        $('#oc-rq-table tbody').on('click', '.oc-rq-restore', function (e) {
            e.stopPropagation();
            var id = $(this).data('id');
            $.post('{{ url('order-centre/orders') }}/' + id + '/refund-restore')
                .done(function (r) { toast(r.message); rqTable.ajax.reload(null, false); })
                .fail(function () { alert('Restore failed'); });
        });

        $('#oc-rq-include-dismissed').on('change', function () {
            rqTable.ajax.reload(null, false);
        });
    }

    $('a[data-bs-toggle="tab"][href="#refunds-queue"]').on('shown.bs.tab', function () {
        initRefundsQueueTable();
        if (rqTable) rqTable.ajax.reload(null, false);
    });

    // =================================================================
    // PHASE 5: ANALYTICS DASHBOARD
    // =================================================================

    var anCharts = { revenue: null, delivery: null, customers: null, products: null };
    var anCurrentDays = 30;
    var anChartJsLoaded = false;

    function loadChartJsThen(callback) {
        if (typeof window.Chart !== 'undefined') { callback(); return; }
        if (anChartJsLoaded) return;  // already loading
        anChartJsLoaded = true;
        var s = document.createElement('script');
        s.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js';
        s.onload = callback;
        document.head.appendChild(s);
    }

    function fetchAndRenderAnalytics(days) {
        anCurrentDays = days;
        $('#oc-an-kpi-revenue, #oc-an-kpi-orders, #oc-an-kpi-aov, #oc-an-kpi-customers').text('…');
        $.get('{{ route('order-centre.data.analytics') }}', { days: days })
            .done(function (d) {
                $('#oc-an-kpi-revenue').text('$' + Number(d.kpi.total_revenue).toLocaleString(undefined, {maximumFractionDigits:0}));
                $('#oc-an-kpi-orders').text(Number(d.kpi.total_orders).toLocaleString());
                $('#oc-an-kpi-aov').text('$' + Number(d.kpi.avg_order_value).toLocaleString(undefined, {maximumFractionDigits:2}));
                $('#oc-an-kpi-customers').text(Number(d.kpi.new_customers).toLocaleString());
                renderAnalyticsCharts(d);
            })
            .fail(function (xhr) {
                console.error('[Analytics] failed:', xhr.responseText);
                toast('Failed to load analytics');
            });
    }

    function renderAnalyticsCharts(d) {
        Object.keys(anCharts).forEach(function (k) { if (anCharts[k]) { anCharts[k].destroy(); anCharts[k] = null; } });

        // Revenue line
        anCharts.revenue = new Chart(document.getElementById('oc-an-chart-revenue'), {
            type: 'line',
            data: {
                labels: d.revenue_trend.labels,
                datasets: [
                    { label: 'Revenue ($)', data: d.revenue_trend.revenue, borderColor: '#667eea', backgroundColor: 'rgba(102,126,234,0.15)', tension: 0.3, fill: true, yAxisID: 'y' },
                    { label: 'Orders',      data: d.revenue_trend.order_count, borderColor: '#f59e0b', backgroundColor: 'transparent', tension: 0.3, yAxisID: 'y1' }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                scales: {
                    y:  { type: 'linear', position: 'left',  title: {display: true, text: 'Revenue ($)'} },
                    y1: { type: 'linear', position: 'right', title: {display: true, text: 'Orders'}, grid: {drawOnChartArea: false} }
                }
            }
        });

        // Delivery donut
        var delivery = d.delivery_performance;
        anCharts.delivery = new Chart(document.getElementById('oc-an-chart-delivery'), {
            type: 'doughnut',
            data: {
                labels: ['Delivered', 'Upcoming', 'Overdue', 'Awaiting Schedule', 'Cancelled'],
                datasets: [{
                    data: [delivery.delivered, delivery.upcoming, delivery.overdue, delivery.awaiting_schedule, delivery.cancelled],
                    backgroundColor: ['#10b981', '#3b82f6', '#ef4444', '#f59e0b', '#6b7280']
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom' } } }
        });

        // Top customers bar
        anCharts.customers = new Chart(document.getElementById('oc-an-chart-customers'), {
            type: 'bar',
            data: {
                labels: d.top_customers.map(function (c) { return c.name.length > 22 ? c.name.substring(0,22) + '…' : c.name; }),
                datasets: [{ label: 'Revenue ($)', data: d.top_customers.map(function (c) { return c.revenue; }), backgroundColor: '#667eea' }]
            },
            options: { responsive: true, maintainAspectRatio: false, indexAxis: 'y', plugins: { legend: { display: false } } }
        });

        // Top products bar
        anCharts.products = new Chart(document.getElementById('oc-an-chart-products'), {
            type: 'bar',
            data: {
                labels: d.top_products.map(function (p) { return p.name.length > 22 ? p.name.substring(0,22) + '…' : p.name; }),
                datasets: [{ label: 'Quantity', data: d.top_products.map(function (p) { return p.qty; }), backgroundColor: '#10b981' }]
            },
            options: { responsive: true, maintainAspectRatio: false, indexAxis: 'y', plugins: { legend: { display: false } } }
        });
    }

    $('.oc-an-range').on('click', function () {
        $('.oc-an-range').removeClass('active');
        $(this).addClass('active');
        var days = parseInt($(this).data('days'), 10);
        loadChartJsThen(function () { fetchAndRenderAnalytics(days); });
    });

    $('a[data-bs-toggle="tab"][href="#analytics"]').on('shown.bs.tab', function () {
        loadChartJsThen(function () { fetchAndRenderAnalytics(anCurrentDays); });
    });

    // =================================================================
    // PHASE 6: WOOCOMMERCE SETTINGS
    // =================================================================

    function loadWooCommerceSettings() {
        $.get('{{ route('order-centre.woocommerce.get') }}').done(function (d) {
            $('#oc-wc-url').val(d.site_url || '');
            $('#oc-wc-key').val(d.consumer_key || '');
            $('#oc-wc-secret').val(d.consumer_secret || '');
            $('#oc-wc-webhook').val(d.webhook_secret || '');
            $('#oc-wc-enabled').prop('checked', !!d.sync_enabled);
            if (d.last_sync_at) {
                $('#oc-wc-last-sync').text('Last tested: ' + d.last_sync_at);
            } else {
                $('#oc-wc-last-sync').text('');
            }

            // Connection status pill
            var $pill = $('#oc-wc-status-pill');
            var $label = $('#oc-wc-status-label');
            $pill.removeClass('connected not-configured error');
            if (d.site_url && d.consumer_key && d.has_secret) {
                $pill.addClass('connected');
                $label.text(d.sync_enabled ? 'Connected · Write mode on' : 'Connected · Read-only');
            } else {
                $pill.addClass('not-configured');
                $label.text('Not configured');
            }
        }).fail(function () {
            $('#oc-wc-status-pill').removeClass('connected not-configured').addClass('error');
            $('#oc-wc-status-label').text('Error loading');
        });
    }

    $('a[data-bs-toggle="tab"][href="#settings"]').on('shown.bs.tab', loadWooCommerceSettings);
    $('a[data-bs-toggle="tab"][href="#settings"]').on('shown.bs.tab', loadNotificationSettings);

    // ---- Settings sub-tab switcher (Connection / Notifications / Order Tags / Maintenance) ----
    $('#oc-settings-subtabs').on('click', 'a.nav-link', function (e) {
        e.preventDefault();
        var section = $(this).data('section');
        $('#oc-settings-subtabs a.nav-link').removeClass('active');
        $(this).addClass('active');
        $('.oc-settings-pane').addClass('d-none');
        $('.oc-settings-pane[data-section="' + section + '"]').removeClass('d-none');
    });

    // ---- Notification settings (Phase 10 hotfix v3) ----

    function loadNotificationSettings() {
        $('#oc-notif-loading').removeClass('d-none');
        $('#oc-notif-form').addClass('d-none');
        $.get('{{ route('order-centre.notifications.get') }}').done(function (r) {
            var s = r.settings || {};
            // Use !! coercion — handles true/false/0/1/null/undefined consistently
            $('#oc-notif-email-placed').prop('checked',     !!s.email_order_placed);
            $('#oc-notif-email-delivered').prop('checked',  !!s.email_order_delivered);
            $('#oc-notif-email-cancelled').prop('checked',  !!s.email_order_cancelled);
            $('#oc-notif-email-delivery').prop('checked',   !!s.email_order_delivery_date_updated);
            $('#oc-notif-email-refund').prop('checked',     !!s.email_payment_refunded);

            $('#oc-notif-push-placed').prop('checked',      !!s.push_order_placed);
            $('#oc-notif-push-delivered').prop('checked',   !!s.push_order_delivered);
            $('#oc-notif-push-cancelled').prop('checked',   !!s.push_order_cancelled);
            $('#oc-notif-push-delivery').prop('checked',    !!s.push_order_delivery_date_updated);
            $('#oc-notif-push-refund').prop('checked',      !!s.push_payment_refunded);

            $('#oc-notif-bcc-enabled').prop('checked', !!s.admin_bcc_enabled);
            $('#oc-notif-bcc-address').val(s.admin_bcc_address || 'admin@Jjsfirewoodperth.com.au');

            // Setmore delivery emails
            $('#oc-setmore-master').prop('checked',      !!s.setmore_email_master);
            $('#oc-setmore-booked').prop('checked',      !!s.setmore_email_booked);
            $('#oc-setmore-rescheduled').prop('checked', !!s.setmore_email_rescheduled);
            $('#oc-setmore-cancelled').prop('checked',   !!s.setmore_email_cancelled);
            $('#oc-setmore-bcc').prop('checked',         !!s.setmore_email_bcc_admin);
            $('#oc-setmore-mailer').val(s.setmore_email_mailer || 'brevo');
            $('#oc-setmore-test-email').val(s.setmore_email_test_recipient || 'admin@jjsfirewoodperth.com.au');

            $('#oc-notif-loading').addClass('d-none');
            $('#oc-notif-form').removeClass('d-none');
        }).fail(function () {
            $('#oc-notif-loading').text('Failed to load notification settings.');
        });
    }

    $('#oc-notif-save').on('click', function () {
        var $btn = $(this);
        var $status = $('#oc-notif-status');
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> Saving…');
        $status.removeClass('text-success text-danger').text('');

        $.post('{{ route('order-centre.notifications.save') }}', {
            email_order_placed:                $('#oc-notif-email-placed').is(':checked') ? 1 : 0,
            email_order_delivered:             $('#oc-notif-email-delivered').is(':checked') ? 1 : 0,
            email_order_cancelled:             $('#oc-notif-email-cancelled').is(':checked') ? 1 : 0,
            email_order_delivery_date_updated: $('#oc-notif-email-delivery').is(':checked') ? 1 : 0,
            email_payment_refunded:            $('#oc-notif-email-refund').is(':checked') ? 1 : 0,
            push_order_placed:                 $('#oc-notif-push-placed').is(':checked') ? 1 : 0,
            push_order_delivered:              $('#oc-notif-push-delivered').is(':checked') ? 1 : 0,
            push_order_cancelled:              $('#oc-notif-push-cancelled').is(':checked') ? 1 : 0,
            push_order_delivery_date_updated:  $('#oc-notif-push-delivery').is(':checked') ? 1 : 0,
            push_payment_refunded:             $('#oc-notif-push-refund').is(':checked') ? 1 : 0,
            admin_bcc_enabled:                 $('#oc-notif-bcc-enabled').is(':checked') ? 1 : 0,
            admin_bcc_address:                 $('#oc-notif-bcc-address').val(),
            setmore_email_master:              $('#oc-setmore-master').is(':checked') ? 1 : 0,
            setmore_email_booked:              $('#oc-setmore-booked').is(':checked') ? 1 : 0,
            setmore_email_rescheduled:         $('#oc-setmore-rescheduled').is(':checked') ? 1 : 0,
            setmore_email_cancelled:           $('#oc-setmore-cancelled').is(':checked') ? 1 : 0,
            setmore_email_bcc_admin:           $('#oc-setmore-bcc').is(':checked') ? 1 : 0,
            setmore_email_mailer:              $('#oc-setmore-mailer').val(),
            _token: '{{ csrf_token() }}'
        }).done(function (r) {
            $btn.prop('disabled', false).html('<i class="fa fa-save me-1"></i> Save Notification Settings');
            $status.addClass('text-success').text(r.message || 'Saved.');
            setTimeout(function () { $status.text(''); }, 4000);
        }).fail(function (xhr) {
            $btn.prop('disabled', false).html('<i class="fa fa-save me-1"></i> Save Notification Settings');
            $status.addClass('text-danger').text((xhr.responseJSON && xhr.responseJSON.message) || 'Save failed');
        });
    });

    // Send a sample delivery email on demand
    $('#oc-setmore-test-btn').on('click', function () {
        var $btn = $(this);
        var $st = $('#oc-setmore-test-status');
        var type = $('#oc-setmore-test-type').val();
        var email = $('#oc-setmore-test-email').val();
        if (!email) { $st.removeClass('text-success').addClass('text-danger').text('Enter an email address first.'); return; }
        $btn.prop('disabled', true).text('Sending…');
        $st.removeClass('text-success text-danger').text('');
        $.post('{{ route('order-centre.notifications.delivery-test') }}', {
            type: type, email: email, _token: '{{ csrf_token() }}'
        }).done(function (r) {
            $btn.prop('disabled', false).text('Send');
            $st.addClass('text-success').text(r.message || 'Test sent.');
            setTimeout(function () { $st.text(''); }, 6000);
        }).fail(function (xhr) {
            $btn.prop('disabled', false).text('Send');
            $st.addClass('text-danger').text((xhr.responseJSON && xhr.responseJSON.message) || 'Send failed.');
        });
    });

    $('#oc-wc-save').on('click', function () {
        var $btn = $(this);
        $btn.prop('disabled', true);
        $.post('{{ route('order-centre.woocommerce.save') }}', {
            site_url:        $('#oc-wc-url').val(),
            consumer_key:    $('#oc-wc-key').val(),
            consumer_secret: $('#oc-wc-secret').val(),
            webhook_secret:  $('#oc-wc-webhook').val(),
            sync_enabled:    $('#oc-wc-enabled').is(':checked') ? 1 : 0,
        }).done(function (r) {
            toast(r.message || 'Saved');
            loadWooCommerceSettings();  // reload to show masked secrets
        }).fail(function (xhr) {
            alert(xhr.responseJSON?.message || 'Save failed');
        }).always(function () { $btn.prop('disabled', false); });
    });

    $('#oc-wc-test').on('click', function () {
        var $btn = $(this);
        var $result = $('#oc-wc-test-result');
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> Testing…');
        $result.empty();
        $.post('{{ route('order-centre.woocommerce.test') }}').done(function (r) {
            var cls = r.success ? 'alert-success' : 'alert-danger';
            var icon = r.success ? 'fa-check-circle' : 'fa-times-circle';
            $result.html('<div class="alert ' + cls + ' mb-0"><i class="fa ' + icon + ' me-1"></i> ' + escapeHtml(r.message) + '</div>');
        }).fail(function (xhr) {
            $result.html('<div class="alert alert-danger mb-0"><i class="fa fa-times-circle me-1"></i> ' + escapeHtml(xhr.responseJSON?.message || 'Test failed') + '</div>');
        }).always(function () {
            $btn.prop('disabled', false).html('<i class="fa fa-plug me-1"></i> Test Connection');
        });
    });

    // =================================================================
    // PHASE 7: WEBSITE ORDERS TAB + WOOCOMMERCE SYNC
    // =================================================================

    var woTable = null;

    function initWebsiteOrdersTable() {
        if (woTable) return;
        woTable = $('#oc-wo-table').DataTable({
            processing: true,
            serverSide: true,
            dom: 'rtip',
            pageLength: 25,
            autoWidth: false,
            ajax: {
                url: '{{ route('order-centre.data.website-orders') }}',
                data: function (d) {
                    d.search_text = $('#oc-wo-search').val() || '';
                    d.wc_status   = $('#oc-wo-status-filter').val() || '';
                },
                error: function (xhr, status) {
                    console.error('[Website Orders] AJAX error:', status, xhr.responseText);
                }
            },
            order: [],
            columns: [
                { data: 'woocommerce_id', orderable: false, render: function (id, t, row) {
                    if (!id) return '<span class="text-muted">—</span>';
                    var label = row.woocommerce_number ? '#' + row.woocommerce_number : 'WC#' + id;
                    if (row.wc_link) {
                        return '<a href="' + row.wc_link + '" target="_blank" class="text-primary fw-semibold" title="Open in WP admin">' + escapeHtml(label) + ' <i class="fa fa-external-link-alt small ms-1"></i></a>';
                    }
                    return '<span class="fw-semibold">' + escapeHtml(label) + '</span>';
                }},
                { data: 'customer', orderable: false, render: function (d) {
                    return '<div><div class="fw-semibold oc-wc-customer-link" style="font-size:.9rem; cursor: pointer; color:#667eea;" data-wc-customer-id="' + (d.id || '') + '">' + escapeHtml(d.name) + '</div>'
                         + '<div class="text-muted small">' + escapeHtml(d.email || '') + (d.phone ? ' · ' + escapeHtml(d.phone) : '') + '</div></div>';
                }},
                { data: 'suburb' },
                { data: 'total_formatted', render: function (d, t, row) {
                    var html = '<span class="fw-bold">$' + d + '</span>';
                    if (row.refund_formatted && parseFloat(row.refund_formatted.replace(/,/g,'')) > 0) {
                        html += '<div class="text-danger small">−$' + row.refund_formatted + ' refunded</div>';
                    }
                    return html;
                }},
                { data: 'wc_status', render: function (s) {
                    return wcStatusBadge(s);
                }},
                { data: 'payment_status', render: function (s) {
                    return paymentBadges[s] || ('<span class="badge bg-secondary">' + s + '</span>');
                }},
                { data: 'delivery_date' },
                { data: 'created_human' },
                { data: null, orderable: false, render: function (d, t, row) {
                    return '<button class="btn btn-sm btn-outline-primary oc-wc-open-btn" data-wc-order-id="' + row.id + '" title="View details"><i class="fa fa-eye"></i></button>';
                }}
            ],
            rowCallback: function (row, data) {
                $(row).attr('data-wc-order-id', data.id);
            },
            drawCallback: function (settings) {
                var info = woTable.page.info();
                $('#oc-wo-result-count').text(
                    info.recordsTotal === 0 ? 'No orders' :
                    'Showing ' + (info.start + 1) + '–' + info.end + ' of ' + info.recordsTotal + ' orders'
                );
            }
        });

        // Row click → WC drawer (entire row is clickable EXCEPT links/customer name)
        $('#oc-wo-table tbody').off('click.wcrow').on('click.wcrow', 'tr', function (e) {
            if ($(e.target).closest('a, .oc-wc-customer-link').length) return;
            var id = $(this).attr('data-wc-order-id');
            if (id) window.openWcDrawer(parseInt(id, 10));
        });

        // Explicit "view" button click
        $('#oc-wo-table tbody').off('click.wcopen').on('click.wcopen', '.oc-wc-open-btn', function (e) {
            e.stopPropagation();
            var id = $(this).attr('data-wc-order-id');
            if (id) window.openWcDrawer(parseInt(id, 10));
        });

        // Customer name click → WC customer modal
        $('#oc-wo-table tbody').off('click.wccust').on('click.wccust', '.oc-wc-customer-link', function (e) {
            e.stopPropagation();
            var cid = $(this).attr('data-wc-customer-id');
            if (cid) window.openWcCustomerProfile(parseInt(cid, 10));
        });

        // Search bar
        var woSearchTimer;
        $('#oc-wo-search').on('input', function () {
            clearTimeout(woSearchTimer);
            woSearchTimer = setTimeout(function () { woTable.ajax.reload(); }, 300);
        });
        $('#oc-wo-search-clear').on('click', function () {
            $('#oc-wo-search').val('');
            woTable.ajax.reload();
        });
        $('#oc-wo-status-filter').on('change', function () {
            woTable.ajax.reload();
        });
    }

    // WC status badge — uses different colour scheme than native because the
    // status set is different (pending/on-hold/processing/completed/cancelled/refunded/failed)
    function wcStatusBadge(s) {
        var classes = {
            'pending':    'bg-warning text-dark',
            'on-hold':    'bg-info text-dark',
            'processing': 'bg-primary',
            'completed':  'bg-success',
            'cancelled':  'bg-secondary',
            'refunded':   'bg-danger',
            'failed':     'bg-dark',
        };
        return '<span class="badge ' + (classes[s] || 'bg-secondary') + '">' + escapeHtml(s) + '</span>';
    }

    function refreshWooCommerceLastSync() {
        $.get('{{ route('order-centre.woocommerce.get') }}').done(function (s) {
            window.ocWcSiteUrl = s.site_url || '';
            window.ocWcSyncEnabled = !!s.sync_enabled;
            $('#oc-wo-last-sync').text(s.last_sync_at ? 'Last sync: ' + s.last_sync_at : 'Last sync: never');
            // Surface the actual configured URL so there's no ambiguity about
            // which site the sync is pulling from.
            if (s.site_url) {
                $('#oc-wo-site-url').text(s.site_url.replace(/^https?:\/\//, '').replace(/\/$/, ''));
            } else {
                $('#oc-wo-site-url').html('<span class="text-warning">(not configured — open Settings tab)</span>');
            }
        });
    }

    $('a[data-bs-toggle="tab"][href="#website-orders"]').on('shown.bs.tab', function () {
        initWebsiteOrdersTable();
        if (woTable) woTable.ajax.reload(null, false);
        refreshWooCommerceLastSync();
    });

    // Recursive sync runner: pulls one batch, auto-continues until nothing left.
    // Each batch is small (200 orders) so we don't hit PHP execution time limits.
    function runWooCommerceSync($btn, $status, full, batchNum, totalsAccum) {
        batchNum = batchNum || 1;
        totalsAccum = totalsAccum || { imported: 0, updated: 0, errors: 0, failures: [] };

        var batchLabel = 'Batch ' + batchNum + ': pulling orders from WooCommerce…';
        $status.removeClass('d-none alert-success alert-danger').addClass('alert-light')
               .html('<i class="fa fa-cloud-download-alt me-1"></i> ' + batchLabel
                   + ' <span class="text-muted">(running total: '
                   + totalsAccum.imported + ' new, ' + totalsAccum.updated + ' updated)</span>');

        $.post('{{ route('order-centre.woocommerce.sync') }}', {
            full: (batchNum === 1 ? full : 0),  // only first batch respects "full" toggle
            max: 200,
        }).done(function (r) {
            if (!r.success) {
                $status.removeClass('alert-light alert-success').addClass('alert-danger')
                       .html('<i class="fa fa-times-circle me-1"></i> ' + escapeHtml(r.message || 'Sync failed'));
                $btn.prop('disabled', false).html('<i class="fa fa-sync me-1"></i> Sync Now');
                return;
            }

            // Accumulate
            if (r.result) {
                totalsAccum.imported += (r.result.imported || 0);
                totalsAccum.updated  += (r.result.updated  || 0);
                totalsAccum.errors   += (r.result.errors   || 0);
                if (r.result.failures && r.result.failures.length) {
                    totalsAccum.failures = totalsAccum.failures.concat(r.result.failures).slice(0, 30);
                }
            }

            // Refresh table + stats after each batch so user sees orders appearing
            if (woTable) woTable.ajax.reload(null, false);
            refreshWooCommerceLastSync();

            if (r.hit_cap && (r.total_run || 0) > 0) {
                // More likely remaining — auto-continue with a brief delay
                setTimeout(function () {
                    runWooCommerceSync($btn, $status, full, batchNum + 1, totalsAccum);
                }, 600);
            } else {
                // All done
                refreshStats();
                var msg = '<i class="fa fa-check-circle me-1"></i> Sync complete: '
                        + '<strong>' + totalsAccum.imported + ' new</strong>, '
                        + '<strong>' + totalsAccum.updated + ' updated</strong>'
                        + (totalsAccum.errors > 0 ? ', <strong class="text-warning">' + totalsAccum.errors + ' errors</strong>' : '')
                        + ' across ' + batchNum + ' batch(es).';
                $status.removeClass('alert-light alert-danger').addClass('alert-success').html(msg);

                if (totalsAccum.failures.length > 0) {
                    var failsHtml = '<div class="mt-2 small"><strong>Failures:</strong><ul class="mb-0">';
                    totalsAccum.failures.forEach(function (f) {
                        failsHtml += '<li>WC#' + escapeHtml(String(f.wc_id)) + ': ' + escapeHtml(f.error) + '</li>';
                    });
                    failsHtml += '</ul></div>';
                    $status.append(failsHtml);
                }
                $btn.prop('disabled', false).html('<i class="fa fa-sync me-1"></i> Sync Now');
            }
        }).fail(function (xhr) {
            $status.removeClass('alert-light alert-success').addClass('alert-danger')
                   .html('<i class="fa fa-times-circle me-1"></i> '
                       + escapeHtml(xhr.responseJSON?.message || 'Sync failed in batch ' + batchNum));
            $btn.prop('disabled', false).html('<i class="fa fa-sync me-1"></i> Sync Now');
        });
    }

    $('#oc-wo-sync-btn').on('click', function () {
        var $btn = $(this);
        var $status = $('#oc-wo-status');
        var full = $('#oc-wo-full-sync').is(':checked') ? 1 : 0;

        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> Syncing…');
        runWooCommerceSync($btn, $status, full);
    });

    // Reset & Pull Latest (Settings tab → WooCommerce section)
    $('#oc-wc-reset-btn').on('click', function () {
        var count = parseInt($('#oc-wc-reset-count').val(), 10) || 100;
        if (count < 1 || count > 500) {
            alert('Count must be between 1 and 500.');
            return;
        }
        var msg = 'WIPE all locally-stored WooCommerce orders and pull only the latest ' + count + ' orders?\n\n' +
                  'This deletes:\n' +
                  '• All WC orders, line items, customers, notes, audit logs, refunds, and tag links\n\n' +
                  'It does NOT touch your live WooCommerce store. Future syncs from this point onward will only pick up new/modified orders.';
        if (!confirm(msg)) return;
        if (!confirm('Are you absolutely sure? This cannot be undone.')) return;

        var $btn = $(this);
        var $status = $('#oc-wc-reset-status');
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> Resetting…');
        $status.removeClass('text-success text-danger').addClass('text-muted').text('Wiping local data and pulling latest ' + count + ' orders…');

        $.post('{{ route('order-centre.woocommerce.reset-pull-latest') }}', {
            count: count,
            _token: '{{ csrf_token() }}'
        }).done(function (r) {
            $btn.prop('disabled', false).html('<i class="fa fa-sync-alt me-1"></i> Reset & Pull Latest');
            if (r.success) {
                $status.removeClass('text-muted text-danger').addClass('text-success').text(r.message);
                if (typeof toastr !== 'undefined') toastr.success(r.message);
                // Refresh table if it's loaded
                if (typeof woTable !== 'undefined' && woTable) woTable.ajax.reload(null, false);
                refreshWooCommerceLastSync();
            } else {
                $status.removeClass('text-muted text-success').addClass('text-danger').text(r.message || 'Reset failed');
            }
        }).fail(function (xhr) {
            $btn.prop('disabled', false).html('<i class="fa fa-sync-alt me-1"></i> Reset & Pull Latest');
            $status.removeClass('text-muted text-success').addClass('text-danger').text((xhr.responseJSON && xhr.responseJSON.message) || 'Reset failed');
        });
    });

    // Drawer: push current order back to WC (write mode only)
    $('#oc-d-wc-push').on('click', function () {
        if (!currentOrderId) return;
        if (!confirm('Push the current status back to WooCommerce? This will overwrite the order status on the website.')) return;

        var $btn = $(this);
        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> Pushing…');

        $.post('{{ url('order-centre/orders') }}/' + currentOrderId + '/push-to-wc')
            .done(function (r) {
                if (r.success) {
                    toast(r.message);
                    loadDrawerOrder(currentOrderId);
                } else {
                    alert(r.message || 'Push failed');
                }
            })
            .fail(function (xhr) {
                alert(xhr.responseJSON?.message || 'Push failed');
            })
            .always(function () {
                $btn.prop('disabled', false).html('<i class="fa fa-cloud-upload-alt me-1"></i> Push');
            });
    });

    // =================================================================
    // CUSTOM TAGS MANAGEMENT (Settings tab)
    // =================================================================

    function renderTagList(tags) {
        var $list = $('#oc-tag-list').empty();
        if (!tags.length) {
            $list.html('<div class="text-muted small">No tags yet. Click "New Tag" to create one.</div>');
            return;
        }
        var $table = $('<table class="table table-sm align-middle mb-0"></table>');
        $table.append('<thead><tr><th>Tag</th><th width="100" class="text-center">Used By</th><th width="120">Actions</th></tr></thead>');
        var $body = $('<tbody></tbody>');
        tags.forEach(function (t) {
            var $row = $(
                '<tr>' +
                    '<td><span class="badge me-2" style="background:' + escapeHtml(t.colour) + '; color:#fff; font-size:.8rem; padding: 0.4em 0.7em;">' + escapeHtml(t.name) + '</span><code class="text-muted small">' + escapeHtml(t.slug) + '</code></td>' +
                    '<td class="text-center"><span class="badge bg-light text-dark">' + (t.orders_count || 0) + '</span></td>' +
                    '<td>' +
                        '<button class="btn btn-sm btn-outline-secondary me-1 oc-tag-edit" title="Edit"><i class="fa fa-pencil-alt"></i></button>' +
                        '<button class="btn btn-sm btn-outline-danger oc-tag-delete" title="Delete"><i class="fa fa-trash"></i></button>' +
                    '</td>' +
                '</tr>'
            );
            $row.find('.oc-tag-edit').data('tag', t);
            $row.find('.oc-tag-delete').data('tag', t);
            $body.append($row);
        });
        $table.append($body);
        $list.append($table);
    }

    function loadTags() {
        $.get('{{ route('order-centre.tags.list') }}').done(function (r) {
            renderTagList(r.data || []);
        }).fail(function () {
            $('#oc-tag-list').html('<div class="text-danger small">Failed to load tags.</div>');
        });
    }

    // Load tags whenever the Settings tab is shown
    $('a[data-bs-toggle="tab"][href="#settings"]').on('shown.bs.tab', loadTags);

    // Show form (new tag)
    $('#oc-tag-new-btn').on('click', function () {
        $('#oc-tag-form-id').val('');
        $('#oc-tag-form-name').val('');
        $('#oc-tag-form-colour').val('#6B7280');
        $('#oc-tag-form').removeClass('d-none');
        $('#oc-tag-form-name').focus();
    });

    // Edit existing tag
    $('#oc-tag-list').on('click', '.oc-tag-edit', function () {
        var t = $(this).data('tag');
        $('#oc-tag-form-id').val(t.id);
        $('#oc-tag-form-name').val(t.name);
        $('#oc-tag-form-colour').val(t.colour || '#6B7280');
        $('#oc-tag-form').removeClass('d-none');
        $('#oc-tag-form-name').focus();
    });

    // Cancel form
    $('#oc-tag-form-cancel').on('click', function () {
        $('#oc-tag-form').addClass('d-none');
    });

    // Save (create or update)
    $('#oc-tag-form-save').on('click', function () {
        var id = $('#oc-tag-form-id').val();
        var name = $('#oc-tag-form-name').val().trim();
        var colour = $('#oc-tag-form-colour').val();

        if (!name) { alert('Tag name is required.'); return; }

        var $btn = $(this);
        $btn.prop('disabled', true).text('Saving…');

        var url, method;
        if (id) {
            url = '{{ url('order-centre/tags') }}/' + id;
            method = 'PUT';
        } else {
            url = '{{ route('order-centre.tags.create') }}';
            method = 'POST';
        }

        $.ajax({
            url: url,
            method: method,
            data: { name: name, colour: colour }
        }).done(function (r) {
            toast(r.message || 'Saved');
            $('#oc-tag-form').addClass('d-none');
            loadTags();
            // Refresh in-memory tag chips inside the drawer if it's open
            if (typeof reloadDrawerTags === 'function') reloadDrawerTags();
        }).fail(function (xhr) {
            var msg = xhr.responseJSON?.message || 'Save failed';
            if (xhr.responseJSON?.errors) {
                msg += '\n' + Object.values(xhr.responseJSON.errors).flat().join('\n');
            }
            alert(msg);
        }).always(function () {
            $btn.prop('disabled', false).text('Save');
        });
    });

    // Delete tag
    $('#oc-tag-list').on('click', '.oc-tag-delete', function () {
        var t = $(this).data('tag');
        var msg = 'Delete the tag "' + t.name + '"?';
        if (t.orders_count > 0) {
            msg += '\n\nThis tag is currently attached to ' + t.orders_count + ' order(s). Deleting will remove the tag from those orders.';
        }
        if (!confirm(msg)) return;

        $.ajax({
            url: '{{ url('order-centre/tags') }}/' + t.id,
            method: 'DELETE',
        }).done(function (r) {
            toast(r.message);
            loadTags();
            if (typeof reloadDrawerTags === 'function') reloadDrawerTags();
        }).fail(function (xhr) {
            alert(xhr.responseJSON?.message || 'Delete failed');
        });
    });

    // =================================================================
    // PHASE 9: TODAY'S DELIVERIES WIDGET
    // =================================================================

    function refreshTodayDeliveries(silent) {
        $.get('{{ route('order-centre.today-deliveries') }}').done(function (d) {
            $('#oc-today-date').text(d.date);
            var count = d.count || 0;
            var $badge = $('#oc-today-count');
            if (count > 0) {
                $badge.text(count).removeClass('d-none');
            } else {
                $badge.addClass('d-none');
            }

            var $list = $('#oc-today-list').empty();
            if (count === 0) {
                $list.append('<div class="oc-empty">No deliveries scheduled for today.</div>');
                return;
            }

            d.orders.forEach(function (o) {
                var paid = o.payment_status === 'paid';
                var paidBadge = paid
                    ? '<span class="badge bg-success-subtle text-success">Paid</span>'
                    : '<span class="badge bg-warning-subtle text-warning">' + escapeHtml(o.payment_status) + '</span>';

                var $item = $(
                    '<div class="oc-today-item">' +
                        '<div class="top-row">' +
                            '<div>' +
                                '<a class="order-link" data-order-id="' + o.id + '">#' + escapeHtml(o.order_number) + '</a> ' +
                                '<span class="oc-customer-link ms-1" data-customer-id="">' + escapeHtml(o.customer_name) + '</span>' +
                            '</div>' +
                            '<div>' + paidBadge + '</div>' +
                        '</div>' +
                        '<div class="meta"><i class="fa fa-map-marker-alt me-1"></i>' + escapeHtml(o.address_line || 'No address') + '</div>' +
                        '<div class="d-flex justify-content-between align-items-center mt-1">' +
                            '<span class="meta">$' + Number(o.total).toFixed(2) + (o.customer_phone ? ' · ' + escapeHtml(o.customer_phone) : '') + '</span>' +
                            '<button class="btn btn-sm btn-success oc-today-deliver" data-id="' + o.id + '" data-payment-status="' + escapeHtml(o.payment_status || '') + '" data-order-number="' + escapeHtml(o.order_number || '') + '">' +
                                '<i class="fa fa-check me-1"></i> Mark Delivered' +
                            '</button>' +
                        '</div>' +
                    '</div>'
                );
                $list.append($item);
            });
        }).fail(function () {
            if (!silent) toast('Could not load today\'s deliveries');
        });
    }

    // Toggle the panel
    $('#oc-today-fab').on('click', function () {
        $('#oc-today-panel').toggleClass('active');
        if ($('#oc-today-panel').hasClass('active')) refreshTodayDeliveries();
    });
    $('#oc-today-close').on('click', function () {
        $('#oc-today-panel').removeClass('active');
    });

    // Click handlers (delegated)
    $('#oc-today-list').on('click', '.order-link', function (e) {
        e.preventDefault();
        var id = $(this).data('order-id');
        $('#oc-today-panel').removeClass('active');
        if (id) openDrawer(id);
    });

    $('#oc-today-list').on('click', '.oc-today-deliver', function () {
        var $btn = $(this);
        var id = $btn.data('id');
        var paymentStatus = $btn.data('payment-status');
        var orderNumber = $btn.data('order-number') || ('#' + id);

        // If payment is pending, route through the confirmation modal to ask
        // about also marking paid. Otherwise (already paid), simple confirm.
        if (paymentStatus === 'pending') {
            window.ocDeliverPaidPrompt(id, orderNumber, $btn);
            return;
        }

        if (!confirm('Mark order ' + orderNumber + ' as delivered?')) return;
        window.ocDoMarkDelivered(id, false, $btn);
    });

    // Smart confirmation modal for "deliver + also pay?"
    // Used by today-panel buttons AND the per-order action menu (if/when added).
    window.ocDeliverPaidPrompt = function (orderId, orderNumberLabel, $btnToRestore) {
        $('#oc-deliver-paid-order-number').text(orderNumberLabel);
        $('#oc-deliver-paid-also').prop('checked', true);
        var modal = new bootstrap.Modal(document.getElementById('oc-modal-deliver-paid'));
        $('#oc-modal-deliver-paid').data('order-id', orderId).data('btn-id', $btnToRestore ? $btnToRestore.data('id') : null);
        modal.show();
    };

    $('#oc-deliver-paid-submit').on('click', function () {
        var $modal = $('#oc-modal-deliver-paid');
        var id = $modal.data('order-id');
        var alsoPaid = $('#oc-deliver-paid-also').is(':checked');
        var $btn = $modal.data('btn-id')
            ? $('.oc-today-deliver[data-id="' + $modal.data('btn-id') + '"]').first()
            : null;
        bootstrap.Modal.getInstance(document.getElementById('oc-modal-deliver-paid')).hide();
        window.ocDoMarkDelivered(id, alsoPaid, $btn);
    });

    window.ocDoMarkDelivered = function (id, alsoPaid, $btn) {
        if ($btn && $btn.length) $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i>');
        $.post('{{ url('order-centre/orders') }}/' + id + '/mark-delivered', { also_mark_paid: alsoPaid ? 1 : 0 })
            .done(function (r) {
                var msg = r.message || 'Delivered';
                if (r.paid_flipped) msg += ' — loyalty points and entry awarded.';
                toast(msg);
                refreshTodayDeliveries(true);
                if (typeof table !== 'undefined' && table) table.ajax.reload(null, false);
                if (typeof refreshStats === 'function') refreshStats();
            })
            .fail(function (xhr) {
                alert(xhr.responseJSON?.message || 'Failed');
                if ($btn && $btn.length) $btn.prop('disabled', false).html('<i class="fa fa-check me-1"></i> Mark Delivered');
            });
    };

    // Initial load (silent — don't toast on initial fail e.g. before login)
    refreshTodayDeliveries(true);

    // Refresh count every 5 minutes in case other staff change orders elsewhere
    setInterval(function () { refreshTodayDeliveries(true); }, 5 * 60 * 1000);

    // =================================================================
    // PHASE 9: CUSTOMER DRILL-THROUGH
    // =================================================================

    function openCustomerProfile(userId) {
        if (!userId) return;
        var $modal = $('#oc-customer-modal');
        var $body = $('#oc-customer-modal-body');
        $body.html('<div class="text-center text-muted py-5"><i class="fa fa-spinner fa-spin me-1"></i> Loading customer…</div>');
        $('#oc-customer-full-link').attr('href', '#').addClass('disabled');

        new bootstrap.Modal($modal[0]).show();

        $.get('{{ url('order-centre/customers') }}/' + userId + '/profile')
            .done(function (d) {
                var u = d.user || {};
                var s = d.stats || {};
                $('#oc-customer-full-link').attr('href', '/customer-management/' + u.id).removeClass('disabled');

                var addressesHtml = '';
                if (d.addresses && d.addresses.length) {
                    addressesHtml = '<div class="row g-3 mb-0">' + d.addresses.map(function (a) {
                        var label = a.label || 'Address';
                        var lines = [];
                        if (a.address)  lines.push(escapeHtml(a.address));
                        var l2 = [a.suburb, a.city, a.state, a.zip_code].filter(Boolean);
                        if (l2.length)  lines.push(escapeHtml(l2.join(', ')));
                        var contactBits = [];
                        if (a.full_name) contactBits.push('<i class="fa fa-user me-1"></i>' + escapeHtml(a.full_name));
                        if (a.phone)     contactBits.push('<i class="fa fa-phone me-1"></i>' + escapeHtml(a.phone));
                        if (a.email)     contactBits.push('<i class="fa fa-envelope me-1"></i>' + escapeHtml(a.email));
                        return '<div class="col-md-6"><div class="addr-card ' + (a.is_default ? 'is-default' : '') + '">' +
                            '<div class="d-flex align-items-center justify-content-between">' +
                                '<span class="addr-label">' + escapeHtml(label) + '</span>' +
                                (a.is_default ? ' <span class="badge bg-primary">Default</span>' : '') +
                            '</div>' +
                            '<div class="addr-lines">' + (lines.join('<br>') || '<em class="text-muted">No address</em>') + '</div>' +
                            (contactBits.length ? '<div class="addr-contact">' + contactBits.join(' &nbsp;·&nbsp; ') + '</div>' : '') +
                        '</div></div>';
                    }).join('') + '</div>';
                } else {
                    addressesHtml = '<div class="text-muted">No saved addresses.</div>';
                }

                // Best-effort phone for the header — falls back to default address phone
                // because users.phone is often null while the address always has one.
                var headerPhone = u.phone;
                if (!headerPhone && d.addresses && d.addresses.length) {
                    var defaultAddr = d.addresses.find(function (x) { return x.is_default; }) || d.addresses[0];
                    headerPhone = defaultAddr?.phone || null;
                }

                var ordersHtml = '';
                if (d.recent_orders && d.recent_orders.length) {
                    ordersHtml = '<div class="table-responsive"><table class="table oc-recent mb-0"><thead><tr>' +
                                 '<th>Order</th><th>Created</th><th>Delivery</th><th>Status</th><th>Pay</th><th class="text-end">Total</th>' +
                                 '</tr></thead><tbody>';
                    d.recent_orders.forEach(function (o) {
                        var statusBadge = statusBadges[o.order_status] || ('<span class="badge bg-secondary">' + o.order_status + '</span>');
                        var payBadge = paymentBadges[o.payment_status] || ('<span class="badge bg-secondary">' + o.payment_status + '</span>');
                        ordersHtml += '<tr>' +
                            '<td><a href="#" class="oc-customer-modal-order text-primary" data-order-id="' + o.id + '">#' + escapeHtml(o.order_number) + '</a></td>' +
                            '<td class="text-muted">' + escapeHtml(o.created_at || '') + '</td>' +
                            '<td class="text-muted">' + escapeHtml(o.delivery_date || '—') + '</td>' +
                            '<td>' + statusBadge + '</td>' +
                            '<td>' + payBadge + '</td>' +
                            '<td class="text-end fw-semibold">$' + Number(o.total).toFixed(2) + '</td>' +
                        '</tr>';
                    });
                    ordersHtml += '</tbody></table></div>';
                } else {
                    ordersHtml = '<div class="text-muted">No orders yet.</div>';
                }

                var headerBits = [];
                if (u.email)       headerBits.push('<i class="fa fa-envelope me-1"></i>' + escapeHtml(u.email));
                if (headerPhone)   headerBits.push('<i class="fa fa-phone me-1"></i>' + escapeHtml(headerPhone));
                if (u.member_since) headerBits.push('<i class="fa fa-calendar me-1"></i>Member since ' + escapeHtml(u.member_since));

                var html =
                    '<div class="d-flex align-items-center mb-4">' +
                        '<img src="' + escapeHtml(u.avatar) + '" style="width:88px;height:88px;border-radius:50%;object-fit:cover;border: 3px solid #f0f0f5;" class="me-3">' +
                        '<div>' +
                            '<div class="h-customer-name">' + escapeHtml(u.name || 'Unknown') +
                                (u.is_guest ? ' <span class="badge bg-light text-dark ms-1" style="font-size:.7rem;">Guest</span>' : '') +
                            '</div>' +
                            '<div class="h-customer-contact">' + headerBits.join(' &nbsp;·&nbsp; ') + '</div>' +
                        '</div>' +
                    '</div>' +

                    '<div class="row g-3 mb-3">' +
                        '<div class="col-md-3"><div class="stat-card">' +
                            '<div class="label">Orders</div>' +
                            '<div class="value text-primary">' + Number(s.order_count || 0).toLocaleString() + '</div>' +
                        '</div></div>' +
                        '<div class="col-md-3"><div class="stat-card">' +
                            '<div class="label">Lifetime</div>' +
                            '<div class="value text-success">$' + Number(s.lifetime_spend || 0).toLocaleString(undefined, {maximumFractionDigits: 0}) + '</div>' +
                        '</div></div>' +
                        '<div class="col-md-3"><div class="stat-card">' +
                            '<div class="label">Permanent Entries</div>' +
                            '<div class="value">' + Number(u.permanent_entries || 0).toLocaleString() + '</div>' +
                        '</div></div>' +
                        '<div class="col-md-3"><div class="stat-card">' +
                            '<div class="label">Temporary Entries</div>' +
                            '<div class="value">' + Number(u.temporary_entries || 0).toLocaleString() + '</div>' +
                        '</div></div>' +
                    '</div>' +

                    '<div class="section-h"><i class="fa fa-map-marker-alt me-1"></i> Addresses</div>' +
                    addressesHtml +

                    '<div class="section-h"><i class="fa fa-shopping-bag me-1"></i> Recent Orders' +
                        (s.last_order_at ? ' <span class="text-muted fw-normal" style="font-size:.85rem;">(last: ' + escapeHtml(s.last_order_at) + ')</span>' : '') +
                    '</div>' +
                    ordersHtml;

                $body.html(html);
            })
            .fail(function (xhr) {
                $body.html('<div class="alert alert-danger mb-0">Failed to load customer: ' + escapeHtml(xhr.responseJSON?.message || 'unknown error') + '</div>');
            });
    }

    // Click an order from the customer modal → close modal, open the order drawer
    $('#oc-customer-modal').on('click', '.oc-customer-modal-order', function (e) {
        e.preventDefault();
        var id = $(this).data('order-id');
        bootstrap.Modal.getInstance(document.getElementById('oc-customer-modal'))?.hide();
        if (id) setTimeout(function () { openDrawer(id); }, 250);
    });

    // Make customer names in any DataTable cell open the profile.
    // Customers in the existing tables render through the `customer` cell renderer,
    // which produces a div with name + email but no anchor. We add a delegated
    // click handler that walks up to find the closest TR and reads user_id from
    // a fresh server lookup. To avoid an extra request per click, the rendered
    // markup is augmented to embed user_id when the row data has it.
    //
    // For simplicity, we hook the customer name in the DRAWER's customer card,
    // and add a "Profile" action in the drawer header.
    $(document).on('click', '.oc-customer-link[data-customer-id]', function (e) {
        e.preventDefault();
        var uid = $(this).data('customer-id');
        if (uid) openCustomerProfile(uid);
    });

    // Drawer customer card → expose user id and link
    var origLoadDrawer = window.loadDrawerOrder;
    if (typeof loadDrawerOrder === 'function') {
        // Augment the drawer's customer name with click-to-open-profile after load
        $(document).on('click', '#oc-d-cust-name', function () {
            var uid = $(this).data('user-id');
            if (uid) openCustomerProfile(uid);
        });
    }

    // ============================================================
    // PHASE 10: WC DRAWER + REFUND HANDLERS
    // ============================================================

    var currentWcOrderId = null;
    var currentWcOrderData = null;

    window.openWcDrawer = function (id) {
        currentWcOrderId = id;
        $('#oc-wc-drawer').addClass('active');
        $('#oc-wc-drawer-overlay').fadeIn(150);
        // Reset to Items tab
        $('#oc-wc-drawer .oc-drawer-tabs button').removeClass('active');
        $('#oc-wc-drawer .oc-drawer-tabs button[data-section="items"]').addClass('active');
        $('#oc-wc-drawer .oc-drawer-section').removeClass('active');
        $('#oc-wc-drawer .oc-drawer-section[data-section="items"]').addClass('active');
        loadWcDrawerOrder(id);
    };

    function closeWcDrawer() {
        $('#oc-wc-drawer').removeClass('active');
        $('#oc-wc-drawer-overlay').fadeOut(150);
        currentWcOrderId = null;
        currentWcOrderData = null;
    }
    $('#oc-wc-drawer-close').on('click', closeWcDrawer);
    $('#oc-wc-drawer-overlay').on('click', closeWcDrawer);

    function loadWcDrawerOrder(id) {
        $.get('/order-centre/wc-orders/' + id + '/details').done(function (o) {
            currentWcOrderData = o;

            // Header
            $('#oc-wcd-number').text('#' + (o.woocommerce_number || ('WC' + o.woocommerce_id)));
            $('#oc-wcd-created').text(o.wc_date_created || '');
            $('#oc-wcd-status-badge').replaceWith(wcStatusBadge(o.wc_status).replace('<span', '<span id="oc-wcd-status-badge"'));
            $('#oc-wcd-payment-badge').attr('class', '').addClass('badge ' + (paymentBadgeClass(o.payment_status))).text(o.payment_status);

            // Customer card
            var custName = ((o.customer.first_name || '') + ' ' + (o.customer.last_name || '')).trim() || 'Guest';
            $('#oc-wcd-cust-initials').text((custName[0] || '?').toUpperCase());
            $('#oc-wcd-cust-name').text(custName);
            $('#oc-wcd-cust-email').text(o.customer.email || '—').attr('href', o.customer.email ? 'mailto:' + o.customer.email : '#');
            $('#oc-wcd-cust-phone').text(o.customer.phone || '');
            $('#oc-wcd-customer').data('wc-customer-id', o.customer.id);

            // Address
            var addr = o.shipping_address && o.shipping_address.address_1 ? o.shipping_address : o.billing_address;
            if (addr && addr.address_1) {
                var bits = [addr.address_1, addr.address_2, addr.city, addr.state, addr.postcode].filter(Boolean);
                $('#oc-wcd-address').html('<i class="fa fa-map-marker-alt me-1"></i>' + bits.join(', '));
            } else {
                $('#oc-wcd-address').html('<i class="fa fa-map-marker-alt me-1"></i><span class="text-muted">No address</span>');
            }

            // WP admin
            $('#oc-wcd-wp-link').attr('href', o.wp_admin_link || '#').toggle(!!o.wp_admin_link);
            $('#oc-wcd-last-sync').text(o.last_synced_at || 'never');

            // Items
            var itemsHtml = '';
            if (o.items && o.items.length) {
                o.items.forEach(function (it) {
                    itemsHtml += '<div class="oc-item-row d-flex justify-content-between py-2" style="border-bottom:1px solid #f1f5f9;">' +
                        '<div><div class="fw-semibold small">' + escapeHtml(it.name) + '</div>' +
                        (it.sku ? '<div class="small text-muted">SKU: ' + escapeHtml(it.sku) + '</div>' : '') +
                        '<div class="small text-muted">Qty: ' + it.quantity + ' × $' + parseFloat(it.price).toFixed(2) + '</div></div>' +
                        '<div class="fw-bold">$' + parseFloat(it.total).toFixed(2) + '</div></div>';
                });
            } else {
                itemsHtml = '<div class="text-muted small">No items.</div>';
            }
            $('#oc-wcd-items').html(itemsHtml);

            // Totals
            $('#oc-wcd-subtotal').text('$' + parseFloat(o.subtotal).toFixed(2));
            $('#oc-wcd-shipping').text('$' + parseFloat(o.shipping_total).toFixed(2));
            $('#oc-wcd-tax').text('$' + parseFloat(o.tax_total).toFixed(2));
            $('#oc-wcd-discount').text('−$' + parseFloat(o.discount_total).toFixed(2));
            $('#oc-wcd-total').text('$' + parseFloat(o.total).toFixed(2));
            if (parseFloat(o.refund_total) > 0) {
                $('#oc-wcd-refund-row').removeClass('d-none');
                $('#oc-wcd-refunded').text('−$' + parseFloat(o.refund_total).toFixed(2));
            } else {
                $('#oc-wcd-refund-row').addClass('d-none');
            }

            // Edit form
            $('#oc-wcd-edit-status').val(o.wc_status);
            $('#oc-wcd-edit-delivery').val(o.preferred_delivery_date || '');
            $('#oc-wcd-edit-note').val(o.customer_note || '');

            // Billing fields
            var b = o.billing_address || {};
            $('#oc-wcd-edit-bill-first').val(b.first_name || '');
            $('#oc-wcd-edit-bill-last').val(b.last_name || '');
            $('#oc-wcd-edit-bill-email').val(b.email || '');
            $('#oc-wcd-edit-bill-phone').val(b.phone || '');
            $('#oc-wcd-edit-bill-addr1').val(b.address_1 || '');
            $('#oc-wcd-edit-bill-addr2').val(b.address_2 || '');
            $('#oc-wcd-edit-bill-city').val(b.city || '');
            $('#oc-wcd-edit-bill-state').val(b.state || '');
            $('#oc-wcd-edit-bill-postcode').val(b.postcode || '');
            $('#oc-wcd-edit-bill-country').val(b.country || 'AU');

            // Shipping fields
            var s = o.shipping_address || {};
            $('#oc-wcd-edit-ship-first').val(s.first_name || '');
            $('#oc-wcd-edit-ship-last').val(s.last_name || '');
            $('#oc-wcd-edit-ship-addr1').val(s.address_1 || '');
            $('#oc-wcd-edit-ship-addr2').val(s.address_2 || '');
            $('#oc-wcd-edit-ship-city').val(s.city || '');
            $('#oc-wcd-edit-ship-state').val(s.state || '');
            $('#oc-wcd-edit-ship-postcode').val(s.postcode || '');
            $('#oc-wcd-edit-ship-country').val(s.country || 'AU');

            $('#oc-wcd-edit-push-hint').html(
                o.sync_enabled
                    ? '<i class="fa fa-check-circle text-success me-1"></i> Write mode is on — changes will push to WooCommerce live.'
                    : '<i class="fa fa-info-circle me-1"></i> Write mode is off — changes stay local only. Enable in Settings → WooCommerce.'
            );

            // Refunds tab
            $('#oc-wcd-rf-total').text('$' + parseFloat(o.total).toFixed(2));
            $('#oc-wcd-rf-refunded').text('$' + parseFloat(o.refund_total).toFixed(2));
            $('#oc-wcd-rf-remaining').text('$' + parseFloat(o.refundable_remaining).toFixed(2));
            $('#oc-wcd-refunds-count').text(o.refunds.length);

            $('#oc-wcd-rf-new')
                .prop('disabled', !(o.sync_enabled && parseFloat(o.refundable_remaining) > 0))
                .attr('title', !o.sync_enabled ? 'Write mode is off' : (parseFloat(o.refundable_remaining) > 0 ? '' : 'Fully refunded'));

            var rfHtml = '';
            if (o.refunds.length) {
                rfHtml = '<strong class="small">Refund History</strong>';
                o.refunds.forEach(function (r) {
                    var statusBadge = r.status === 'completed'
                        ? '<span class="badge bg-success">completed</span>'
                        : '<span class="badge bg-danger">failed</span>';
                    rfHtml += '<div class="card border-0 bg-light mt-2"><div class="card-body p-2 small">' +
                        '<div class="d-flex justify-content-between"><span>$' + parseFloat(r.amount).toFixed(2) + ' ' + statusBadge + '</span>' +
                        '<span class="text-muted">' + (r.refunded_at || '—') + '</span></div>' +
                        (r.reason ? '<div class="text-muted">' + escapeHtml(r.reason) + '</div>' : '') +
                        (r.wc_refund_id ? '<div class="text-muted small">WC refund #' + r.wc_refund_id + '</div>' : '') +
                        '</div></div>';
                });
            }
            $('#oc-wcd-rf-history').html(rfHtml);

            // Notes count
            $('#oc-wcd-notes-count').text(o.notes_count || 0);

            // Tags
            renderWcTags(o.tags);
        }).fail(function () {
            alert('Failed to load WooCommerce order details.');
        });
    }

    function paymentBadgeClass(s) {
        return ({pending:'bg-warning text-dark',paid:'bg-success',refunded:'bg-danger',partially_refunded:'bg-warning text-dark',failed:'bg-dark'})[s] || 'bg-secondary';
    }

    // Tab switching for WC drawer
    $('#oc-wc-drawer .oc-drawer-tabs').on('click', 'button', function () {
        var section = $(this).data('section');
        $('#oc-wc-drawer .oc-drawer-tabs button').removeClass('active');
        $(this).addClass('active');
        $('#oc-wc-drawer .oc-drawer-section').removeClass('active');
        $('#oc-wc-drawer .oc-drawer-section[data-section="' + section + '"]').addClass('active');

        if (section === 'notes')    loadWcNotes();
        if (section === 'timeline') loadWcTimeline();
        if (section === 'tags')     loadWcAvailableTags();
    });

    // Customer card click → WC customer profile
    $('#oc-wcd-customer').on('click', function () {
        var cid = $(this).data('wc-customer-id');
        if (cid) openWcCustomerProfile(cid);
    });

    // Helper: read billing form values into a flat object
    function readBillingForm() {
        return {
            first_name: $('#oc-wcd-edit-bill-first').val(),
            last_name:  $('#oc-wcd-edit-bill-last').val(),
            email:      $('#oc-wcd-edit-bill-email').val(),
            phone:      $('#oc-wcd-edit-bill-phone').val(),
            address_1:  $('#oc-wcd-edit-bill-addr1').val(),
            address_2:  $('#oc-wcd-edit-bill-addr2').val(),
            city:       $('#oc-wcd-edit-bill-city').val(),
            state:      $('#oc-wcd-edit-bill-state').val(),
            postcode:   $('#oc-wcd-edit-bill-postcode').val(),
            country:    $('#oc-wcd-edit-bill-country').val()
        };
    }
    function readShippingForm() {
        return {
            first_name: $('#oc-wcd-edit-ship-first').val(),
            last_name:  $('#oc-wcd-edit-ship-last').val(),
            address_1:  $('#oc-wcd-edit-ship-addr1').val(),
            address_2:  $('#oc-wcd-edit-ship-addr2').val(),
            city:       $('#oc-wcd-edit-ship-city').val(),
            state:      $('#oc-wcd-edit-ship-state').val(),
            postcode:   $('#oc-wcd-edit-ship-postcode').val(),
            country:    $('#oc-wcd-edit-ship-country').val()
        };
    }

    // Copy billing → shipping
    $('#oc-wcd-edit-copy-billing').on('click', function () {
        $('#oc-wcd-edit-ship-first').val($('#oc-wcd-edit-bill-first').val());
        $('#oc-wcd-edit-ship-last').val($('#oc-wcd-edit-bill-last').val());
        $('#oc-wcd-edit-ship-addr1').val($('#oc-wcd-edit-bill-addr1').val());
        $('#oc-wcd-edit-ship-addr2').val($('#oc-wcd-edit-bill-addr2').val());
        $('#oc-wcd-edit-ship-city').val($('#oc-wcd-edit-bill-city').val());
        $('#oc-wcd-edit-ship-state').val($('#oc-wcd-edit-bill-state').val());
        $('#oc-wcd-edit-ship-postcode').val($('#oc-wcd-edit-bill-postcode').val());
        $('#oc-wcd-edit-ship-country').val($('#oc-wcd-edit-bill-country').val());
    });

    // Edit save
    $('#oc-wcd-edit-save').on('click', function () {
        if (!currentWcOrderId) return;
        var btn = $(this);
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> Saving…');
        $.post('/order-centre/wc-orders/' + currentWcOrderId + '/edit', {
            wc_status:               $('#oc-wcd-edit-status').val(),
            customer_note:           $('#oc-wcd-edit-note').val(),
            preferred_delivery_date: $('#oc-wcd-edit-delivery').val(),
            billing:                 readBillingForm(),
            shipping:                readShippingForm(),
            _token: '{{ csrf_token() }}'
        }).done(function (r) {
            btn.prop('disabled', false).html('<i class="fa fa-save me-1"></i> Save Changes');
            if (typeof toastr !== 'undefined') toastr.success(r.message);
            else alert(r.message);
            loadWcDrawerOrder(currentWcOrderId);
            if (woTable) woTable.ajax.reload(null, false);
        }).fail(function (xhr) {
            btn.prop('disabled', false).html('<i class="fa fa-save me-1"></i> Save Changes');
            var msg = (xhr.responseJSON && xhr.responseJSON.message) || 'Save failed';
            if (xhr.responseJSON && xhr.responseJSON.errors) {
                msg += '\n' + Object.values(xhr.responseJSON.errors).flat().join('\n');
            }
            alert(msg);
        });
    });

    // Notes
    function loadWcNotes() {
        if (!currentWcOrderId) return;
        $.get('/order-centre/wc-orders/' + currentWcOrderId + '/notes').done(function (r) {
            var html = '';
            (r.data || []).forEach(function (n) {
                html += '<div class="oc-note-item d-flex gap-2 mb-2" style="padding:.5rem; background:#f8fafc; border-radius:.5rem;">' +
                    '<img src="' + (n.avatar || '') + '" style="width:32px;height:32px;border-radius:50%;">' +
                    '<div class="flex-grow-1"><div class="fw-semibold small">' + escapeHtml(n.author) + ' <span class="text-muted small">· ' + escapeHtml(n.created_at) + '</span></div>' +
                    '<div class="small">' + escapeHtml(n.body) + '</div></div>' +
                    '<button class="btn-close btn-close-sm oc-wc-note-del" data-note-id="' + n.id + '"></button>' +
                    '</div>';
            });
            if (!html) html = '<div class="text-muted small">No notes yet.</div>';
            $('#oc-wcd-notes-list').html(html);
        });
    }

    $('#oc-wcd-note-add').on('click', function () {
        if (!currentWcOrderId) return;
        var body = $('#oc-wcd-note-input').val().trim();
        if (!body) return;
        $.post('/order-centre/wc-orders/' + currentWcOrderId + '/notes', { body: body, _token: '{{ csrf_token() }}' })
            .done(function () {
                $('#oc-wcd-note-input').val('');
                loadWcNotes();
                if (currentWcOrderData) {
                    currentWcOrderData.notes_count = (currentWcOrderData.notes_count || 0) + 1;
                    $('#oc-wcd-notes-count').text(currentWcOrderData.notes_count);
                }
            });
    });

    $('#oc-wcd-notes-list').on('click', '.oc-wc-note-del', function () {
        if (!currentWcOrderId) return;
        if (!confirm('Delete this note?')) return;
        var noteId = $(this).data('note-id');
        $.ajax({
            url: '/order-centre/wc-orders/' + currentWcOrderId + '/notes/' + noteId,
            type: 'DELETE',
            data: { _token: '{{ csrf_token() }}' }
        }).done(function () {
            loadWcNotes();
            if (currentWcOrderData) {
                currentWcOrderData.notes_count = Math.max(0, (currentWcOrderData.notes_count || 1) - 1);
                $('#oc-wcd-notes-count').text(currentWcOrderData.notes_count);
            }
        });
    });

    // Timeline
    function loadWcTimeline() {
        if (!currentWcOrderId) return;
        $.get('/order-centre/wc-orders/' + currentWcOrderId + '/audit').done(function (r) {
            var html = '';
            (r.data || []).forEach(function (a) {
                html += '<div class="d-flex gap-2 mb-2" style="padding:.5rem; border-left: 2px solid #667eea; background:#f8fafc;">' +
                    '<div class="flex-grow-1 small">' +
                    '<div><strong>' + escapeHtml(a.action) + '</strong> <span class="text-muted">· ' + escapeHtml(a.created_at) + '</span></div>' +
                    (a.description ? '<div class="text-muted">' + escapeHtml(a.description) + '</div>' : '') +
                    '<div class="text-muted small">by ' + escapeHtml(a.author) + '</div>' +
                    '</div></div>';
            });
            if (!html) html = '<div class="text-muted small">No history yet.</div>';
            $('#oc-wcd-timeline-list').html(html);
        });
    }

    // Tags
    function renderWcTags(attached) {
        var html = '';
        if (attached && attached.length) {
            attached.forEach(function (t) {
                html += '<span class="oc-tag-pill" style="background:' + (t.color || '#667eea') + '20; color:' + (t.color || '#667eea') + '; border:1px solid ' + (t.color || '#667eea') + ';">'
                     + escapeHtml(t.name) + ' <i class="fa fa-times ms-1 oc-wc-tag-detach" style="cursor:pointer;" data-tag-id="' + t.id + '"></i></span>';
            });
        } else {
            html = '<span class="text-muted small">None attached.</span>';
        }
        $('#oc-wcd-tags-attached').html(html);
    }

    function loadWcAvailableTags() {
        if (!currentWcOrderId) return;
        // Reuse the existing tag list endpoint (tags are shared between native + WC)
        $.get('{{ route("order-centre.tags.list") }}').done(function (r) {
            var attached = (currentWcOrderData && currentWcOrderData.tags) || [];
            var attachedIds = attached.map(function (t) { return t.id; });
            var html = '';
            (r.data || []).forEach(function (t) {
                if (attachedIds.indexOf(t.id) !== -1) return;
                html += '<span class="oc-tag-pill oc-wc-tag-attach" data-tag-id="' + t.id + '" style="background:' + (t.color || '#667eea') + '20; color:' + (t.color || '#667eea') + '; border:1px solid ' + (t.color || '#667eea') + '; cursor: pointer;">'
                     + escapeHtml(t.name) + ' <i class="fa fa-plus ms-1"></i></span>';
            });
            if (!html) html = '<span class="text-muted small">All tags attached.</span>';
            $('#oc-wcd-tags-available').html(html);
        });
    }

    $('#oc-wcd-tags-attached').on('click', '.oc-wc-tag-detach', function () {
        if (!currentWcOrderId) return;
        var tagId = $(this).data('tag-id');
        $.ajax({
            url: '/order-centre/wc-orders/' + currentWcOrderId + '/tags/' + tagId,
            type: 'DELETE',
            data: { _token: '{{ csrf_token() }}' }
        }).done(function () {
            // Refresh order
            loadWcDrawerOrder(currentWcOrderId);
            if (woTable) woTable.ajax.reload(null, false);
        });
    });

    $('#oc-wcd-tags-available').on('click', '.oc-wc-tag-attach', function () {
        if (!currentWcOrderId) return;
        var tagId = $(this).data('tag-id');
        $.post('/order-centre/wc-orders/' + currentWcOrderId + '/tags', { tag_id: tagId, _token: '{{ csrf_token() }}' })
            .done(function () {
                loadWcDrawerOrder(currentWcOrderId);
                if (woTable) woTable.ajax.reload(null, false);
            });
    });

    // ----- Refund modal -----
    $('#oc-wcd-rf-new').on('click', function () {
        if (!currentWcOrderData) return;
        $('#oc-wc-rf-modal-order').text('#' + (currentWcOrderData.woocommerce_number || ('WC' + currentWcOrderData.woocommerce_id)));
        $('#oc-wc-rf-modal-total').text('$' + parseFloat(currentWcOrderData.total).toFixed(2));
        $('#oc-wc-rf-modal-remaining').text('$' + parseFloat(currentWcOrderData.refundable_remaining).toFixed(2));
        $('#oc-wc-rf-modal-amount').val('').attr('max', currentWcOrderData.refundable_remaining);
        $('#oc-wc-rf-modal-reason').val('');
        new bootstrap.Modal(document.getElementById('oc-wc-refund-modal')).show();
    });

    $('#oc-wc-rf-modal-fill-max').on('click', function () {
        if (!currentWcOrderData) return;
        $('#oc-wc-rf-modal-amount').val(parseFloat(currentWcOrderData.refundable_remaining).toFixed(2));
    });

    $('#oc-wc-rf-modal-submit').on('click', function () {
        if (!currentWcOrderId) return;
        var amount = parseFloat($('#oc-wc-rf-modal-amount').val());
        var reason = $('#oc-wc-rf-modal-reason').val();
        if (!amount || amount <= 0) { alert('Enter a valid refund amount.'); return; }
        if (!confirm('Process a $' + amount.toFixed(2) + ' refund through WooCommerce now?')) return;

        var btn = $(this);
        btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> Processing…');

        $.post('/order-centre/wc-orders/' + currentWcOrderId + '/refund', {
            amount: amount,
            reason: reason,
            _token: '{{ csrf_token() }}'
        }).done(function (r) {
            btn.prop('disabled', false).html('<i class="fa fa-check me-1"></i> Process Refund');
            bootstrap.Modal.getInstance(document.getElementById('oc-wc-refund-modal')).hide();
            if (typeof toastr !== 'undefined') toastr.success(r.message);
            loadWcDrawerOrder(currentWcOrderId);
            if (woTable) woTable.ajax.reload(null, false);
        }).fail(function (xhr) {
            btn.prop('disabled', false).html('<i class="fa fa-check me-1"></i> Process Refund');
            alert((xhr.responseJSON && xhr.responseJSON.message) || 'Refund failed');
        });
    });

    // ----- WC Customer profile modal (uses native customer-modal markup) -----
    window.openWcCustomerProfile = function (id) {
        var $body = $('#oc-customer-modal-body');
        $body.html('<div class="text-center text-muted py-5"><i class="fa fa-spinner fa-spin"></i> Loading…</div>');
        // Hide the "Open Full Customer Page" footer link — WC customers don't have a native page
        $('#oc-customer-full-link').hide();

        // Show the modal immediately so the user gets visual feedback
        new bootstrap.Modal(document.getElementById('oc-customer-modal')).show();

        $.get('/order-centre/wc-customers/' + id + '/profile').done(function (r) {
            var c = r.customer;
            var s = r.stats;

            // Build addresses
            var addressesHtml = '';
            if (c.shipping_data && c.shipping_data.address_1) {
                var sa = c.shipping_data;
                addressesHtml += '<div class="card border-0 bg-light mb-2"><div class="card-body p-2 small">' +
                    '<strong class="d-block mb-1"><i class="fa fa-truck me-1"></i> Shipping</strong>' +
                    [sa.first_name, sa.last_name].filter(Boolean).join(' ') + '<br>' +
                    [sa.address_1, sa.address_2, sa.city, sa.state, sa.postcode].filter(Boolean).join(', ') +
                    '</div></div>';
            }
            if (c.billing_data && c.billing_data.address_1) {
                var ba = c.billing_data;
                addressesHtml += '<div class="card border-0 bg-light mb-2"><div class="card-body p-2 small">' +
                    '<strong class="d-block mb-1"><i class="fa fa-credit-card me-1"></i> Billing</strong>' +
                    [ba.first_name, ba.last_name].filter(Boolean).join(' ') + '<br>' +
                    [ba.address_1, ba.address_2, ba.city, ba.state, ba.postcode].filter(Boolean).join(', ') +
                    '</div></div>';
            }
            if (!addressesHtml) addressesHtml = '<div class="text-muted small">No saved addresses.</div>';

            // Recent orders table
            var ordersHtml = '';
            if ((r.recent_orders || []).length === 0) {
                ordersHtml = '<div class="text-muted small">No orders yet.</div>';
            } else {
                ordersHtml = '<div class="table-responsive"><table class="table table-sm align-middle mb-0">' +
                    '<thead><tr><th>Order</th><th>Status</th><th>Total</th><th>Delivery</th><th>Suburb</th><th>Date</th></tr></thead><tbody>';
                r.recent_orders.forEach(function (o) {
                    ordersHtml += '<tr class="oc-wc-modal-order" style="cursor:pointer" data-wc-id="' + o.id + '">' +
                        '<td><strong class="text-primary">#' + escapeHtml(String(o.order_number)) + '</strong></td>' +
                        '<td>' + wcStatusBadge(o.wc_status) + '</td>' +
                        '<td class="fw-bold">$' + parseFloat(o.total).toFixed(2) + '</td>' +
                        '<td>' + (o.delivery_date || '—') + '</td>' +
                        '<td class="text-muted small">' + escapeHtml(o.suburb || '') + '</td>' +
                        '<td class="text-muted small">' + escapeHtml(o.created_at || '') + '</td>' +
                        '</tr>';
                });
                ordersHtml += '</tbody></table></div>';
            }

            // Build the full body HTML (mirrors the native customer profile layout)
            var initials = (c.name && c.name[0] || '?').toUpperCase();
            var html =
                '<div class="d-flex align-items-start gap-3 mb-3 pb-3 border-bottom">' +
                    '<div style="width:64px;height:64px;border-radius:50%;background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;display:flex;align-items:center;justify-content:center;font-size:1.5rem;font-weight:bold;">' + escapeHtml(initials) + '</div>' +
                    '<div class="flex-grow-1">' +
                        '<h4 class="mb-1">' + escapeHtml(c.name || 'Guest') +
                            (c.is_guest ? ' <span class="badge bg-light text-dark fw-normal small">Guest</span>' : '') +
                        '</h4>' +
                        '<div class="text-muted">' +
                            (c.email ? '<i class="fa fa-envelope me-1"></i><a href="mailto:' + escapeHtml(c.email) + '">' + escapeHtml(c.email) + '</a>' : '') +
                            (c.phone ? ' &middot; <i class="fa fa-phone me-1"></i>' + escapeHtml(c.phone) : '') +
                        '</div>' +
                        (c.created_at ? '<div class="small text-muted">First seen ' + escapeHtml(c.created_at) + '</div>' : '') +
                    '</div>' +
                '</div>' +

                '<div class="row g-2 mb-3">' +
                    '<div class="col-md-4"><div class="card border-0 bg-light h-100"><div class="card-body p-3 text-center">' +
                        '<div class="text-muted small text-uppercase">Orders</div>' +
                        '<div class="h4 mb-0 text-primary">' + Number(s.total_orders || 0).toLocaleString() + '</div>' +
                    '</div></div></div>' +
                    '<div class="col-md-4"><div class="card border-0 bg-light h-100"><div class="card-body p-3 text-center">' +
                        '<div class="text-muted small text-uppercase">Lifetime</div>' +
                        '<div class="h4 mb-0 text-success">$' + Number(s.total_spent || 0).toLocaleString(undefined, {maximumFractionDigits: 0}) + '</div>' +
                    '</div></div></div>' +
                    '<div class="col-md-4"><div class="card border-0 bg-light h-100"><div class="card-body p-3 text-center">' +
                        '<div class="text-muted small text-uppercase">Avg Order</div>' +
                        '<div class="h4 mb-0">$' + Number(s.avg_order || 0).toLocaleString(undefined, {maximumFractionDigits: 0}) + '</div>' +
                    '</div></div></div>' +
                '</div>' +

                '<h6 class="mt-4 mb-2"><i class="fa fa-map-marker-alt me-1"></i> Addresses</h6>' +
                addressesHtml +

                '<h6 class="mt-4 mb-2"><i class="fa fa-shopping-bag me-1"></i> Recent Orders' +
                    (c.last_order_at ? ' <span class="text-muted small fw-normal">(last: ' + escapeHtml(c.last_order_at) + ')</span>' : '') +
                '</h6>' +
                ordersHtml;

            $body.html(html);
        }).fail(function (xhr) {
            $body.html('<div class="alert alert-danger mb-0">Failed to load WC customer: ' + escapeHtml((xhr.responseJSON && xhr.responseJSON.message) || 'unknown error') + '</div>');
        });
    };

    // Click a WC customer's recent order row → close modal, open WC drawer
    $('#oc-customer-modal').on('click', '.oc-wc-modal-order', function () {
        var id = $(this).data('wc-id');
        bootstrap.Modal.getInstance(document.getElementById('oc-customer-modal'))?.hide();
        if (id) setTimeout(function () { openWcDrawer(id); }, 250);
    });

});
</script>

{{-- ---------------------------------------------------------------------------
     Mobile behaviour. Kept separate from the main page script so it is obvious
     what exists purely to make this page work on a phone.
     ------------------------------------------------------------------------ --}}
<script>
$(function () {
    'use strict';

    var isPhone = function () { return window.innerWidth < 992; };

    // --- Landing tab -------------------------------------------------------
    // On a phone the useful view is the work queue, not the full order list.
    // Desktop keeps App Orders. A ?tab= parameter always wins.
    (function () {
        var requested = new URLSearchParams(window.location.search).get('tab');

        if (requested) {
            var el = document.getElementById(requested + '-tab');
            if (el) { new bootstrap.Tab(el).show(); return; }
        }

        if (isPhone()) {
            var needsAction = document.getElementById('needs-action-tab');
            if (needsAction) new bootstrap.Tab(needsAction).show();
        }
    })();

    // Keep the active tab visible in the scrolling strip.
    $('#orderCentreTabs a[data-bs-toggle="tab"]').on('shown.bs.tab', function () {
        if (this.scrollIntoView) {
            this.scrollIntoView({ block: 'nearest', inline: 'center', behavior: 'smooth' });
        }
    });

    // Stat tiles jump to the list they summarise.
    $('[data-oc-tab]').on('click', function (e) {
        e.preventDefault();
        var el = document.getElementById($(this).data('oc-tab'));
        if (el) new bootstrap.Tab(el).show();
    });

    // --- Refresh -----------------------------------------------------------
    // location.reload() threw away scroll position, the open tab and every
    // filter. Redraw the visible table instead.
    $('#oc-refresh').on('click', function () {
        var $btn = $(this).prop('disabled', true);

        $('.tab-pane.active table').each(function () {
            if ($.fn.dataTable.isDataTable(this)) {
                $(this).DataTable().ajax.reload(null, false);
            }
        });

        if (typeof refreshStats === 'function') { refreshStats(); }

        setTimeout(function () { $btn.prop('disabled', false); }, 800);
        if (window.toastr) toastr.info('Refreshed');
    });

    // --- Filter count ------------------------------------------------------
    // The sheet hides the filters, so the trigger has to say how many are on.
    function countFilters() {
        var n = 0;

        ['#oc-search', '#oc-date-from', '#oc-date-to', '#oc-value-min',
         '#oc-value-max', '#oc-suburb', '#oc-source'].forEach(function (sel) {
            if ($(sel).val()) n++;
        });

        n += $('.oc-filter-status:checked, .oc-filter-payment:checked, .oc-filter-tag:checked').length;

        if (window.AdminV2 && AdminV2.Sheet) AdminV2.Sheet.count('oc-filters', n);
    }

    $(document).on('change keyup', '#oc-filters input, #oc-filters select', countFilters);
    $('#oc-clear-filters').on('click', function () { setTimeout(countFilters, 50); });

    // The sheet footer's Clear mirrors the desktop rail's, which owns the reset
    // logic — trigger that rather than duplicating it.
    $('#oc-clear-filters-sheet').on('click', function () {
        $('#oc-clear-filters').trigger('click');
        setTimeout(countFilters, 50);
    });

    countFilters();

    // --- Tap to call / email ----------------------------------------------
    // The whole point of running this from a handset: one tap to ring the
    // customer about a delivery.
    function linkifyContacts() {
        var $phone = $('#oc-d-cust-phone');
        var phone = $.trim($phone.text());

        if (phone && phone !== '--' && !$phone.find('a').length) {
            $phone.html('<a class="oc-contact-action" href="tel:' + phone.replace(/[^\d+]/g, '') + '">' +
                '<i class="fa fa-phone"></i>' + $('<div>').text(phone).html() + '</a>');
        }

        var $email = $('#oc-d-cust-email');
        var email = $.trim($email.text());

        if (email && email !== '--' && $email.attr('href') === '#') {
            $email.attr('href', 'mailto:' + email).addClass('oc-contact-action');
        }
    }

    // The drawer is populated asynchronously, so re-run whenever it opens.
    var drawer = document.getElementById('oc-drawer');

    if (drawer && window.MutationObserver) {
        new MutationObserver(function () {
            if (drawer.classList.contains('active')) setTimeout(linkifyContacts, 120);
        }).observe(drawer, { childList: true, subtree: true, attributes: true, attributeFilter: ['class'] });
    }

    // --- Drawer scroll lock ------------------------------------------------
    // Without this the list behind the sheet scrolls under the finger.
    if (drawer && window.MutationObserver) {
        new MutationObserver(function () {
            document.body.classList.toggle('av-lock', drawer.classList.contains('active') && isPhone());
        }).observe(drawer, { attributes: true, attributeFilter: ['class'] });
    }
});
</script>
@endpush
