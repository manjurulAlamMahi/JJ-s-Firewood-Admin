@php
    $campaign = $campaign ?? null;
    $channels = old('channels', $campaign->channels ?? []);
    $weatherCondition = old('weather_condition', $campaign->weather_condition ?? []);
    $activeDays = old('active_days', $campaign->active_days ?? []);
    $weekdays = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];
    $conditionOptions = ['Rain', 'Thunderstorm', 'Drizzle', 'Snow', 'Clouds', 'Clear'];
    
    $channelContent = old('channel_content', $campaign->channel_content ?? []);
    // Migrate legacy fields to channel_content if empty
    if (empty($channelContent) && $campaign) {
        if (!empty($campaign->title) || !empty($campaign->message) || !empty($campaign->banner_image)) {
            $channelContent['banner'] = [
                'heading' => $campaign->title,
                'message' => $campaign->message,
                'image_url' => $campaign->banner_image,
                'cta_text' => $campaign->banner_cta_text,
                'cta_link' => $campaign->banner_cta_link,
            ];
        }
    }

    $targetSegments = $campaign->target_segments ?? null;
    if (empty($targetSegments) && !empty($campaign->target_zip_group_id)) {
        $targetSegments = [
            'condition' => 'AND',
            'rules' => [
                [
                    'type' => 'zip_groups',
                    'value' => [(string) $campaign->target_zip_group_id]
                ]
            ]
        ];
    }
@endphp

<style>
    .campaign-section {
        background: #f8f9fa;
        border: 1px solid #e9ecef;
        border-radius: 8px;
        padding: 24px;
        margin-bottom: 24px;
    }
    .campaign-section-title {
        color: #495057;
        font-weight: 600;
        margin-bottom: 20px;
        font-size: 1.1rem;
        border-bottom: 2px solid #e9ecef;
        padding-bottom: 10px;
    }
    .rule-box {
        background: #ffffff;
        box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        border: 1px solid #dee2e6;
        transition: all 0.2s;
    }
    .rule-box:hover {
        border-color: #adb5bd;
    }
    .group-box {
        background: #fdfdfd;
        border: 1px dashed #ced4da;
    }
    .form-label {
        font-weight: 500;
        color: #495057;
    }
</style>

<input type="hidden" name="target_segments" id="target_segments_input" value="{{ old('target_segments', json_encode($targetSegments)) }}">
<input type="hidden" name="exclusions" id="exclusions_input" value="{{ old('exclusions', json_encode($campaign->exclusions ?? null)) }}">

