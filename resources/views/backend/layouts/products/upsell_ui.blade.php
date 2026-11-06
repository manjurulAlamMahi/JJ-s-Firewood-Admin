<div class="col-md-12">
    <div class="card border mb-4">
        <div class="card-header bg-light">
            <h4 class="card-title mb-0">Upsells (Extras & Upgrades)</h4>
        </div>
        <div class="card-body">
            <!-- Filters -->
            <div class="row mb-3">
                <div class="col-md-4 mb-2">
                    <label class="form-label">Category Filter</label>
                    <select id="upsell_category_id" class="form-control">
                        <option value="">All Categories</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-md-4 mb-2">
                    <label class="form-label">SubCategory Filter</label>
                    <select id="upsell_subcategory_id" class="form-control">
                        <option value="">All SubCategories</option>
                    </select>
                </div>
                <div class="col-md-4 mb-2">
                    <label class="form-label">Search Product</label>
                    <input type="text" id="upsell_search" class="form-control" placeholder="Type to search...">
                </div>
            </div>

            <div class="row">
                <!-- Available Products -->
                <div class="col-md-4 mb-3">
                    <label class="form-label d-flex justify-content-between align-items-center">
                        <span>Available Products</span>
                        <span class="badge bg-secondary" id="upsell_available_count">0</span>
                    </label>
                    <p class="text-muted small mb-2">Click a product below to add it as an Extra or Upgrade.</p>
                    <div class="border rounded p-2" style="height: 350px; overflow-y: auto; background: var(--av-bg-subtle, #fdfdfd);" id="upsell_available_list">
                        <div class="text-center text-muted mt-5">Loading products...</div>
                    </div>
                </div>

                <!-- Selected Extras -->
                <div class="col-md-4 mb-3">
                    <label class="form-label d-flex justify-content-between align-items-center">
                        <span>Selected Extras</span>
                        <span class="badge bg-success" id="upsell_extra_count">0</span>
                    </label>
                    <p class="text-muted small mb-2">Products shown as quick-add "extras" at checkout.</p>
                    <div class="border rounded p-2" style="height: 350px; overflow-y: auto; background: rgba(25, 135, 84, 0.05);" id="upsell_extra_list">
                        <!-- Populated by JS -->
                    </div>
                    <div id="upsell_extra_inputs"></div>
                </div>

                <!-- Selected Upgrades -->
                <div class="col-md-4 mb-3">
                    <label class="form-label d-flex justify-content-between align-items-center">
                        <span>Selected Upgrades</span>
                        <span class="badge bg-primary" id="upsell_upgrade_count">0</span>
                    </label>
                    <p class="text-muted small mb-2">Products shown as quick-add "upgrades" at checkout.</p>
                    <div class="border rounded p-2" style="height: 350px; overflow-y: auto; background: rgba(13, 110, 253, 0.05);" id="upsell_upgrade_list">
                        <!-- Populated by JS -->
                    </div>
                    <div id="upsell_upgrade_inputs"></div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('styles')
