@extends('backend.master')

@section('title')
    Manage User Reward Entries
@endsection

@section('body')
    <div class="page-header">
        <div>
            <h1 class="page-title">Manage User Reward Entries</h1>
            <p class="text-muted">View permanent &amp; temporary reward entries for every user. Sort by any column. Click a user to open their full customer profile.</p>
        </div>
    </div>

    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    {{-- Summary cards --}}
    <div class="row mb-3" id="entrySummaryCards">
        <div class="col-md-3 col-6 mb-2">
            <div class="card stat-card stat-blue">
                <div class="card-body">
                    <div class="stat-label">Total Users</div>
                    <div class="stat-value" id="statTotalUsers">—</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-2">
            <div class="card stat-card stat-primary">
                <div class="card-body">
                    <div class="stat-label">Permanent Entries</div>
                    <div class="stat-value" id="statPermanent">—</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-2">
            <div class="card stat-card stat-info">
                <div class="card-body">
                    <div class="stat-label">Temporary Entries</div>
                    <div class="stat-value" id="statTemporary">—</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-6 mb-2">
            <div class="card stat-card stat-success">
                <div class="card-body">
                    <div class="stat-label">Users With Entries</div>
                    <div class="stat-value" id="statUsersWithEntries">—</div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card mb-3">
        <div class="card-body py-3">
            <div class="row align-items-end">
                <div class="col-md-5">
                    <label class="mb-1">Search</label>
                    <input type="text" class="form-control" id="searchInput" placeholder="Search by name or email…">
                </div>
                <div class="col-md-3">
                    <label class="mb-1">Filter</label>
                    <select class="form-control" id="hasEntriesFilter">
                        <option value="all">All Users</option>
                        <option value="1">Only Users With Entries</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="mb-1">Per Page</label>
                    <select class="form-control" id="perPage">
                        <option value="10">10</option>
                        <option value="25" selected>25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </div>
                <div class="col-md-2 text-right">
                    <button class="btn btn-secondary btn-block" id="refreshBtn" title="Refresh data">
                        <i class="fe fe-refresh-cw"></i> Refresh
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- Table --}}
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover entries-table mb-0">
                    <colgroup>
                        <col style="width:50px;">
                        <col style="width:240px;">
                        <col>
                        <col style="width:120px;">
                        <col style="width:120px;">
                        <col style="width:90px;">
                        <col style="width:140px;">
                        <col style="width:160px;">
                    </colgroup>
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>User</th>
                            <th>Email</th>
                            <th class="sortable text-center" data-sort="permanent_entries">
                                <i class="fe fe-shield"></i> Permanent
                                <i class="fe fe-arrow-down sort-icon" id="sort-icon-permanent_entries"></i>
                            </th>
                            <th class="sortable text-center" data-sort="temporary_entries">
                                <i class="fe fe-clock"></i> Temporary
                                <i class="fe fe-arrow-down sort-icon" id="sort-icon-temporary_entries"></i>
                            </th>
                            <th class="sortable text-center" data-sort="total_entries">
                                Total
                                <i class="fe fe-arrow-down sort-icon" id="sort-icon-total_entries"></i>
                            </th>
                            <th class="text-center">Last Updated</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="entriesTableBody">
                        <tr>
                            <td colspan="8" class="text-center text-muted py-5">
                                <i class="fe fe-loader" style="font-size:36px;"></i>
                                <div class="mt-2">Loading users…</div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            {{-- Pagination --}}
            <div class="d-flex justify-content-between align-items-center mt-3" id="paginationBar" style="display:none !important;">
                <div class="text-muted small">
                    Showing <strong id="showingFrom">0</strong>–<strong id="showingTo">0</strong>
                    of <strong id="showingTotal">0</strong> users
                </div>
                <nav>
                    <ul class="pagination pagination-sm mb-0" id="paginationLinks"></ul>
                </nav>
            </div>
        </div>
    </div>

    {{-- Manage Modal --}}
    <div class="modal fade" id="manageEntriesModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fe fe-edit"></i> Manage Permanent Entries
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close" onclick="$('#manageEntriesModal').modal('hide');">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="modalUserId">

                    <div class="user-summary mb-3">
                        <div class="d-flex align-items-center">
                            <div class="avatar avatar-md mr-3" id="modalUserAvatar">U</div>
                            <div class="flex-grow-1">
                                <div class="font-weight-bold" id="modalUserName">—</div>
                                <div class="text-muted small" id="modalUserEmail">—</div>
                            </div>
                        </div>
                        <div class="row mt-3 text-center">
                            <div class="col-6">
                                <div class="text-muted small text-uppercase">Permanent</div>
                                <div class="h4 mb-0 text-primary" id="modalCurrentPermanent">0</div>
                            </div>
                            <div class="col-6">
                                <div class="text-muted small text-uppercase">Temporary</div>
                                <div class="h4 mb-0 text-info" id="modalCurrentTemporary">0</div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-light border small">
                        Only <strong>permanent</strong> entries can be adjusted here. Temporary entries are tied to specific giveaways and expire when those giveaways end.
                    </div>

                    <div class="form-group">
                        <label>Action</label>
                        <select class="form-control" id="modalAction">
                            <option value="add">Add Entries</option>
                            <option value="deduct">Deduct Entries</option>
                            <option value="set">Set To Specific Amount</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label id="modalAmountLabel">Amount to Add</label>
                        <input type="number" class="form-control" id="modalAmount" min="0" value="1">
                    </div>

                    <div class="form-group">
                        <label>Reason <span class="text-muted">(optional, logged for audit)</span></label>
                        <textarea class="form-control" id="modalReason" rows="2" placeholder="e.g. Loyalty reward, Bank transfer correction, Promotional bonus…"></textarea>
                    </div>

                    <div class="alert alert-warning small mb-0" id="modalDeductWarning" style="display:none;">
                        <i class="fe fe-alert-triangle"></i>
                        This will <strong>deduct</strong> entries. Make sure the user has enough.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-dismiss="modal" onclick="$('#manageEntriesModal').modal('hide');">Cancel</button>
                    <button type="button" class="btn btn-primary" id="modalSaveBtn">
                        <i class="fe fe-save"></i> Save Changes
                    </button>
                </div>
            </div>
        </div>
    </div>

