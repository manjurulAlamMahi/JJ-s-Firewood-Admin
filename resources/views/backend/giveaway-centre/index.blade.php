@extends('backend.master')

@section('title', 'Giveaway Centre')

@section('body')
<style>
    /* Giveaway Centre — uses design system tokens */
    .giveaway-centre-header {
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
        cursor: pointer;
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

    .filter-section {
        background: var(--av-surface);
        padding: 1.25rem;
        border-radius: var(--av-r-sm);
        margin-bottom: 1rem;
        border: 1px solid var(--av-border);
    }

    .img-preview {
        width: 50px;
        height: 60px;
        object-fit: cover;
        border-radius: 6px;
        border: 1px solid var(--av-border);
    }

    .badge-large {
        font-size: 14px;
        padding: 6px 10px;
    }

    thead th[onclick] {
        cursor: pointer;
        user-select: none;
    }

    thead th[onclick]:hover {
        background-color: var(--av-surface-3);
    }
</style>

<div class="giveaway-centre-header">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
        <div>
            <h2 class="mb-1">🎁 Giveaway Centre</h2>
            <p class="mb-0 text-muted">Manage all your giveaways, entry packages, and permanent entries in one place</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('giveaway-centre.create') }}" class="btn btn-primary">
                <i class="fe fe-plus"></i> Create New Giveaway
            </a>
            <button class="btn btn-secondary" onclick="location.reload()">
                <i class="fe fe-refresh-cw"></i> Refresh
            </button>
        </div>
    </div>
    
    <div class="row mt-4">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(245, 87, 108, 0.1); color: #f5576c;">
                    <i class="fe fe-gift"></i>
                </div>
                <h3 class="mb-0">{{ $stats['total_giveaways'] }}</h3>
                <small class="text-muted">Total Giveaways</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(40, 167, 69, 0.1); color: #28a745;">
                    <i class="fe fe-check-circle"></i>
                </div>
                <h3 class="mb-0">{{ $stats['active_giveaways'] }}</h3>
                <small class="text-muted">Active Giveaways</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(23, 162, 184, 0.1); color: #17a2b8;">
                    <i class="fe fe-clock"></i>
                </div>
                <h3 class="mb-0">{{ $stats['scheduled_giveaways'] }}</h3>
                <small class="text-muted">Scheduled</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-icon" style="background: rgba(108, 117, 125, 0.1); color: #6c757d;">
                    <i class="fe fe-users"></i>
                </div>
                <h3 class="mb-0">{{ $stats['users_with_permanent_entries'] }}</h3>
                <small class="text-muted">Users with Permanent Entries</small>
            </div>
        </div>
    </div>
</div>

@if($poolStatus && !$poolStatus['healthy'])
<div class="alert alert-danger d-flex justify-content-between align-items-center flex-wrap" style="gap:.5rem;">
    <div>
        <strong>Giveaways aren't drawing automatically.</strong>
        The server scheduler {{ $poolStatus['last_heartbeat_at'] ? 'last ran ' . $poolStatus['last_heartbeat_at']->diffForHumans() : 'has never run' }}.
        The cron job for <code>php artisan schedule:run</code> needs to be set up on the server.
    </div>
    <a href="?tab=auto-pool" class="btn btn-sm btn-light">Open Auto Pool</a>
</div>
@endif

<ul class="nav nav-tabs nav-tabs-custom" id="giveawayCentreTabs" role="tablist">
    <li class="nav-item">
        <a class="nav-link active" id="giveaways-tab" data-toggle="tab" href="#giveaways" role="tab">
            <i class="fe fe-gift mr-2"></i> Giveaways
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="auto-pool-tab" data-toggle="tab" href="#auto-pool" role="tab">
            <i class="fe fe-repeat mr-2"></i> Auto Pool
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="live-draws-tab" data-toggle="tab" href="#live-draws" role="tab">
            <i class="fe fe-video mr-2"></i> Live Draws
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="entry-packages-tab" data-toggle="tab" href="#entry-packages" role="tab">
            <i class="fe fe-package mr-2"></i> Entry Packages
        </a>
    </li>
    <li class="nav-item">
        <a class="nav-link" id="permanent-entries-tab" data-toggle="tab" href="#permanent-entries" role="tab">
            <i class="fe fe-star mr-2"></i> Permanent Entries
        </a>
    </li>
</ul>

<div class="tab-content" id="giveawayCentreTabContent">
    <div class="tab-pane fade show active" id="giveaways" role="tabpanel">
        @include('backend.giveaway-centre.tabs.giveaways')
    </div>
    
    <div class="tab-pane fade" id="auto-pool" role="tabpanel">
        @include('backend.giveaway-centre.tabs.pool')
    </div>

    <div class="tab-pane fade" id="live-draws" role="tabpanel">
        @include('backend.giveaway-centre.tabs.live-draws')
    </div>
    
    <div class="tab-pane fade" id="entry-packages" role="tabpanel">
        @include('backend.giveaway-centre.tabs.entry-packages')
    </div>
    
    <div class="tab-pane fade" id="permanent-entries" role="tabpanel">
        @include('backend.giveaway-centre.tabs.permanent-entries')
    </div>