<style>
    .upsell-item {
        padding: 8px 12px;
        border: 1px solid var(--av-border-color, #e0e0e0);
        border-radius: 4px;
        margin-bottom: 8px;
        background: var(--av-bg-base, #fff);
        transition: all 0.2s ease-in-out;
    }
    .upsell-item:hover {
        border-color: #aaa;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    .upsell-item-title {
        font-weight: 500;
        margin-bottom: 4px;
        font-size: 14px;
    }
    .upsell-item-price {
        font-size: 12px;
        color: #666;
    }
    .upsell-btn-group {
        display: flex;
        gap: 5px;
        margin-top: 8px;
    }
</style>
@endpush

@push('scripts')
<script>
$(document).ready(function() {
    var allProducts = @json($allProducts);
    var extraUpsellIds = @json($extraUpsellIds);
    var upgradeUpsellIds = @json($upgradeUpsellIds);
    var currentProductId = "{{ isset($product) ? $product->id : '' }}";
    
    // Convert to numbers
    extraUpsellIds = extraUpsellIds.map(id => parseInt(id));
    upgradeUpsellIds = upgradeUpsellIds.map(id => parseInt(id));
    
    // We will store selected product objects here
    var selectedExtras = [];
    var selectedUpgrades = [];
    var availableProducts = [];

    // Populate initial selected arrays
    allProducts.forEach(function(product) {
        if (extraUpsellIds.includes(product.id)) {
            selectedExtras.push(product);
        }
        if (upgradeUpsellIds.includes(product.id)) {
            selectedUpgrades.push(product);
        }
    });

    // Subcategory Filter Fetch
    $('#upsell_category_id').on('change', function () {
        var categoryId = $(this).val();
        $('#upsell_subcategory_id').empty().append('<option value="">All SubCategories</option>');
        if (categoryId) {
            $.ajax({
                // Using existing subcategories endpoint that doesn't need to be recreated
                url: "{{ url('/get-subcategories') }}/" + categoryId,
                type: "GET",
                dataType: "json",
                success: function (data) {
                    $.each(data, function (key, value) {
                        $('#upsell_subcategory_id').append('<option value="' + value.id + '">' + value.name + '</option>');
                    });
                }
            });
        }
        fetchAvailableProducts();
    });

    $('#upsell_subcategory_id').on('change', function() {
        fetchAvailableProducts();
    });

    // Debounce search
    var searchTimeout;
    $('#upsell_search').on('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            fetchAvailableProducts();
        }, 300);
    });

    function fetchAvailableProducts() {
        var categoryId = $('#upsell_category_id').val();
        var subcategoryId = $('#upsell_subcategory_id').val();
        var search = $('#upsell_search').val();

        $.ajax({
            url: "{{ route('products.upsells') }}",
            type: "GET",
            data: {
                category_id: categoryId,
                subcategory_id: subcategoryId,
                search: search,
                exclude_id: currentProductId
            },
            success: function(response) {
                availableProducts = response;
                renderAvailableProducts();
            }
        });
    }

    function renderAvailableProducts() {
        var html = '';
        var count = 0;
        
        availableProducts.forEach(function(product) {
            // Don't show if already in extras or upgrades
            if (extraUpsellIds.includes(product.id) || upgradeUpsellIds.includes(product.id)) {
                return;
            }
            
            count++;
            var priceDisplay = product.price ? '$' + product.price : 'N/A';
            html += `
                <div class="upsell-item" id="avail-item-${product.id}">
                    <div class="upsell-item-title">${product.name}</div>
                    <div class="upsell-item-price">Price: ${priceDisplay}</div>
                    <div class="upsell-btn-group">
                        <button type="button" class="btn btn-sm btn-outline-success flex-grow-1 btn-add-extra" data-id="${product.id}">+ Extra</button>
                        <button type="button" class="btn btn-sm btn-outline-primary flex-grow-1 btn-add-upgrade" data-id="${product.id}">+ Upgrade</button>
                    </div>
                </div>
            `;
        });

        if (count === 0) {
            html = '<div class="text-center text-muted mt-4">No products found.</div>';
        }

        $('#upsell_available_list').html(html);
        $('#upsell_available_count').text(count);
    }

    function renderSelectedExtras() {
        var html = '';
        var inputsHtml = '';
        
        selectedExtras.forEach(function(product) {
            var priceDisplay = product.price ? '$' + product.price : 'N/A';
            html += `
                <div class="upsell-item">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="upsell-item-title">${product.name}</div>
                            <div class="upsell-item-price">Price: ${priceDisplay}</div>
                        </div>
                        <button type="button" class="btn btn-sm btn-danger btn-remove-extra" data-id="${product.id}">
                            <i class="fa fa-times"></i>
                        </button>
                    </div>
                </div>
            `;
            inputsHtml += `<input type="hidden" name="extra_upsells[]" value="${product.id}">`;
        });

        if (selectedExtras.length === 0) {
            html = '<div class="text-center text-muted mt-4">No Extras selected.</div>';
        }

        $('#upsell_extra_list').html(html);
        $('#upsell_extra_inputs').html(inputsHtml);
        $('#upsell_extra_count').text(selectedExtras.length);
    }

    function renderSelectedUpgrades() {
        var html = '';
        var inputsHtml = '';
        
        selectedUpgrades.forEach(function(product) {
            var priceDisplay = product.price ? '$' + product.price : 'N/A';
            html += `
                <div class="upsell-item">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="upsell-item-title">${product.name}</div>
                            <div class="upsell-item-price">Price: ${priceDisplay}</div>
                        </div>
                        <button type="button" class="btn btn-sm btn-danger btn-remove-upgrade" data-id="${product.id}">
                            <i class="fa fa-times"></i>
                        </button>
                    </div>
                </div>
            `;
            inputsHtml += `<input type="hidden" name="upgrade_upsells[]" value="${product.id}">`;
        });

        if (selectedUpgrades.length === 0) {
            html = '<div class="text-center text-muted mt-4">No Upgrades selected.</div>';
        }

        $('#upsell_upgrade_list').html(html);
        $('#upsell_upgrade_inputs').html(inputsHtml);
        $('#upsell_upgrade_count').text(selectedUpgrades.length);
    }

    // Add as Extra
    $(document).on('click', '.btn-add-extra', function() {
        var id = $(this).data('id');
        var product = availableProducts.find(p => p.id === id);
        if (product) {
            extraUpsellIds.push(id);
            selectedExtras.push(product);
            renderSelectedExtras();
            renderAvailableProducts();
        }
    });

    // Add as Upgrade
    $(document).on('click', '.btn-add-upgrade', function() {
        var id = $(this).data('id');
        var product = availableProducts.find(p => p.id === id);
        if (product) {
            upgradeUpsellIds.push(id);
            selectedUpgrades.push(product);
            renderSelectedUpgrades();
            renderAvailableProducts();
        }
    });

    // Remove from Extras
    $(document).on('click', '.btn-remove-extra', function() {
        var id = $(this).data('id');
        extraUpsellIds = extraUpsellIds.filter(pid => pid !== id);
        selectedExtras = selectedExtras.filter(p => p.id !== id);
        renderSelectedExtras();
        renderAvailableProducts();
    });

    // Remove from Upgrades
    $(document).on('click', '.btn-remove-upgrade', function() {
        var id = $(this).data('id');
        upgradeUpsellIds = upgradeUpsellIds.filter(pid => pid !== id);
        selectedUpgrades = selectedUpgrades.filter(p => p.id !== id);
        renderSelectedUpgrades();
        renderAvailableProducts();
    });

    // Initial Fetch & Render
    fetchAvailableProducts();
    renderSelectedExtras();
    renderSelectedUpgrades();
});
</script>
@endpush