@endsection

@push('styles')
<style>
    /* Stat cards */
    .stat-card { border: 0; border-left: 4px solid #dee2e6; }
    .stat-card .stat-label { font-size: 12px; color: #6c757d; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; }
    .stat-card .stat-value { font-size: 28px; font-weight: 700; line-height: 1.2; margin-top: 4px; color: #212529; }
    .stat-card.stat-primary { border-left-color: #007bff; }
    .stat-card.stat-info    { border-left-color: #17a2b8; }
    .stat-card.stat-success { border-left-color: #28a745; }
    .stat-card.stat-blue    { border-left-color: #6f42c1; }

    /* Table */
    .entries-table { table-layout: fixed; width: 100%; }
    .entries-table thead th {
        font-size: 12px;
        text-transform: uppercase;
        letter-spacing: 0.4px;
        color: #495057;
        background: #f8f9fa;
        border-bottom: 2px solid #dee2e6;
        white-space: nowrap;
        padding: 12px 10px;
    }
    .entries-table tbody td {
        vertical-align: middle;
        padding: 10px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .entries-table .sortable { cursor: pointer; user-select: none; }
    .entries-table .sortable:hover { background: #eef1f4; }
    .entries-table .sort-icon { opacity: 0.25; margin-left: 4px; transition: opacity 0.15s ease; }
    .entries-table .sortable.active .sort-icon { opacity: 1; color: #007bff; }

    /* Email cell — allow truncation */
    .entries-table .email-cell {
        color: #6c757d;
        font-size: 13px;
    }

    /* Avatar */
    .avatar {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 36px;
        height: 36px;
        border-radius: 50%;
        font-weight: 600;
        color: #fff;
        background: #6c757d;
        flex-shrink: 0;
        text-transform: uppercase;
        font-size: 13px;
    }
    .avatar.avatar-md { width: 48px; height: 48px; font-size: 16px; }

    /* User cell */
    .user-cell { display: flex; align-items: center; min-width: 0; gap: 12px; }
    .user-cell .avatar { flex-shrink: 0; }
    .user-cell .user-meta {
        line-height: 1.25;
        min-width: 0;
        overflow: hidden;
    }
    .user-cell .user-meta .name {
        font-weight: 600;
        color: #212529;
        font-size: 13px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .user-cell .user-meta .name a { color: #212529; text-decoration: none; }
    .user-cell .user-meta .name a:hover { color: #007bff; text-decoration: underline; }
    .user-cell .user-meta .id { font-size: 11px; color: #adb5bd; }

    /* Entry badges */
    .entry-badge {
        display: inline-block;
        min-width: 38px;
        padding: 4px 10px;
        font-size: 13px;
        font-weight: 600;
        border-radius: 4px;
        text-align: center;
        line-height: 1.4;
    }
    .entry-badge.permanent { background: #e7f1ff; color: #0d6efd; border: 1px solid #b6d4fe; }
    .entry-badge.temporary { background: #cff4fc; color: #055160; border: 1px solid #9eeaf9; }
    .entry-badge.total     { background: #d1e7dd; color: #0f5132; border: 1px solid #a3cfbb; }
    .entry-badge.zero      { background: #f1f3f5; color: #adb5bd; border: 1px solid #e9ecef; font-weight: 500; }

    /* Action buttons */
    .entries-table .action-group {
        display: inline-flex;
        gap: 4px;
        align-items: center;
        justify-content: center;
    }
    .entries-table .action-group .btn {
        height: 32px;
        padding: 0 10px;
        font-size: 13px;
        line-height: 1;
        display: inline-flex;
        align-items: center;
        gap: 4px;
        border-radius: 4px;
    }
    .entries-table .action-group .btn-icon {
        width: 32px;
        padding: 0;
        justify-content: center;
    }

    /* Modal user summary */
    .user-summary {
        background: #f8f9fa;
        border-radius: 6px;
        padding: 16px;
    }

    /* Empty state */
    .empty-state { padding: 60px 20px; text-align: center; color: #6c757d; }
    .empty-state i { font-size: 56px; opacity: 0.3; }

    /* Pagination */
    .pagination .page-link { color: #007bff; }
    .pagination .page-item.active .page-link { background: #007bff; border-color: #007bff; }
</style>
@endpush

@push('scripts')
<script>
$(document).ready(function () {
    const csrf = $('meta[name="csrf-token"]').attr('content');

    // State
    let allUsers = [];
    let filteredUsers = [];
    let sortColumn = 'permanent_entries';
    let sortDir = 'desc';
    let currentPage = 1;
    let perPage = 25;
    let searchTimer = null;

    // Avatar palette - deterministic per user id
    const palette = ['#6f42c1','#0d6efd','#20c997','#fd7e14','#d63384','#198754','#dc3545','#0dcaf0','#6610f2','#ffc107'];
    function avatarColor(id) { return palette[Math.abs(parseInt(id, 10) || 0) % palette.length]; }
    function initials(name) {
        if (!name) return 'U';
        const parts = name.trim().split(/\s+/);
        return ((parts[0]?.[0] || '') + (parts[1]?.[0] || '')).toUpperCase() || 'U';
    }
    function escapeHtml(s) {
        return String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
    }
    function profileUrl(userId) {
        return "{{ route('customer-management.show', ['id' => 'USER_ID_PLACEHOLDER']) }}".replace('USER_ID_PLACEHOLDER', userId);
    }

    // ---------- Data load ----------
    function loadData() {
        const search = $('#searchInput').val() || '';
        const hasEntries = $('#hasEntriesFilter').val() || 'all';

        $('#entriesTableBody').html(
            '<tr><td colspan="8" class="text-center text-muted py-5"><i class="fe fe-loader" style="font-size:36px;"></i><div class="mt-2">Loading users…</div></td></tr>'
        );

        $.ajax({
            url: "{{ route('manage-entries.data') }}",
            type: 'GET',
            data: { search, has_entries: hasEntries },
            success: function (response) {
                allUsers = response.data || [];
                filteredUsers = allUsers.slice();
                currentPage = 1;
                applySort();
                render();
                renderSummary();
                handleDeepLink();
            },
            error: function () {
                $('#entriesTableBody').html(
                    '<tr><td colspan="8" class="text-center text-danger py-5"><i class="fe fe-alert-triangle"></i> Failed to load users. Try refreshing.</td></tr>'
                );
            }
        });
    }

    // ---------- Sorting ----------
    function applySort() {
        filteredUsers.sort((a, b) => {
            const av = a[sortColumn] ?? 0;
            const bv = b[sortColumn] ?? 0;
            return sortDir === 'asc' ? av - bv : bv - av;
        });
    }

    $('.entries-table .sortable').on('click', function () {
        const col = $(this).data('sort');
        if (sortColumn === col) {
            sortDir = sortDir === 'desc' ? 'asc' : 'desc';
        } else {
            sortColumn = col;
            sortDir = 'desc';
        }
        updateSortIcons();
        applySort();
        currentPage = 1;
        render();
    });

    function updateSortIcons() {
        $('.entries-table .sortable').removeClass('active');
        $('.sort-icon').removeClass('fe-arrow-up fe-arrow-down').addClass('fe-arrow-down');

        const $active = $('.entries-table .sortable[data-sort="' + sortColumn + '"]');
        $active.addClass('active');
        const icon = sortDir === 'desc' ? 'fe-arrow-down' : 'fe-arrow-up';
        $('#sort-icon-' + sortColumn).removeClass('fe-arrow-down fe-arrow-up').addClass(icon);
    }

    // ---------- Render ----------
    function render() {
        const tbody = $('#entriesTableBody');
        tbody.empty();

        if (!filteredUsers.length) {
            tbody.html(
                '<tr><td colspan="8" class="empty-state">' +
                '<i class="fe fe-inbox"></i><p class="mb-0 mt-3">No users match the current filters.</p></td></tr>'
            );
            $('#paginationBar').hide();
            return;
        }

        const totalPages = Math.max(1, Math.ceil(filteredUsers.length / perPage));
        if (currentPage > totalPages) currentPage = totalPages;

        const start = (currentPage - 1) * perPage;
        const end = Math.min(start + perPage, filteredUsers.length);
        const pageData = filteredUsers.slice(start, end);

        pageData.forEach((user, idx) => {
            const num = start + idx + 1;
            const profile = profileUrl(user.user_id);
            const perm = parseInt(user.permanent_entries, 10) || 0;
            const temp = parseInt(user.temporary_entries, 10) || 0;
            const total = parseInt(user.total_entries, 10) || 0;

            const row = `
                <tr>
                    <td class="text-muted">${num}</td>
                    <td>
                        <div class="user-cell">
                            <div class="avatar" style="background:${avatarColor(user.user_id)}">${initials(user.user_name)}</div>
                            <div class="user-meta">
                                <div class="name"><a href="${profile}" title="Open customer profile">${escapeHtml(user.user_name || '—')}</a></div>
                                <div class="id">ID #${user.user_id}</div>
                            </div>
                        </div>
                    </td>
                    <td class="email-cell" title="${escapeHtml(user.user_email || '')}">${escapeHtml(user.user_email || '—')}</td>
                    <td class="text-center">
                        <span class="entry-badge ${perm ? 'permanent' : 'zero'}">${perm}</span>
                    </td>
                    <td class="text-center">
                        <span class="entry-badge ${temp ? 'temporary' : 'zero'}">${temp}</span>
                    </td>
                    <td class="text-center">
                        <span class="entry-badge ${total ? 'total' : 'zero'}">${total}</span>
                    </td>
                    <td class="text-center text-muted small">${escapeHtml(user.last_updated_at || 'Never')}</td>
                    <td class="text-center">
                        <div class="action-group">
                            <a href="${profile}" class="btn btn-outline-secondary btn-icon" title="View customer profile">
                                <i class="fe fe-user"></i>
                            </a>
                            <button class="btn btn-primary manage-btn" title="Manage permanent entries"
                                    data-user='${JSON.stringify(user).replace(/'/g, "&#39;")}'>
                                <i class="fe fe-edit"></i> Manage
                            </button>
                        </div>
                    </td>
                </tr>`;
            tbody.append(row);
        });

        // Pagination info
        $('#showingFrom').text(start + 1);
        $('#showingTo').text(end);
        $('#showingTotal').text(filteredUsers.length);
        renderPagination(totalPages);
        $('#paginationBar').css('display', 'flex');
    }

    function renderPagination(totalPages) {
        const ul = $('#paginationLinks');
        ul.empty();

        const addItem = (label, page, opts = {}) => {
            const li = $('<li class="page-item"></li>');
            if (opts.disabled) li.addClass('disabled');
            if (opts.active) li.addClass('active');
            const a = $('<a class="page-link" href="#"></a>').html(label);
            if (!opts.disabled && !opts.active) {
                a.on('click', e => { e.preventDefault(); currentPage = page; render(); });
            } else {
                a.on('click', e => e.preventDefault());
            }
            li.append(a);
            ul.append(li);
        };

        addItem('<i class="fe fe-chevron-left"></i>', currentPage - 1, { disabled: currentPage === 1 });

        // Page numbers (compact)
        const range = [];
        const window = 2;
        for (let p = 1; p <= totalPages; p++) {
            if (p === 1 || p === totalPages || (p >= currentPage - window && p <= currentPage + window)) {
                range.push(p);
            } else if (range[range.length - 1] !== '…') {
                range.push('…');
            }
        }
        range.forEach(p => {
            if (p === '…') addItem('…', null, { disabled: true });
            else addItem(p, p, { active: p === currentPage });
        });

        addItem('<i class="fe fe-chevron-right"></i>', currentPage + 1, { disabled: currentPage === totalPages });
    }

    function renderSummary() {
        const totalUsers = allUsers.length;
        let perm = 0, temp = 0, withEntries = 0;
        allUsers.forEach(u => {
            perm += parseInt(u.permanent_entries, 10) || 0;
            temp += parseInt(u.temporary_entries, 10) || 0;
            if ((parseInt(u.total_entries, 10) || 0) > 0) withEntries += 1;
        });
        $('#statTotalUsers').text(totalUsers.toLocaleString());
        $('#statPermanent').text(perm.toLocaleString());
        $('#statTemporary').text(temp.toLocaleString());
        $('#statUsersWithEntries').text(withEntries.toLocaleString());
    }

    // ---------- Filters ----------
    $('#searchInput').on('input', function () {
        clearTimeout(searchTimer);
        searchTimer = setTimeout(loadData, 300);
    });
    $('#hasEntriesFilter').on('change', loadData);
    $('#perPage').on('change', function () {
        perPage = parseInt($(this).val(), 10);
        currentPage = 1;
        render();
    });
    $('#refreshBtn').on('click', loadData);

    // ---------- Manage modal ----------
    $(document).on('click', '.manage-btn', function () {
        const user = JSON.parse($(this).attr('data-user').replace(/&#39;/g, "'"));
        openManageModalFor(user);
    });

    $('#modalAction').on('change', function () {
        const action = $(this).val();
        const $label = $('#modalAmountLabel');
        const $amount = $('#modalAmount');
        const $warn = $('#modalDeductWarning');

        if (action === 'add') {
            $label.text('Amount to Add');
            $amount.val(1).attr('min', 1);
            $warn.hide();
        } else if (action === 'deduct') {
            $label.text('Amount to Deduct');
            $amount.val(1).attr('min', 1);
            $warn.show();
        } else {
            $label.text('Set Permanent Entries To');
            $amount.val($('#modalCurrentPermanent').text() || 0).attr('min', 0);
            $warn.hide();
        }
    });

    $('#modalSaveBtn').on('click', function () {
        const userId = $('#modalUserId').val();
        const action = $('#modalAction').val();
        const amount = parseInt($('#modalAmount').val(), 10);
        const reason = $('#modalReason').val();

        if (isNaN(amount) || amount < 0 || (action !== 'set' && amount < 1)) {
            toastr.error('Please enter a valid amount');
            return;
        }

        const $btn = $(this);
        $btn.prop('disabled', true).html('<i class="fe fe-loader"></i> Saving…');

        $.ajax({
            url: "{{ url('manage-entries') }}/" + userId + "/update",
            type: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf },
            data: { action, amount, reason },
            success: function (response) {
                if (response.success) {
                    toastr.success(response.message || 'Updated');
                    $('#manageEntriesModal').modal('hide');
                    loadData();
                } else {
                    toastr.error(response.message || 'Update failed');
                }
            },
            error: function (xhr) {
                const msg = xhr.responseJSON?.message || 'Failed to update entries';
                toastr.error(msg);
            },
            complete: function () {
                $btn.prop('disabled', false).html('<i class="fe fe-save"></i> Save Changes');
            }
        });
    });

    // Clean up modal backdrop on close
    $('#manageEntriesModal').on('hidden.bs.modal', function () {
        $('.modal-backdrop').remove();
        $('body').removeClass('modal-open').css('padding-right', '');
    });

    // ---------- Deep link: ?user_id=N auto-opens the manage modal ----------
    let firstLoadDone = false;
    function handleDeepLink() {
        if (firstLoadDone) return;
        firstLoadDone = true;
        const params = new URLSearchParams(window.location.search);
        const targetId = parseInt(params.get('user_id'), 10);
        if (!targetId) return;

        const user = allUsers.find(u => parseInt(u.user_id, 10) === targetId);
        if (!user) {
            toastr.warning('User #' + targetId + ' was not found in the list.');
            return;
        }
        openManageModalFor(user);
    }

    function openManageModalFor(user) {
        $('#modalUserId').val(user.user_id);
        $('#modalUserAvatar').css('background', avatarColor(user.user_id)).text(initials(user.user_name));
        $('#modalUserName').text(user.user_name || '—');
        $('#modalUserEmail').text(user.user_email || '—');
        $('#modalCurrentPermanent').text(user.permanent_entries || 0);
        $('#modalCurrentTemporary').text(user.temporary_entries || 0);
        $('#modalAction').val('add');
        $('#modalAmount').val(1);
        $('#modalReason').val('');
        $('#modalAmountLabel').text('Amount to Add');
        $('#modalDeductWarning').hide();
        $('#manageEntriesModal').modal('show');
    }

    // ---------- Init ----------
    updateSortIcons();
    loadData();
});
</script>
@endpush
