<!-- Permanent Entries Tab Content -->
<div class="filter-section">
    <div class="row align-items-end">
        <div class="col-md-4">
            <label>Search Users</label>
            <input type="text" class="form-control" id="permanentEntriesSearch" placeholder="Search by name or email...">
        </div>
        <div class="col-md-3">
            <label>Filter</label>
            <select class="form-control" id="hasEntriesFilter">
                <option value="all">All Users</option>
                <option value="1">Only Users with Entries</option>
            </select>
        </div>
        <div class="col-md-3">
            <button class="btn btn-primary" onclick="loadPermanentEntries()">
                <i class="fe fe-search"></i> Search
            </button>
        </div>
        <div class="col-md-2 text-right">
            <button class="btn btn-success" onclick="exportPermanentEntries()">
                <i class="fe fe-download"></i> Export CSV
            </button>
        </div>
    </div>
</div>

<div class="alert alert-info">
    <i class="fe fe-info"></i>
    <strong>About Entry Types:</strong>
    <ul class="mb-0 mt-2" style="list-style: none; padding-left: 0;">
        <li><strong>Permanent Entries (Blue):</strong> Never expire. Can be used in ANY active giveaway. Given by admins as rewards/bonuses.</li>
        <li><strong>Temporary Entries (Cyan):</strong> Purchased for specific giveaways. Expire when that giveaway ends (used or not).</li>
        <li><strong>Total:</strong> Sum of permanent + temporary entries currently available.</li>
        <li><strong>Earned:</strong> Lifetime total of permanent entries given to this user.</li>
        <li><strong>Used:</strong> How many times user has entered giveaways (tracking coming soon).</li>
    </ul>
</div>

<!-- Permanent Entries Table -->
<div class="modern-table">
    <table class="table table-hover mb-0">
        <thead>
            <tr>
                <th>USER ID</th>
                <th>USER NAME</th>
                <th>EMAIL</th>
                <th style="cursor: pointer;" onclick="sortPermanentEntries('permanent')" title="Click to sort">
                    PERMANENT <i class="fe fe-arrow-down" id="sort-permanent"></i>
                </th>
                <th style="cursor: pointer;" onclick="sortPermanentEntries('temporary')" title="Click to sort">
                    TEMPORARY <i class="fe fe-arrow-down" id="sort-temporary"></i>
                </th>
                <th style="cursor: pointer;" onclick="sortPermanentEntries('total')" title="Click to sort">
                    TOTAL <i class="fe fe-arrow-down" id="sort-total"></i>
                </th>
                <th>EARNED</th>
                <th>USED</th>
                <th>LAST UPDATED</th>
                <th>ACTIONS</th>
            </tr>
        </thead>
        <tbody id="permanentEntriesTableBody">
            <tr>
                <td colspan="10" class="text-center text-muted py-4">
                    <i class="fe fe-loader" style="font-size: 48px;"></i>
                    <p class="mt-2">Loading users...</p>
                </td>
            </tr>
        </tbody>
    </table>
</div>

<!-- Pagination -->
<div class="d-flex justify-content-between align-items-center mt-3" id="permanentEntriesPagination" style="display: none !important;">
    <div>
        Showing <strong id="permanentShowingFrom">1</strong> to <strong id="permanentShowingTo">30</strong> 
        of <strong id="permanentTotalUsers">0</strong> users
    </div>
    <div>
        <button class="btn btn-sm btn-secondary" id="permanentPrevPage" onclick="changePermanentPage(-1)" disabled>
            <i class="fe fe-chevron-left"></i> Previous
        </button>
        <span class="mx-3">Page <strong id="permanentCurrentPage">1</strong></span>
        <button class="btn btn-sm btn-secondary" id="permanentNextPage" onclick="changePermanentPage(1)">
            Next <i class="fe fe-chevron-right"></i>
        </button>
    </div>
</div>

