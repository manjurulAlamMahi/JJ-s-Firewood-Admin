<div class="filter-section">
    <div class="row align-items-center">
        <div class="col-md-6">
            <div class="input-group">
                <span class="input-group-text"><i class="fe fe-search"></i></span>
                <input type="text" class="form-control" id="categorySearch" placeholder="Search categories...">
            </div>
        </div>
        <div class="col-md-6 text-right">
            <button class="btn btn-primary" onclick="window.location='{{ route('category.create') }}'">
                <i class="fe fe-plus mr-2"></i> Add Category
            </button>
        </div>
    </div>
</div>

<!-- Bulk Actions Bar -->
<div class="bulk-actions-bar" id="categoryBulkActions">
    <div>
        <strong id="categorySelectedCount">0</strong> categories selected
    </div>
    <div>
        <button class="btn btn-sm btn-danger" onclick="bulkDeleteCategories()">
            <i class="fe fe-trash-2 mr-1"></i> Delete Selected
        </button>
        <button class="btn btn-sm btn-secondary" onclick="clearCategorySelection()">
            <i class="fe fe-x mr-1"></i> Clear
        </button>
    </div>
</div>

<!-- Categories Table -->
<div class="modern-table">
    <table class="table table-hover mb-0" id="categoriesTable">
        <thead>
            <tr>
                <th width="40px">
                    <input type="checkbox" id="categorySelectAll">
                </th>
                <th width="40px" class="d-none d-md-table-cell"></th>
                <th width="80px">Image</th>
                <th>Name</th>
                <th>App Name</th>
                <th>SubCategories</th>
                <th>Products</th>
                <th width="80px">Status</th>
                <th width="150px">Actions</th>
            </tr>
        </thead>
        <tbody id="categoriesTableBody">
            <!-- Loaded via AJAX -->
        </tbody>
    </table>
</div>