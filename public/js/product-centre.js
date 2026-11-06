$(document).ready(function() {
    // Global CSRF token
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });
    
    /**
     * Build a usable <img src> from whatever the API returned.
     *
     * The Category and SubCategory models run their `image` column through
     * image_asset(), which always hands back an absolute URL (and falls back to
     * the shared placeholder when the column is empty). Products, meanwhile,
     * are serialised with getAttributes() and stay relative. Blindly prefixing
     * "/" turned the absolute ones into "/https://host/..." and broke every
     * placeholder thumbnail on the page.
     */
    function imageSrc(value) {
        if (!value) return '/no_image/no_image.jpg';
        if (/^(https?:)?\/\//i.test(value) || value.charAt(0) === '/') return value;
        return '/' + value;
    }


    // ==========================================
    // CATEGORIES TAB
    // ==========================================
    let categoriesData = [];

    function loadCategories() {
        console.log('Loading categories...');
        $.ajax({
            url: '/product-centre/categories/data',
            type: 'GET',
            success: function(response) {
                console.log('Categories loaded:', response);
                categoriesData = response.data;
                renderCategories();
                enableCategorySortable();
            },
            error: function(xhr, status, error) {
                console.error('Failed to load categories:', xhr);
                toastr.error('Failed to load categories: ' + xhr.statusText);
            }
        });
    }

    function renderCategories(searchTerm = '') {
        let tbody = $('#categoriesTableBody');
        tbody.empty();
        
        let filtered = categoriesData.filter(cat => 
            searchTerm === '' || 
            cat.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
            (cat.inapp_name && cat.inapp_name.toLowerCase().includes(searchTerm.toLowerCase()))
        );
        
        if (filtered.length === 0) {
            tbody.append(`
                <tr>
                    <td colspan="9" class="text-center text-muted py-4">
                        <i class="fe fe-inbox" style="font-size: 48px;"></i>
                        <p class="mt-2">No categories found</p>
                    </td>
                </tr>
            `);
            return;
        }
        
        filtered.forEach(cat => {
            let imageUrl = imageSrc(cat.image);
            let checked = cat.status === 1 ? 'checked' : '';
            
            tbody.append(`
                <tr data-id="${cat.id}">
                    <td>
                        <input type="checkbox" class="category-checkbox" value="${cat.id}">
                    </td>
                    <td class="d-none d-md-table-cell">
                        <i class="fe fe-menu drag-handle"></i>
                    </td>
                    <td>
                        <img src="${imageUrl}" class="img-preview" alt="${cat.name}">
                    </td>
                    <td><strong>${cat.name}</strong></td>
                    <td><span class="text-muted">${cat.inapp_name}</span></td>
                    <td><span class="custom-badge badge-info">${cat.subcategories_count}</span></td>
                    <td><span class="custom-badge badge-secondary">${cat.products_count}</span></td>
                    <td>
                        <span class="d-none">Status</span>
                        <label class="status-toggle">
                            <input type="checkbox" class="category-status-toggle" data-id="${cat.id}" ${checked}>
                            <span class="toggle-slider"></span>
                        </label>
                    </td>
                    <td>
                        <div class="d-flex justify-content-end gap-2">
                            <a href="/category/${cat.id}/edit" class="action-btn btn btn-warning" aria-label="Edit">
                                <i class="fe fe-edit"></i>
                            </a>
                            <button class="action-btn btn btn-info" onclick="duplicateCategory(${cat.id})" aria-label="Duplicate">
                                <i class="fe fe-copy"></i>
                            </button>
                            <button class="action-btn btn btn-danger" onclick="deleteCategory(${cat.id})" aria-label="Delete">
                                <i class="fe fe-trash-2"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `);
        });
    }

    function enableCategorySortable() {
        if (typeof $.fn.sortable === 'function') {
            $('#categoriesTableBody').sortable({
                handle: '.drag-handle',
                axis: 'y',
                update: function(event, ui) {
                    saveCategoryOrder();
                }
            });
        }
    }

    function saveCategoryOrder() {
        let order = [];
        $('#categoriesTableBody tr').each(function(index) {
            let id = $(this).data('id');
            if (id) {
                order.push({ id: id, position: index + 1 });
            }
        });
        
        $.ajax({
            url: '/product-centre/categories/reorder',
            type: 'POST',
            data: { order: order },
            success: function(response) {
                toastr.success(response.message);
            },
            error: function() {
                toastr.error('Failed to update order');
                loadCategories();
            }
        });
    }

    $(document).on('change', '.category-status-toggle', function() {
        let checkbox = $(this);
        let id = checkbox.data('id');
        let status = checkbox.is(':checked') ? 1 : 0;
        
        checkbox.prop('disabled', true);
        
        $.ajax({
            url: `/product-centre/categories/${id}/status`,
            type: 'POST',
            data: { status: status },
            success: function(response) {
                toastr.success(response.message);
                checkbox.prop('disabled', false);
            },
            error: function() {
                toastr.error('Failed to update status');
                checkbox.prop('checked', !checkbox.is(':checked'));
                checkbox.prop('disabled', false);
            }
        });
    });

    window.duplicateCategory = function(id) {
        Swal.fire({
            title: 'Duplicate Category?',
            text: "All details including image will be cloned.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#667eea',
            confirmButtonText: 'Yes, duplicate it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: `/category/${id}/duplicate`,
                    type: 'POST',
                    success: function(response) {
                        toastr.success('Category duplicated successfully!');
                        loadCategories();
                    },
                    error: function() {
                        toastr.error('Failed to duplicate category');
                    }
                });
            }
        });
    };

    window.deleteCategory = function(id) {
        Swal.fire({
            title: 'Delete Category?',
            text: "This action cannot be undone!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: `/category/${id}`,
                    type: 'DELETE',
                    success: function(response) {
                        toastr.success('Category deleted successfully!');
                        loadCategories();
                    },
                    error: function() {
                        toastr.error('Failed to delete category');
                    }
                });
            }
        });
    };

    window.bulkDeleteCategories = function() {
        let selected = $('.category-checkbox:checked').map(function() { return $(this).val(); }).get();
        if (selected.length === 0) return;
        
        Swal.fire({
            title: `Delete ${selected.length} Categories?`,
            text: "This action cannot be undone!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'Yes, delete them!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '/product-centre/categories/bulk-delete',
                    type: 'POST',
                    data: { ids: selected },
                    success: function(response) {
                        toastr.success(response.message);
                        loadCategories();
                        clearCategorySelection();
                    },
                    error: function() {
                        toastr.error('Failed to delete categories');
                    }
                });
            }
        });
    };

    $('#categorySelectAll').on('change', function() {
        $('.category-checkbox').prop('checked', $(this).is(':checked'));
        updateCategoryBulkActions();
    });

    $(document).on('change', '.category-checkbox', function() {
        updateCategoryBulkActions();
    });

    function updateCategoryBulkActions() {
        let count = $('.category-checkbox:checked').length;
        $('#categorySelectedCount').text(count);
        if (count > 0) {
            $('#categoryBulkActions').addClass('active');
        } else {
            $('#categoryBulkActions').removeClass('active');
        }
    }

    window.clearCategorySelection = function() {
        $('.category-checkbox').prop('checked', false);
        $('#categorySelectAll').prop('checked', false);
        updateCategoryBulkActions();
    };

    $('#categorySearch').on('keyup', function() {
        renderCategories($(this).val().toLowerCase());
        enableCategorySortable();
    });

    // ==========================================
    // SUBCATEGORIES TAB
    // ==========================================
    let subcategoriesData = [];
    let categoriesForFilter = [];

    function loadSubCategoryFilters() {
        $.ajax({
            url: '/product-centre/filter-data',
            type: 'GET',
            success: function(response) {
                categoriesForFilter = response.categories;
                let select = $('#subcategoryFilterCategory');
                select.empty().append('<option value="">All Categories</option>');
                response.categories.forEach(cat => {
                    select.append(`<option value="${cat.id}">${cat.name}</option>`);
                });
            }
        });
    }

    function loadSubCategories() {
        let categoryId = $('#subcategoryFilterCategory').val();
        console.log('Loading subcategories for category:', categoryId);
        
        $.ajax({
            url: '/product-centre/subcategories/data',
            type: 'GET',
            data: { category_id: categoryId },
            success: function(response) {
                console.log('SubCategories loaded:', response);
                subcategoriesData = response.data;
                renderSubCategories();
                enableSubCategorySortable();
            },
            error: function(xhr) {
                console.error('Failed to load subcategories:', xhr);
                toastr.error('Failed to load subcategories');
            }
        });
    }

    function renderSubCategories(searchTerm = '') {
        let tbody = $('#subcategoriesTableBody');
        tbody.empty();
        
        let filtered = subcategoriesData.filter(sub => 
            searchTerm === '' || 
            sub.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
            sub.category_name.toLowerCase().includes(searchTerm.toLowerCase())
        );
        
        if (filtered.length === 0) {
            tbody.append(`
                <tr>
                    <td colspan="8" class="text-center text-muted py-4">
                        <i class="fe fe-inbox" style="font-size: 48px;"></i>
                        <p class="mt-2">No subcategories found</p>
                    </td>
                </tr>
            `);
            return;
        }
        
        filtered.forEach(sub => {
            let imageUrl = imageSrc(sub.image);
            let checked = sub.status === 1 ? 'checked' : '';
            
            tbody.append(`
                <tr data-id="${sub.id}">
                    <td>
                        <input type="checkbox" class="subcategory-checkbox" value="${sub.id}">
                    </td>
                    <td class="d-none d-md-table-cell">
                        <i class="fe fe-menu drag-handle"></i>
                    </td>
                    <td>
                        <img src="${imageUrl}" class="img-preview" alt="${sub.name}">
                    </td>
                    <td><strong>${sub.name}</strong></td>
                    <td><span class="custom-badge badge-info">${sub.category_name}</span></td>
                    <td><span class="custom-badge badge-secondary">${sub.products_count}</span></td>
                    <td>
                        <span class="d-none">Status</span>
                        <label class="status-toggle">
                            <input type="checkbox" class="subcategory-status-toggle" data-id="${sub.id}" ${checked}>
                            <span class="toggle-slider"></span>
                        </label>
                    </td>
                    <td>
                        <div class="d-flex justify-content-end gap-2">
                            <a href="/subcategory/${sub.id}/edit" class="action-btn btn btn-warning" aria-label="Edit">
                                <i class="fe fe-edit"></i>
                            </a>
                            <button class="action-btn btn btn-info" onclick="duplicateSubCategory(${sub.id})" aria-label="Duplicate">
                                <i class="fe fe-copy"></i>
                            </button>
                            <button class="action-btn btn btn-danger" onclick="deleteSubCategory(${sub.id})" aria-label="Delete">
                                <i class="fe fe-trash-2"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `);
        });
    }

    function enableSubCategorySortable() {
        if (typeof $.fn.sortable === 'function') {
            $('#subcategoriesTableBody').sortable({
                handle: '.drag-handle',
                axis: 'y',
                update: function(event, ui) {
                    saveSubCategoryOrder();
                }
            });
        }
    }

    function saveSubCategoryOrder() {
        let order = [];
        $('#subcategoriesTableBody tr').each(function(index) {
            let id = $(this).data('id');
            if (id) {
                order.push({ id: id, position: index + 1 });
            }
        });
        
        $.ajax({
            url: '/product-centre/subcategories/reorder',
            type: 'POST',
            data: { order: order },
            success: function(response) {
                toastr.success(response.message);
            },
            error: function() {
                toastr.error('Failed to update order');
                loadSubCategories();
            }
        });
    }

    $(document).on('change', '.subcategory-status-toggle', function() {
        let checkbox = $(this);
        let id = checkbox.data('id');
        let status = checkbox.is(':checked') ? 1 : 0;
        
        checkbox.prop('disabled', true);
        
        $.ajax({
            url: `/product-centre/subcategories/${id}/status`,
            type: 'POST',
            data: { status: status },
            success: function(response) {
                toastr.success(response.message);
                checkbox.prop('disabled', false);
            },
            error: function() {
                toastr.error('Failed to update status');
                checkbox.prop('checked', !checkbox.is(':checked'));
                checkbox.prop('disabled', false);
            }
        });
    });

    window.duplicateSubCategory = function(id) {
        Swal.fire({
            title: 'Duplicate SubCategory?',
            text: "All details including image will be cloned.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#667eea',
            confirmButtonText: 'Yes, duplicate it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: `/subcategory/${id}/duplicate`,
                    type: 'POST',
                    success: function(response) {
                        toastr.success('SubCategory duplicated successfully!');
                        loadSubCategories();
                    },
                    error: function() {
                        toastr.error('Failed to duplicate subcategory');
                    }
                });
            }
        });
    };

    window.deleteSubCategory = function(id) {
        Swal.fire({
            title: 'Delete SubCategory?',
            text: "This action cannot be undone!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: `/subcategory/${id}`,
                    type: 'DELETE',
                    success: function(response) {
                        toastr.success('SubCategory deleted successfully!');
                        loadSubCategories();
                    },
                    error: function() {
                        toastr.error('Failed to delete subcategory');
                    }
                });
            }
        });
    };

    window.bulkDeleteSubCategories = function() {
        let selected = $('.subcategory-checkbox:checked').map(function() { return $(this).val(); }).get();
        if (selected.length === 0) return;
        
        Swal.fire({
            title: `Delete ${selected.length} SubCategories?`,
            text: "This action cannot be undone!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'Yes, delete them!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '/product-centre/subcategories/bulk-delete',
                    type: 'POST',
                    data: { ids: selected },
                    success: function(response) {
                        toastr.success(response.message);
                        loadSubCategories();
                        clearSubCategorySelection();
                    },
                    error: function() {
                        toastr.error('Failed to delete subcategories');
                    }
                });
            }
        });
    };

    $('#subcategorySelectAll').on('change', function() {
        $('.subcategory-checkbox').prop('checked', $(this).is(':checked'));
        updateSubCategoryBulkActions();
    });

    $(document).on('change', '.subcategory-checkbox', function() {
        updateSubCategoryBulkActions();
    });

    function updateSubCategoryBulkActions() {
        let count = $('.subcategory-checkbox:checked').length;
        $('#subcategorySelectedCount').text(count);
        if (count > 0) {
            $('#subcategoryBulkActions').addClass('active');
        } else {
            $('#subcategoryBulkActions').removeClass('active');
        }
    }

    window.clearSubCategorySelection = function() {
        $('.subcategory-checkbox').prop('checked', false);
        $('#subcategorySelectAll').prop('checked', false);
        updateSubCategoryBulkActions();
    };

    $('#subcategorySearch').on('keyup', function() {
        renderSubCategories($(this).val().toLowerCase());
        enableSubCategorySortable();
    });

    $('#subcategoryFilterCategory').on('change', function() {
        loadSubCategories();
    });

    // ==========================================
    // PRODUCTS TAB
    // ==========================================
    let productsData = [];
    let productCategories = [];
    let productSubCategories = [];

    function loadProductFilters() {
        $.ajax({
            url: '/product-centre/filter-data',
            type: 'GET',
            success: function(response) {
                productCategories = response.categories;
                productSubCategories = response.subcategories;
                
                let catSelect = $('#productFilterCategory');
                catSelect.empty().append('<option value="">All Categories</option>');
                response.categories.forEach(cat => {
                    catSelect.append(`<option value="${cat.id}">${cat.name}</option>`);
                });
                
                updateProductSubCategoryFilter();
            }
        });
    }

    function updateProductSubCategoryFilter() {
        let categoryId = $('#productFilterCategory').val();
        let subSelect = $('#productFilterSubCategory');
        
        subSelect.empty().append('<option value="">All SubCategories</option>');
        
        if (categoryId) {
            let filtered = productSubCategories.filter(sub => sub.category_id == categoryId);
            filtered.forEach(sub => {
                subSelect.append(`<option value="${sub.id}">${sub.name}</option>`);
            });
        } else {
            productSubCategories.forEach(sub => {
                subSelect.append(`<option value="${sub.id}">${sub.name}</option>`);
            });
        }
    }

    function loadProducts() {
        let categoryId = $('#productFilterCategory').val();
        let subcategoryId = $('#productFilterSubCategory').val();
        
        console.log('Loading products...', { categoryId, subcategoryId });
        
        $.ajax({
            url: '/product-centre/products/data',
            type: 'GET',
            data: { 
                category_id: categoryId,
                subcategory_id: subcategoryId
            },
            success: function(response) {
                console.log('Products loaded:', response);
                productsData = response.data;
                renderProducts();
                enableProductSortable();
            },
            error: function(xhr) {
                console.error('Failed to load products:', xhr);
                toastr.error('Failed to load products');
            }
        });
    }

    function renderProducts(searchTerm = '') {
        let tbody = $('#productsTableBody');
        tbody.empty();
        
        let filtered = productsData.filter(prod => 
            searchTerm === '' || 
            prod.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
            (prod.short_title && prod.short_title.toLowerCase().includes(searchTerm.toLowerCase())) ||
            prod.category_name.toLowerCase().includes(searchTerm.toLowerCase()) ||
            prod.subcategory_name.toLowerCase().includes(searchTerm.toLowerCase())
        );
        
        if (filtered.length === 0) {
            tbody.append(`
                <tr>
                    <td colspan="10" class="text-center text-muted py-4">
                        <i class="fe fe-inbox" style="font-size: 48px;"></i>
                        <p class="mt-2">No products found</p>
                    </td>
                </tr>
            `);
            return;
        }
        
        filtered.forEach(prod => {
            let imageUrl = imageSrc(prod.image);
            let checked = prod.status === 1 ? 'checked' : '';
            let stockBadge = prod.quantity > 0 
                ? `<span class="custom-badge badge-success">${prod.quantity} in stock</span>` 
                : `<span class="custom-badge badge-danger">Out of stock</span>`;
            
            tbody.append(`
                <tr data-id="${prod.id}">
                    <td>
                        <input type="checkbox" class="product-checkbox" value="${prod.id}">
                    </td>
                    <td class="d-none d-md-table-cell">
                        <i class="fe fe-menu drag-handle"></i>
                    </td>
                    <td>
                        <img src="${imageUrl}" class="img-preview" alt="${prod.name}">
                    </td>
                    <td>
                        <strong>${prod.name}</strong>
                        ${prod.short_title ? `<br><small class="text-muted">${prod.short_title}</small>` : ''}
                    </td>
                    <td><span class="custom-badge badge-info">${prod.category_name}</span></td>
                    <td><span class="custom-badge badge-secondary">${prod.subcategory_name}</span></td>
                    <td><strong>$${parseFloat(prod.price).toFixed(2)}</strong></td>
                    <td>${stockBadge}</td>
                    <td>
                        <span class="d-none">Status</span>
                        <label class="status-toggle">
                            <input type="checkbox" class="product-status-toggle" data-id="${prod.id}" ${checked}>
                            <span class="toggle-slider"></span>
                        </label>
                    </td>
                    <td>
                        <div class="d-flex justify-content-end gap-2">
                            <a href="/products/${prod.id}/edit" class="action-btn btn btn-warning" aria-label="Edit">
                                <i class="fe fe-edit"></i>
                            </a>
                            <button class="action-btn btn btn-info" onclick="duplicateProduct(${prod.id})" aria-label="Duplicate">
                                <i class="fe fe-copy"></i>
                            </button>
                            <button class="action-btn btn btn-danger" onclick="deleteProduct(${prod.id})" aria-label="Delete">
                                <i class="fe fe-trash-2"></i>
                            </button>
                        </div>
                    </td>
                </tr>
            `);
        });
    }

    function enableProductSortable() {
        if (typeof $.fn.sortable === 'function') {
            $('#productsTableBody').sortable({
                handle: '.drag-handle',
                axis: 'y',
                update: function(event, ui) {
                    saveProductOrder();
                }
            });
        }
    }

    function saveProductOrder() {
        let order = [];
        $('#productsTableBody tr').each(function(index) {
            let id = $(this).data('id');
            if (id) {
                order.push({ id: id, position: index + 1 });
            }
        });
        
        $.ajax({
            url: '/product-centre/products/reorder',
            type: 'POST',
            data: { order: order },
            success: function(response) {
                toastr.success(response.message);
            },
            error: function() {
                toastr.error('Failed to update order');
                loadProducts();
            }
        });
    }

    $(document).on('change', '.product-status-toggle', function() {
        let checkbox = $(this);
        let id = checkbox.data('id');
        let status = checkbox.is(':checked') ? 1 : 0;
        
        checkbox.prop('disabled', true);
        
        $.ajax({
            url: `/product-centre/products/${id}/status`,
            type: 'POST',
            data: { status: status },
            success: function(response) {
                toastr.success(response.message);
                checkbox.prop('disabled', false);
            },
            error: function() {
                toastr.error('Failed to update status');
                checkbox.prop('checked', !checkbox.is(':checked'));
                checkbox.prop('disabled', false);
            }
        });
    });

    window.duplicateProduct = function(id) {
        Swal.fire({
            title: 'Duplicate Product?',
            text: "All details including images will be cloned.",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#667eea',
            confirmButtonText: 'Yes, duplicate it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: `/products/${id}/duplicate`,
                    type: 'POST',
                    success: function(response) {
                        toastr.success('Product duplicated successfully!');
                        loadProducts();
                    },
                    error: function() {
                        toastr.error('Failed to duplicate product');
                    }
                });
            }
        });
    };

    window.deleteProduct = function(id) {
        Swal.fire({
            title: 'Delete Product?',
            text: "This action cannot be undone!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'Yes, delete it!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: `/products/${id}`,
                    type: 'DELETE',
                    success: function(response) {
                        toastr.success('Product deleted successfully!');
                        loadProducts();
                    },
                    error: function() {
                        toastr.error('Failed to delete product');
                    }
                });
            }
        });
    };

    window.bulkDeleteProducts = function() {
        let selected = $('.product-checkbox:checked').map(function() { return $(this).val(); }).get();
        if (selected.length === 0) return;
        
        Swal.fire({
            title: `Delete ${selected.length} Products?`,
            text: "This action cannot be undone!",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc3545',
            confirmButtonText: 'Yes, delete them!'
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '/product-centre/products/bulk-delete',
                    type: 'POST',
                    data: { ids: selected },
                    success: function(response) {
                        toastr.success(response.message);
                        loadProducts();
                        clearProductSelection();
                    },
                    error: function() {
                        toastr.error('Failed to delete products');
                    }
                });
            }
        });
    };

    $('#productSelectAll').on('change', function() {
        $('.product-checkbox').prop('checked', $(this).is(':checked'));
        updateProductBulkActions();
    });

    $(document).on('change', '.product-checkbox', function() {
        updateProductBulkActions();
    });

    function updateProductBulkActions() {
        let count = $('.product-checkbox:checked').length;
        $('#productSelectedCount').text(count);
        if (count > 0) {
            $('#productBulkActions').addClass('active');
        } else {
            $('#productBulkActions').removeClass('active');
        }
    }

    window.clearProductSelection = function() {
        $('.product-checkbox').prop('checked', false);
        $('#productSelectAll').prop('checked', false);
        updateProductBulkActions();
    };

    $('#productSearch').on('keyup', function() {
        renderProducts($(this).val().toLowerCase());
        enableProductSortable();
    });

    $('#productFilterCategory').on('change', function() {
        updateProductSubCategoryFilter();
        loadProducts();
    });

    $('#productFilterSubCategory').on('change', function() {
        loadProducts();
    });

    // ==========================================
    // TAB SWITCHING
    // ==========================================
    $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
        let target = $(e.target).attr("href");
        console.log('Switched to tab:', target);
        
        if (target === '#categories') {
            loadCategories();
        } else if (target === '#subcategories') {
            loadSubCategoryFilters();
            loadSubCategories();
        } else if (target === '#products') {
            loadProductFilters();
            loadProducts();
        }
    });
    
    $('a[data-toggle="tab"]').on('click', function(e) {
        e.preventDefault();
        $(this).tab('show');
    });

    // Check URL hash and activate correct tab on page load
    var urlHash = window.location.hash;
    console.log('URL hash on page load:', urlHash);
    
    if (urlHash === '#products') {
        console.log('Activating products tab from URL');
        $('#categories-tab').removeClass('active');
        $('#subcategories-tab').removeClass('active');
        $('#products-tab').addClass('active');
        $('#categories').removeClass('active show');
        $('#subcategories').removeClass('active show');
        $('#products').addClass('active show');
        loadProductFilters();
        loadProducts();
    } else if (urlHash === '#subcategories') {
        console.log('Activating subcategories tab from URL');
        $('#categories-tab').removeClass('active');
        $('#products-tab').removeClass('active');
        $('#subcategories-tab').addClass('active');
        $('#categories').removeClass('active show');
        $('#products').removeClass('active show');
        $('#subcategories').addClass('active show');
        loadSubCategoryFilters();
        loadSubCategories();
    } else {
        console.log('Loading default categories tab');
        loadCategories();
    }
    
}); // End document.ready