</div>

@endsection

@push('scripts')
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<script>
$(document).ready(function() {
    console.log('🎁 Giveaway Centre - WITH CREATE BUTTON');
    
    $.ajaxSetup({
        headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')}
    });
    
    // Check URL parameter for tab activation
    const urlParams = new URLSearchParams(window.location.search);
    const tabParam = urlParams.get('tab');
    
    if (tabParam === 'live-draws') {
        // Activate Live Draws tab
        $('.nav-tabs a').removeClass('active');
        $('.tab-pane').removeClass('show active');
        $('#live-draws-tab').addClass('active');
        $('#live-draws').addClass('show active');
    } else if (tabParam === 'auto-pool') {
        $('.nav-tabs a').removeClass('active');
        $('.tab-pane').removeClass('show active');
        $('#auto-pool-tab').addClass('active');
        $('#auto-pool').addClass('show active');
        loadPool();
    }
    
    let activeCurrentPage = 1;
    let completedCurrentPage = 1;
    const itemsPerPage = 10;
    let allActiveGiveaways = [];
    let allCompletedGiveaways = [];
    
    let allPermanentUsers = [];
    let permanentCurrentPage = 1;
    let permanentItemsPerPage = 30;
    let permanentSortColumn = 'permanent';
    let permanentSortDirection = 'desc';
    
    loadGiveaways();
    
    $('.nav-tabs a').on('click', function(e) {
        e.preventDefault();
        let targetId = $(this).attr('href');
        $('.nav-tabs a').removeClass('active');
        $('.tab-pane').removeClass('show active');
        $(this).addClass('active');
        $(targetId).addClass('show active');
        
        if (targetId === '#entry-packages') {
            loadEntryPackages();
        } else if (targetId === '#permanent-entries') {
            loadPermanentEntries();
        } else if (targetId === '#giveaways') {
            loadGiveaways();
        } else if (targetId === '#auto-pool') {
            loadPool();
        }
    });
    
    function loadGiveaways() {
        let search = $('#giveawaySearch').val() || '';
        let status = $('#giveawayStatusFilter').val() || 'all';
        
        $.ajax({
            url: '/giveaway-centre/giveaways/data',
            type: 'GET',
            data: { search: search, status: status },
            success: function(response) {
                if (!response.data || response.data.length === 0) {
                    allActiveGiveaways = [];
                    allCompletedGiveaways = [];
                } else {
                    // Active includes: 1 (active), draft, scheduled
                    allActiveGiveaways = response.data.filter(g => {
                        let status = String(g.status).toLowerCase();
                        return status === "1" || status === "active" || status === "draft" || status === "scheduled";
                    });
                    
                    // Completed includes: 0 (completed), cancelled
                    allCompletedGiveaways = response.data.filter(g => {
                        let status = String(g.status).toLowerCase();
                        return status === "0" || status === "completed" || status === "cancelled";
                    });
                }
                
                console.log('📊 Filtered:', {
                    active: allActiveGiveaways.length,
                    completed: allCompletedGiveaways.length,
                    total: response.data.length
                });
                
                activeCurrentPage = 1;
                completedCurrentPage = 1;
                renderActiveGiveaways();
                renderCompletedGiveaways();
            },
            error: function(xhr) {
                $('#activeGiveawaysTableBody').html('<tr><td colspan="10" class="text-center text-danger py-4">Error: ' + xhr.statusText + '</td></tr>');
            }
        });
    }
    
    function renderActiveGiveaways() {
        let tbody = $('#activeGiveawaysTableBody');
        tbody.empty();
        $('#activeCount').text('(' + allActiveGiveaways.length + ')');
        
        if (allActiveGiveaways.length === 0) {
            tbody.html('<tr><td colspan="10" class="text-center py-5"><i class="fe fe-inbox" style="font-size:48px;color:#ccc;"></i><p class="mt-3 text-muted">No active giveaways</p></td></tr>');
            $('#activePagination').hide();
            return;
        }
        
        let start = (activeCurrentPage - 1) * itemsPerPage;
        let end = Math.min(start + itemsPerPage, allActiveGiveaways.length);
        let pageData = allActiveGiveaways.slice(start, end);
        
        pageData.forEach(g => tbody.append(createActiveRow(g)));
        
        $('#activeTotalGiveaways').text(allActiveGiveaways.length);
        $('#activeShowingFrom').text(start + 1);
        $('#activeShowingTo').text(end);
        $('#activeCurrentPage').text(activeCurrentPage);
        
        let totalPages = Math.ceil(allActiveGiveaways.length / itemsPerPage);
        $('#activePrevPage').prop('disabled', activeCurrentPage === 1);
        $('#activeNextPage').prop('disabled', activeCurrentPage >= totalPages);
        $('#activePagination').show();
    }
    
    function renderCompletedGiveaways() {
        let tbody = $('#completedGiveawaysTableBody');
        tbody.empty();
        $('#completedCount').text('(' + allCompletedGiveaways.length + ')');
        
        if (allCompletedGiveaways.length === 0) {
            tbody.html('<tr><td colspan="11" class="text-center py-5"><i class="fe fe-inbox" style="font-size:48px;color:#ccc;"></i><p class="mt-3 text-muted">No completed giveaways</p></td></tr>');
            $('#completedPagination').hide();
            return;
        }
        
        let start = (completedCurrentPage - 1) * itemsPerPage;
        let end = Math.min(start + itemsPerPage, allCompletedGiveaways.length);
        let pageData = allCompletedGiveaways.slice(start, end);
        
        pageData.forEach(g => tbody.append(createCompletedRow(g)));
        
        $('#completedTotalGiveaways').text(allCompletedGiveaways.length);
        $('#completedShowingFrom').text(start + 1);
        $('#completedShowingTo').text(end);
        $('#completedCurrentPage').text(completedCurrentPage);
        
        let totalPages = Math.ceil(allCompletedGiveaways.length / itemsPerPage);
        $('#completedPrevPage').prop('disabled', completedCurrentPage === 1);
        $('#completedNextPage').prop('disabled', completedCurrentPage >= totalPages);
        $('#completedPagination').show();
    }
    
    function createActiveRow(g) {
        let img = AdminV2.imageSrc(g.image);
        
        // Calculate entries
        let permanentEntries = g.permanent_entries || 0;
        let temporaryEntries = g.temporary_entries || 0;
        let totalEntries = g.total_entries || 0;
        
        return $('<tr>').attr('data-id', g.id).append([
            $('<td>').html('<input type="checkbox" value="' + g.id + '">'),
            $('<td>').html('<i class="fe fe-menu text-muted" style="cursor:move;"></i>'),
            $('<td>').html('<img src="' + img + '" class="img-preview">'),
            $('<td>').html('<strong>' + g.prize_name + '</strong>'),
            $('<td>').html('<span class="text-muted">' + (g.prize_type || '-') + '</span>'),
            $('<td>').html('<strong class="text-success">$' + g.prize_value + '</strong>'),
            $('<td>').html('<small>' + (g.draw_date ? formatDateDDMMYYYY(new Date(g.draw_date)) : '-') + '</small>'),
            // NEW: Permanent entries
            $('<td>').html('<span class="badge badge-primary badge-large">' + permanentEntries + '</span>'),
            // NEW: Temporary entries
            $('<td>').html('<span class="badge badge-info badge-large">' + temporaryEntries + '</span>'),
            // NEW: Total entries
            $('<td>').html('<span class="badge badge-success badge-large">' + totalEntries + '</span><br><small class="text-muted">' + g.total_participants + ' users</small>'),
            // Status badge with dynamic color
            $('<td>').html(getStatusBadge(g.status)),
            // NEW: Action buttons
            createActionButtons(g, 'active')
        ]);
    }
    
    function getStatusBadge(status) {
        let statusLower = String(status).toLowerCase();
        let badgeClass = 'badge-secondary';
        let statusText = status;
        
        if (statusLower === '1' || statusLower === 'active') {
            badgeClass = 'badge-success';
            statusText = 'Active';
        } else if (statusLower === 'draft') {
            badgeClass = 'badge-secondary';
            statusText = 'Draft';
        } else if (statusLower === 'scheduled') {
            badgeClass = 'badge-info';
            statusText = 'Scheduled';
        } else if (statusLower === '0' || statusLower === 'completed') {
            badgeClass = 'badge-primary';
            statusText = 'Completed';
        } else if (statusLower === 'cancelled') {
            badgeClass = 'badge-danger';
            statusText = 'Cancelled';
        }
        
        return '<span class="badge ' + badgeClass + '">' + statusText + '</span>';
    }
    
    function createCompletedRow(g) {
        let img = AdminV2.imageSrc(g.image);
        let winnerHtml = '-';
        
        if (g.winner_id && g.winner_name) {
            winnerHtml = '<a href="/customer-management/' + g.winner_id + '" class="text-primary" title="View customer profile" style="text-decoration:none;font-weight:600;"><i class="fe fe-user"></i> ' + g.winner_name + '</a>';
        } else {
            winnerHtml = '<span class="text-muted"><i class="fe fe-help-circle"></i> No winner</span>';
        }
        
        // Calculate entries
        let permanentEntries = g.permanent_entries || 0;
        let temporaryEntries = g.temporary_entries || 0;
        let totalEntries = g.total_entries || 0;
        
        return $('<tr>').attr('data-id', g.id).css('opacity', '0.65').append([
            $('<td>').html('<input type="checkbox" value="' + g.id + '">'),
            $('<td>').html('<i class="fe fe-menu text-muted"></i>'),
            $('<td>').html('<img src="' + img + '" class="img-preview">'),
            $('<td>').html('<strong>' + g.prize_name + '</strong>'),
            $('<td>').html('<span class="text-muted">' + (g.prize_type || '-') + '</span>'),
            $('<td>').html('<strong class="text-success">$' + g.prize_value + '</strong>'),
            $('<td>').html('<small>' + (g.draw_date ? formatDateDDMMYYYY(new Date(g.draw_date)) : '-') + '</small>'),
            // NEW: Permanent entries
            $('<td>').html('<span class="badge badge-primary badge-large">' + permanentEntries + '</span>'),
            // NEW: Temporary entries
            $('<td>').html('<span class="badge badge-info badge-large">' + temporaryEntries + '</span>'),
            // NEW: Total entries
            $('<td>').html('<span class="badge badge-success badge-large">' + totalEntries + '</span><br><small class="text-muted">' + g.total_participants + ' users</small>'),
            $('<td>').html(winnerHtml),
            $('<td>').html(getStatusBadge(g.status)),
            // NEW: Action buttons (no Draw for completed)
            createActionButtons(g, 'completed')
        ]);
    }
    
    window.changeActivePage = function(dir) {
        let total = Math.ceil(allActiveGiveaways.length / itemsPerPage);
        activeCurrentPage = Math.max(1, Math.min(activeCurrentPage + dir, total));
        renderActiveGiveaways();
    };
    
    window.changeCompletedPage = function(dir) {
        let total = Math.ceil(allCompletedGiveaways.length / itemsPerPage);
        completedCurrentPage = Math.max(1, Math.min(completedCurrentPage + dir, total));
        renderCompletedGiveaways();
    };
    
    $('#giveawaySearch').on('keypress', e => { if (e.which === 13) loadGiveaways(); });
    $('#giveawayStatusFilter').on('change', loadGiveaways);
    
    function loadEntryPackages() {
        let tbody = $('#packagesTableBody');
        if (tbody.length === 0) return;
        
        tbody.html('<tr><td colspan="9" class="text-center py-4"><i class="fe fe-loader"></i> Loading...</td></tr>');
        
        $.ajax({
            url: '/giveaway-centre/entry-packages/data',
            type: 'GET',
            success: function(response) {
                tbody.empty();
                
                if (!response.data || response.data.length === 0) {
                    tbody.html('<tr><td colspan="9" class="text-center py-5"><i class="fe fe-inbox" style="font-size:48px;color:#ccc;"></i><p class="mt-3 text-muted">No packages</p></td></tr>');
                    return;
                }
                
                response.data.forEach(p => {
                    let row = $('<tr>').attr('data-id', p.id);
                    row.append($('<td>').html('<input type="checkbox">'));
                    row.append($('<td>').html('<i class="fe fe-menu"></i>'));
                    row.append($('<td>').html('<strong>' + p.name + '</strong>'));
                    row.append($('<td>').html('<small>' + (p.description || '-') + '</small>'));
                    row.append($('<td>').html('<span class="badge badge-info">' + p.entry_quantity + '</span>'));
                    row.append($('<td>').html('$' + parseFloat(p.price).toFixed(2)));
                    row.append($('<td>').html(p.is_featured ? '⭐' : '-'));
                    row.append($('<td>').html('<span class="badge badge-' + (p.status ? 'success' : 'secondary') + '">' + (p.status ? 'Active' : 'Inactive') + '</span>'));
                    row.append($('<td>').html('<a href="/entry-packages/' + p.id + '/edit" class="btn btn-sm btn-warning"><i class="fe fe-edit"></i></a>'));
                    tbody.append(row);
                });
            },
            error: function(xhr) {
                tbody.html('<tr><td colspan="9" class="text-center text-danger">Error loading</td></tr>');
            }
        });
    }
    
    function loadPermanentEntries() {
        let tbody = $('#permanentEntriesTableBody');
        if (tbody.length === 0) return;
        
        let search = $('#permanentEntriesSearch').val() || '';
        let hasEntries = $('#hasEntriesFilter').val() || 'all';
        
        tbody.html('<tr><td colspan="10" class="text-center py-4"><i class="fe fe-loader"></i> Loading...</td></tr>');
        
        $.ajax({
            url: '/giveaway-centre/permanent-entries/data',
            type: 'GET',
            data: { search: search, has_entries: hasEntries },
            success: function(response) {
                allPermanentUsers = response.data;
                permanentCurrentPage = 1;
                sortUsers();
                renderPermanentEntriesPage();
            },
            error: function(xhr) {
                tbody.html('<tr><td colspan="10" class="text-center text-danger">Error loading users</td></tr>');
            }
        });
    }
    
    function sortUsers() {
        allPermanentUsers.sort((a, b) => {
            let aVal, bVal;
            
            switch(permanentSortColumn) {
                case 'permanent': aVal = a.permanent_entries; bVal = b.permanent_entries; break;
                case 'temporary': aVal = a.temporary_entries; bVal = b.temporary_entries; break;
                case 'total': aVal = a.total_entries; bVal = b.total_entries; break;
                default: return 0;
            }
            
            return permanentSortDirection === 'asc' ? aVal - bVal : bVal - aVal;
        });
    }
    
    window.sortPermanentEntries = function(column) {
        if (permanentSortColumn === column) {
            permanentSortDirection = permanentSortDirection === 'desc' ? 'asc' : 'desc';
        } else {
            permanentSortColumn = column;
            permanentSortDirection = 'desc';
        }
        
        $('#sort-permanent, #sort-temporary, #sort-total').removeClass('fe-arrow-up fe-arrow-down').addClass('fe-arrow-down');
        let icon = permanentSortDirection === 'desc' ? 'fe-arrow-down' : 'fe-arrow-up';
        $('#sort-' + column).removeClass('fe-arrow-down fe-arrow-up').addClass(icon);
        
        sortUsers();
        renderPermanentEntriesPage();
    };
    
    function renderPermanentEntriesPage() {
        let tbody = $('#permanentEntriesTableBody');
        tbody.empty();
        
        if (allPermanentUsers.length === 0) {
            tbody.html('<tr><td colspan="10" class="text-center py-5"><i class="fe fe-inbox" style="font-size:48px;color:#ccc;"></i><p class="mt-3 text-muted">No users found</p></td></tr>');
            $('#permanentEntriesPagination').hide();
            return;
        }
        
        let start = (permanentCurrentPage - 1) * permanentItemsPerPage;
        let end = Math.min(start + permanentItemsPerPage, allPermanentUsers.length);
        let pageData = allPermanentUsers.slice(start, end);
        
        pageData.forEach(user => {
            let row = $('<tr>');
            row.append($('<td>').html('<small class="text-muted">#' + user.user_id + '</small>'));
            row.append($('<td>').html('<strong>' + user.user_name + '</strong>'));
            row.append($('<td>').html('<small>' + user.user_email + '</small>'));
            row.append($('<td>').html('<span class="badge badge-primary badge-large">' + user.permanent_entries + '</span>'));
            row.append($('<td>').html('<span class="badge badge-info badge-large">' + user.temporary_entries + '</span>'));
            row.append($('<td>').html('<span class="badge badge-success badge-large">' + user.total_entries + '</span>'));
            row.append($('<td>').html('<span class="badge badge-success">' + user.total_earned + '</span>'));
            row.append($('<td>').html('<span class="badge badge-warning" style="color:#212529;">' + user.total_used + '</span>'));
            row.append($('<td>').html('<small class="text-muted">' + user.last_updated_at + '</small>'));
            
            let actions = $('<td>');
            let editBtn = $('<button>').addClass('btn btn-sm btn-primary mr-1').attr('title', 'Manage Permanent Entries')
                .html('<i class="fe fe-edit"></i>').on('click', function() { openEditEntriesModal(user); });
            let historyBtn = $('<button>').addClass('btn btn-sm btn-info').attr('title', 'View Entry History')
                .html('<i class="fe fe-clock"></i>').on('click', function() { viewEntryHistory(user.user_id, user.user_name); });
            actions.append(editBtn).append(historyBtn);
            row.append(actions);
            
            tbody.append(row);
        });
        
        $('#permanentTotalUsers').text(allPermanentUsers.length);
        $('#permanentShowingFrom').text(start + 1);
        $('#permanentShowingTo').text(end);
        $('#permanentCurrentPage').text(permanentCurrentPage);
        
        let totalPages = Math.ceil(allPermanentUsers.length / permanentItemsPerPage);
        $('#permanentPrevPage').prop('disabled', permanentCurrentPage === 1);
        $('#permanentNextPage').prop('disabled', permanentCurrentPage >= totalPages);
        $('#permanentEntriesPagination').show();
    }
    
    window.changePermanentPage = function(dir) {
        let totalPages = Math.ceil(allPermanentUsers.length / permanentItemsPerPage);
        permanentCurrentPage = Math.max(1, Math.min(permanentCurrentPage + dir, totalPages));
        renderPermanentEntriesPage();
    };
    
    function openEditEntriesModal(user) {
        $('#editUserId').val(user.user_id);
        $('#editUserName').text(user.user_name);
        $('#editCurrentPermanent').text(user.permanent_entries);
        $('#editCurrentTemporary').text(user.temporary_entries);
        $('#setAmount').val(user.permanent_entries);
        $('#adjustAmount').val('');
        $('#entryReason').val('');
        $('#entryAction').val('set');
        toggleEntryActionInputs();
        $('#editPermanentEntriesModal').modal('show');
    }
    
    window.toggleEntryActionInputs = function() {
        let action = $('#entryAction').val();
        
        if (action === 'set') {
            $('#setAmountGroup').show();
            $('#adjustAmountGroup').hide();
            $('#deductWarning').hide();
        } else {
            $('#setAmountGroup').hide();
            $('#adjustAmountGroup').show();
            
            if (action === 'add') {
                $('#actionLabel').text('Amount to Add:');
                $('#deductWarning').hide();
            } else if (action === 'deduct') {
                $('#actionLabel').text('Amount to Deduct:');
                $('#deductWarning').show();
            }
        }
    };
    
    window.savePermanentEntries = function() {
        let userId = $('#editUserId').val();
        let action = $('#entryAction').val();
        let reason = $('#entryReason').val();
        let amount = action === 'set' ? $('#setAmount').val() : $('#adjustAmount').val();
        
        if (!amount || (action === 'set' && amount < 0) || (action !== 'set' && amount < 1)) {
            toastr.error('Please enter a valid amount');
            return;
        }
        
        $.ajax({
            url: '/giveaway-centre/permanent-entries/user/' + userId + '/update',
            type: 'POST',
            data: { action: action, amount: amount, reason: reason },
            success: function(response) {
                $('#editPermanentEntriesModal').modal('hide');
                toastr.success(response.message);
                loadPermanentEntries();
            },
            error: function(xhr) {
                let errorMsg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Failed to update entries';
                toastr.error(errorMsg);
            }
        });
    };
    
    function viewEntryHistory(userId, userName) {
        $('#historyUserName').text(userName);
        $('#historyEntriesTableBody').html('<tr><td colspan="6" class="text-center">Loading...</td></tr>');
        $('#viewEntryHistoryModal').modal('show');
        
        $.ajax({
            url: '/giveaway-centre/permanent-entries/user/' + userId + '/history',
            type: 'GET',
            success: function(response) {
                let tbody = $('#historyEntriesTableBody');
                tbody.empty();
                
                if (!response.entries || response.entries.length === 0) {
                    tbody.html('<tr><td colspan="6" class="text-center text-muted">No entry history found</td></tr>');
                    return;
                }
                
                response.entries.forEach(entry => {
                    let row = $('<tr>');
                    row.append($('<td>').text(entry.giveaway_name));
                    
                    let badgeClass = 'badge-info';
                    if (entry.type.includes('Added')) badgeClass = 'badge-success';
                    else if (entry.type.includes('Removed')) badgeClass = 'badge-danger';
                    row.append($('<td>').html('<span class="badge ' + badgeClass + '">' + entry.entry_count + '</span>'));
                    
                    row.append($('<td>').html('<small>' + entry.type + '</small>'));
                    row.append($('<td>').html('<small>' + (entry.admin || '-') + '</small>'));
                    row.append($('<td>').html('<small class="text-muted">' + (entry.reason || '-') + '</small>'));
                    row.append($('<td>').html('<small>' + entry.created_at + '</small>'));
                    
                    tbody.append(row);
                });
            },
            error: function(xhr) {
                $('#historyEntriesTableBody').html('<tr><td colspan="6" class="text-center text-danger">Error loading history</td></tr>');
            }
        });
    }
    
    window.exportPermanentEntries = function() {
        toastr.info('CSV export functionality coming soon!');
    };
    
    // === GIVEAWAY ACTION BUTTONS ===
    function createActionButtons(g, type) {
        let actions = $('<td>').css({
            'white-space': 'nowrap',
            'min-width': '200px'
        });
        
        // Edit button
        let editBtn = $('<a>')
            .attr('href', '/giveaway-centre/' + g.id + '/edit')
            .addClass('btn btn-sm btn-warning')
            .attr('title', 'Edit')
            .css('margin-right', '5px')
            .html('<i class="fe fe-edit"></i>');
        actions.append(editBtn);
        
        // Duplicate button
        let duplicateBtn = $('<button>')
            .addClass('btn btn-sm btn-info')
            .attr('title', 'Duplicate')
            .css('margin-right', '5px')
            .html('<i class="fe fe-copy"></i>')
            .on('click', function() {
                showDuplicateModal(g);
            });
        actions.append(duplicateBtn);
        
        // Draw button - only for active without winner
        if (type === 'active' && !g.winner_id && (String(g.status) === '1' || String(g.status) === 'active')) {
            let drawBtn = $('<button>')
                .addClass('btn btn-sm btn-primary')
                .attr('title', 'Draw Winner')
                .css('margin-right', '5px')
                .html('<i class="fe fe-award"></i>')
                .on('click', function() {
                    drawWinner(g.id, g.prize_name);
                });
            actions.append(drawBtn);
        }
        
        // Delete button
        let deleteBtn = $('<button>')
            .addClass('btn btn-sm btn-danger')
            .attr('title', 'Delete')
            .html('<i class="fe fe-trash-2"></i>')
            .on('click', function() {
                deleteGiveaway(g.id, g.prize_name);
            });
        actions.append(deleteBtn);
        
        return actions;
    }
    
    // === DUPLICATE WITH MANUAL DATE/TIME CONTROL ===
    function showDuplicateModal(giveaway) {
        // Parse original dates
        let originalDrawDate = new Date(giveaway.draw_date);
        let originalDrawTime = giveaway.draw_time || '17:00';
        
        // Prefill suggestions
        let suggestedStartDate = new Date(originalDrawDate); // Start = when previous draw ends
        let suggestedDrawDate = new Date(originalDrawDate);
        suggestedDrawDate.setDate(suggestedDrawDate.getDate() + 7); // Draw = 7 days later
        
        // Format dates as YYYY-MM-DD for input fields (browser requirement)
        let suggestedStartInput = suggestedStartDate.toISOString().split('T')[0];
        let suggestedDrawInput = suggestedDrawDate.toISOString().split('T')[0];
        
        // Format dates as DD/MM/YYYY for display
        let originalDrawDisplay = formatDateDDMMYYYY(originalDrawDate);
        
        let modalHtml = `
            <div class="modal fade show" id="duplicateModal" style="display: block; background: rgba(0,0,0,0.5);">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header bg-info text-white">
                            <h5 class="modal-title">
                                <i class="fe fe-copy"></i> Duplicate Giveaway
                            </h5>
                            <button type="button" class="close text-white" onclick="closeDuplicateModal()">
                                <span>&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            <p><strong>Duplicating:</strong> ${giveaway.prize_name}</p>
                            <p class="text-muted small">Original draw: ${originalDrawDisplay} at ${originalDrawTime}</p>
                            <hr>
                            
                            <div class="form-group">
                                <label>New Start Date <span class="text-danger">*</span></label>
                                <input type="date" 
                                       id="duplicateStartDate" 
                                       class="form-control" 
                                       value="${suggestedStartInput}"
                                       required>
                                <small class="form-text text-muted">
                                    When the new giveaway becomes active
                                </small>
                            </div>
                            
                            <div class="form-group">
                                <label>New Draw Date <span class="text-danger">*</span></label>
                                <input type="date" 
                                       id="duplicateDrawDate" 
                                       class="form-control" 
                                       value="${suggestedDrawInput}"
                                       required>
                                <small class="form-text text-muted">
                                    When the winner will be drawn
                                </small>
                            </div>
                            
                            <div class="form-group">
                                <label>Draw Time <span class="text-danger">*</span></label>
                                <input type="time" 
                                       id="duplicateDrawTime" 
                                       class="form-control" 
                                       value="${originalDrawTime}"
                                       required>
                                <small class="form-text text-muted">
                                    Exact time for winner draw
                                </small>
                            </div>
                            
                            <div class="alert alert-info">
                                <strong><i class="fe fe-info"></i> Preview:</strong><br>
                                <span id="duplicatePreview">Loading...</span>
                            </div>
                            
                            <p class="text-muted small">
                                <i class="fe fe-lightbulb"></i> <strong>Tip:</strong> Dates are prefilled as suggestions. Adjust as needed.
                            </p>
                        </div>
                        <div class="modal-footer">
                            <button class="btn btn-secondary" onclick="closeDuplicateModal()">
                                <i class="fe fe-x"></i> Cancel
                            </button>
                            <button class="btn btn-info" onclick="confirmDuplicate(${giveaway.id}, '${giveaway.prize_name}')">
                                <i class="fe fe-copy"></i> Duplicate
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        $('body').append(modalHtml);
        
        // Update preview when any field changes
        function updatePreview() {
            let startDate = $('#duplicateStartDate').val();
            let drawDate = $('#duplicateDrawDate').val();
            let drawTime = $('#duplicateDrawTime').val();
            
            if (startDate && drawDate && drawTime) {
                let startDisplay = formatDateDDMMYYYY(new Date(startDate));
                let drawDisplay = formatDateDDMMYYYY(new Date(drawDate));
                
                let preview = `
                    Giveaway starts: <strong>${startDisplay}</strong><br>
                    Winner drawn: <strong>${drawDisplay} at ${drawTime}</strong>
                `;
                $('#duplicatePreview').html(preview);
            }
        }
        
        // Initialize preview
        updatePreview();
        
        // Update preview on change
        $('#duplicateStartDate, #duplicateDrawDate, #duplicateDrawTime').on('change input', updatePreview);
    }
    
    function formatDateDDMMYYYY(date) {
        let d = new Date(date);
        let day = String(d.getDate()).padStart(2, '0');
        let month = String(d.getMonth() + 1).padStart(2, '0');
        let year = d.getFullYear();
        return `${day}/${month}/${year}`;
    }
    
    window.closeDuplicateModal = function() {
        $('#duplicateModal').fadeOut(300, function() {
            $(this).remove();
        });
    };
    
    window.confirmDuplicate = function(giveawayId, prizeName) {
        let startDate = $('#duplicateStartDate').val();
        let drawDate = $('#duplicateDrawDate').val();
        let drawTime = $('#duplicateDrawTime').val();
        
        if (!startDate || !drawDate || !drawTime) {
            toastr.error('Please fill in all date and time fields');
            return;
        }
        
        // Validate draw date is after start date
        if (new Date(drawDate) < new Date(startDate)) {
            toastr.error('Draw date must be on or after start date');
            return;
        }
        
        closeDuplicateModal();
        toastr.info('Duplicating giveaway...', '', {timeOut: 0, extendedTimeOut: 0, closeButton: false});
        
        $.ajax({
            url: '/giveaway-centre/' + giveawayId + '/duplicate',
            type: 'POST',
            data: { 
                start_date: startDate,
                draw_date: drawDate,
                draw_time: drawTime
            },
            success: function(response) {
                toastr.clear();
                let drawDisplay = formatDateDDMMYYYY(new Date(response.new_dates.draw_date));
                toastr.success('Giveaway duplicated! New draw: ' + drawDisplay + ' at ' + drawTime, '', {timeOut: 5000});
                loadGiveaways();
            },
            error: function(xhr) {
                toastr.clear();
                let errorMsg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Failed to duplicate giveaway';
                toastr.error(errorMsg, '', {timeOut: 5000});
            }
        });
    };
    
    window.drawWinner = function(giveawayId, prizeName) {
        if (!confirm('Draw winner for "' + prizeName + '"?\n\nThis will execute the draw and show the live animation!')) {
            return;
        }
        
        // Show loading
        toastr.info('Preparing draw...', '', {timeOut: 0, extendedTimeOut: 0, closeButton: false});
        
        $.ajax({
            url: '/giveaway-centre/' + giveawayId + '/draw',
            type: 'POST',
            success: function(response) {
                // Clear loading toast
                toastr.clear();
                
                if (response.success) {
                    // Open draw-live page in a new window/tab
                    const drawUrl = '/giveaway-centre/' + giveawayId + '/draw-live';
                    window.open(drawUrl, '_blank', 'width=1200,height=800');
                    
                    // Show success message
                    toastr.success('Winner drawn! Opening animation...', '', {timeOut: 3000});
                    
                    // Reload list after 2 seconds to show updated status
                    setTimeout(function() {
                        loadGiveaways();
                    }, 2000);
                }
            },
            error: function(xhr) {
                toastr.clear();
                let errorMsg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Failed to draw winner';
                toastr.error(errorMsg, '', {timeOut: 5000});
            }
        });
    };
    
    function showWinnerModal(winnerName, prizeName, giveawayId) {
        // Create modal HTML
        let modalHtml = `
            <div class="modal fade show" id="winnerModal" style="display: block; background: rgba(0,0,0,0.8);">
                <div class="modal-dialog modal-dialog-centered modal-lg">
                    <div class="modal-content" style="border: 5px solid #28a745; border-radius: 15px;">
                        <div class="modal-body text-center py-5">
                            <div style="font-size: 80px; color: #ffc107; margin-bottom: 20px;">
                                🎉 🏆 🎊
                            </div>
                            <h2 style="color: #28a745; font-size: 36px; font-weight: bold; margin-bottom: 20px;">
                                WINNER DRAWN!
                            </h2>
                            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 10px; margin: 20px 0;">
                                <h3 style="font-size: 28px; margin-bottom: 10px; color: white;">
                                    ${winnerName}
                                </h3>
                                <p style="font-size: 18px; margin: 0; opacity: 0.9; color: white;">
                                    ${prizeName}
                                </p>
                            </div>
                            <div style="font-size: 16px; color: #6c757d; margin-top: 20px;">
                                The winner has been notified and the giveaway is now completed.
                            </div>
                            <button class="btn btn-success btn-lg mt-4" onclick="closeWinnerModal()" style="font-size: 20px; padding: 15px 50px;">
                                <i class="fe fe-check"></i> Awesome!
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Append to body
        $('body').append(modalHtml);
        
        // Add confetti effect
        confetti();
    }
    
    window.closeWinnerModal = function() {
        $('#winnerModal').fadeOut(300, function() {
            $(this).remove();
            // Reload giveaways AFTER modal closes so user sees the update
            loadGiveaways();
            // Also reload stats
            location.reload();
        });
    };
    
    function confetti() {
        // Simple confetti animation
        for (let i = 0; i < 50; i++) {
            let confettiPiece = $('<div>')
                .css({
                    position: 'fixed',
                    width: '10px',
                    height: '10px',
                    background: ['#ff0000', '#00ff00', '#0000ff', '#ffff00', '#ff00ff'][Math.floor(Math.random() * 5)],
                    top: '-10px',
                    left: Math.random() * window.innerWidth + 'px',
                    opacity: 1,
                    zIndex: 99999
                });
            
            $('body').append(confettiPiece);
            
            confettiPiece.animate({
                top: window.innerHeight + 'px',
                left: '+=' + (Math.random() * 200 - 100) + 'px',
                opacity: 0
            }, 3000 + Math.random() * 2000, function() {
                $(this).remove();
            });
        }
    }
    
    window.deleteGiveaway = function(giveawayId, prizeName) {
        if (!confirm('Delete "' + prizeName + '"?\n\nThis action cannot be undone!')) {
            return;
        }
        
        $.ajax({
            url: '/giveaway-centre/' + giveawayId,
            type: 'DELETE',
            success: function(response) {
                toastr.success('Giveaway deleted successfully!');
                loadGiveaways();
            },
            error: function(xhr) {
                let errorMsg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Failed to delete giveaway';
                toastr.error(errorMsg);
            }
        });
    };
    
    $(document).on('keypress', '#permanentEntriesSearch', function(e) {
        if (e.which === 13) loadPermanentEntries();
    });
    
    $(document).on('change', '#hasEntriesFilter', function() {
        loadPermanentEntries();
    });
    
    window.loadGiveaways = loadGiveaways;
    window.loadEntryPackages = loadEntryPackages;
    window.loadPermanentEntries = loadPermanentEntries;
    window.createActionButtons = createActionButtons;
});
</script>
@endpush