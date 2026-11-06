<div class="filter-section">
    <div class="row">
        <div class="col-md-3">
            <div class="input-group">
                <span class="input-group-text"><i class="fe fe-search"></i></span>
                <input type="text" class="form-control" id="productSearch" placeholder="Search products...">
            </div>
        </div>
        <div class="col-md-3">
            <select class="form-control" id="productFilterCategory">
                <option value="">All Categories</option>
            </select>
        </div>
        <div class="col-md-3">
            <select class="form-control" id="productFilterSubCategory">
                <option value="">All SubCategories</option>
            </select>
        </div>
        <div class="col-md-3 text-right">
            <button class="btn btn-primary" onclick="window.location='{{ route('products.create') }}'">
                <i class="fe fe-plus mr-2"></i> Add Product
            </button>
        </div>
    </div>
</div>

<!-- Bulk Actions Bar -->
<div class="bulk-actions-bar" id="productBulkActions">
    <div>
        <strong id="productSelectedCount">0</strong> products selected
    </div>
    <div>
        <button class="btn btn-sm btn-danger" onclick="bulkDeleteProducts()">
            <i class="fe fe-trash-2 mr-1"></i> Delete Selected
        </button>
        <button class="btn btn-sm btn-secondary" onclick="clearProductSelection()">
            <i class="fe fe-x mr-1"></i> Clear
        </button>
    </div>
</div>

<!-- Products Table -->
<div class="modern-table">
    <table class="table table-hover mb-0" id="productsTable">
        <thead>
            <tr>
                <th width="40px">
                    <input type="checkbox" id="productSelectAll">
                </th>
                <th width="40px" class="d-none d-md-table-cell"></th>
                <th width="80px">Image</th>
                <th>Name</th>
                <th>Category</th>
                <th>SubCategory</th>
                <th>Price</th>
                <th>Stock</th>
                <th width="80px">Status</th>
                <th width="150px">Actions</th>
            </tr>
        </thead>
        <tbody id="productsTableBody">
            <!-- Loaded via AJAX -->
        </tbody>
    </table>
</div>