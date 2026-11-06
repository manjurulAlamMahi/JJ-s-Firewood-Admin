@extends('backend.master')

@section('title', 'Create Giveaway')

@section('body')
<style>
    .create-giveaway-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 2rem;
        border-radius: 10px;
        margin-bottom: 2rem;
    }
    
    .form-section {
        background: white;
        padding: 2rem;
        border-radius: 10px;
        margin-bottom: 1.5rem;
        box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    }
    
    .form-section h5 {
        color: #667eea;
        border-bottom: 2px solid #667eea;
        padding-bottom: 0.5rem;
        margin-bottom: 1.5rem;
    }
    
    .image-upload-area {
        border: 2px dashed #dee2e6;
        border-radius: 10px;
        padding: 3rem;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s;
    }
    
    .image-upload-area:hover {
        border-color: #667eea;
        background: #f8f9fa;
    }
    
    .image-preview {
        max-width: 300px;
        max-height: 300px;
        margin: 1rem auto;
        border-radius: 10px;
        display: none;
    }
    
    .live-preview {
        background: white;
        border-radius: 10px;
        padding: 2rem;
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        position: sticky;
        top: 20px;
    }
    
    .entry-package-item {
        border: 2px solid #e9ecef;
        border-radius: 8px;
        padding: 1rem;
        margin-bottom: 1rem;
        transition: all 0.3s;
    }
    
    .entry-package-item:hover {
        border-color: #667eea;
        background: #f8f9fa;
    }
    
    .entry-package-item.selected {
        border-color: #667eea;
        background: #f0f4ff;
    }
    
    .entry-package-item input[type="checkbox"] {
        width: 20px;
        height: 20px;
        cursor: pointer;
    }
    
    .custom-price-input {
        display: none;
    }
    
    .entry-package-item.selected .custom-price-input {
        display: block;
    }
</style>

<div class="create-giveaway-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h2 class="mb-1">🎁 Create New Giveaway</h2>
            <p class="mb-0 opacity-75">Set up a new giveaway with automatic drawing and entry management</p>
        </div>
        <a href="/giveaway-centre" class="btn btn-light">
            <i class="fe fe-arrow-left"></i> Back to Giveaway Centre
        </a>
    </div>
</div>

@if ($errors->any() || session('error'))
    <div class="alert alert-danger">
        <strong>The giveaway wasn't saved:</strong>
        <ul class="mb-0 mt-1">
            @if (session('error'))<li>{{ session('error') }}</li>@endif
            @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
        @if (old('prize_name'))<div class="mt-1 small">Your details are kept below. The image and entry packages need re-selecting.</div>@endif
    </div>
@endif

