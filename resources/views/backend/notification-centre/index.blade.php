@extends('backend.master')

@section('title', 'Notification Centre')

@push('styles')
<style>
    /* === Notification Centre layout === */
    .nc-header {
        background: var(--av-surface);
        color: var(--av-text);
        padding: 1.25rem;
        border-radius: var(--av-r);
        margin-bottom: 1.25rem;
        border: 1px solid var(--av-border);
    }
    .nc-header h2 { color: var(--av-text); margin:0; font-weight:600; }
    .nc-header p { color: var(--av-muted); margin:.25rem 0 0 0; font-size:.9rem; }

    /* Channel tabs (top of page) */
    .nc-channel-tabs {
        display: flex; gap: .5rem; flex-wrap: nowrap;
        border-bottom: 1px solid var(--av-border);
        margin-bottom: 1.25rem; padding-bottom: 0;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
    }
    .nc-channel-tabs::-webkit-scrollbar { display: none; }
    .nc-channel-tab {
        padding: .75rem 1rem;
        border: none; background: transparent;
        color: var(--av-muted); font-weight: 500;
        border-bottom: 2px solid transparent;
        margin-bottom: -1px;
        cursor: pointer; transition: color .15s, border-color .15s;
        white-space: nowrap;
    }
    .nc-channel-tab:hover { color: var(--av-primary); }
    .nc-channel-tab.active {
        color: var(--av-primary); border-bottom-color: var(--av-primary);
    }
    .nc-channel-tab .badge {
        font-size: .7rem; margin-left: .35rem;
    }

    /* Sub-views inside each channel: Compose / Drafts / Scheduled / Sent / Templates / Suppressions */
    .nc-subnav {
        display: flex; gap: .25rem; flex-wrap: wrap; margin-bottom: 1.25rem;
    }
    .nc-subnav-btn {
        padding: .45rem .85rem;
        border: 1px solid var(--av-border); background: var(--av-surface);
        color: var(--av-muted); font-size: .875rem; font-weight: 500;
        border-radius: 8px; cursor: pointer; transition: all .15s;
    }
    .nc-subnav-btn:hover { background: var(--av-surface-2); }
    .nc-subnav-btn.active {
        background: var(--av-primary); color: #fff; border-color: var(--av-primary);
    }

    /* Section card pattern */
    .nc-card {
        background: var(--av-surface); border: 1px solid var(--av-border);
        border-radius: var(--av-r); padding: 1.25rem; margin-bottom: 1rem;
    }
    .nc-card-title {
        font-size: .75rem; text-transform: uppercase; letter-spacing: .05em;
        color: #6b7280; font-weight: 600;
        margin-bottom: 1rem; padding-bottom: .5rem;
        border-bottom: 1px solid #f3f4f6;
    }

    /* Audience method blocks (Filter / Hand-pick / Raw & CSV) */
    .nc-method-block {
        background: var(--av-surface-2);
        border: 1px solid var(--av-border);
        border-radius: 10px;
        padding: 1rem;
        margin-bottom: 0.75rem;
    }
    .nc-method-block:last-child { margin-bottom: 0; }
    .nc-method-header {
        display: flex; gap: 0.85rem; align-items: flex-start;
        margin-bottom: 1rem;
    }
    .nc-method-num {
        flex-shrink: 0;
        width: 28px; height: 28px;
        background: #667eea;
        color: #fff;
        border-radius: 50%;
        display: inline-flex; align-items: center; justify-content: center;
        font-weight: 600; font-size: .85rem;
    }
    .nc-method-title {
        font-weight: 600; color: #111827; font-size: .95rem;
    }
    .nc-method-desc {
        font-size: .82rem; color: #6b7280;
    }

    /* Composer layout: 2 cols on desktop */
    .nc-composer {
        display: grid; gap: 1rem;
        grid-template-columns: 1fr;
    }
    @media (min-width: 992px) {
        .nc-composer { grid-template-columns: 1.6fr 1fr; }
    }

    /* Audience rule chips */
    .nc-rule-chip {
        display: inline-flex; align-items: center; gap: .4rem;
        padding: .35rem .75rem; border-radius: 20px;
        background: #eef2ff; color: #4338ca; font-size: .8rem;
        margin: .15rem; border: 1px solid #c7d2fe;
    }
    .nc-rule-chip.exclude {
        background: #fee2e2; color: #991b1b; border-color: #fecaca;
    }
    .nc-rule-chip .x {
        cursor: pointer; font-weight: bold; opacity: .7;
    }
    .nc-rule-chip .x:hover { opacity: 1; }

    /* Audience estimate banner */
    .nc-est-banner {
        background: #f0fdf4; border: 1px solid #86efac;
        border-radius: 8px; padding: .85rem 1rem;
        font-size: .9rem; color: #166534;
    }
    .nc-est-banner.warn {
        background: #fef3c7; border-color: #fcd34d; color: #92400e;
    }
    .nc-est-banner.danger {
        background: #fee2e2; border-color: #fca5a5; color: #991b1b;
    }
    .nc-est-banner strong { font-size: 1.05rem; }

    /* Status pills */
    .nc-status {
        display: inline-block; padding: .2rem .6rem;
        border-radius: 12px; font-size: .72rem; font-weight: 600;
        text-transform: uppercase; letter-spacing: .03em;
    }
    .nc-status-draft     { background: #f3f4f6; color: #374151; }
    .nc-status-scheduled { background: #dbeafe; color: #1e40af; }
    .nc-status-running   { background: #fef3c7; color: #92400e; }
    .nc-status-paused    { background: #fde68a; color: #78350f; }
    .nc-status-sent      { background: #d1fae5; color: #065f46; }
    .nc-status-failed    { background: #fee2e2; color: #991b1b; }
    .nc-status-cancelled { background: #e5e7eb; color: #6b7280; }

    /* Recipient picker chips */
    .nc-recipient-chip {
        display: inline-flex; align-items: center; gap: .4rem;
        background: #f3f4f6; border: 1px solid #e5e7eb;
        border-radius: 18px; padding: .25rem .65rem;
        font-size: .8rem; margin: .15rem;
    }
    .nc-recipient-chip .x {
        cursor: pointer; opacity: .6; font-weight: bold;
    }
    .nc-recipient-chip .x:hover { opacity: 1; }

    /* SMS character counter */
    .nc-sms-meter {
        display: flex; gap: 1rem; align-items: center;
        margin-top: .5rem; font-size: .82rem;
    }
    .nc-sms-meter .seg-pill {
        background: #eef2ff; color: #4338ca;
        padding: .2rem .55rem; border-radius: 12px; font-weight: 600;
    }
    .nc-sms-meter .seg-pill.warn { background: #fef3c7; color: #92400e; }
    .nc-sms-meter .seg-pill.danger { background: #fee2e2; color: #991b1b; }

    /* GSM-7 character fixer panel */
    .nc-gsm7-fixer {
        background: #fef3c7;
        border: 1px solid #fcd34d;
        border-radius: 8px;
        padding: .75rem 1rem;
        font-size: .85rem;
    }
    .nc-gsm7-fixer .nc-gsm7-title {
        font-weight: 600; color: #92400e;
        margin-bottom: .5rem;
    }
    .nc-gsm7-fixer ul {
        list-style: none; padding-left: 0; margin: .25rem 0 .5rem;
    }
    .nc-gsm7-fixer ul li {
        padding: .15rem 0; color: #78350f;
    }
    .nc-gsm7-fixer code {
        background: #fff; border: 1px solid #fbbf24;
        padding: .05rem .35rem; border-radius: 4px;
        color: #92400e; font-size: .9em;
    }
    .nc-gsm7-fixer .nc-gsm7-bad-char {
        background: #fde68a; color: #7c2d12;
        font-weight: 700; font-family: monospace;
        padding: .05rem .4rem; border-radius: 4px;
        font-size: 1rem; min-width: 1.5rem;
        display: inline-block; text-align: center;
    }

    /* Empty state */
    .nc-empty {
        text-align: center; padding: 3rem 1rem;
        color: #9ca3af;
    }
    .nc-empty i { font-size: 2.5rem; margin-bottom: 1rem; }

    /* Live recipient suggestions */
    .nc-search-results {
        position: absolute; top: 100%; left: 0; right: 0;
        background: var(--av-surface); border: 1px solid var(--av-border);
        border-radius: 0 0 8px 8px; box-shadow: 0 4px 12px rgba(0,0,0,.08);
        max-height: 280px; overflow-y: auto; z-index: 1000;
        max-width: 100vw;
    }
    .nc-search-result {
        padding: .65rem .85rem; cursor: pointer;
        border-bottom: 1px solid #f3f4f6;
    }
    .nc-search-result:hover { background: #f9fafb; }
    .nc-search-result:last-child { border-bottom: none; }

    /* Modal touch-up */
    #nc-test-modal .modal-content,
    #nc-template-modal .modal-content,
    #nc-suppression-modal .modal-content {
        border-radius: 12px;
    }
</style>
@endpush

@section('body')

<div>
    <div class="nc-header d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h2><i class="fa fa-paper-plane me-2"></i> Notification Centre</h2>
            <p>Compose, schedule and send messages across push, SMS and email — with audience builder, opt-out compliance, and full send logs.</p>
        </div>
        <div>
            <button class="btn btn-light" id="nc-suppressions-link">
                <i class="fa fa-ban me-1"></i> Suppression List
            </button>
        </div>
    </div>

    {{-- Channel tabs --}}
    <ul class="nc-channel-tabs nav" id="nc-channel-tabs">
        <li><button class="nc-channel-tab active" data-channel="push" type="button">
            <i class="fa fa-bell me-1"></i> Push <span class="badge bg-light text-dark d-none" id="nc-count-push" title="Pending campaigns (drafts + scheduled)">0</span>
        </button></li>
        <li><button class="nc-channel-tab" data-channel="sms" type="button">
            <i class="fa fa-sms me-1"></i> SMS <span class="badge bg-light text-dark d-none" id="nc-count-sms" title="Pending campaigns (drafts + scheduled)">0</span>
        </button></li>
        <li><button class="nc-channel-tab" data-channel="email" type="button">
            <i class="fa fa-envelope me-1"></i> Email <span class="badge bg-light text-dark d-none" id="nc-count-email" title="Pending campaigns (drafts + scheduled)">0</span>
        </button></li>
    </ul>

    {{-- Sub-nav: Compose / Drafts / Scheduled / Sent / Templates --}}
    <div class="nc-subnav">
        <button class="nc-subnav-btn active" data-view="compose"><i class="fa fa-plus me-1"></i> Compose</button>
        <button class="nc-subnav-btn" data-view="drafts"><i class="fa fa-pencil-alt me-1"></i> Drafts</button>
        <button class="nc-subnav-btn" data-view="scheduled"><i class="fa fa-clock me-1"></i> Scheduled</button>
        <button class="nc-subnav-btn" data-view="sent"><i class="fa fa-check me-1"></i> Sent</button>
        <button class="nc-subnav-btn" data-view="templates"><i class="fa fa-bookmark me-1"></i> Templates</button>
    </div>

    {{-- ============= COMPOSE VIEW ============= --}}
    <div class="nc-view" data-view="compose">
        <div class="nc-composer">
            {{-- LEFT: content + audience --}}
            <div>
                {{-- Content card --}}
                <div class="nc-card">
                    <div class="nc-card-title">Content <span class="float-end text-primary text-lowercase fw-normal" id="nc-channel-label">push</span></div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Campaign Name <span class="text-muted small">(internal — not shown to recipients)</span></label>
                        <input type="text" class="form-control" id="nc-campaign-name" placeholder="e.g. Christmas Promo 2026">
                    </div>

                    <div class="mb-3" id="nc-title-wrap">
                        <label class="form-label fw-semibold" id="nc-title-label">Title</label>
                        <input type="text" class="form-control" id="nc-title" placeholder="Push title or email subject" maxlength="100">
                        <div class="form-text" id="nc-title-help">Max 100 characters. Keep it punchy.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold" id="nc-body-label">Message</label>
                        <textarea class="form-control" id="nc-body" rows="5" placeholder="Your message…"></textarea>

                        {{-- SMS counter (visible only on SMS channel) --}}
                        <div class="nc-sms-meter d-none" id="nc-sms-meter">
                            <span><span id="nc-sms-chars">0</span> chars</span>
                            <span>·</span>
                            <span class="seg-pill" id="nc-sms-segments">1 segment</span>
                            <span>·</span>
                            <span class="text-muted" id="nc-sms-cost">~$0.00 estimated cost</span>
                        </div>

                        {{-- GSM-7 character fixer (only visible when problematic chars detected) --}}
                        <div class="nc-gsm7-fixer d-none mt-2" id="nc-gsm7-fixer"></div>

                        <div class="form-text" id="nc-body-help">Plain text. Variables like <code>@{{first_name}}</code> get replaced when sent.</div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center">
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="nc-load-template-btn">
                            <i class="fa fa-bookmark me-1"></i> Load Template
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm" id="nc-save-template-btn">
                            <i class="fa fa-save me-1"></i> Save as Template
                        </button>
                    </div>
                </div>

                {{-- Audience builder card --}}
                <div class="nc-card">
                    <div class="nc-card-title">Audience</div>
                    <p class="text-muted small mb-3">Combine any of the three methods below — final reach is everyone matching your rules, plus anyone hand-picked, plus any raw numbers/emails you add.</p>

                    {{-- ====== METHOD 1: RULES ====== --}}
                    <div class="nc-method-block">
                        <div class="nc-method-header">
                            <span class="nc-method-num">1</span>
                            <div>
                                <div class="nc-method-title">Filter by Rules <span class="text-muted small fw-normal">(optional)</span></div>
                                <div class="nc-method-desc">Add a group of customers based on attributes (e.g. everyone in a suburb, or everyone who's placed an order). Skip this entirely if you only want to message hand-picked customers or raw recipients.</div>
                            </div>
                        </div>

                        {{-- Include --}}
                        <div class="mb-3">
                            <label class="form-label fw-semibold mb-2">Include</label>
                            <div id="nc-include-chips" class="mb-2">
                                <span class="text-muted small" id="nc-include-empty">No rules added — that's fine if you're using Methods 2 or 3 below. Add a rule here to include groups of customers.</span>
                            </div>
                            <div class="d-flex gap-2 flex-wrap">
                                <select class="form-select form-select-sm" id="nc-include-rule-type" style="max-width: 250px;">
                                    <option value="all_users">Everyone</option>
                                    <option value="suburb">Suburb…</option>
                                    <option value="state">State…</option>
                                    <option value="active_since">Active in last X days</option>
                                    <option value="has_orders">Has placed at least N orders</option>
                                    <option value="min_lifetime_spend">Lifetime spend ≥ $X</option>
                                    <option value="has_referral_code">Has referral code</option>
                                    <option value="never_ordered">Never ordered</option>
                                </select>
                                <input type="text" class="form-control form-control-sm d-none" id="nc-include-rule-value" style="max-width: 220px;">
                                <button type="button" class="btn btn-primary btn-sm" id="nc-add-include">Add</button>
                            </div>
                        </div>

                        {{-- Exclude --}}
                        <div class="mb-0">
                            <label class="form-label fw-semibold mb-2">Exclude <span class="text-muted small">(optional)</span></label>
                            <div id="nc-exclude-chips" class="mb-2">
                                <span class="text-muted small" id="nc-exclude-empty">No exclusions.</span>
                            </div>
                            <div class="d-flex gap-2 flex-wrap">
                                <select class="form-select form-select-sm" id="nc-exclude-rule-type" style="max-width: 250px;">
                                    <option value="suburb">Suburb…</option>
                                </select>
                                <input type="text" class="form-control form-control-sm" id="nc-exclude-rule-value" placeholder="e.g. Mandurah" style="max-width: 220px;">
                                <button type="button" class="btn btn-outline-danger btn-sm" id="nc-add-exclude">Add</button>
                            </div>
                        </div>
                    </div>

                    {{-- ====== METHOD 2: HAND-PICK ====== --}}
                    <div class="nc-method-block">
                        <div class="nc-method-header">
                            <span class="nc-method-num">2</span>
                            <div>
                                <div class="nc-method-title">Hand-pick Specific Customers</div>
                                <div class="nc-method-desc">Search by name, email, or phone — these people are added on top of any rule matches.</div>
                            </div>
                        </div>

                        <div class="position-relative">
                            <input type="text" class="form-control" id="nc-handpick-search" placeholder="Type at least 2 characters to search…" autocomplete="off">
                            <div class="nc-search-results d-none" id="nc-handpick-results"></div>
                        </div>
                        <div id="nc-handpick-chips" class="mt-2"></div>
                    </div>

                    {{-- ====== METHOD 3: RAW & CSV (SMS/Email only) ====== --}}
                    <div class="nc-method-block d-none" id="nc-raw-method">
                        <div class="nc-method-header">
                            <span class="nc-method-num">3</span>
                            <div>
                                <div class="nc-method-title">Add Raw Recipients <span class="text-muted small fw-normal">(non-customers)</span></div>
                                <div class="nc-method-desc" id="nc-raw-desc">Send to phone numbers or email addresses that aren't registered as customers.</div>
                            </div>
                        </div>

                        <div class="mb-3" id="nc-raw-wrap">
                            <label class="form-label fw-semibold mb-2">Type or paste recipients</label>
                            <textarea class="form-control" id="nc-raw-recipients" rows="3" placeholder="+61400123456, +61400789012"></textarea>
                            <div class="form-text">One per line or comma-separated. AU phone numbers will be normalised to E.164.</div>
                        </div>

                        <div class="mb-0" id="nc-csv-wrap">
                            <label class="form-label fw-semibold mb-2">…or upload from CSV</label>
                            <input type="file" class="form-control" id="nc-csv-file" accept=".csv,.txt">
                            <div class="form-text">CSV with one phone number or email per row. Adds to the textarea above.</div>
                        </div>
                    </div>
                </div>

                {{-- Compliance --}}
                <div class="nc-card">
                    <div class="nc-card-title">Compliance</div>
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" role="switch" id="nc-respects-optout" checked>
                        <label class="form-check-label fw-semibold" for="nc-respects-optout">Respect marketing opt-outs</label>
                        <div class="form-text">Recommended ON. Required by Australian Spam Act for marketing messages. Turn OFF only for transactional broadcasts you're legally required to send (e.g. service notices).</div>
                    </div>
                </div>
            </div>

            {{-- RIGHT: send + schedule --}}
            <div>
                {{-- Audience estimate --}}
                <div class="nc-card">
                    <div class="nc-card-title">Estimated Reach</div>
                    <div class="nc-est-banner" id="nc-est-banner">
                        <strong id="nc-est-total">0</strong> recipients
                        <div class="small mt-1" id="nc-est-detail">Use any of the three audience methods below — the running total appears here.</div>
                    </div>
                </div>

                {{-- Schedule --}}
                <div class="nc-card">
                    <div class="nc-card-title">Schedule</div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="radio" name="nc-schedule" id="nc-sch-now" value="now" checked>
                        <label class="form-check-label" for="nc-sch-now"><strong>Send Now</strong></label>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="radio" name="nc-schedule" id="nc-sch-later" value="later">
                        <label class="form-check-label" for="nc-sch-later"><strong>Schedule for Later</strong></label>
                    </div>
                    <div class="d-none" id="nc-schedule-when">
                        <input type="datetime-local" class="form-control form-control-sm" id="nc-scheduled-at">
                        <div class="form-text">Australia/Perth timezone.</div>
                    </div>
                </div>

                {{-- Action buttons --}}
                <div class="nc-card">
                    <button type="button" class="btn btn-primary w-100 mb-2" id="nc-action-send">
                        <i class="fa fa-paper-plane me-1"></i> <span id="nc-action-send-label">Send Now</span>
                    </button>
                    <button type="button" class="btn btn-outline-primary w-100 mb-2" id="nc-action-test">
                        <i class="fa fa-vial me-1"></i> Send Test
                    </button>
                    <button type="button" class="btn btn-outline-secondary w-100" id="nc-action-draft">
                        <i class="fa fa-save me-1"></i> Save as Draft
                    </button>
                </div>
            </div>
        </div>
    </div>

    {{-- ============= DRAFTS / SCHEDULED / SENT VIEWS (shared list pattern) ============= --}}
    <div class="nc-view d-none" data-view="drafts">
        <div class="nc-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0"><i class="fa fa-pencil-alt me-2 text-muted"></i> Drafts</h5>
                <input type="search" class="form-control form-control-sm" id="nc-drafts-search" placeholder="Search drafts…" style="max-width: 300px;">
            </div>
            <div id="nc-drafts-list"><div class="nc-empty"><i class="fa fa-pencil-alt"></i><div>No drafts yet</div></div></div>
        </div>
    </div>

    <div class="nc-view d-none" data-view="scheduled">
        <div class="nc-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0"><i class="fa fa-clock me-2 text-primary"></i> Scheduled</h5>
                <input type="search" class="form-control form-control-sm" id="nc-scheduled-search" placeholder="Search scheduled…" style="max-width: 300px;">
            </div>
            <div id="nc-scheduled-list"><div class="nc-empty"><i class="fa fa-clock"></i><div>No scheduled campaigns</div></div></div>
        </div>
    </div>

    <div class="nc-view d-none" data-view="sent">
        <div class="nc-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0"><i class="fa fa-check me-2 text-success"></i> Sent</h5>
                <input type="search" class="form-control form-control-sm" id="nc-sent-search" placeholder="Search sent…" style="max-width: 300px;">
            </div>
            <div id="nc-sent-list"><div class="nc-empty"><i class="fa fa-check"></i><div>No sent campaigns yet</div></div></div>
        </div>
    </div>

    {{-- ============= TEMPLATES VIEW ============= --}}
    <div class="nc-view d-none" data-view="templates">
        <div class="nc-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="mb-0"><i class="fa fa-bookmark me-2 text-warning"></i> Templates</h5>
                <button class="btn btn-primary btn-sm" id="nc-new-template-btn">
                    <i class="fa fa-plus me-1"></i> New Template
                </button>
            </div>
            <div id="nc-templates-list"><div class="nc-empty"><i class="fa fa-bookmark"></i><div>No templates saved</div></div></div>
        </div>
    </div>

</div>

{{-- ============= SEND TEST MODAL ============= --}}
<div class="modal fade" id="nc-test-modal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg,#667eea,#764ba2); color:#fff;">
                <h5 class="modal-title"><i class="fa fa-vial me-2"></i> Send Test</h5>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <ul class="nav nav-tabs" id="nc-test-tabs">
                    <li class="nav-item"><button class="nav-link active" data-mode="users">Pick Users</button></li>
                    <li class="nav-item"><button class="nav-link" data-mode="raw">Raw Numbers / Emails</button></li>
                </ul>

                <div class="mt-3" id="nc-test-mode-users">
                    <label class="form-label fw-semibold">Search and Pick</label>
                    <div class="position-relative">
                        <input type="text" class="form-control" id="nc-test-search" placeholder="Type name, email, or phone…" autocomplete="off">
                        <div class="nc-search-results d-none" id="nc-test-results"></div>
                    </div>
                    <div id="nc-test-chips" class="mt-2"></div>
                </div>

                <div class="mt-3 d-none" id="nc-test-mode-raw">
                    <label class="form-label fw-semibold">Phone Numbers / Emails</label>
                    <textarea class="form-control" id="nc-test-raw" rows="4" placeholder="+61400123456&#10;tester@example.com"></textarea>
                    <div class="form-text">One per line. AU phones get normalised to E.164.</div>
                </div>

                {{-- Result panel — populated after Send Test --}}
                <div class="mt-3 d-none" id="nc-test-result-panel"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" id="nc-test-submit">
                    <i class="fa fa-paper-plane me-1"></i> Send Test
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ============= TEMPLATE MODAL ============= --}}
<div class="modal fade" id="nc-template-modal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header" style="background: linear-gradient(135deg,#667eea,#764ba2); color:#fff;">
                <h5 class="modal-title"><i class="fa fa-bookmark me-2"></i> Template</h5>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="nc-tpl-id">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Name</label>
                    <input type="text" class="form-control" id="nc-tpl-name" placeholder="e.g. Christmas Promo — SMS">
                </div>
                <div class="mb-3" id="nc-tpl-title-wrap">
                    <label class="form-label fw-semibold">Title / Subject</label>
                    <input type="text" class="form-control" id="nc-tpl-title">
                </div>
                <div class="mb-0">
                    <label class="form-label fw-semibold">Body</label>
                    <textarea class="form-control" id="nc-tpl-body" rows="5"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-primary" id="nc-tpl-save">
                    <i class="fa fa-save me-1"></i> Save Template
                </button>
            </div>
        </div>
    </div>
</div>

{{-- ============= SUPPRESSION MODAL ============= --}}
<div class="modal fade" id="nc-suppression-modal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header" style="background: #ef4444; color:#fff;">
                <h5 class="modal-title"><i class="fa fa-ban me-2"></i> Suppression List</h5>
                <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">People in this list will <strong>never</strong> receive notifications on the listed channel — even if they appear in your audience. Used for STOP replies, hard bounces, and unsubscribes.</p>

                <div class="d-flex gap-2 mb-3 flex-wrap">
                    <select class="form-select form-select-sm" id="nc-supp-channel-filter" style="max-width: 180px;">
                        <option value="">All channels</option>
                        <option value="email">Email</option>
                        <option value="sms">SMS</option>
                        <option value="push">Push</option>
                        <option value="email_marketing">Email (marketing)</option>
                    </select>
                    <input type="search" class="form-control form-control-sm" id="nc-supp-search" placeholder="Search email/phone…" style="max-width: 280px;">
                    <button class="btn btn-primary btn-sm ms-auto" id="nc-supp-add-btn">
                        <i class="fa fa-plus me-1"></i> Add to Suppression List
                    </button>
                </div>

                {{-- Add form (hidden by default) --}}
                <div class="d-none mb-3 p-3" id="nc-supp-add-form" style="background:#f9fafb;border-radius:8px;">
                    <div class="row g-2">
                        <div class="col-md-3">
                            <label class="form-label small">Channel</label>
                            <select class="form-select form-select-sm" id="nc-supp-add-channel">
                                <option value="email">Email</option>
                                <option value="sms">SMS</option>
                                <option value="push">Push</option>
                                <option value="email_marketing">Email (marketing only)</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small">Email or Phone</label>
                            <input type="text" class="form-control form-control-sm" id="nc-supp-add-value" placeholder="user@example.com or +61400123456">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small">Reason</label>
                            <select class="form-select form-select-sm" id="nc-supp-add-reason">
                                <option value="manual">Manual</option>
                                <option value="user_unsubscribed">Unsubscribed</option>
                                <option value="hard_bounce">Hard bounce</option>
                                <option value="complaint">Complaint</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end gap-1">
                            <button class="btn btn-success btn-sm flex-fill" id="nc-supp-save">Save</button>
                            <button class="btn btn-light btn-sm" id="nc-supp-cancel">Cancel</button>
                        </div>
                    </div>
                </div>

                <div id="nc-supp-list" style="max-height: 60vh; overflow-y: auto;"></div>
            </div>
        </div>
    </div>
</div>

@endsection

@push('scripts')
<script>
$(function () {
    // ============================================================
    // STATE
    // ============================================================
    var state = {
        channel: 'push',                           // active channel
        view: 'compose',                           // active sub-view
        editingId: null,                           // when editing an existing draft/scheduled
        include: [],                               // include rule list
        exclude: [],                               // exclude rule list
        handpicked: [],                            // [{id,name,email,phone,label}]
        rawText: '',                               // raw recipients textarea
        respectsOptOut: true,
        scheduleType: 'now',
        scheduledAt: null,
        testPicks: [],                             // for the test modal
        testMode: 'users',
    };

    // ============================================================
    // HELPERS
    // ============================================================
    function escapeHtml(s) {
        if (s === null || s === undefined) return '';
        return String(s).replace(/[&<>"']/g, function(c) {
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
        });
    }
    function formatNum(n) {
        return Number(n || 0).toLocaleString();
    }
    function csrf() { return $('meta[name="csrf-token"]').attr('content') || '{{ csrf_token() }}'; }

    function api(method, url, data) {
        var settings = {
            url: url,
            type: method,
            headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' }
        };
        if (data) {
            // contentType=application/json so the controller validates arrays correctly
            settings.contentType = 'application/json';
            settings.data = JSON.stringify(data);
        }
        return $.ajax(settings);
    }

    function showToast(msg, type) {
        type = type || 'success';
        if (typeof toastr !== 'undefined') {
            toastr[type](msg);
        } else {
            alert(msg);
        }
    }

    // Channel-specific config
    function isSmsChannel()   { return state.channel === 'sms' || state.channel === 'sms_marketing'; }
    function isEmailChannel() { return state.channel === 'email' || state.channel === 'email_marketing'; }
    function isPushChannel()  { return state.channel === 'push' || state.channel === 'push_marketing'; }

    function applyChannelUI() {
        $('#nc-channel-label').text(state.channel.replace('_', ' '));

        // Title field
        if (isSmsChannel()) {
            $('#nc-title-wrap').addClass('d-none');
        } else {
            $('#nc-title-wrap').removeClass('d-none');
            $('#nc-title-label').text(isPushChannel() ? 'Push Title' : 'Email Subject');
        }

        // Body label
        $('#nc-body-label').text(isSmsChannel() ? 'SMS Message' : (isPushChannel() ? 'Push Body' : 'Email Body'));

        // SMS meter
        $('#nc-sms-meter').toggleClass('d-none', !isSmsChannel());

        // Raw recipients block (SMS or Email — hidden for push)
        if (isSmsChannel()) {
            $('#nc-raw-method').removeClass('d-none');
            $('#nc-raw-desc').text('Send to phone numbers that aren\'t registered as customers — useful for testing, or one-off broadcasts to a list.');
            $('#nc-raw-recipients').attr('placeholder', '+61400123456, +61400789012');
        } else if (isEmailChannel()) {
            $('#nc-raw-method').removeClass('d-none');
            $('#nc-raw-desc').text('Send to email addresses that aren\'t registered as customers — useful for testing, or one-off broadcasts to a list.');
            $('#nc-raw-recipients').attr('placeholder', 'tester@example.com, vip@example.com');
        } else {
            $('#nc-raw-method').addClass('d-none');
        }

        // Marketing channels default opt-out respect to ON; transactional default to ON but allow OFF
        // (keeping current state across switches is fine)

        debouncedEstimate();
    }

    // ============================================================
    // CHANNEL TAB SWITCHING
    // ============================================================
    $('#nc-channel-tabs').on('click', '.nc-channel-tab', function () {
        $('#nc-channel-tabs .nc-channel-tab').removeClass('active');
        $(this).addClass('active');
        state.channel = $(this).data('channel');
        applyChannelUI();
        loadListsForCurrentChannel();
    });

    // SUB-VIEW SWITCHING
    $('.nc-subnav').on('click', '.nc-subnav-btn', function () {
        $('.nc-subnav-btn').removeClass('active');
        $(this).addClass('active');
        state.view = $(this).data('view');
        $('.nc-view').addClass('d-none');
        $('.nc-view[data-view="' + state.view + '"]').removeClass('d-none');
        if (state.view === 'drafts')    loadCampaigns('draft');
        if (state.view === 'scheduled') loadCampaigns('scheduled');
        if (state.view === 'sent')      loadCampaigns('sent');
        if (state.view === 'templates') loadTemplates();
    });

    // ============================================================
    // INCLUDE / EXCLUDE RULES
    // ============================================================

    var INCLUDE_SCHEMA = {
        all_users:           { needsValue: false, valueType: 'none',  label: 'Everyone' },
        suburb:              { needsValue: true,  valueType: 'text',  label: 'Suburb' },
        state:               { needsValue: true,  valueType: 'text',  label: 'State' },
        active_since:        { needsValue: true,  valueType: 'days',  label: 'Active in last' },
        has_orders:          { needsValue: true,  valueType: 'min',   label: 'Has placed at least' },
        min_lifetime_spend:  { needsValue: true,  valueType: 'money', label: 'Lifetime spend ≥' },
        has_referral_code:   { needsValue: false, valueType: 'none',  label: 'Has referral code' },
        never_ordered:       { needsValue: false, valueType: 'none',  label: 'Never ordered' },
    };

    $('#nc-include-rule-type').on('change', function () {
        var schema = INCLUDE_SCHEMA[$(this).val()];
        var $val = $('#nc-include-rule-value');
        if (schema && schema.needsValue) {
            $val.removeClass('d-none').attr('placeholder', placeholderFor(schema.valueType)).val('');
            if (schema.valueType === 'days' || schema.valueType === 'min' || schema.valueType === 'money') {
                $val.attr('type', 'number');
            } else {
                $val.attr('type', 'text');
            }
        } else {
            $val.addClass('d-none').val('');
        }
    });
    function placeholderFor(t) {
        return ({text:'e.g. Joondalup', days:'30', min:'1', money:'200'})[t] || '';
    }

    $('#nc-add-include').on('click', function () {
        var type = $('#nc-include-rule-type').val();
        var schema = INCLUDE_SCHEMA[type];
        var rule = { type: type };
        if (schema && schema.needsValue) {
            var v = $('#nc-include-rule-value').val().trim();
            if (!v) { showToast('Enter a value first.', 'warning'); return; }
            if (schema.valueType === 'text') rule.value = v;
            else if (schema.valueType === 'days') rule.days = parseInt(v, 10);
            else if (schema.valueType === 'min') rule.min = parseInt(v, 10);
            else if (schema.valueType === 'money') rule.value = parseFloat(v);
        }
        state.include.push(rule);
        renderIncludeChips();
        debouncedEstimate();
    });

    $('#nc-add-exclude').on('click', function () {
        var v = $('#nc-exclude-rule-value').val().trim();
        if (!v) { showToast('Enter a suburb to exclude.', 'warning'); return; }
        state.exclude.push({ type: 'suburb', value: v });
        $('#nc-exclude-rule-value').val('');
        renderExcludeChips();
        debouncedEstimate();
    });

    function renderIncludeChips() {
        var $c = $('#nc-include-chips').empty();
        if (state.include.length === 0) {
            $c.html('<span class="text-muted small" id="nc-include-empty">No rules added — that\'s fine if you\'re using Methods 2 or 3 below. Add a rule here to include groups of customers.</span>');
            return;
        }
        state.include.forEach(function (r, i) {
            $c.append(buildChip(r, i, 'include'));
        });
    }
    function renderExcludeChips() {
        var $c = $('#nc-exclude-chips').empty();
        if (state.exclude.length === 0) {
            $c.html('<span class="text-muted small" id="nc-exclude-empty">No exclusions.</span>');
            return;
        }
        state.exclude.forEach(function (r, i) {
            $c.append(buildChip(r, i, 'exclude'));
        });
    }
    function buildChip(rule, index, kind) {
        var label = describeRule(rule);
        var cls = kind === 'exclude' ? 'nc-rule-chip exclude' : 'nc-rule-chip';
        return $('<span class="' + cls + '"></span>')
            .text(label + ' ')
            .append($('<span class="x">×</span>').on('click', function () {
                if (kind === 'include') state.include.splice(index, 1);
                else state.exclude.splice(index, 1);
                if (kind === 'include') renderIncludeChips(); else renderExcludeChips();
                debouncedEstimate();
            }));
    }
    function describeRule(r) {
        switch (r.type) {
            case 'all_users': return 'Everyone';
            case 'suburb': return 'Suburb: ' + r.value;
            case 'state': return 'State: ' + r.value;
            case 'active_since': return 'Active in last ' + r.days + ' days';
            case 'has_orders': return 'Has ≥ ' + r.min + ' orders';
            case 'min_lifetime_spend': return 'Spent ≥ $' + r.value;
            case 'has_referral_code': return 'Has referral code';
            case 'never_ordered': return 'Never ordered';
            default: return r.type;
        }
    }

    // ============================================================
    // HAND-PICK SEARCH
    // ============================================================
    var handpickTimer;
    $('#nc-handpick-search').on('input', function () {
        var q = $(this).val();
        clearTimeout(handpickTimer);
        if (q.length < 2) { $('#nc-handpick-results').addClass('d-none'); return; }
        handpickTimer = setTimeout(function () {
            $.get('{{ route("backend.notification_centre.recipients.search") }}', { q: q, channel: state.channel })
                .done(function (r) {
                    var $r = $('#nc-handpick-results').empty();
                    if (!r.data || r.data.length === 0) {
                        $r.html('<div class="nc-search-result text-muted">No matches</div>').removeClass('d-none');
                        return;
                    }
                    r.data.forEach(function (u) {
                        var icon = u.reachable
                            ? '<span class="text-success me-1" title="Has contact info"><i class="fa fa-check-circle"></i></span>'
                            : '<span class="text-warning me-1" title="' + escapeHtml(u.why || '') + '"><i class="fa fa-exclamation-triangle"></i></span>';
                        var contactInfo = u.email || u.phone || '<span class="text-warning">' + escapeHtml(u.why || 'no contact') + '</span>';
                        var $row = $('<div class="nc-search-result"></div>')
                            .html(icon + '<strong>' + escapeHtml(u.name) + '</strong> <span class="text-muted small">' + contactInfo + '</span>')
                            .data('user', u)
                            .on('click', function () {
                                if (!u.reachable) {
                                    showToast('Can\'t add ' + u.name + ': ' + (u.why || 'no contact info for this channel'), 'warning');
                                    return;
                                }
                                if (!state.handpicked.find(function (h) { return h.id === u.id; })) {
                                    state.handpicked.push(u);
                                    renderHandpickChips();
                                    debouncedEstimate();
                                }
                                $('#nc-handpick-search').val('');
                                $r.addClass('d-none');
                            });
                        if (!u.reachable) $row.css({opacity: 0.55, cursor: 'not-allowed'});
                        $row.appendTo($r);
                    });
                    $r.removeClass('d-none');
                });
        }, 250);
    });
    $(document).on('click', function (e) {
        if (!$(e.target).closest('#nc-handpick-search, #nc-handpick-results').length) {
            $('#nc-handpick-results').addClass('d-none');
        }
    });

    function renderHandpickChips() {
        var $c = $('#nc-handpick-chips').empty();
        state.handpicked.forEach(function (u, i) {
            $c.append(
                $('<span class="nc-recipient-chip"></span>')
                    .text(u.name + ' ')
                    .append($('<span class="x">×</span>').on('click', function () {
                        state.handpicked.splice(i, 1);
                        renderHandpickChips();
                        debouncedEstimate();
                    }))
            );
        });
    }

    // ============================================================
    // RAW RECIPIENTS / CSV
    // ============================================================
    $('#nc-raw-recipients').on('input', debounce(function () {
        state.rawText = $(this).val();
        debouncedEstimate();
    }, 500));

    $('#nc-csv-file').on('change', function (e) {
        var file = e.target.files[0];
        if (!file) return;
        var reader = new FileReader();
        reader.onload = function (ev) {
            var lines = String(ev.target.result || '').split(/[\r\n,]+/).map(function (s) { return s.trim(); }).filter(Boolean);
            var existing = $('#nc-raw-recipients').val();
            $('#nc-raw-recipients').val((existing ? existing + ', ' : '') + lines.join(', '));
            state.rawText = $('#nc-raw-recipients').val();
            debouncedEstimate();
            showToast('Loaded ' + lines.length + ' recipients from CSV.');
        };
        reader.readAsText(file);
    });

    // ============================================================
    // BUILD AUDIENCE SPEC + LIVE ESTIMATE
    // ============================================================
    function buildSpec() {
        var raw = ($('#nc-raw-recipients').val() || '').split(/[\s,]+/).map(function (s) { return s.trim(); }).filter(Boolean);
        return {
            include:             state.include.slice(),
            exclude:             state.exclude.slice(),
            handpicked_user_ids: state.handpicked.map(function (u) { return u.id; }),
            raw_recipients:      raw,
        };
    }

    var debouncedEstimate = debounce(function () {
        var spec = buildSpec();
        var msg  = $('#nc-body').val();
        $.ajax({
            url: '{{ route("backend.notification_centre.audience.estimate") }}',
            type: 'POST',
            contentType: 'application/json',
            headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' },
            data: JSON.stringify({
                audience_spec: spec,
                channel: state.channel,
                respects_marketing_optout: state.respectsOptOut,
                message: msg,
            })
        }).done(function (r) {
            $('#nc-est-total').text(formatNum(r.total));
            var customerLabel = formatNum(r.matched_users) + ' ' + (r.matched_users === 1 ? 'registered customer' : 'registered customers');
            var rawLabel      = formatNum(r.raw_count)     + ' ' + (r.raw_count === 1 ? 'raw recipient' : 'raw recipients');
            var detail;
            if (r.total === 0) {
                detail = 'Add at least one rule, hand-picked customer, or raw recipient.';
            } else if (r.matched_users && r.raw_count) {
                detail = customerLabel + ' + ' + rawLabel;
            } else if (r.matched_users) {
                detail = customerLabel + ' (from rules and/or hand-picks)';
            } else {
                detail = rawLabel;
            }
            $('#nc-est-detail').text(detail);
            var $b = $('#nc-est-banner').removeClass('warn danger');
            if (r.total === 0) $b.addClass('warn');
            else if (isSmsChannel() && r.total > 200) $b.addClass('warn');
            else if (isSmsChannel() && r.total > 1000) $b.addClass('danger');

            if (isSmsChannel()) {
                var segs = r.sms_segments || 1;
                var perSeg = r.sms_cost_per_segment || 0.06;
                var enc = r.sms_encoding ? ' · ' + r.sms_encoding : '';
                $('#nc-sms-segments').text(segs + ' segment' + (segs === 1 ? '' : 's') + enc);
                // Show the math: e.g. "~$4.32  (72 × 1 seg × $0.06)"
                if (r.total > 0) {
                    var mathStr = ' (' + r.total + ' × ' + segs + ' seg × $' + perSeg.toFixed(3).replace(/0+$/, '').replace(/\.$/, '') + ')';
                    $('#nc-sms-cost').text('~$' + (r.estimated_cost || 0).toFixed(2) + mathStr);
                } else {
                    $('#nc-sms-cost').text('~$0.00 estimated cost');
                }
                var segPill = $('#nc-sms-segments').removeClass('warn danger');
                if (segs === 2) segPill.addClass('warn');
                else if (segs >= 3) segPill.addClass('danger');
            }
        }).fail(function (xhr) {
            $('#nc-est-total').text('?');
            $('#nc-est-detail').text('Estimate failed: ' + ((xhr.responseJSON && xhr.responseJSON.message) || 'unknown'));
        });
    }, 350);

    function debounce(fn, ms) {
        var t; return function () {
            var ctx = this, args = arguments;
            clearTimeout(t);
            t = setTimeout(function () { fn.apply(ctx, args); }, ms);
        };
    }

    // ============================================================
    // SMS encoding & segment math (mirrors Brevo's countSmsCharacters)
    //
    // GSM-7 alphabet covers basic Latin + a few accented chars + GSM extensions.
    // Anything outside it (emoji, smart quotes, non-Latin) forces UCS-2 encoding.
    //
    // Per-segment limits:
    //   GSM-7  single: 160     multi: 153 (7 chars per segment lost to UDH)
    //   UCS-2  single:  70     multi:  67 (3 chars per segment lost to UDH)
    //
    // GSM-7 extension chars ({, }, [, ], \, ~, ^, |, €) take 2 GSM-7 chars each.
    // ============================================================
    var GSM7_BASIC = "@£$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞ\x1BÆæßÉ !\"#¤%&'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà";
    var GSM7_EXT   = "^{}\\[~]|€";

    // Common non-GSM-7 characters that have safe ASCII replacements
    var GSM7_FIXES = {
        '\u2014': '-',          // em-dash
        '\u2013': '-',          // en-dash
        '\u2212': '-',          // unicode minus
        '\u2018': "'",          // left single quote
        '\u2019': "'",          // curly apostrophe
        '\u201A': "'",          // single low quote
        '\u201B': "'",          // single high reversed quote
        '\u201C': '"',          // left double quote
        '\u201D': '"',          // right double quote
        '\u201E': '"',          // double low quote
        '\u00AB': '"',          // french left guillemet
        '\u00BB': '"',          // french right guillemet
        '\u2026': '...',        // horizontal ellipsis
        '\u2022': '*',          // bullet
        '\u00B7': '*',          // middle dot
        '\u00A0': ' ',          // non-breaking space
        '\u200B': '',           // zero-width space (remove)
        '\u200C': '',           // zero-width non-joiner (remove)
        '\u200D': '',           // zero-width joiner (remove)
        '\uFEFF': '',           // byte order mark (remove)
        '\u2192': '->',         // right arrow
        '\u2190': '<-',         // left arrow
        '\u2191': '^',          // up arrow
        '\u2193': 'v',          // down arrow
        '\u2122': '(TM)',       // trademark
        '\u00A9': '(c)',        // copyright
        '\u00AE': '(R)',        // registered
        '\u00BD': '1/2',        // half
        '\u00BC': '1/4',        // quarter
        '\u00BE': '3/4',        // three-quarters
        '\u00D7': 'x',          // multiplication sign
        '\u2013': '-',          // en-dash (dup-safe)
    };

    var GSM7_NAMES = {
        '\u2014': 'em-dash', '\u2013': 'en-dash', '\u2212': 'unicode minus',
        '\u2018': 'left single quote', '\u2019': 'curly apostrophe',
        '\u201A': 'low single quote', '\u201B': 'reversed single quote',
        '\u201C': 'curly left double quote', '\u201D': 'curly right double quote',
        '\u201E': 'low double quote',
        '\u00AB': 'left guillemet', '\u00BB': 'right guillemet',
        '\u2026': 'ellipsis (…)', '\u2022': 'bullet (•)', '\u00B7': 'middle dot',
        '\u00A0': 'non-breaking space', '\u200B': 'zero-width space',
        '\u200C': 'zero-width non-joiner', '\u200D': 'zero-width joiner',
        '\uFEFF': 'byte-order mark',
        '\u2192': 'right arrow (→)', '\u2190': 'left arrow (←)',
        '\u2191': 'up arrow (↑)', '\u2193': 'down arrow (↓)',
        '\u2122': 'trademark (™)', '\u00A9': 'copyright (©)', '\u00AE': 'registered (®)',
        '\u00BD': 'one-half (½)', '\u00BC': 'one-quarter (¼)', '\u00BE': 'three-quarters (¾)',
        '\u00D7': 'multiplication sign (×)',
    };

    function describeChar(ch) {
        if (GSM7_NAMES[ch]) return GSM7_NAMES[ch];
        // Probably an emoji or other Unicode — show codepoint
        var cp = ch.codePointAt(0).toString(16).toUpperCase();
        while (cp.length < 4) cp = '0' + cp;
        return 'U+' + cp;
    }

    function smsAnalyse(message) {
        var msg = message || '';
        var len = msg.length;
        var ext = 0;
        var problemChars = {};   // char → count
        for (var i = 0; i < len; i++) {
            var ch = msg[i];
            if (GSM7_BASIC.indexOf(ch) >= 0) continue;
            if (GSM7_EXT.indexOf(ch) >= 0) { ext++; continue; }
            problemChars[ch] = (problemChars[ch] || 0) + 1;
        }
        var isGsm7 = Object.keys(problemChars).length === 0;
        var encoding, single, multi, count;
        if (isGsm7) {
            encoding = 'GSM-7';
            single = 160; multi = 153;
            count = len + ext;
        } else {
            encoding = 'UCS-2';
            single = 70; multi = 67;
            count = len;
        }
        var segments = count <= single ? 1 : Math.ceil(count / multi);
        return {
            encoding: encoding, count: count, length: len, segments: segments,
            problemChars: problemChars,
        };
    }

    function autoFixGsm7(message) {
        var fixed = '';
        var changes = {}; // char → count
        var i = 0;
        while (i < message.length) {
            var ch = message[i];
            // Handle astral plane (emoji) — they take 2 UTF-16 code units
            var code = message.codePointAt(i);
            var width = code > 0xFFFF ? 2 : 1;
            if (GSM7_FIXES[ch] !== undefined && width === 1) {
                fixed += GSM7_FIXES[ch];
                changes[ch] = (changes[ch] || 0) + 1;
            } else {
                fixed += message.substr(i, width);
            }
            i += width;
        }
        return { result: fixed, changes: changes };
    }

    function renderGsm7Fixer(info) {
        var $box = $('#nc-gsm7-fixer');
        var problemChars = Object.keys(info.problemChars);

        if (problemChars.length === 0) {
            $box.addClass('d-none').empty();
            return;
        }

        // Build the breakdown
        var fixableCount = 0;
        var totalProblems = 0;
        var listHtml = '';
        problemChars.forEach(function (ch) {
            var count = info.problemChars[ch];
            totalProblems += count;
            var name = describeChar(ch);
            var fix  = GSM7_FIXES[ch];
            var fixDescription;
            if (fix === '') {
                fixDescription = '<span class="text-success">→ remove (invisible char)</span>';
                fixableCount += count;
            } else if (fix !== undefined) {
                fixDescription = '<span class="text-success">→ replace with <code>' + escapeHtml(fix) + '</code></span>';
                fixableCount += count;
            } else {
                fixDescription = '<span class="text-danger">no auto-fix — remove or replace manually</span>';
            }
            listHtml += '<li>'
                + '<span class="nc-gsm7-bad-char">' + escapeHtml(ch) + '</span> '
                + '<strong>' + escapeHtml(name) + '</strong> × ' + count + ' '
                + fixDescription
                + '</li>';
        });

        var titleHtml = '<i class="fa fa-exclamation-triangle me-1"></i> '
            + '<strong>' + totalProblems + ' character' + (totalProblems === 1 ? '' : 's')
            + '</strong> outside the SMS-safe (GSM-7) alphabet — forcing more expensive UCS-2 encoding';

        var buttonHtml = '';
        if (fixableCount > 0) {
            buttonHtml = '<button type="button" class="btn btn-warning btn-sm" id="nc-sms-autofix">'
                + '<i class="fa fa-magic me-1"></i> Auto-fix ' + fixableCount + ' character'
                + (fixableCount === 1 ? '' : 's')
                + '</button>';
        }

        var manualNote = (fixableCount < totalProblems)
            ? '<div class="small text-muted mt-2"><i class="fa fa-info-circle me-1"></i> Some characters (e.g. emoji) have no auto-replacement and need manual editing.</div>'
            : '';

        $box.html('<div class="nc-gsm7-title">' + titleHtml + '</div>'
            + '<ul>' + listHtml + '</ul>'
            + buttonHtml + manualNote
        ).removeClass('d-none');
    }

    // SMS character live counter (without round-trip)
    $('#nc-body').on('input', function () {
        if (isSmsChannel()) {
            var info = smsAnalyse($(this).val());
            $('#nc-sms-chars').text(info.length);
            var $segPill = $('#nc-sms-segments')
                .text(info.segments + ' segment' + (info.segments === 1 ? '' : 's') + ' · ' + info.encoding);
            $segPill.removeClass('warn danger');
            if (info.segments === 2) $segPill.addClass('warn');
            else if (info.segments >= 3) $segPill.addClass('danger');
            renderGsm7Fixer(info);
        } else {
            $('#nc-gsm7-fixer').addClass('d-none').empty();
        }
        debouncedEstimate();
    });

    // Auto-fix button
    $(document).on('click', '#nc-sms-autofix', function () {
        var current = $('#nc-body').val();
        var result = autoFixGsm7(current);
        $('#nc-body').val(result.result).trigger('input');
        var summary = Object.keys(result.changes).map(function (ch) {
            return result.changes[ch] + '× ' + describeChar(ch);
        }).join(', ');
        showToast('Replaced: ' + summary, 'success');
    });


    // ============================================================
    // SCHEDULE
    // ============================================================
    $('input[name="nc-schedule"]').on('change', function () {
        state.scheduleType = $(this).val();
        if (state.scheduleType === 'later') {
            $('#nc-schedule-when').removeClass('d-none');
            $('#nc-action-send-label').text('Schedule');
        } else {
            $('#nc-schedule-when').addClass('d-none');
            $('#nc-action-send-label').text('Send Now');
        }
    });

    $('#nc-respects-optout').on('change', function () {
        state.respectsOptOut = $(this).is(':checked');
        debouncedEstimate();
    });

    // ============================================================
    // SAVE / SEND ACTIONS
    // ============================================================

    function buildPayload(forStatus) {
        var raw = ($('#nc-raw-recipients').val() || '').split(/[\s,]+/).map(function (s) { return s.trim(); }).filter(Boolean);
        return {
            id:            state.editingId,
            channel:       state.channel,
            audience:      'custom',
            audience_spec: {
                include:             state.include.slice(),
                exclude:             state.exclude.slice(),
                handpicked_user_ids: state.handpicked.map(function (u) { return u.id; }),
                raw_recipients:      raw,
            },
            title:           $('#nc-title').val(),
            message:         $('#nc-body').val(),
            campaign_name:   $('#nc-campaign-name').val(),
            schedule_type:   state.scheduleType === 'later' ? 'one_time' : 'one_time',
            scheduled_at:    state.scheduleType === 'later' ? $('#nc-scheduled-at').val() : null,
            status:          forStatus,
            respects_marketing_optout: state.respectsOptOut,
            test_mode:       false,
        };
    }

    $('#nc-action-draft').on('click', function () {
        if (!validateBasic()) return;
        var $btn = $(this).prop('disabled', true);
        var origLabel = $btn.html();
        $btn.html('<i class="fa fa-spinner fa-spin me-1"></i> Saving…');
        var url = state.editingId
            ? '{{ url("/backend/notification-centre") }}/' + state.editingId + '/update'
            : '{{ url("/backend/notification-centre") }}';
        api('POST', url, buildPayload('draft'))
            .done(function (r) {
                $btn.prop('disabled', false).html(origLabel);
                state.editingId = (r.data && r.data.id) || state.editingId;
                showToast('Draft saved' + (state.editingId ? ' (#' + state.editingId + ')' : '') + '.');
            })
            .fail(function (xhr) {
                $btn.prop('disabled', false).html(origLabel);
                showToast('Failed to save: ' + (typeof extractError === 'function' ? extractError(xhr) : ((xhr.responseJSON && xhr.responseJSON.message) || 'error')), 'error');
            });
    });

    $('#nc-action-send').on('click', function () {
        if (!validateBasic()) return;

        // Validate audience for the actual send (not for test)
        var spec = buildSpec();
        var hasAnyRecipient = (spec.include && spec.include.length > 0)
            || (spec.handpicked_user_ids && spec.handpicked_user_ids.length > 0)
            || (spec.raw_recipients && spec.raw_recipients.length > 0);
        if (!hasAnyRecipient) {
            showToast('Add at least one audience rule, hand-picked person, or raw recipient before sending.', 'warning');
            return;
        }

        if (state.scheduleType === 'later') {
            var when = $('#nc-scheduled-at').val();
            if (!when) {
                showToast('Pick a date/time to schedule for.', 'warning');
                return;
            }
            // sanity check it's in the future
            if (new Date(when).getTime() <= Date.now()) {
                showToast('Scheduled time must be in the future.', 'warning');
                return;
            }
        }

        if (state.scheduleType === 'now') {
            // Confirm with cost preview if SMS
            var est = $('#nc-est-total').text();
            var msg = 'Send to ~' + est + ' recipients now?';
            if (isSmsChannel()) {
                msg += ' SMS may cost approximately ' + $('#nc-sms-cost').text().replace('~', '');
            }
            if (!confirm(msg)) return;
        }

        var $btn = $(this).prop('disabled', true);
        var origLabel = $btn.html();
        $btn.html('<i class="fa fa-spinner fa-spin me-1"></i> Working…');

        var status = state.scheduleType === 'now' ? 'send_now' : 'scheduled';
        var url = state.editingId
            ? '{{ url("/backend/notification-centre") }}/' + state.editingId + '/update'
            : '{{ url("/backend/notification-centre") }}';

        api('POST', url, buildPayload(status))
            .done(function (r) {
                var newId = (r.data && r.data.id) || state.editingId;
                if (!newId) {
                    $btn.prop('disabled', false).html(origLabel);
                    showToast('Saved but couldn\'t find campaign ID — please refresh.', 'error');
                    return;
                }
                if (state.scheduleType === 'now') {
                    api('POST', '{{ url("/backend/notification-centre") }}/' + newId + '/send-now', {})
                        .done(function () {
                            showToast('Send queued. Switch to the Sent tab to track progress.');
                            resetComposer(); loadListsForCurrentChannel();
                            $btn.prop('disabled', false).html(origLabel);
                        })
                        .fail(function (xhr) {
                            $btn.prop('disabled', false).html(origLabel);
                            showToast('Send failed: ' + extractError(xhr), 'error');
                        });
                } else {
                    api('POST', '{{ url("/backend/notification-centre") }}/' + newId + '/schedule', { schedule_type: 'one_time', scheduled_at: $('#nc-scheduled-at').val() })
                        .done(function () {
                            showToast('Scheduled. Switch to the Scheduled tab to see it.');
                            resetComposer(); loadListsForCurrentChannel();
                            $btn.prop('disabled', false).html(origLabel);
                        })
                        .fail(function (xhr) {
                            $btn.prop('disabled', false).html(origLabel);
                            showToast('Schedule failed: ' + extractError(xhr), 'error');
                        });
                }
            })
            .fail(function (xhr) {
                $btn.prop('disabled', false).html(origLabel);
                showToast('Failed to save campaign: ' + extractError(xhr), 'error');
            });
    });

    function extractError(xhr) {
        if (xhr.responseJSON) {
            if (xhr.responseJSON.message) return xhr.responseJSON.message;
            if (xhr.responseJSON.errors) {
                var first = Object.values(xhr.responseJSON.errors)[0];
                if (first && first.length) return first[0];
            }
            if (xhr.responseJSON.error) return xhr.responseJSON.error;
        }
        return xhr.statusText || ('HTTP ' + xhr.status);
    }


    $('#nc-action-test').on('click', function () {
        if (!$('#nc-body').val().trim()) {
            showToast('Add some message text first.', 'warning');
            return;
        }
        if (!isSmsChannel() && !$('#nc-title').val().trim()) {
            showToast(isPushChannel() ? 'Add a push title first.' : 'Add an email subject first.', 'warning');
            return;
        }
        openTestModal();
    });

    function validateBasic() {
        if (!$('#nc-body').val().trim()) {
            showToast('Message body is required.', 'warning');
            return false;
        }
        if (!isSmsChannel() && !$('#nc-title').val().trim()) {
            showToast(isPushChannel() ? 'Push title is required.' : 'Email subject is required.', 'warning');
            return false;
        }
        return true;
    }

    function resetComposer() {
        state.editingId = null;
        state.include = [];
        state.exclude = [];
        state.handpicked = [];
        $('#nc-campaign-name, #nc-title, #nc-body, #nc-raw-recipients, #nc-scheduled-at').val('');
        $('#nc-sch-now').prop('checked', true).trigger('change');
        renderIncludeChips();
        renderExcludeChips();
        renderHandpickChips();
        debouncedEstimate();
    }

    // ============================================================
    // SEND TEST MODAL — direct send, no draft required
    // ============================================================
    function openTestModal() {
        state.testPicks = [];
        $('#nc-test-search, #nc-test-raw').val('');
        $('#nc-test-chips').empty();
        $('#nc-test-results').addClass('d-none');
        $('#nc-test-result-panel').addClass('d-none').empty();
        $('#nc-test-tabs button[data-mode="users"]').addClass('active').siblings().removeClass('active');
        $('#nc-test-mode-users').removeClass('d-none');
        $('#nc-test-mode-raw').addClass('d-none');
        // Hide raw tab for push (no raw FCM tokens)
        $('#nc-test-tabs button[data-mode="raw"]').toggle(!isPushChannel());
        $('#nc-test-submit').prop('disabled', false).html('<i class="fa fa-paper-plane me-1"></i> Send Test');
        new bootstrap.Modal(document.getElementById('nc-test-modal')).show();
    }

    $('#nc-test-tabs').on('click', 'button', function () {
        $('#nc-test-tabs button').removeClass('active');
        $(this).addClass('active');
        var mode = $(this).data('mode');
        state.testMode = mode;
        $('#nc-test-mode-users').toggleClass('d-none', mode !== 'users');
        $('#nc-test-mode-raw').toggleClass('d-none', mode !== 'raw');
    });

    var testTimer;
    $('#nc-test-search').on('input', function () {
        var q = $(this).val();
        clearTimeout(testTimer);
        if (q.length < 2) { $('#nc-test-results').addClass('d-none'); return; }
        testTimer = setTimeout(function () {
            $.get('{{ route("backend.notification_centre.recipients.search") }}', { q: q, channel: state.channel })
                .done(function (r) {
                    var $r = $('#nc-test-results').empty();
                    if (!r.data || r.data.length === 0) {
                        $r.html('<div class="nc-search-result text-muted">No matches</div>').removeClass('d-none');
                        return;
                    }
                    r.data.forEach(function (u) {
                        var icon = u.reachable
                            ? '<span class="text-success me-1" title="Has contact info"><i class="fa fa-check-circle"></i></span>'
                            : '<span class="text-warning me-1" title="' + escapeHtml(u.why || '') + '"><i class="fa fa-exclamation-triangle"></i></span>';
                        var contactInfo = u.email || u.phone || '<span class="text-warning">' + escapeHtml(u.why || 'no contact') + '</span>';
                        var $row = $('<div class="nc-search-result"></div>')
                            .html(icon + '<strong>' + escapeHtml(u.name) + '</strong> <span class="text-muted small">' + contactInfo + '</span>')
                            .on('click', function () {
                                if (!u.reachable) {
                                    showToast('Can\'t test on ' + u.name + ': ' + (u.why || 'no contact info'), 'warning');
                                    return;
                                }
                                if (!state.testPicks.find(function (h) { return h.id === u.id; })) {
                                    state.testPicks.push(u);
                                    renderTestChips();
                                }
                                $('#nc-test-search').val('');
                                $r.addClass('d-none');
                            });
                        if (!u.reachable) $row.css({opacity: 0.55, cursor: 'not-allowed'});
                        $row.appendTo($r);
                    });
                    $r.removeClass('d-none');
                })
                .fail(function (xhr) {
                    $('#nc-test-results').html('<div class="nc-search-result text-danger">Search failed: ' + ((xhr.responseJSON && xhr.responseJSON.message) || 'error') + '</div>').removeClass('d-none');
                });
        }, 250);
    });
    function renderTestChips() {
        var $c = $('#nc-test-chips').empty();
        state.testPicks.forEach(function (u, i) {
            $c.append(
                $('<span class="nc-recipient-chip"></span>')
                    .text(u.name + ' ')
                    .append($('<span class="x">×</span>').on('click', function () {
                        state.testPicks.splice(i, 1);
                        renderTestChips();
                    }))
            );
        });
    }

    $('#nc-test-submit').on('click', function () {
        var data = {
            channel:  state.channel,
            title:    $('#nc-title').val(),
            message:  $('#nc-body').val(),
            user_ids: [],
            emails:   [],
            phones:   [],
        };

        if (state.testMode === 'users') {
            data.user_ids = state.testPicks.map(function (u) { return u.id; });
        } else {
            var raw = ($('#nc-test-raw').val() || '').split(/[\s,]+/).map(function (s) { return s.trim(); }).filter(Boolean);
            raw.forEach(function (r) {
                if (r.indexOf('@') !== -1) data.emails.push(r);
                else data.phones.push(r);
            });
        }

        if (!data.message || !data.message.trim()) {
            showTestResult('error', 'Message body is empty. Type a message in the composer first.');
            return;
        }
        if (data.user_ids.length === 0 && data.emails.length === 0 && data.phones.length === 0) {
            showTestResult('error', 'Pick at least one recipient or enter a phone/email.');
            return;
        }

        $('#nc-test-result-panel').addClass('d-none').empty();
        var $btn = $('#nc-test-submit').prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> Sending…');

        api('POST', '{{ route("backend.notification_centre.send_test_direct") }}', data)
            .done(function (r) {
                $btn.prop('disabled', false).html('<i class="fa fa-paper-plane me-1"></i> Send Test');
                var html = '<div><strong>' + (r.sent || 0) + '</strong> delivered';
                if (r.failed) html += ' · <strong class="text-danger">' + r.failed + '</strong> failed';
                if (r.skipped && r.skipped.length) html += ' · <strong class="text-warning">' + r.skipped.length + '</strong> skipped';
                html += '</div>';
                if (r.skipped && r.skipped.length) {
                    html += '<div class="small text-muted mt-2"><strong>Skipped:</strong><ul class="mb-0">' +
                        r.skipped.map(function (s) { return '<li>' + escapeHtml(s) + '</li>'; }).join('') + '</ul></div>';
                }
                if (r.errors && r.errors.length) {
                    html += '<div class="small text-danger mt-2"><strong>Errors:</strong><ul class="mb-0">' +
                        r.errors.map(function (e) { return '<li>' + escapeHtml(e) + '</li>'; }).join('') + '</ul></div>';
                }
                showTestResult((r.failed || 0) > 0 ? 'warning' : 'success', html);
            })
            .fail(function (xhr) {
                $btn.prop('disabled', false).html('<i class="fa fa-paper-plane me-1"></i> Send Test');
                var msg = (xhr.responseJSON && xhr.responseJSON.message) ? xhr.responseJSON.message : ('Request failed (' + xhr.status + ')');
                if (xhr.responseJSON && xhr.responseJSON.errors) {
                    var first = Object.values(xhr.responseJSON.errors)[0];
                    if (first && first.length) msg += ' — ' + first[0];
                }
                showTestResult('error', escapeHtml(msg));
            });
    });

    function showTestResult(level, html) {
        var cls = level === 'success' ? 'alert-success' : (level === 'warning' ? 'alert-warning' : 'alert-danger');
        $('#nc-test-result-panel').removeClass('d-none alert-success alert-warning alert-danger').addClass('alert ' + cls).html(html);
    }


    // ============================================================
    // CAMPAIGN LISTS (Drafts / Scheduled / Sent)
    // ============================================================
    function loadListsForCurrentChannel() {
        // Counts + the currently visible list
        $.get('{{ url("/backend/notification-centre/queue-data") }}', { channel: state.channel })
            .done(function (r) {
                var all = (r && r.rows) || [];
                if (state.view === 'drafts')    renderCampaignList(all.filter(function (n) { return n.status === 'draft'; }), '#nc-drafts-list', 'draft');
                if (state.view === 'scheduled') renderCampaignList(all.filter(function (n) { return n.status === 'scheduled' || n.status === 'running' || n.status === 'paused'; }), '#nc-scheduled-list', 'scheduled');
                if (state.view === 'sent')      renderCampaignList(all.filter(function (n) { return ['sent','failed','cancelled'].indexOf(n.status) !== -1; }), '#nc-sent-list', 'sent');
            });
        updateCounts();
    }

    function loadCampaigns(status) {
        var statusList = status === 'sent' ? ['sent','failed','cancelled']
                       : status === 'scheduled' ? ['scheduled','running','paused']
                       : [status];
        $.get('{{ url("/backend/notification-centre/queue-data") }}', { channel: state.channel })
            .done(function (r) {
                var all = (r && r.rows) || [];
                var filtered = all.filter(function (n) { return statusList.indexOf(n.status) !== -1; });
                var target = status === 'draft' ? '#nc-drafts-list'
                           : status === 'scheduled' ? '#nc-scheduled-list'
                           : '#nc-sent-list';
                renderCampaignList(filtered, target, status);
            })
            .fail(function (xhr) {
                var target = status === 'draft' ? '#nc-drafts-list'
                           : status === 'scheduled' ? '#nc-scheduled-list'
                           : '#nc-sent-list';
                $(target).html('<div class="nc-empty text-danger"><i class="fa fa-exclamation-triangle"></i><div>Failed to load: ' + (xhr.statusText || 'error') + '</div></div>');
            });
    }

    function renderCampaignList(items, selector, type) {
        var $el = $(selector);
        if (!items || items.length === 0) {
            $el.html('<div class="nc-empty"><i class="fa fa-inbox"></i><div>Nothing here yet</div></div>');
            return;
        }
        var html = '<div class="table-responsive"><table class="table align-middle">' +
            '<thead><tr><th>Title</th><th>Status</th><th>Targets</th><th>When</th><th></th></tr></thead><tbody>';
        items.forEach(function (n) {
            var st = n.status || 'draft';
            html += '<tr>' +
                '<td><strong>' + escapeHtml(n.title || n.message || '(no title)') + '</strong>' +
                '<div class="text-muted small">' + escapeHtml(((n.message || '').substring(0, 80))) + '</div></td>' +
                '<td><span class="nc-status nc-status-' + st + '">' + st + '</span></td>' +
                '<td>' + (n.total_targets || n.sent_count || 0) + (n.failed_count ? ' <span class="text-danger">/' + n.failed_count + ' failed</span>' : '') + '</td>' +
                '<td><span class="text-muted small">' + escapeHtml(n.scheduled_at || n.sent_at || n.created_at || '') + '</span></td>' +
                '<td class="text-end">';
            if (st === 'draft') {
                html += '<button class="btn btn-sm btn-outline-primary nc-edit-btn" data-id="' + n.id + '"><i class="fa fa-edit"></i></button> ';
                html += '<button class="btn btn-sm btn-outline-danger nc-delete-btn" data-id="' + n.id + '"><i class="fa fa-trash"></i></button>';
            } else if (st === 'scheduled') {
                html += '<button class="btn btn-sm btn-outline-secondary nc-cancel-btn" data-id="' + n.id + '">Cancel</button>';
            } else {
                html += '<button class="btn btn-sm btn-outline-secondary nc-logs-btn" data-id="' + n.id + '">Logs</button>';
            }
            html += '</td></tr>';
        });
        html += '</tbody></table></div>';
        $el.html(html);
    }

    function updateCounts() {
        // The badge shows campaigns that need attention: drafts, scheduled, paused.
        // Sent / failed / cancelled aren't pending work, so they're excluded.
        ['push', 'sms', 'email'].forEach(function (ch) {
            $.get('{{ url("/backend/notification-centre/queue-data") }}', { channel: ch })
                .done(function (r) {
                    var rows = (r && r.rows) || [];
                    var pending = rows.filter(function (n) {
                        return ['draft', 'scheduled', 'running', 'paused'].indexOf(n.status) !== -1;
                    }).length;
                    var $badge = $('#nc-count-' + ch).text(pending);
                    // Hide the badge entirely when there's nothing pending — keeps the tab strip clean
                    $badge.toggleClass('d-none', pending === 0);
                });
        });
    }

    // Cancel scheduled
    $(document).on('click', '.nc-cancel-btn', function () {
        if (!confirm('Cancel this scheduled campaign?')) return;
        var id = $(this).data('id');
        api('POST', '{{ url("/backend/notification-centre") }}/' + id + '/cancel', {})
            .done(function () { showToast('Cancelled.'); loadCampaigns('scheduled'); updateCounts(); });
    });

    // Edit draft → load into composer
    $(document).on('click', '.nc-edit-btn', function () {
        var id = $(this).data('id');
        $.get('{{ url("/backend/notification-centre/queue-data") }}', { channel: state.channel })
            .done(function (r) {
                var rows = (r && r.rows) || [];
                var n = rows.find(function (x) { return x.id === id; });
                if (!n) { showToast('Could not load campaign.', 'error'); return; }
                state.editingId = n.id;
                $('#nc-campaign-name').val(n.campaign_name || '');
                $('#nc-title').val(n.title || '');
                $('#nc-body').val(n.message || '').trigger('input');
                state.include = (n.audience_spec && n.audience_spec.include) || [];
                state.exclude = (n.audience_spec && n.audience_spec.exclude) || [];
                state.handpicked = []; // chips not rehydrated; user can re-pick if needed
                $('#nc-raw-recipients').val(((n.audience_spec && n.audience_spec.raw_recipients) || []).join(', '));
                renderIncludeChips(); renderExcludeChips(); renderHandpickChips();
                debouncedEstimate();
                $('.nc-subnav-btn[data-view="compose"]').click();
            });
    });

    $(document).on('click', '.nc-logs-btn', function () {
        var id = $(this).data('id');
        window.open('{{ url("/backend/notification-centre") }}/' + id + '/logs', '_blank');
    });

    // ============================================================
    // TEMPLATES
    // ============================================================
    function loadTemplates() {
        $.get('{{ route("backend.notification_centre.templates.list") }}', { channel: state.channel })
            .done(function (r) {
                var $el = $('#nc-templates-list');
                if (!r.data || r.data.length === 0) {
                    $el.html('<div class="nc-empty"><i class="fa fa-bookmark"></i><div>No templates for ' + state.channel + '</div></div>');
                    return;
                }
                var html = '<div class="row g-2">';
                r.data.forEach(function (t) {
                    html += '<div class="col-md-6"><div class="border rounded p-3">' +
                        '<div class="d-flex justify-content-between align-items-start">' +
                            '<div><strong>' + escapeHtml(t.name) + '</strong><div class="text-muted small">' + escapeHtml((t.title || '')) + '</div></div>' +
                            '<div>' +
                                '<button class="btn btn-sm btn-outline-primary nc-tpl-load" data-id="' + t.id + '" title="Load into composer"><i class="fa fa-arrow-up"></i></button> ' +
                                '<button class="btn btn-sm btn-outline-secondary nc-tpl-edit" data-id="' + t.id + '"><i class="fa fa-edit"></i></button> ' +
                                '<button class="btn btn-sm btn-outline-danger nc-tpl-delete" data-id="' + t.id + '"><i class="fa fa-trash"></i></button>' +
                            '</div>' +
                        '</div>' +
                        '<div class="mt-2 small text-muted" style="white-space:pre-wrap;">' + escapeHtml(((t.body || '').substring(0, 120))) + '</div>' +
                        '</div></div>';
                });
                html += '</div>';
                $el.html(html);
                $el.data('cache', r.data);
            });
    }

    $('#nc-new-template-btn, #nc-save-template-btn').on('click', function (e) {
        e.preventDefault();
        var fromComposer = $(this).attr('id') === 'nc-save-template-btn';
        $('#nc-tpl-id').val('');
        $('#nc-tpl-name').val(fromComposer ? ($('#nc-campaign-name').val() || '') : '');
        $('#nc-tpl-title').val(fromComposer ? $('#nc-title').val() : '');
        $('#nc-tpl-body').val(fromComposer ? $('#nc-body').val() : '');
        $('#nc-tpl-title-wrap').toggleClass('d-none', isSmsChannel());
        new bootstrap.Modal(document.getElementById('nc-template-modal')).show();
    });

    $('#nc-load-template-btn').on('click', function () {
        $('.nc-subnav-btn[data-view="templates"]').click();
    });

    $(document).on('click', '.nc-tpl-load', function () {
        var id = $(this).data('id');
        var cache = $('#nc-templates-list').data('cache') || [];
        var t = cache.find(function (x) { return x.id === id; });
        if (!t) return;
        $('#nc-title').val(t.title || '');
        $('#nc-body').val(t.body || '').trigger('input');
        $('.nc-subnav-btn[data-view="compose"]').click();
        showToast('Template loaded into composer.');
    });

    $(document).on('click', '.nc-tpl-edit', function () {
        var id = $(this).data('id');
        var cache = $('#nc-templates-list').data('cache') || [];
        var t = cache.find(function (x) { return x.id === id; });
        if (!t) return;
        $('#nc-tpl-id').val(t.id);
        $('#nc-tpl-name').val(t.name);
        $('#nc-tpl-title').val(t.title || '');
        $('#nc-tpl-body').val(t.body || '');
        $('#nc-tpl-title-wrap').toggleClass('d-none', isSmsChannel());
        new bootstrap.Modal(document.getElementById('nc-template-modal')).show();
    });

    $(document).on('click', '.nc-tpl-delete', function () {
        var id = $(this).data('id');
        if (!confirm('Delete this template?')) return;
        api('DELETE', '{{ url("/backend/notification-centre/templates-library") }}/' + id, {})
            .done(function () { showToast('Deleted.'); loadTemplates(); });
    });

    $('#nc-tpl-save').on('click', function () {
        var data = {
            id:      $('#nc-tpl-id').val() || null,
            name:    $('#nc-tpl-name').val(),
            channel: state.channel,
            title:   $('#nc-tpl-title').val(),
            body:    $('#nc-tpl-body').val(),
        };
        if (!data.name || !data.body) { showToast('Name and body are required.', 'warning'); return; }
        api('POST', '{{ route("backend.notification_centre.templates.save") }}', data)
            .done(function () {
                bootstrap.Modal.getInstance(document.getElementById('nc-template-modal')).hide();
                showToast('Template saved.');
                if (state.view === 'templates') loadTemplates();
            })
            .fail(function (xhr) {
                showToast('Failed to save: ' + ((xhr.responseJSON && xhr.responseJSON.message) || 'error'), 'error');
            });
    });

    // ============================================================
    // SUPPRESSION LIST
    // ============================================================
    $('#nc-suppressions-link').on('click', function () {
        loadSuppressions();
        new bootstrap.Modal(document.getElementById('nc-suppression-modal')).show();
    });

    $('#nc-supp-add-btn').on('click', function () {
        $('#nc-supp-add-form').toggleClass('d-none');
    });
    $('#nc-supp-cancel').on('click', function () {
        $('#nc-supp-add-form').addClass('d-none');
    });

    $('#nc-supp-save').on('click', function () {
        var data = {
            channel: $('#nc-supp-add-channel').val(),
            value:   $('#nc-supp-add-value').val().trim(),
            reason:  $('#nc-supp-add-reason').val(),
        };
        if (!data.value) { showToast('Enter an email or phone.', 'warning'); return; }
        api('POST', '{{ route("backend.notification_centre.suppressions.add") }}', data)
            .done(function () {
                $('#nc-supp-add-value').val('');
                $('#nc-supp-add-form').addClass('d-none');
                loadSuppressions();
                showToast('Added to suppression list.');
            });
    });

    $('#nc-supp-channel-filter, #nc-supp-search').on('input change', debounce(loadSuppressions, 250));

    function loadSuppressions() {
        $.get('{{ route("backend.notification_centre.suppressions.list") }}', {
            channel: $('#nc-supp-channel-filter').val(),
            q: $('#nc-supp-search').val(),
        }).done(function (r) {
            var $el = $('#nc-supp-list');
            if (!r.data || r.data.length === 0) {
                $el.html('<div class="nc-empty"><i class="fa fa-ban"></i><div>No suppressed recipients</div></div>');
                return;
            }
            var html = '<table class="table align-middle"><thead><tr><th>Channel</th><th>Value</th><th>Reason</th><th>When</th><th></th></tr></thead><tbody>';
            r.data.forEach(function (s) {
                html += '<tr>' +
                    '<td><span class="nc-status nc-status-failed">' + escapeHtml(s.channel) + '</span></td>' +
                    '<td>' + escapeHtml(s.value) + '</td>' +
                    '<td>' + escapeHtml(s.reason || '—') + '</td>' +
                    '<td><span class="text-muted small">' + escapeHtml(s.suppressed_at || s.created_at || '') + '</span></td>' +
                    '<td class="text-end"><button class="btn btn-sm btn-outline-danger nc-supp-delete" data-id="' + s.id + '"><i class="fa fa-trash"></i></button></td>' +
                    '</tr>';
            });
            html += '</tbody></table>';
            $el.html(html);
        });
    }

    $(document).on('click', '.nc-supp-delete', function () {
        var id = $(this).data('id');
        if (!confirm('Remove from suppression list? They will be eligible to receive notifications again.')) return;
        api('DELETE', '{{ url("/backend/notification-centre/suppressions") }}/' + id, {})
            .done(function () { loadSuppressions(); });
    });

    // ============================================================
    // INIT
    // ============================================================
    applyChannelUI();
    renderIncludeChips();
    renderExcludeChips();
    debouncedEstimate();
    updateCounts();
});
</script>
@endpush
