@extends('backend.master')

@section('title', 'Customers')

@section('body')

<style>
    /* Sortable column headers — inherit design system colours */
    #customersTable thead th a {
        display: inline-block;
        width: 100%;
        font-weight: 600;
        color: var(--av-text);
    }
    #customersTable thead th a:hover {
        color: var(--av-primary);
        text-decoration: none;
    }
    #customersTable thead th a:hover i.fe-chevrons-down {
        opacity: .75;
    }
</style>

{{-- One primary action. Export and the reachability test are occasional jobs,
     so they sit behind a "More" menu instead of three shouting buttons. --}}
<x-admin.page-header
    title="Customers"
    subtitle="Everyone who has an account or has ordered from you."
    :crumbs="['Dashboard' => route('dashboard'), 'Customers' => null]">
    <x-slot:actions>
        <a href="{{ route('customer-management.create') }}" class="btn btn-primary">
            <i class="fa fa-plus"></i> Add customer
        </a>

        <div class="dropdown">
            <button type="button" class="btn btn-secondary" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fa fa-ellipsis-h"></i> More
            </button>
            <div class="dropdown-menu dropdown-menu-end av-menu">
                <button type="button" class="av-menu__item" onclick="exportCustomers()">
                    <i class="fa fa-download"></i> Export to Excel
                </button>
                <button type="button" class="av-menu__item" id="bulkReachabilityBtn"
                        title="Check which customers can still receive app notifications">
                    <i class="fa fa-bell"></i> Test notification reach
                </button>
            </div>
        </div>
    </x-slot:actions>
</x-admin.page-header>

@if(session('success'))
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        {{ session('success') }}
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
@endif

@if(session('error'))
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        {{ session('error') }}
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
            <span aria-hidden="true">&times;</span>
        </button>
    </div>
@endif