<!-- Edit Permanent Entries Modal -->
<div class="modal fade" id="editPermanentEntriesModal" tabindex="-1" role="dialog" aria-labelledby="editPermanentEntriesModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editPermanentEntriesModalLabel">Manage Permanent Entries</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" onclick="$('#editPermanentEntriesModal').modal('hide');">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editUserId">
                
                <div class="alert alert-info">
                    <strong>User:</strong> <span id="editUserName"></span><br>
                    <strong>Current Permanent Entries:</strong> 
                    <span id="editCurrentPermanent" class="badge badge-primary" style="font-size: 14px; background-color: #007bff !important;">0</span><br>
                    <strong>Current Temporary Entries:</strong> 
                    <span id="editCurrentTemporary" class="badge badge-info" style="font-size: 14px; background-color: #17a2b8 !important;">0</span>
                </div>

                <div class="form-group">
                    <label>Action</label>
                    <select class="form-control" id="entryAction" onchange="toggleEntryActionInputs()">
                        <option value="set">Set to Specific Amount</option>
                        <option value="add">Add Entries</option>
                        <option value="deduct">Deduct Entries</option>
                    </select>
                </div>

                <div class="form-group" id="setAmountGroup">
                    <label>Set Permanent Entries To:</label>
                    <input type="number" class="form-control" id="setAmount" min="0" value="0">
                    <small class="form-text text-muted">This will replace their current permanent entry count.</small>
                </div>

                <div class="form-group" id="adjustAmountGroup" style="display:none;">
                    <label id="actionLabel">Amount to Add:</label>
                    <input type="number" class="form-control" id="adjustAmount" min="1" value="1">
                </div>

                <div class="form-group">
                    <label>Reason (Optional)</label>
                    <textarea class="form-control" id="entryReason" rows="2" placeholder="e.g., Loyalty reward, Purchase bonus, Admin adjustment..."></textarea>
                    <small class="form-text text-muted">This reason will be logged for tracking purposes.</small>
                </div>

                <div class="alert alert-warning" id="deductWarning" style="display:none;">
                    <i class="fe fe-alert-triangle"></i> <strong>Warning:</strong> This will deduct entries from the user's permanent balance. Make sure they have enough entries.
                </div>

                <div class="alert alert-success">
                    <small><strong>How it works:</strong>
                    <ul class="mb-0">
                        <li><strong>Set:</strong> Directly sets permanent entries to specified amount</li>
                        <li><strong>Add:</strong> Adds to permanent entries (shows in app immediately)</li>
                        <li><strong>Deduct:</strong> Removes from permanent entries</li>
                    </ul>
                    <em class="mt-2 d-block">Note: Only PERMANENT entries are managed here. Temporary entries are tied to specific giveaways and expire when those giveaways end.</em>
                    </small>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="savePermanentEntries()">
                    <i class="fe fe-save"></i> Save Changes
                </button>
            </div>
        </div>
    </div>
</div>

<!-- View Entry History Modal -->
<div class="modal fade" id="viewEntryHistoryModal" tabindex="-1" role="dialog" aria-labelledby="viewEntryHistoryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="viewEntryHistoryModalLabel">Entry History</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close" onclick="$('#viewEntryHistoryModal').modal('hide');">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info">
                    <strong>User:</strong> <span id="historyUserName"></span>
                </div>

                <h6>All Entries & Admin Actions</h6>
                <p class="text-muted"><small>Shows entry records and admin modifications (adds/removes).</small></p>
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Giveaway / Type</th>
                                <th>Entries</th>
                                <th>Type</th>
                                <th>Admin</th>
                                <th>Reason</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody id="historyEntriesTableBody">
                            <tr>
                                <td colspan="6" class="text-center">Loading...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    <p class="text-muted"><small><i class="fe fe-info"></i> <strong>Note:</strong> Temporary entries for completed giveaways are automatically removed when the giveaway ends.</small></p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeHistoryModal()">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
function closeEditModal() {
    $('#editPermanentEntriesModal').modal('hide');
}

function closeHistoryModal() {
    $('#viewEntryHistoryModal').modal('hide');
}

$('#editPermanentEntriesModal, #viewEntryHistoryModal').on('hidden.bs.modal', function () {
    $('.modal-backdrop').remove();
    $('body').removeClass('modal-open').css('padding-right', '');
});
</script>

<style>
    #permanentEntriesTableBody td {
        vertical-align: middle !important;
    }
    
    .badge-large {
        font-size: 14px;
        padding: 6px 10px;
    }
    
    thead th[onclick] {
        user-select: none;
    }
    
    thead th[onclick]:hover {
        background-color: #e9ecef !important;
    }
    
    .badge-primary {
        background-color: #007bff !important;
        color: #fff !important;
    }
    
    .badge-info {
        background-color: #17a2b8 !important;
        color: #fff !important;
    }
    
    .badge-success {
        background-color: #28a745 !important;
        color: #fff !important;
    }
    
    .badge-warning {
        background-color: #ffc107 !important;
        color: #212529 !important;
    }
    
    .badge-danger {
        background-color: #dc3545 !important;
        color: #fff !important;
    }
</style>