<div class="row">

    <!-- Campaign Basics -->
    <div class="col-md-12">
        <div class="campaign-section">
            <h5 class="campaign-section-title"><i class="fa fa-info-circle me-2 text-primary"></i> 1. Campaign Basics</h5>
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Campaign Name <span class="text-danger">*</span></label>
                    <input type="text" name="name" class="form-control"
                        value="{{ old('name', $campaign->name ?? '') }}" placeholder="e.g. Cold Weekend Firewood Push" required>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Campaign Type</label>
                    <select name="campaign_type" class="form-select">
                        <option value="automated" {{ old('campaign_type', $campaign->campaign_type ?? 'automated') === 'automated' ? 'selected' : '' }}>Automated (Weather-triggered)</option>
                        <option value="one_off" {{ old('campaign_type', $campaign->campaign_type ?? '') === 'one_off' ? 'selected' : '' }}>One-off (Manual trigger)</option>
                    </select>
                </div>
                <div class="col-md-12 mt-2">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="requires_approval" value="1"
                            id="requires_approval" {{ old('requires_approval', $campaign->requires_approval ?? false) ? 'checked' : '' }}>
                        <label class="form-check-label" for="requires_approval">
                            Requires manual approval before sending
                        </label>
                        <small class="d-block text-muted">If checked, the campaign will generate a draft send that an admin must approve.</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Audience Targeting -->
    <div class="col-md-12">
        <div class="campaign-section">
            <h5 class="campaign-section-title"><i class="fa fa-users me-2 text-success"></i> 2. Audience Targeting</h5>
            <div id="audience-builder-container" class="mb-3">
                <!-- Rendered via jQuery -->
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-sm btn-primary add-rule" data-path="rules"><i class="fa fa-plus me-1"></i> Add Rule</button>
                <button type="button" class="btn btn-sm btn-outline-secondary add-group" data-path="rules"><i class="fa fa-layer-group me-1"></i> Add Group (AND/OR)</button>
            </div>
        </div>
    </div>

    <!-- Exclusions -->
    <div class="col-md-12">
        <div class="campaign-section">
            <h5 class="campaign-section-title"><i class="fa fa-user-times me-2 text-danger"></i> 3. Exclusions</h5>
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="border p-3 rounded bg-white h-100 d-flex align-items-center">
                        <div class="form-check form-switch mb-0 ps-5">
                            <input class="form-check-input exclusion-checkbox fs-5" style="margin-left: -2.5rem;" type="checkbox" value="has_current_order" id="excl_has_current_order">
                            <label class="form-check-label ms-2 mt-1" for="excl_has_current_order">Exclude users with an active order</label>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="border p-3 rounded bg-white h-100 d-flex align-items-center">
                        <div class="form-check form-switch mb-0 ps-5">
                            <input class="form-check-input exclusion-checkbox fs-5" style="margin-left: -2.5rem;" type="checkbox" value="marketing_permissions" id="excl_marketing_permissions">
                            <label class="form-check-label ms-2 mt-1" for="excl_marketing_permissions">Exclude marketing opt-outs</label>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="border p-3 rounded bg-white h-100 d-flex flex-column justify-content-center">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <div class="form-check form-switch mb-0 ps-5">
                                <input class="form-check-input exclusion-checkbox fs-5" style="margin-left: -2.5rem;" type="checkbox" value="recent_order_days" id="excl_recent_order_days">
                                <label class="form-check-label ms-2 mt-1" for="excl_recent_order_days">Exclude if ordered in last</label>
                            </div>
                            <div class="input-group input-group-sm w-auto mt-2 mt-sm-0">
                                <input type="number" class="form-control exclusion-input" style="width: 70px;" data-key="recent_order_days" placeholder="0">
                                <span class="input-group-text">days</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="border p-3 rounded bg-white h-100 d-flex flex-column justify-content-center">
                        <div class="d-flex flex-wrap align-items-center gap-2">
                            <div class="form-check form-switch mb-0 ps-5">
                                <input class="form-check-input exclusion-checkbox fs-5" style="margin-left: -2.5rem;" type="checkbox" value="recent_promo_days" id="excl_recent_promo_days">
                                <label class="form-check-label ms-2 mt-1" for="excl_recent_promo_days">Exclude if received promo in last</label>
                            </div>
                            <div class="input-group input-group-sm w-auto mt-2 mt-sm-0">
                                <input type="number" class="form-control exclusion-input" style="width: 70px;" data-key="recent_promo_days" placeholder="0">
                                <span class="input-group-text">days</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Weather Conditions -->
    <div class="col-md-12">
        <div class="campaign-section">
            <h5 class="campaign-section-title"><i class="fa fa-cloud-sun-rain me-2 text-info"></i> 4. Weather Conditions</h5>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <label class="form-label">Max Temp (&deg;C)</label>
                    <input type="number" step="0.1" name="temp_max_c" class="form-control"
                        value="{{ old('temp_max_c', $weatherCondition['temp_max_c'] ?? '') }}" placeholder="e.g. 15">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Min Rain Probability (%)</label>
                    <input type="number" step="1" min="0" max="100" name="rain_probability_min" class="form-control"
                        value="{{ old('rain_probability_min', $weatherCondition['rain_probability_min'] ?? '') }}" placeholder="e.g. 80">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Expected Rainfall Min (mm)</label>
                    <input type="number" step="0.1" min="0" name="expected_rainfall_min_mm" class="form-control"
                        value="{{ old('expected_rainfall_min_mm', $weatherCondition['expected_rainfall_min_mm'] ?? '') }}" placeholder="e.g. 5.0">
                </div>

                <div class="col-md-12 mb-3">
                    <label class="form-label d-block">Condition Codes</label>
                    <div class="btn-group" role="group">
                        @php $selectedConditions = old('condition_codes', $weatherCondition['condition_codes'] ?? []); @endphp
                        @foreach ($conditionOptions as $code)
                            <input type="checkbox" class="btn-check" name="condition_codes[]" value="{{ $code }}"
                                id="condition_{{ $code }}" autocomplete="off" {{ in_array($code, $selectedConditions) ? 'checked' : '' }}>
                            <label class="btn btn-outline-secondary" for="condition_{{ $code }}">{{ $code }}</label>
                        @endforeach
                    </div>
                </div>

                <div class="col-md-4 mb-3">
                    <label class="form-label">Condition Logic</label>
                    <select name="condition_logic" class="form-select">
                        <option value="AND" {{ ($weatherCondition['condition_logic'] ?? 'AND') === 'AND' ? 'selected' : '' }}>Match ALL selected criteria</option>
                        <option value="OR" {{ ($weatherCondition['condition_logic'] ?? 'AND') === 'OR' ? 'selected' : '' }}>Match ANY selected criteria</option>
                    </select>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Forecast Days Out</label>
                    <div class="input-group">
                        <input type="number" min="1" name="forecast_days_out" class="form-control"
                            value="{{ old('forecast_days_out', $weatherCondition['forecast_days_out'] ?? '3') }}">
                        <span class="input-group-text">days</span>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Required Matching Days</label>
                    <div class="input-group">
                        <input type="number" min="1" name="required_matching_days" class="form-control"
                            value="{{ old('required_matching_days', $weatherCondition['required_matching_days'] ?? '1') }}">
                        <span class="input-group-text">days</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scheduling -->
    <div class="col-md-12">
        <div class="campaign-section">
            <h5 class="campaign-section-title"><i class="fa fa-calendar-alt me-2 text-warning"></i> 5. Scheduling & Delivery</h5>
            <div class="row">
                <div class="col-md-12 mb-3">
                    <label class="form-label d-block">Permitted Sending Days</label>
                    <div class="btn-group" role="group">
                        @foreach ($weekdays as $iso => $label)
                            <input type="checkbox" class="btn-check" name="active_days[]" value="{{ $iso }}"
                                id="day_{{ $iso }}" autocomplete="off" {{ in_array($iso, $activeDays) ? 'checked' : '' }}>
                            <label class="btn btn-outline-secondary" for="day_{{ $iso }}">{{ $label }}</label>
                        @endforeach
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Lead Time</label>
                    <div class="input-group">
                        <input type="number" min="0" name="lead_time_hours" class="form-control"
                            value="{{ old('lead_time_hours', $campaign->lead_time_hours ?? 24) }}">
                        <span class="input-group-text">hours</span>
                    </div>
                    <small class="text-muted d-block mt-1">Hours before weather event to send.</small>
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Sending Time Start</label>
                    <input type="time" name="sending_time_start" class="form-control"
                        value="{{ old('sending_time_start', ($campaign->sending_time_start ?? null) ? substr($campaign->sending_time_start, 0, 5) : '') }}">
                </div>
                <div class="col-md-4 mb-3">
                    <label class="form-label">Sending Time End</label>
                    <input type="time" name="sending_time_end" class="form-control"
                        value="{{ old('sending_time_end', ($campaign->sending_time_end ?? null) ? substr($campaign->sending_time_end, 0, 5) : '') }}">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Active From Date</label>
                    <input type="date" name="active_from" class="form-control"
                        value="{{ old('active_from', optional($campaign->active_from ?? null)->format('Y-m-d')) }}">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Active Until Date</label>
                    <input type="date" name="active_until" class="form-control"
                        value="{{ old('active_until', optional($campaign->active_until ?? null)->format('Y-m-d')) }}">
                </div>
            </div>
        </div>
    </div>

    <!-- Channel Content -->
    <div class="col-md-12">
        <div class="campaign-section">
            <h5 class="campaign-section-title"><i class="fa fa-bullhorn me-2 text-secondary"></i> 6. Channel Content</h5>
            <div class="mb-4">
                <label class="form-label fw-bold me-3">Enabled Channels:</label>
                @foreach (['push' => 'Push Notification', 'email' => 'Email', 'banner' => 'App Banner'] as $value => $label)
                    <div class="form-check form-check-inline form-switch">
                        <input class="form-check-input channel-toggle" type="checkbox" name="channels[]" value="{{ $value }}"
                            id="channel_{{ $value }}" {{ in_array($value, $channels) ? 'checked' : '' }}>
                        <label class="form-check-label" for="channel_{{ $value }}">{{ $label }}</label>
                    </div>
                @endforeach
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-header bg-white border-bottom-0 pt-3 pb-0">
                    <ul class="nav nav-tabs" id="channelTabs" role="tablist">
                        <li class="nav-item" role="presentation" id="tab-push" style="display: none;">
                            <button class="nav-link active fw-bold" data-bs-toggle="tab" data-bs-target="#content-push" type="button" role="tab">📱 Push Notification</button>
                        </li>
                        <li class="nav-item" role="presentation" id="tab-email" style="display: none;">
                            <button class="nav-link fw-bold" data-bs-toggle="tab" data-bs-target="#content-email" type="button" role="tab">✉️ Email</button>
                        </li>
                        <li class="nav-item" role="presentation" id="tab-banner" style="display: none;">
                            <button class="nav-link fw-bold" data-bs-toggle="tab" data-bs-target="#content-banner" type="button" role="tab">🖼️ App Banner</button>
                        </li>
                    </ul>
                </div>
                <div class="card-body bg-white border border-top-0 rounded-bottom">
                    <div class="tab-content" id="channelTabsContent">
                        <!-- Push Tab -->
                        <div class="tab-pane fade show active" id="content-push" role="tabpanel">
                            <div class="mb-3">
                                <label class="form-label">Push Title</label>
                                <input type="text" name="channel_content[push][title]" class="form-control" value="{{ old('channel_content.push.title', $channelContent['push']['title'] ?? '') }}" placeholder="e.g. Free delivery this weekend!">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Push Body <span class="badge bg-light text-dark">Supports @{{first_name}}</span></label>
                                <textarea name="channel_content[push][body]" class="form-control" rows="3" placeholder="e.g. Hey @{{first_name}}, it's going to rain! Order now.">{{ old('channel_content.push.body', $channelContent['push']['body'] ?? '') }}</textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Deep Link</label>
                                <input type="text" name="channel_content[push][deep_link]" class="form-control" value="{{ old('channel_content.push.deep_link', $channelContent['push']['deep_link'] ?? '') }}" placeholder="e.g. cart (Leave blank for default Campaign screen)">
                            </div>
                        </div>
                        <!-- Email Tab -->
                        <div class="tab-pane fade" id="content-email" role="tabpanel">
                            <div class="mb-3">
                                <label class="form-label">Email Subject</label>
                                <input type="text" name="channel_content[email][subject]" class="form-control" value="{{ old('channel_content.email.subject', $channelContent['email']['subject'] ?? '') }}">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Preview Text</label>
                                <input type="text" name="channel_content[email][preview_text]" class="form-control" value="{{ old('channel_content.email.preview_text', $channelContent['email']['preview_text'] ?? '') }}">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Email Body HTML <span class="badge bg-light text-dark">Supports @{{first_name}}</span></label>
                                <textarea name="channel_content[email][body_html]" class="form-control text-monospace" rows="6" style="font-family: monospace;">{{ old('channel_content.email.body_html', $channelContent['email']['body_html'] ?? '') }}</textarea>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Button Text</label>
                                    <input type="text" name="channel_content[email][button_text]" class="form-control" value="{{ old('channel_content.email.button_text', $channelContent['email']['button_text'] ?? '') }}">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Button Link</label>
                                    <input type="text" name="channel_content[email][button_link]" class="form-control" value="{{ old('channel_content.email.button_link', $channelContent['email']['button_link'] ?? '') }}">
                                </div>
                            </div>
                        </div>
                        <!-- Banner Tab -->
                        <div class="tab-pane fade" id="content-banner" role="tabpanel">
                            <div class="mb-3">
                                <label class="form-label">Banner Heading</label>
                                <input type="text" name="channel_content[banner][heading]" class="form-control" value="{{ old('channel_content.banner.heading', $channelContent['banner']['heading'] ?? '') }}">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Banner Message</label>
                                <textarea name="channel_content[banner][message]" class="form-control" rows="2">{{ old('channel_content.banner.message', $channelContent['banner']['message'] ?? '') }}</textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Banner Image URL</label>
                                <input type="url" name="channel_content[banner][image_url]" class="form-control" value="{{ old('channel_content.banner.image_url', $channelContent['banner']['image_url'] ?? '') }}">
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">CTA Text</label>
                                    <input type="text" name="channel_content[banner][cta_text]" class="form-control" value="{{ old('channel_content.banner.cta_text', $channelContent['banner']['cta_text'] ?? '') }}">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">CTA Link</label>
                                    <input type="text" name="channel_content[banner][cta_link]" class="form-control" value="{{ old('channel_content.banner.cta_link', $channelContent['banner']['cta_link'] ?? '') }}">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
    // Handle Channels Tabs Visibility
    function updateChannelTabs() {
        let firstVisible = null;
        $('.channel-toggle').each(function() {
            let val = $(this).val();
            let tab = $('#tab-' + val);
            if ($(this).is(':checked')) {
                tab.show();
                if (!firstVisible) firstVisible = val;
            } else {
                tab.hide();
            }
        });

        let activeTab = $('#channelTabs .nav-link.active').parent().attr('id');
        if ((!activeTab || $('#' + activeTab).css('display') === 'none') && firstVisible) {
            $('#tab-' + firstVisible + ' .nav-link').get(0).click();
        }
    }

    $('.channel-toggle').change(updateChannelTabs);
    updateChannelTabs();

    // jQuery Rule Builder for Audience
    let audienceData = { condition: 'AND', rules: [] };

    try {
        let val = $('#target_segments_input').val();
        if (val && val !== 'null' && val !== '""') {
            let parsed = JSON.parse(val);
            if (parsed && typeof parsed === 'object') {
                audienceData = parsed;
            }
        }
    } catch (e) {
        console.error("Failed to parse audienceData:", e);
    }

    if (!audienceData) audienceData = { condition: 'AND', rules: [] };
    if (!audienceData.condition) audienceData.condition = 'AND';
    if (!audienceData.rules || !Array.isArray(audienceData.rules)) audienceData.rules = [];

    function renderRule(rule, index, path) {
        let options = `
            <option value="all_marketable" ${rule.type === 'all_marketable' ? 'selected' : ''}>All Marketable Customers</option>
            <option value="delivery_customer" ${rule.type === 'delivery_customer' ? 'selected' : ''}>Delivery Customer</option>
            <option value="min_orders" ${rule.type === 'min_orders' ? 'selected' : ''}>Repeat Customer (Min Orders)</option>
            <option value="previous_winter_customer" ${rule.type === 'previous_winter_customer' ? 'selected' : ''}>Previous Winter</option>
            <option value="no_order_days" ${rule.type === 'no_order_days' ? 'selected' : ''}>No Order in X Days</option>
            <option value="zip_groups" ${rule.type === 'zip_groups' ? 'selected' : ''}>Zip Group(s)</option>
            <option value="postcodes" ${rule.type === 'postcodes' ? 'selected' : ''}>Specific Postcodes</option>
        `;

        let valueField = '';
        if (rule.type === 'min_orders' || rule.type === 'no_order_days') {
            valueField = `<input type="number" class="form-control form-control-sm ms-2 w-auto rule-value" data-path="${path}" value="${rule.value || ''}" placeholder="Enter number">`;
        } else if (rule.type === 'postcodes') {
            valueField = `<input type="text" class="form-control form-control-sm ms-2 w-auto rule-value" data-path="${path}" value="${(rule.value || []).join(', ')}" placeholder="Comma separated">`;
        } else if (rule.type === 'zip_groups') {
            valueField = `<input type="text" class="form-control form-control-sm ms-2 w-auto rule-value" data-path="${path}" value="${(rule.value || []).join(', ')}" placeholder="Zip Group IDs (comma sep)">`;
        }

        return `
            <div class="d-flex flex-wrap mb-2 align-items-center p-2 rounded gap-2 rule-box">
                <select class="form-select form-select-sm rule-type fw-bold text-secondary" style="max-width: 260px; min-width: 180px;" data-path="${path}">
                    ${options}
                </select>
                ${valueField}
                <button type="button" class="btn btn-sm btn-outline-danger ms-auto delete-rule" data-path="${path}" title="Remove Rule"><i class="fa fa-trash"></i></button>
            </div>
        `;
    }

    function renderGroup(group, path) {
        let html = `
            <div class="p-3 mb-3 rounded group-box">
                <div class="d-flex mb-3 align-items-center border-bottom pb-2">
                    <span class="me-2 text-muted fw-bold small text-uppercase">Match</span>
                    <select class="form-select form-select-sm w-auto group-logic fw-bold text-primary" data-path="${path}">
                        <option value="AND" ${group.condition === 'AND' ? 'selected' : ''}>ALL of the following (AND)</option>
                        <option value="OR" ${group.condition === 'OR' ? 'selected' : ''}>ANY of the following (OR)</option>
                    </select>
                    ${path !== '' ? `<button type="button" class="btn btn-sm btn-outline-danger ms-auto delete-rule" data-path="${path}"><i class="fa fa-times"></i> Remove Group</button>` : ''}
                </div>
                <div class="ms-1 ms-md-4 border-start border-2 border-primary ps-3 pt-1">
        `;

        (group.rules || []).forEach((rule, index) => {
            let childPath = path ? `${path}.rules.${index}` : `rules.${index}`;
            if (rule.condition) {
                html += renderGroup(rule, childPath);
            } else {
                html += renderRule(rule, index, childPath);
            }
        });

        if ((group.rules || []).length === 0) {
            html += `<div class="text-muted small fst-italic mb-3">No rules added yet.</div>`;
        }

        let addPath = path ? `${path}.rules` : 'rules';
        html += `
                </div>
                <div class="ms-1 ms-md-4 ps-3 mt-3">
                    <button type="button" class="btn btn-sm btn-primary add-rule" data-path="${addPath}"><i class="fa fa-plus me-1"></i> Add Rule</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary add-group" data-path="${addPath}"><i class="fa fa-layer-group me-1"></i> Add Sub-group</button>
                </div>
            </div>
        `;
        return html;
    }

    function renderAudience() {
        if (!audienceData.rules) audienceData.rules = [];
        $('#audience-builder-container').html(renderGroup(audienceData, ''));
        $('#target_segments_input').val(JSON.stringify(audienceData));
    }

    function getObjPath(obj, path) {
        if (!path) return obj;
        return path.split('.').reduce((o, i) => o[i], obj);
    }

    $(document).on('click', '.add-rule', function() {
        let path = $(this).data('path');
        let rulesArr = getObjPath(audienceData, path);
        if (!rulesArr) {
            let parentPath = path.split('.').slice(0, -1).join('.');
            let parentObj = getObjPath(audienceData, parentPath);
            parentObj.rules = [];
            rulesArr = parentObj.rules;
        }
        rulesArr.push({ type: 'delivery_customer' });
        renderAudience();
    });

    $(document).on('click', '.add-group', function() {
        let path = $(this).data('path');
        let rulesArr = getObjPath(audienceData, path);
        if (!rulesArr) {
            let parentPath = path.split('.').slice(0, -1).join('.');
            let parentObj = getObjPath(audienceData, parentPath);
            parentObj.rules = [];
            rulesArr = parentObj.rules;
        }
        rulesArr.push({ condition: 'AND', rules: [] });
        renderAudience();
    });

    $(document).on('click', '.delete-rule', function() {
        let path = $(this).data('path');
        let parts = path.split('.');
        let index = parts.pop();
        let parentPath = parts.join('.');
        let parent = getObjPath(audienceData, parentPath);
        parent.splice(index, 1);
        renderAudience();
    });

    $(document).on('change', '.rule-type', function() {
        let path = $(this).data('path');
        let rule = getObjPath(audienceData, path);
        rule.type = $(this).val();

        if (rule.type === 'min_orders') rule.value = 3;
        else if (rule.type === 'no_order_days') rule.value = 30;
        else delete rule.value;

        renderAudience();
    });

    $(document).on('change', '.group-logic', function() {
        let path = $(this).data('path');
        let obj = path ? getObjPath(audienceData, path) : audienceData;
        obj.condition = $(this).val();
        renderAudience();
    });

    $(document).on('change', '.rule-value', function() {
        let path = $(this).data('path');
        let rule = getObjPath(audienceData, path);
        let val = $(this).val();

        if (rule.type === 'min_orders' || rule.type === 'no_order_days') {
            rule.value = parseInt(val) || 0;
        } else if (rule.type === 'postcodes' || rule.type === 'zip_groups') {
            rule.value = val.split(',').map(s => s.trim()).filter(s => s !== '');
            if (rule.type === 'zip_groups') {
                rule.value = rule.value.map(n => parseInt(n) || n);
            }
        }
        $('#target_segments_input').val(JSON.stringify(audienceData));
    });

    renderAudience();

    // Handle Exclusions
    let exclusionsData = {};
    try {
        let val = $('#exclusions_input').val();
        if (val) exclusionsData = JSON.parse(val);
    } catch (e) {}

    function updateExclusionsUI() {
        $('.exclusion-checkbox').each(function() {
            let key = $(this).val();
            $(this).prop('checked', !!exclusionsData[key]);
        });
        $('.exclusion-input').each(function() {
            let key = $(this).data('key');
            $(this).val(exclusionsData[key] || '');
        });
    }

    function saveExclusions() {
        exclusionsData = {};
        $('.exclusion-checkbox:checked').each(function() {
            let key = $(this).val();
            if (key === 'recent_order_days' || key === 'recent_promo_days') {
                let inputVal = $(`.exclusion-input[data-key="${key}"]`).val();
                if (inputVal) exclusionsData[key] = parseInt(inputVal);
            } else {
                exclusionsData[key] = true;
            }
        });
        $('#exclusions_input').val(JSON.stringify(exclusionsData));
    }

    $(document).on('change', '.exclusion-checkbox, .exclusion-input', saveExclusions);
    updateExclusionsUI();

</script>
@endpush