<form id="createGiveawayForm" action="{{ route('giveaway-centre.store') }}" method="POST" enctype="multipart/form-data">
    @csrf
    <input type="hidden" name="action" id="form_action" value="create">
    
    <div class="row">
        <!-- Left Column - Form -->
        <div class="col-lg-8">
            
            <!-- Basic Information -->
            <div class="form-section">
                <h5><i class="fe fe-info mr-2"></i> Basic Information</h5>
                
                <div class="form-group">
                    <label>Prize Name <span class="text-danger">*</span></label>
                    <input type="text" name="prize_name" id="prize_name" class="form-control" 
                           placeholder="e.g., iPhone 15 Pro Max" value="{{ old('prize_name') }}" required>
                    <small class="form-text text-muted">This will be displayed to users</small>
                </div>
                
                <div class="form-group">
                    <label>Description <span class="text-danger">*</span></label>
                    <textarea name="description" id="description" class="form-control" rows="4" 
                              placeholder="Describe the prize, rules, and eligibility. Press Enter for a new line or a blank line between paragraphs." required>{{ old('description') }}</textarea>
                    <small class="form-text text-muted">Line breaks show in the app exactly as typed. Formatting like **bold** isn't supported and shows as plain text.</small>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Prize Value ($) <span class="text-danger">*</span></label>
                            <input type="number" name="prize_value" id="prize_value" class="form-control" 
                                   step="0.01" min="0" placeholder="0.00" value="{{ old('prize_value') }}" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Status <span class="text-danger">*</span></label>
                            <select name="status" id="status" class="form-control" required>
                                @php $st = old('status', 'active'); @endphp
                                <option value="draft" @selected($st === 'draft')>Draft (Not visible to users)</option>
                                <option value="scheduled" @selected($st === 'scheduled')>Scheduled (Will go live at start date)</option>
                                <option value="active" @selected($st === 'active')>Active (Live now)</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Prize Type <span class="text-danger">*</span></label>
                            <select name="prize_type" id="prize_type" class="form-control" required>
                                @php $pt = old('prize_type', 'others'); @endphp
                                <option value="bundle" @selected($pt === 'bundle')>Bundle</option>
                                <option value="discount" @selected($pt === 'discount')>Discount</option>
                                <option value="accessory" @selected($pt === 'accessory')>Accessory</option>
                                <option value="others" @selected($pt === 'others')>Others</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Prize Image -->
            <div class="form-section">
                <h5><i class="fe fe-image mr-2"></i> Prize Image</h5>
                
                <div class="image-upload-area" onclick="document.getElementById('image_input').click()">
                    <i class="fe fe-upload" style="font-size: 3rem; color: #667eea;"></i>
                    <h5 class="mt-3">Click to upload or drag & drop</h5>
                    <p class="text-muted">JPG, PNG or WEBP, up to 10MB</p>
                </div>
                
                <input type="file" name="image" id="image_input" accept="image/jpeg,image/png,image/webp" style="display: none;">
                <img id="image_preview" class="image-preview">
            </div>
            
            <!-- Schedule & Dates -->
            <div class="form-section">
                <h5><i class="fe fe-calendar mr-2"></i> Schedule & Dates</h5>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Start Date <span class="text-danger">*</span></label>
                            <input type="date" name="start_date" id="start_date" class="form-control" value="{{ old('start_date') }}" required>
                            <small class="form-text text-muted">When giveaway becomes active</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Start Time <span class="text-danger">*</span></label>
                            <input type="time" name="start_time" id="start_time" class="form-control" value="{{ old('start_time') }}" required>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Draw Date <span class="text-danger">*</span></label>
                            <input type="date" name="draw_date" id="draw_date" class="form-control" value="{{ old('draw_date') }}" required>
                            <small class="form-text text-muted">When winner will be drawn</small>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Draw Time <span class="text-danger">*</span></label>
                            <input type="time" name="draw_time" id="draw_time" class="form-control" value="{{ old('draw_time') }}" required>
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="auto_draw" name="auto_draw" value="1" checked>
                        <label class="custom-control-label" for="auto_draw">
                            <strong>Enable Automatic Drawing</strong>
                            <br><small class="text-muted">Winner will be automatically selected at draw date/time</small>
                        </label>
                    </div>
                </div>
                
                <!-- Animation Style Selection -->
                <div class="form-group">
                    <label for="animation_style">
                        Draw Animation Style 
                        <span class="text-muted">(How winner reveal looks)</span>
                    </label>
                    <select name="animation_style" id="animation_style" class="form-control">
                        <option value="lottery_balls" selected>
                            🎱 Lottery Ball Machine (Professional)
                        </option>
                        <option value="raffle_tickets">
                            🎡 Spinning Wheel (With Names)
                        </option>
                    </select>
                    <small class="form-text text-muted">
                        Choose the animation style for the winner reveal. Both include sound effects and confetti!
                    </small>
                </div>
            </div>
            
            <!-- Entry Settings -->
            <div class="form-section">
                <h5><i class="fe fe-shopping-cart mr-2"></i> Entry Packages & Settings</h5>
                
                <div class="alert alert-info">
                    <i class="fe fe-info"></i> <strong>How entries work:</strong>
                    <ul class="mb-0 mt-2">
                        <li>Users with <strong>permanent entries</strong> are automatically entered</li>
                        <li>Users can purchase <strong>temporary entries</strong> using the packages below</li>
                        <li>Total entries = Permanent + Purchased (Temporary)</li>
                        <li>Temporary entries expire after the draw</li>
                    </ul>
                </div>
                
                <div class="form-group mt-4">
                    <label class="d-block mb-3">
                        <strong>Select Entry Packages for this Giveaway</strong>
                        <br><small class="text-muted">Choose which packages users can buy. You can customize prices for this giveaway.</small>
                    </label>
                    
                    <div id="entry_packages_list">
                        <div class="text-center py-4">
                            <i class="fe fe-loader"></i> Loading packages...
                        </div>
                    </div>
                </div>
                
                <div class="form-group mt-4">
                    <label>Maximum Entries Per User (Optional)</label>
                    <input type="number" name="max_entries_per_user" id="max_entries_per_user" 
                           class="form-control" min="1" placeholder="Leave blank for unlimited" value="{{ old('max_entries_per_user') }}">
                    <small class="form-text text-muted">Limit total entries (permanent + purchased) each user can have</small>
                </div>
            </div>
            
        </div>
        
        <!-- Right Column - Live Preview -->
        <div class="col-lg-4">
            <div class="live-preview">
                <h5 class="mb-3"><i class="fe fe-eye mr-2"></i> Live Preview</h5>
                
                <div class="preview-card">
                    <div id="preview_image_wrapper" style="display: none;">
                        <img id="preview_image" src="" style="width: 100%; border-radius: 8px; margin-bottom: 1rem;">
                    </div>
                    
                    <div class="badge badge-success mb-2" id="preview_status">Active</div>
                    
                    <h4 id="preview_prize_name" class="text-muted">Prize Name</h4>
                    
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <span class="badge badge-primary badge-lg" id="preview_prize_value">$0.00</span>
                    </div>
                    
                    <p id="preview_description" class="text-muted" style="font-size: 0.9rem; white-space: pre-line;">
                        Prize description will appear here...
                    </p>
                    
                    <hr>
                    
                    <div class="row text-center">
                        <div class="col-6">
                            <i class="fe fe-calendar text-primary"></i>
                            <p class="mb-0" style="font-size: 0.75rem;">Start Date</p>
                            <strong id="preview_start_date">-</strong>
                        </div>
                        <div class="col-6">
                            <i class="fe fe-target text-primary"></i>
                            <p class="mb-0" style="font-size: 0.75rem;">Draw Date</p>
                            <strong id="preview_draw_date">-</strong>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div id="preview_packages">
                        <p class="mb-2" style="font-size: 0.85rem; font-weight: 600;">Available Entry Packages:</p>
                        <div id="preview_packages_list" class="text-muted" style="font-size: 0.85rem;">
                            No packages selected
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Action Buttons -->
    <div class="row">
        <div class="col-12">
            <div class="form-section">
                <button type="submit" class="btn btn-primary btn-lg" data-action="create">
                    <i class="fe fe-check mr-2"></i> Create Giveaway
                </button>
                <button type="submit" class="btn btn-secondary btn-lg" data-action="draft" formnovalidate>
                    <i class="fe fe-save mr-2"></i> Save as Draft
                </button>
                <a href="/giveaway-centre" class="btn btn-outline-secondary btn-lg">
                    <i class="fe fe-x mr-2"></i> Cancel
                </a>
            </div>
        </div>
    </div>
    
