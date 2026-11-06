<div class="filter-section">
    <div class="row align-items-center">
        <div class="col-md-4">
            <div class="input-group">
                <span class="input-group-text"><i class="fe fe-search"></i></span>
                <input type="text" class="form-control" id="subcategorySearch" placeholder="Search subcategories...">
            </div>
        </div>
        <div class="col-md-4">
            <select class="form-control" id="subcategoryFilterCategory">
                <option value="">All Categories</option>
            </select>
        </div>
        <div class="col-md-4 text-right">
            <button class="btn btn-primary" onclick="window.location='{{ route('subcategory.create') }}'">
                <i class="fe fe-plus mr-2"></i> Add SubCategory
            </button>
        </div>
    </div>
</div>

<!-- Bulk Actions Bar -->
<div class="bulk-actions-bar" id="subcategoryBulkActions">
    <div>
        <strong id="subcategorySelectedCount">0</strong> subcategories selected
    </div>
    <div>
        <button class="btn btn-sm btn-danger" onclick="bulkDeleteSubCategories()">
            <i class="fe fe-trash-2 mr-1"></i> Delete Selected
        </button>
        <button class="btn btn-sm btn-secondary" onclick="clearSubCategorySelection()">
            <i class="fe fe-x mr-1"></i> Clear
        </button>
    </div>
</div>

<!-- SubCategories Table -->
<div class="modern-table">
    <table class="table table-hover mb-0" id="subcategoriesTable">
        <thead>
            <tr>
                <th width="40px">
                    <input type="checkbox" id="subcategorySelectAll">
                </th>
                <th width="40px" class="d-none d-md-table-cell"></th>
                <th width="80px">Image</th>
                <th>Name</th>
                <th>Category</th>
                <th>Products</th>
                <th width="80px">Status</th>
                <th width="150px">Actions</th>
            </tr>
        </thead>
        <tbody id="subcategoriesTableBody">
            <!-- Loaded via AJAX -->
        </tbody>
    </table>
</div>