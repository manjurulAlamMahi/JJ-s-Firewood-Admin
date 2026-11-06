<!-- Giveaways Tab Content -->
<div class="filter-section">
    <div class="row align-items-end">
        <div class="col-md-3">
            <label>Search Giveaways</label>
            <input type="text" id="giveawaySearch" class="form-control" placeholder="Search by name, type...">
        </div>
        <div class="col-md-2">
            <label>Status Filter</label>
            <select id="giveawayStatusFilter" class="form-control">
                <option value="all">All Statuses</option>
                <option value="active">Active</option>
                <option value="scheduled">Scheduled</option>
                <option value="draft">Draft</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
            </select>
        </div>
        <div class="col-md-2">
            <button class="btn btn-primary btn-block" onclick="loadGiveaways()">
                <i class="fe fe-search"></i> Search
            </button>
        </div>
    </div>
</div>

<!-- RUNNING GIVEAWAYS SECTION -->
<div class="mb-4">
    <h5 class="mb-3" style="color: #212529;">
        <i class="fe fe-zap text-success"></i> Running Giveaways
        <small class="text-muted" id="activeCount">(0)</small>
    </h5>
    <div class="modern-table">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th style="width: 40px;"><input type="checkbox"></th>
                        <th style="width: 40px;">Order</th>
                        <th style="width: 80px;">Image</th>
                        <th>Prize Name</th>
                        <th style="width: 100px;">Type</th>
                        <th style="width: 100px;">Value</th>
                        <th style="width: 130px;">Draw Date</th>
                        <th style="width: 90px;">Perm</th>
                        <th style="width: 90px;">Temp</th>
                        <th style="width: 90px;">Total</th>
                        <th style="width: 110px;">Status</th>
                        <th style="width: 150px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="activeGiveawaysTableBody">
                    <tr><td colspan="12" class="text-center py-4">Loading...</td></tr>
                </tbody>
            </table>
        </div>
        <div class="p-3 border-top" id="activePagination" style="display:none;">
            <div class="d-flex justify-content-between align-items-center">
                <span class="text-muted">Showing <strong id="activeShowingFrom">0</strong> to <strong id="activeShowingTo">0</strong> of <strong id="activeTotalGiveaways">0</strong></span>
                <div>
                    <button class="btn btn-sm btn-outline-primary" id="activePrevPage" onclick="changeActivePage(-1)">
                        <i class="fe fe-chevron-left"></i> Prev
                    </button>
                    <span class="mx-2">Page <strong id="activeCurrentPage">1</strong></span>
                    <button class="btn btn-sm btn-outline-primary" id="activeNextPage" onclick="changeActivePage(1)">
                        Next <i class="fe fe-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- COMPLETED/CANCELLED SECTION -->
<div>
    <h5 class="mb-3" style="color: #212529;">
        <i class="fe fe-check-circle text-primary"></i> Completed / Cancelled Giveaways
        <small class="text-muted" id="completedCount">(0)</small>
    </h5>
    <div class="modern-table">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th style="width: 40px;"><input type="checkbox"></th>
                        <th style="width: 40px;">Order</th>
                        <th style="width: 80px;">Image</th>
                        <th>Prize Name</th>
                        <th style="width: 100px;">Type</th>
                        <th style="width: 100px;">Value</th>
                        <th style="width: 130px;">Draw Date</th>
                        <th style="width: 90px;">Perm</th>
                        <th style="width: 90px;">Temp</th>
                        <th style="width: 90px;">Total</th>
                        <th style="width: 150px;">Winner</th>
                        <th style="width: 110px;">Status</th>
                        <th style="width: 150px;">Actions</th>
                    </tr>
                </thead>
                <tbody id="completedGiveawaysTableBody">
                    <tr><td colspan="13" class="text-center py-4">Loading...</td></tr>
                </tbody>
            </table>
        </div>
        <div class="p-3 border-top" id="completedPagination" style="display:none;">
            <div class="d-flex justify-content-between align-items-center">
                <span class="text-muted">Showing <strong id="completedShowingFrom">0</strong> to <strong id="completedShowingTo">0</strong> of <strong id="completedTotalGiveaways">0</strong></span>
                <div>
                    <button class="btn btn-sm btn-outline-primary" id="completedPrevPage" onclick="changeCompletedPage(-1)">
                        <i class="fe fe-chevron-left"></i> Prev
                    </button>
                    <span class="mx-2">Page <strong id="completedCurrentPage">1</strong></span>
                    <button class="btn btn-sm btn-outline-primary" id="completedNextPage" onclick="changeCompletedPage(1)">
                        Next <i class="fe fe-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>