</form>

@endsection

@push('scripts')
<script>
// Suppress PerfectScrollbar errors that break form submission
(function() {
    const originalError = window.onerror;
    window.onerror = function(msg, url, lineNo, columnNo, error) {
        if (msg && (msg.includes('PerfectScrollbar') || msg.includes('perfect-scrollbar'))) {
            console.warn('PerfectScrollbar error suppressed');
            return true; // Suppress error
        }
        if (originalError) {
            return originalError(msg, url, lineNo, columnNo, error);
        }
        return false;
    };
})();

$(document).ready(function() {
    console.log('🎁 Create Giveaway - Loaded');
    
    let allPackages = [];
    let selectedPackages = {};
    
    // Load entry packages
    loadEntryPackages();
    
    function loadEntryPackages() {
        $.ajax({
            url: '/giveaway-centre/entry-packages/data',
            type: 'GET',
            success: function(response) {
                allPackages = response.data || [];
                renderPackages();
            },
            error: function() {
                $('#entry_packages_list').html('<p class="text-danger">Error loading packages</p>');
            }
        });
    }
    
    function renderPackages() {
        let html = '';
        
        if (allPackages.length === 0) {
            html = '<div class="alert alert-warning">No entry packages available. <a href="/entry-package/create">Create one first</a></div>';
        } else {
            allPackages.forEach(pkg => {
                html += `
                    <div class="entry-package-item" data-package-id="${pkg.id}">
                        <div class="d-flex align-items-start">
                            <input type="checkbox" class="package-checkbox mr-3" 
                                   data-package-id="${pkg.id}" 
                                   name="entry_packages[]" 
                                   value="${pkg.id}">
                            <div class="flex-grow-1">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>${pkg.name}</strong>
                                        <span class="badge badge-info ml-2">${pkg.entry_quantity} entries</span>
                                    </div>
                                    <div>
                                        <strong class="text-success">$${pkg.price}</strong>
                                    </div>
                                </div>
                                ${pkg.description ? '<small class="text-muted">' + pkg.description + '</small>' : ''}
                                
                                <div class="custom-price-input mt-2">
                                    <label class="mb-1"><small>Custom Price for this Giveaway (optional):</small></label>
                                    <div class="input-group input-group-sm">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text">$</span>
                                        </div>
                                        <input type="number" 
                                               step="0.01" 
                                               min="0" 
                                               class="form-control" 
                                               name="package_custom_price[${pkg.id}]" 
                                               placeholder="${pkg.price_raw}"
                                               data-package-id="${pkg.id}">
                                        <div class="input-group-append">
                                            <span class="input-group-text">
                                                <small class="text-muted">Original: $${pkg.price}</small>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
        }
        
        $('#entry_packages_list').html(html);
        
        // Bind checkbox events
        $('.package-checkbox').on('change', function() {
            let packageId = $(this).data('package-id');
            let isChecked = $(this).is(':checked');
            let $item = $(this).closest('.entry-package-item');
            
            if (isChecked) {
                $item.addClass('selected');
                let pkg = allPackages.find(p => p.id == packageId);
                selectedPackages[packageId] = pkg;
            } else {
                $item.removeClass('selected');
                delete selectedPackages[packageId];
            }
            
            updatePreviewPackages();
        });
        
        // Bind custom price inputs
        $('input[name^="package_custom_price"]').on('input', function() {
            let packageId = $(this).data('package-id');
            let customPrice = $(this).val();
            
            if (selectedPackages[packageId] && customPrice) {
                selectedPackages[packageId].custom_price = customPrice;
            } else if (selectedPackages[packageId]) {
                delete selectedPackages[packageId].custom_price;
            }
            
            updatePreviewPackages();
        });
    }
    
    function updatePreviewPackages() {
        let html = '';
        let count = Object.keys(selectedPackages).length;
        
        if (count === 0) {
            html = '<span class="text-muted">No packages selected</span>';
        } else {
            Object.values(selectedPackages).forEach(pkg => {
                let price = pkg.custom_price || pkg.price_raw;
                html += `
                    <div class="mb-1">
                        <i class="fe fe-check-circle text-success"></i> 
                        ${pkg.entry_quantity} entries - $${parseFloat(price).toFixed(2)}
                        ${pkg.custom_price ? '<span class="badge badge-warning badge-sm ml-1">Custom</span>' : ''}
                    </div>
                `;
            });
        }
        
        $('#preview_packages_list').html(html);
    }
    
    // Image Upload & Preview
    $('#image_input').on('change', function(e) {
        let file = e.target.files[0];
        if (file && (!/^image\/(jpeg|png|webp)$/.test(file.type) || file.size > 10 * 1024 * 1024)) {
            toastr.error('Image must be a JPG, PNG or WEBP under 10MB (this one is ' + (file.size / 1048576).toFixed(1) + 'MB, ' + (file.type || 'unknown type') + ').');
            this.value = '';
            return;
        }
        if (file) {
            let reader = new FileReader();
            reader.onload = function(e) {
                $('#image_preview').attr('src', e.target.result).show();
                $('#preview_image').attr('src', e.target.result);
                $('#preview_image_wrapper').show();
            };
            reader.readAsDataURL(file);
        }
    });
    
    // Drag & Drop
    $('.image-upload-area').on('dragover', function(e) {
        e.preventDefault();
        $(this).css('border-color', '#667eea');
    });
    
    $('.image-upload-area').on('dragleave', function(e) {
        e.preventDefault();
        $(this).css('border-color', '#dee2e6');
    });
    
    $('.image-upload-area').on('drop', function(e) {
        e.preventDefault();
        $(this).css('border-color', '#dee2e6');
        
        let files = e.originalEvent.dataTransfer.files;
        if (files.length > 0) {
            $('#image_input')[0].files = files;
            $('#image_input').trigger('change');
        }
    });
    
    // Live Preview Updates
    function updatePreview() {
        $('#preview_prize_name').text($('#prize_name').val() || 'Prize Name');
        $('#preview_description').text($('#description').val() || 'Prize description will appear here...');
        $('#preview_prize_value').text('$' + ($('#prize_value').val() || '0.00'));
        
        let startDate = $('#start_date').val();
        if (startDate) {
            $('#preview_start_date').text(new Date(startDate).toLocaleDateString());
        } else {
            $('#preview_start_date').text('-');
        }
        
        let drawDate = $('#draw_date').val();
        if (drawDate) {
            $('#preview_draw_date').text(new Date(drawDate).toLocaleDateString());
        } else {
            $('#preview_draw_date').text('-');
        }
        
        let status = $('#status option:selected').text();
        $('#preview_status').text(status);
        
        let statusClass = 'badge-success';
        if (status.includes('Draft')) statusClass = 'badge-secondary';
        if (status.includes('Scheduled')) statusClass = 'badge-info';
        $('#preview_status').removeClass('badge-success badge-secondary badge-info').addClass(statusClass);
    }
    
    // Bind preview updates
    $('#prize_name, #description, #prize_value, #start_date, #draw_date, #status').on('input change', updatePreview);
    
    // Set minimum dates
    let today = new Date().toISOString().split('T')[0];
    $('#start_date').attr('min', today);
    $('#draw_date').attr('min', today);
    
    // Auto-set times if empty
    if (!$('#start_time').val()) {
        $('#start_time').val('00:00');
    }
    if (!$('#draw_time').val()) {
        $('#draw_time').val('20:00');
    }
    
    // Form Validation
    // Which button was pressed (a disabled button's name/value isn't sent, so use a hidden field).
    $('#createGiveawayForm button[type="submit"]').on('click', function() {
        $('#form_action').val($(this).data('action') || 'create');
    });

    $('#createGiveawayForm').on('submit', function(e) {
        let prizeName = $('#prize_name').val();
        let prizeValue = $('#prize_value').val();
        let startDate = $('#start_date').val();
        let drawDate = $('#draw_date').val();
        let isDraft = $('#form_action').val() === 'draft';

        if (isDraft) {
            if (!prizeName) {
                e.preventDefault();
                toastr.error('Give the draft a prize name');
                return false;
            }
            $('#status').val('draft');
            $(this).find('button[type="submit"]').prop('disabled', true);
            return true;
        }
        
        if (!prizeName || !prizeValue || !startDate || !drawDate) {
            e.preventDefault();
            toastr.error('Please fill in all required fields');
            return false;
        }
        
        if (new Date(startDate) > new Date(drawDate)) {
            e.preventDefault();
            toastr.error('Draw date must be after start date');
            return false;
        }
        
        // Show loading
        let submitBtn = $(this).find('button[type="submit"]');
        submitBtn.prop('disabled', true).html('<i class="fe fe-loader"></i> Creating...');
        
        // Allow form to submit
        console.log('✅ Form validation passed - submitting');
        return true;
    });
    
    // Initial preview
    updatePreview();
});
</script>
@endpush