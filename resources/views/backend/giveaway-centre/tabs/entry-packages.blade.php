<!-- Entry Packages Tab Content -->
<div class="filter-section">
    <div class="row align-items-end">
        <div class="col-md-8">
            <p class="mb-0" style="color: #6c757d !important;">
                <i class="fe fe-info"></i> 
                Manage pre-saved entry packages that users can purchase for temporary entries in giveaways.
            </p>
        </div>
        <div class="col-md-4 text-right">
            <a href="{{ route('entry-package.create') }}" class="btn btn-success">
                <i class="fe fe-plus"></i> Add New Package
            </a>
        </div>
    </div>
</div>

<!-- Bulk Actions Bar -->
<div class="bulk-actions-bar" id="packageBulkActions">
    <div>
        <strong><span id="packageSelectedCount">0</span> selected</strong>
    </div>
    <div>
        <button class="btn btn-danger" onclick="bulkDeletePackages()">
            <i class="fe fe-trash-2"></i> Delete Selected
        </button>
    </div>
</div>

<!-- Entry Packages Table -->
<div class="modern-table">
    <table class="table table-hover mb-0">
        <thead>
            <tr>
                <th style="width: 40px;">
                    <input type="checkbox" id="packageSelectAll">
                </th>
                <th style="width: 40px;">Order</th>
                <th>Package Name</th>
                <th>Description</th>
                <th>Entries</th>
                <th>Price</th>
                <th>Featured</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="packagesTableBody">
            <tr>
                <td colspan="9" class="text-center text-muted py-4">
                    <i class="fe fe-loader" style="font-size: 48px;"></i>
                    <p class="mt-2">Loading entry packages...</p>
                </td>
            </tr>
        </tbody>
    </table>
</div>

<div class="mt-3">
    <div class="alert alert-info">
        <i class="fe fe-info"></i>
        <strong>How it works:</strong> Create reusable entry packages here. When creating/editing a giveaway, you can select which packages to enable and optionally override their prices for that specific giveaway.
    </div>
</div>