<div class="row">
    <div class="col-lg-12">
        <div class="card custom-card">
            <div class="card-body">
                {{-- Filters. Five stacked selects ahead of the list is most of a
                     phone screen, so on mobile they move into a bottom sheet
                     behind a single button. --}}
                @php
                    $activeFilters = collect(['search', 'role', 'status', 'city', 'state'])
                        ->filter(fn ($key) => filled(request($key)))->count();
                @endphp

                <div class="av-row av-row--between mb-3 av-only-mobile">
                    <button type="button" class="btn btn-secondary" data-sheet-open="cust-filters">
                        <i class="fa fa-filter"></i> Filters
                        @if ($activeFilters)
                            <span class="av-sheet-count">{{ $activeFilters }}</span>
                        @endif
                    </button>

                    @if ($activeFilters)
                        <a href="{{ route('customer-management.index') }}" class="btn btn-sm btn-link">Clear</a>
                    @endif
                </div>

                <form method="GET" action="{{ route('customer-management.index') }}" class="mb-4 av-sheet" id="cust-filters">
                    {{-- Preserve sort state across filter submissions --}}
                    @if(request('sort'))
                        <input type="hidden" name="sort" value="{{ request('sort') }}">
                        <input type="hidden" name="direction" value="{{ request('direction', 'desc') }}">
                    @endif

                    <div class="av-sheet__handle"></div>
                    <div class="av-sheet__head">
                        <h2 class="av-sheet__title">Filter customers</h2>
                        <button type="button" class="av-iconbtn" data-sheet-close aria-label="Close">
                            <i class="fa fa-times"></i>
                        </button>
                    </div>

                    <div class="av-sheet__body">
                    <div class="row g-2">
                        <div class="col-md-3">
                            <input type="text" name="search" class="form-control" placeholder="Search name, email, phone..." value="{{ request('search') }}">
                        </div>
                        <div class="col-md-2">
                            <select name="role" class="form-control">
                                <option value="">All Roles</option>
                                <option value="customer" {{ request('role') === 'customer' ? 'selected' : '' }}>Customer</option>
                                <option value="business" {{ request('role') === 'business' ? 'selected' : '' }}>Business</option>
                                <option value="admin" {{ request('role') === 'admin' ? 'selected' : '' }}>Admin</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="status" class="form-control">
                                <option value="">All Status</option>
                                <option value="reachable" {{ request('status') === 'reachable' ? 'selected' : '' }}>🟢 Reachable</option>
                                <option value="cooling"   {{ request('status') === 'cooling'   ? 'selected' : '' }}>🟡 Cooling</option>
                                <option value="stale"     {{ request('status') === 'stale'     ? 'selected' : '' }}>🟠 Stale</option>
                                <option value="no_app"    {{ request('status') === 'no_app'    ? 'selected' : '' }}>⚪ No App</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="city" class="form-control">
                                <option value="">All Cities</option>
                                @foreach($cities as $city)
                                    <option value="{{ $city }}" {{ request('city') === $city ? 'selected' : '' }}>{{ $city }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-2">
                            <select name="state" class="form-control">
                                <option value="">All States</option>
                                @foreach($states as $state)
                                    <option value="{{ $state }}" {{ request('state') === $state ? 'selected' : '' }}>{{ $state }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-1 av-only-desktop">
                            <button type="submit" class="btn btn-primary w-100">Filter</button>
                        </div>
                    </div>
                    </div>{{-- /av-sheet__body --}}

                    <div class="av-sheet__foot">
                        <a href="{{ route('customer-management.index') }}" class="btn btn-secondary">Clear</a>
                        <button type="submit" class="btn btn-primary">Show results</button>
                    </div>
                </form>

                <!-- Bulk Actions -->
                <div class="mb-3">
                    <button type="button" class="btn btn-sm btn-danger" onclick="bulkDelete()" id="bulkDeleteBtn" style="display: none;">
                        <i class="fe fe-trash-2"></i> Delete Selected
                    </button>
                    <span id="selectedCount" class="ml-2 text-muted"></span>
                </div>

                <!-- Customers Table -->
                @php
                    // Sort helper — builds a header cell that links to ?sort=X&direction=Y,
                    // cycling: none → asc → desc → none.
                    $currentSort = request('sort');
                    $currentDir  = strtolower(request('direction', 'desc'));
                    $sortLink = function ($column, $label) use ($currentSort, $currentDir) {
                        $params = request()->except(['sort', 'direction', 'page']);
                        if ($currentSort !== $column) {
                            // Not currently sorted by this column → first click = descending (most→least)
                            $next = ['sort' => $column, 'direction' => 'desc'];
                            $icon = '<i class="fe fe-chevrons-down ml-1 text-muted" style="opacity:.35"></i>';
                        } elseif ($currentDir === 'desc') {
                            // Currently desc → next click = ascending (least→most)
                            $next = ['sort' => $column, 'direction' => 'asc'];
                            $icon = '<i class="fe fe-chevron-down ml-1 text-primary"></i>';
                        } else {
                            // Currently asc → next click = clear sort (back to default newest-first)
                            $next = []; // unsetting
                            $icon = '<i class="fe fe-chevron-up ml-1 text-primary"></i>';
                        }
                        $url = url()->current() . '?' . http_build_query(array_merge($params, $next));
                        return '<a href="' . e($url) . '" class="text-dark text-decoration-none" style="cursor:pointer;display:inline-block;width:100%">' . e($label) . $icon . '</a>';
                    };
                @endphp
                <div class="table-responsive">
                    <table class="table table-bordered text-nowrap border-bottom" id="customersTable">
                        <thead>
                            <tr>
                                <th>
                                    <input type="checkbox" id="selectAll">
                                </th>
                                <th>{!! $sortLink('id', 'ID') !!}</th>
                                <th>{!! $sortLink('name', 'Name') !!}</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>{!! $sortLink('role', 'Role') !!}</th>
                                <th>Location</th>
                                <th title="Reachable → Cooling → Stale → No App (descending)">{!! $sortLink('status', 'Status') !!}</th>
                                <th>{!! $sortLink('orders_count', 'Orders') !!}</th>
                                <th>{!! $sortLink('total_spent', 'Total Spent') !!}</th>
                                <th title="Permanent entries from $200+ orders or admin gifts. Never reset.">{!! $sortLink('permanent_entries_sum', 'Permanent') !!}</th>
                                <th title="Paid/free entries in the current week's draw — not yet drawn.">{!! $sortLink('active_temp_entries_sum', 'Active Temp') !!}</th>
                                <th title="Every paid/free entry ever bought, including past draws — lifetime view.">{!! $sortLink('lifetime_temp_entries_sum', 'Temp Lifetime') !!}</th>
                                <th>{!! $sortLink('joined', 'Joined') !!}</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($customers as $customer)
                                <tr>
                                    <td>
                                        <input type="checkbox" class="customer-checkbox" value="{{ $customer->id }}">
                                    </td>
                                    <td>{{ $customer->id }}</td>
                                    <td>
                                        <a href="{{ route('customer-management.show', $customer->id) }}" class="font-weight-bold">
                                            {{ $customer->name }}
                                        </a>
                                    </td>
                                    <td>{{ $customer->email }}</td>
                                    <td>{{ $customer->phone ?? '-' }}</td>
                                    <td>
                                        @php
                                            $roleColors = [
                                                'admin' => 'danger',      // Red
                                                'business' => 'warning',  // Orange/Yellow
                                                'customer' => 'info',     // Blue
                                            ];
                                            $roleColor = $roleColors[$customer->role] ?? 'secondary';
                                        @endphp
                                        <span class="badge badge-{{ $roleColor }}">
                                            {{ ucfirst($customer->role ?? 'customer') }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($customer->shippingAddresses->first())
                                            {{ $customer->shippingAddresses->first()->city }}, {{ $customer->shippingAddresses->first()->state }}
                                        @else
                                            -
                                        @endif
                                    </td>
                                    <td class="reachability-cell" data-customer-id="{{ $customer->id }}">
                                        @php
                                            $state = $customer->reachability_state ?? 'no_app';
                                            $badgeMap = [
                                                'reachable' => ['success',   '🟢', 'Reachable', 'Token validated recently — device confirmed receiving push notifications.'],
                                                'cooling'   => ['warning',   '🟡', 'Cooling',   'Token present but not validated for ' . \App\Services\ReachabilityService::COOLING_THRESHOLD_DAYS . '+ days. May still work — click Test to confirm.'],
                                                'stale'     => ['danger',    '🟠', 'Stale',     'Token very old (' . \App\Services\ReachabilityService::STALE_THRESHOLD_DAYS . '+ days). Almost certainly uninstalled — click Test to verify.'],
                                                'no_app'    => ['secondary', '⚪', 'No App',    'Customer has not installed the app or has fully uninstalled.'],
                                            ];
                                            [$color, $emoji, $label, $tooltip] = $badgeMap[$state] ?? $badgeMap['no_app'];
                                        @endphp
                                        <span class="badge badge-{{ $color }} reachability-badge" title="{{ $tooltip }}">
                                            {{ $emoji }} {{ $label }}
                                        </span>
                                        @if($customer->fcm_token)
                                            <button type="button"
                                                    class="btn btn-xs btn-link p-0 ml-1 reachability-test-btn"
                                                    data-customer-id="{{ $customer->id }}"
                                                    title="Send silent test ping to confirm device still receives notifications">
                                                <i class="fe fe-radio"></i>
                                            </button>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="av-badge badge-light">{{ $customer->orders_count }}</span>
                                    </td>
                                    <td>${{ number_format($customer->total_spent ?? 0, 2) }}</td>
                                    <td>
                                        <span class="av-badge badge-warning" title="Permanent entries — always active">{{ $customer->permanent_entries_sum ?? 0 }}</span>
                                    </td>
                                    <td>
                                        <span class="av-badge badge-info" title="Active temporary entries — in the current draw">{{ $customer->active_temp_entries_sum ?? 0 }}</span>
                                    </td>
                                    <td>
                                        <span class="av-badge badge-light" title="Lifetime temporary entries — total ever purchased">{{ $customer->lifetime_temp_entries_sum ?? 0 }}</span>
                                    </td>
                                    <td>{{ $customer->created_at?->format('M d, Y') ?? 'N/A' }}</td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="{{ route('customer-management.show', $customer->id) }}" class="btn btn-sm btn-info" aria-label="View">
                                                <i class="fe fe-eye"></i>
                                            </a>
                                            <a href="{{ route('customer-management.edit', $customer->id) }}" class="btn btn-sm btn-primary" aria-label="Edit">
                                                <i class="fe fe-edit"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-danger" onclick="deleteCustomer({{ $customer->id }})" aria-label="Delete">
                                                <i class="fe fe-trash-2"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="15" class="text-center">No customers found</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <div class="mt-3">
                    {{ $customers->appends(request()->query())->links() }}
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Delete Form -->
<form id="deleteForm" method="POST" style="display: none;">
    @csrf
    @method('DELETE')
</form>

<!-- Bulk Delete Form -->
<form id="bulkDeleteForm" action="{{ route('customer-management.bulk-delete') }}" method="POST" style="display: none;">
    @csrf
    <input type="hidden" name="customer_ids" id="bulkDeleteIds">
</form>

@endsection

{{-- Scripts pushed to the scripts stack in master.blade.php.
     Note: the push/endpush directive is used here (not section/endsection)
     because the master layout uses a stack, not a yield, for scripts. --}}
@push('scripts')
<script>
// Select All Checkbox
document.getElementById('selectAll').addEventListener('change', function() {
    const checkboxes = document.querySelectorAll('.customer-checkbox');
    checkboxes.forEach(cb => cb.checked = this.checked);
    updateBulkActions();
});

// Individual Checkboxes
document.querySelectorAll('.customer-checkbox').forEach(cb => {
    cb.addEventListener('change', updateBulkActions);
});

// Update Bulk Actions Display
function updateBulkActions() {
    const checked = document.querySelectorAll('.customer-checkbox:checked');
    const bulkBtn = document.getElementById('bulkDeleteBtn');
    const countSpan = document.getElementById('selectedCount');
    
    if (checked.length > 0) {
        bulkBtn.style.display = 'inline-block';
        countSpan.textContent = `${checked.length} selected`;
    } else {
        bulkBtn.style.display = 'none';
        countSpan.textContent = '';
    }
}

// Delete Single Customer
function deleteCustomer(id) {
    if (confirm('Are you sure you want to delete this customer? This action cannot be undone.')) {
        const form = document.getElementById('deleteForm');
        form.action = `/backend/customers/${id}`;
        form.submit();
    }
}

// Bulk Delete
function bulkDelete() {
    const checked = Array.from(document.querySelectorAll('.customer-checkbox:checked')).map(cb => cb.value);
    
    if (checked.length === 0) {
        alert('Please select customers to delete');
        return;
    }
    
    if (confirm(`Are you sure you want to delete ${checked.length} customer(s)? This action cannot be undone.`)) {
        document.getElementById('bulkDeleteIds').value = JSON.stringify(checked);
        document.getElementById('bulkDeleteForm').submit();
    }
}

// Export Customers
function exportCustomers() {
    const params = new URLSearchParams(window.location.search);
    window.location.href = '{{ route("customers.export") }}?' + params.toString();
}

// ============================================================
// Bulk Reachability Test — batched, with live progress.
// Step 1: fetch list of candidate user IDs (fast)
// Step 2: ping in batches of 25, updating the progress bar each batch
// ============================================================
document.getElementById('bulkReachabilityBtn')?.addEventListener('click', function () {
    if (!confirm(
        "Test reachability for ALL customers with an app installed?\n\n" +
        "This sends silent push notifications (invisible to customers) to verify each one's device still receives notifications.\n\n" +
        "It can take several minutes depending on how many customers you have."
    )) return;

    openBulkReachabilityModal();
});

function openBulkReachabilityModal() {
    // Build the modal once and reuse
    let modal = document.getElementById('bulkReachabilityModal');
    if (!modal) {
        const div = document.createElement('div');
        div.innerHTML = `
            <div id="bulkReachabilityModalBackdrop" style="position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9998;display:flex;align-items:center;justify-content:center;">
                <div id="bulkReachabilityModal" style="background:#fff;border-radius:8px;width:90%;max-width:520px;padding:1.5rem;box-shadow:0 10px 40px rgba(0,0,0,.2);">
                    <h5 style="margin:0 0 .25rem;display:flex;align-items:center;gap:.5rem;">
                        <i class="fe fe-radio" style="color:#17a2b8;"></i>
                        Bulk Reachability Test
                    </h5>
                    <div id="bulkReachStatus" style="color:#6c757d;font-size:.85rem;margin-bottom:1rem;">Preparing…</div>

                    <div style="height:24px;background:#e9ecef;border-radius:12px;overflow:hidden;position:relative;">
                        <div id="bulkReachBar" style="height:100%;width:0%;background:linear-gradient(90deg,#667eea,#764ba2);transition:width .3s;"></div>
                        <div id="bulkReachPct" style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:600;color:#1a1a1a;text-shadow:0 0 4px rgba(255,255,255,.8);">0%</div>
                    </div>
                    <div id="bulkReachCount" style="color:#6c757d;font-size:.75rem;margin-top:.4rem;text-align:center;">0 of 0</div>

                    <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:.4rem;margin-top:1rem;">
                        <div style="background:#d4edda;border-radius:6px;padding:.5rem;text-align:center;">
                            <div style="font-size:.7rem;color:#155724;">REACHABLE</div>
                            <div id="bulkReachR" style="font-size:1.4rem;font-weight:bold;color:#155724;">0</div>
                        </div>
                        <div style="background:#f8d7da;border-radius:6px;padding:.5rem;text-align:center;">
                            <div style="font-size:.7rem;color:#721c24;">DEAD</div>
                            <div id="bulkReachD" style="font-size:1.4rem;font-weight:bold;color:#721c24;">0</div>
                        </div>
                        <div style="background:#fff3cd;border-radius:6px;padding:.5rem;text-align:center;">
                            <div style="font-size:.7rem;color:#856404;">ERRORS</div>
                            <div id="bulkReachE" style="font-size:1.4rem;font-weight:bold;color:#856404;">0</div>
                        </div>
                        <div style="background:#e2e3e5;border-radius:6px;padding:.5rem;text-align:center;">
                            <div style="font-size:.7rem;color:#383d41;">SKIPPED</div>
                            <div id="bulkReachS" style="font-size:1.4rem;font-weight:bold;color:#383d41;">0</div>
                        </div>
                    </div>

                    <div style="display:flex;justify-content:flex-end;gap:.5rem;margin-top:1.25rem;">
                        <button type="button" id="bulkReachAbort" class="btn btn-sm btn-secondary">Cancel</button>
                        <button type="button" id="bulkReachClose" class="btn btn-sm btn-primary" style="display:none;">Close & Reload</button>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(div.firstElementChild);
        modal = document.getElementById('bulkReachabilityModal');
    }

    // Reset state each time
    document.getElementById('bulkReachStatus').textContent = 'Preparing list of customers to test…';
    document.getElementById('bulkReachBar').style.width = '0%';
    document.getElementById('bulkReachPct').textContent = '0%';
    document.getElementById('bulkReachCount').textContent = '0 of 0';
    document.getElementById('bulkReachR').textContent = '0';
    document.getElementById('bulkReachD').textContent = '0';
    document.getElementById('bulkReachE').textContent = '0';
    document.getElementById('bulkReachS').textContent = '0';
    document.getElementById('bulkReachAbort').style.display = '';
    document.getElementById('bulkReachClose').style.display = 'none';
    document.getElementById('bulkReachabilityModalBackdrop').style.display = 'flex';

    runBulkReachabilityTest();
}

// Track abort state across batches
let bulkReachAborted = false;
document.body.addEventListener('click', function (e) {
    if (e.target.id === 'bulkReachAbort') {
        bulkReachAborted = true;
        document.getElementById('bulkReachStatus').textContent = 'Aborting after current batch…';
    } else if (e.target.id === 'bulkReachClose') {
        document.getElementById('bulkReachabilityModalBackdrop').style.display = 'none';
        window.location.reload();
    }
});

async function runBulkReachabilityTest() {
    bulkReachAborted = false;
    const token = document.querySelector('meta[name="csrf-token"]')?.content
        || document.querySelector('input[name="_token"]')?.value;

    const totals = { reachable: 0, unreachable: 0, error: 0, no_token: 0 };
    const BATCH_SIZE = 25;

    const setStatus = (msg, isError) => {
        const el = document.getElementById('bulkReachStatus');
        if (el) {
            el.textContent = msg;
            el.style.color = isError ? '#dc3545' : '#6c757d';
        }
        console.log('[BulkReach]', msg);
    };
    const finishWithError = (msg) => {
        setStatus(msg, true);
        document.getElementById('bulkReachAbort').style.display = 'none';
        document.getElementById('bulkReachClose').style.display = '';
        document.getElementById('bulkReachClose').textContent = 'Close';
    };

    // Step 1 — get the candidate IDs
    setStatus('Preparing list of customers to test…');
    let candidates;
    try {
        const url = '{{ url("customer-management/bulk-test-reachability/candidates") }}';
        console.log('[BulkReach] Fetching candidates from:', url);
        const r = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token || '',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({ scope: 'all_with_token' }),
            credentials: 'same-origin',
        });
        console.log('[BulkReach] Candidates response status:', r.status);
        if (!r.ok) {
            const txt = await r.text();
            console.error('[BulkReach] Server returned non-200:', r.status, txt);
            return finishWithError(`Failed to fetch list (HTTP ${r.status}). The server may need a route cache clear. Run on the server:\n\nphp artisan route:clear && php artisan optimize:clear`);
        }
        candidates = await r.json();
    } catch (err) {
        console.error('[BulkReach] Fetch failed:', err);
        return finishWithError('Failed to fetch candidates: ' + err.message);
    }

    if (!candidates.ok) {
        return finishWithError('Failed: ' + (candidates.message || 'unknown error'));
    }

    const ids = candidates.ids || [];
    const total = ids.length;

    if (total === 0) {
        setStatus('No customers with app tokens found. Nothing to test.');
        document.getElementById('bulkReachAbort').style.display = 'none';
        document.getElementById('bulkReachClose').style.display = '';
        return;
    }

    setStatus(`Testing ${total} customers in batches of ${BATCH_SIZE}…`);
    document.getElementById('bulkReachCount').textContent = `0 of ${total}`;

    // Step 2 — page through batches
    let processed = 0;
    const startTs = Date.now();
    const batchUrl = '{{ url("customer-management/bulk-test-reachability/batch") }}';

    for (let i = 0; i < ids.length; i += BATCH_SIZE) {
        if (bulkReachAborted) break;
        const batch = ids.slice(i, i + BATCH_SIZE);
        try {
            const r = await fetch(batchUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token || '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ ids: batch }),
                credentials: 'same-origin',
            });
            if (!r.ok) {
                console.error('[BulkReach] Batch HTTP ' + r.status);
                totals.error += batch.length;
            } else {
                const data = await r.json();
                if (data.ok && data.stats) {
                    totals.reachable   += data.stats.reachable || 0;
                    totals.unreachable += data.stats.unreachable || 0;
                    totals.error       += data.stats.error || 0;
                    totals.no_token    += data.stats.no_token || 0;
                } else {
                    totals.error += batch.length;
                }
            }
            processed += batch.length;
        } catch (err) {
            console.error('[BulkReach] Batch error:', err);
            totals.error += batch.length;
            processed += batch.length;
        }

        // Update UI
        const pct = Math.round((processed / total) * 100);
        document.getElementById('bulkReachBar').style.width = pct + '%';
        document.getElementById('bulkReachPct').textContent = pct + '%';
        document.getElementById('bulkReachCount').textContent = `${processed} of ${total}`;
        document.getElementById('bulkReachR').textContent = totals.reachable;
        document.getElementById('bulkReachD').textContent = totals.unreachable;
        document.getElementById('bulkReachE').textContent = totals.error;
        document.getElementById('bulkReachS').textContent = totals.no_token;

        // ETA in status line
        const elapsedSec = (Date.now() - startTs) / 1000;
        const rate = processed / elapsedSec;
        const remaining = total - processed;
        const etaSec = rate > 0 ? Math.round(remaining / rate) : 0;
        if (remaining > 0 && !bulkReachAborted) {
            setStatus(`Processed ${processed} of ${total}` + (etaSec > 0 ? ` — about ${etaSec}s remaining` : ''));
        }
    }

    const elapsed = ((Date.now() - startTs) / 1000).toFixed(1);
    const finalMsg = bulkReachAborted
        ? `Aborted at ${processed} of ${total} (${elapsed}s).`
        : `Done — tested ${processed} customers in ${elapsed}s.`;
    setStatus(finalMsg);
    document.getElementById('bulkReachAbort').style.display = 'none';
    document.getElementById('bulkReachClose').style.display = '';
}

// ============================================================
// Reachability test — click the radio icon next to the badge.
// Sends a silent FCM ping; on response, the badge updates inline.
// ============================================================
document.querySelectorAll('.reachability-test-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        const customerId = this.dataset.customerId;
        const cell = document.querySelector(`.reachability-cell[data-customer-id="${customerId}"]`);
        const oldHtml = this.innerHTML;
        this.disabled = true;
        this.innerHTML = '<i class="fe fe-loader" style="animation: spin 1s linear infinite"></i>';

        const token = document.querySelector('meta[name="csrf-token"]')?.content
            || document.querySelector('input[name="_token"]')?.value;

        fetch(`{{ url('customer-management') }}/${customerId}/test-reachability`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': token || '',
            },
        })
        .then(r => r.json())
        .then(r => {
            // Update the badge in place
            const badge = cell.querySelector('.reachability-badge');
            const colorMap = {
                reachable: ['success',   '🟢'],
                cooling:   ['warning',   '🟡'],
                stale:     ['danger',    '🟠'],
                no_app:    ['secondary', '⚪'],
            };
            const [color, emoji] = colorMap[r.badge_state] || colorMap.no_app;
            badge.className = `badge badge-${color} reachability-badge`;
            badge.textContent = `${emoji} ${r.badge_label}`;
            badge.title = r.detail;

            // If the token was cleared, hide the test button entirely
            if (r.status === 'unreachable' || r.status === 'no_token') {
                this.style.display = 'none';
            } else {
                this.innerHTML = oldHtml;
                this.disabled = false;
            }

            // Toast feedback
            showTinyToast(r.detail, r.status === 'reachable' ? 'success' : (r.status === 'error' ? 'error' : 'warning'));
        })
        .catch(err => {
            this.innerHTML = oldHtml;
            this.disabled = false;
            showTinyToast('Request failed: ' + err.message, 'error');
        });
    });
});

function showTinyToast(msg, type) {
    const colors = { success: '#28a745', warning: '#ffc107', error: '#dc3545' };
    const t = document.createElement('div');
    t.style.cssText = `position:fixed;top:20px;right:20px;z-index:9999;padding:.5rem 1rem;background:${colors[type] || '#333'};color:#fff;border-radius:4px;box-shadow:0 2px 8px rgba(0,0,0,.2);font-size:.85rem;max-width:320px;`;
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(() => { t.style.opacity = '0'; t.style.transition = 'opacity .3s'; }, 3000);
    setTimeout(() => t.remove(), 3500);
}

// Spin keyframe for the loading icon
if (!document.getElementById('reachability-spin-keyframe')) {
    const style = document.createElement('style');
    style.id = 'reachability-spin-keyframe';
    style.textContent = `@keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }`;
    document.head.appendChild(style);
}
</script>
@endpush