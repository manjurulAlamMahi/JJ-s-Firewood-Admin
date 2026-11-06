<!-- Auto Pool Tab: weekly rotation of giveaway templates -->
<style>
    .pool-card {
        background: var(--av-surface);
        border: 1px solid var(--av-border);
        border-radius: var(--av-r-sm);
        padding: 1.1rem 1.25rem;
        height: 100%;
    }
    .pool-card h6 {
        text-transform: uppercase;
        letter-spacing: .04em;
        font-size: 11px;
        color: var(--av-text-muted, #6c757d);
        margin-bottom: .6rem;
    }
    .pool-live-item { display: flex; gap: .75rem; align-items: center; }
    .pool-live-item + .pool-live-item { margin-top: .75rem; padding-top: .75rem; border-top: 1px solid var(--av-border); }
    .pool-live-item img, .pool-thumb { width: 48px; height: 48px; object-fit: cover; border-radius: 8px; border: 1px solid var(--av-border); }
    .pool-big { font-size: 1.05rem; font-weight: 600; line-height: 1.25; }
    .pool-muted { color: var(--av-text-muted, #6c757d); font-size: 13px; }
    .pool-health-bad { border-left: 4px solid #9b2d2d; }
    .pool-health-ok { border-left: 4px solid #28a745; }
    .pool-row-paused td { opacity: .55; }
    .pool-next-badge { background: #c89538; color: #fff; font-size: 11px; padding: 2px 7px; border-radius: 10px; }
    #poolTemplateModal .pkg-row { display: flex; align-items: center; gap: .5rem; padding: .35rem 0; border-bottom: 1px solid var(--av-border); }
    #poolTemplateModal .pkg-row label { flex: 1; margin: 0; }
    #poolTemplateModal .pkg-row input[type=number] { width: 110px; }
    #poolTemplateModal .tpl-img-preview { max-height: 80px; border-radius: 6px; margin-top: .4rem; }
</style>

<div id="poolHealth" class="pool-card mb-3" style="display:none;"></div>

<div class="row mb-3">
    <div class="col-lg-4 mb-3">
        <div class="pool-card">
            <h6><i class="fe fe-radio"></i> Live now</h6>
            <div id="poolLive" class="pool-muted">Loading…</div>
        </div>
    </div>
    <div class="col-lg-4 mb-3">
        <div class="pool-card">
            <h6><i class="fe fe-skip-forward"></i> Up next</h6>
            <div id="poolUpNext" class="pool-muted">Loading…</div>
        </div>
    </div>
    <div class="col-lg-4 mb-3">
        <div class="pool-card">
            <h6><i class="fe fe-settings"></i> Weekly schedule</h6>
            <div class="custom-control custom-switch mb-2">
                <input type="checkbox" class="custom-control-input" id="poolEnabled">
                <label class="custom-control-label" for="poolEnabled">Auto-rotate from the pool</label>
            </div>
            <div class="form-row">
                <div class="col-7 mb-2">
                    <select id="poolDrawDay" class="form-control form-control-sm"></select>
                </div>
                <div class="col-5 mb-2">
                    <input type="time" id="poolDrawTime" class="form-control form-control-sm">
                </div>
            </div>
            <select id="poolRotation" class="form-control form-control-sm mb-2"></select>
            <div class="d-flex gap-2">
                <button class="btn btn-primary btn-sm" onclick="poolSaveSettings()">Save</button>
                <button class="btn btn-outline-secondary btn-sm ml-2" onclick="poolRunNow()" title="Does what the every-minute server job does, right now">
                    <i class="fe fe-play"></i> Run check now
                </button>
            </div>
        </div>
    </div>
</div>

<div id="poolScheduledWrap" class="mb-3" style="display:none;">
    <h6 class="mb-2">Manually scheduled giveaways <small class="pool-muted">— these take priority over the pool</small></h6>
    <div class="modern-table">
        <table class="table mb-0">
            <thead><tr><th>Giveaway</th><th>Starts</th><th>Draws</th><th></th></tr></thead>
            <tbody id="poolScheduled"></tbody>
        </table>
    </div>
</div>

<div class="filter-section">
    <div class="row align-items-center">
        <div class="col-md-5 mb-2 mb-md-0">
            <strong>Giveaway pool</strong>
            <div class="pool-muted">Prizes that rotate each week. Pause any you don't want picked for a while.</div>
        </div>
        <div class="col-md-7 text-md-right">
            <div class="d-inline-flex align-items-center flex-wrap" style="gap:.5rem;">
                <select id="poolImportSelect" class="form-control form-control-sm" style="width:auto;max-width:240px;">
                    <option value="">Copy from an existing giveaway…</option>
                </select>
                <button class="btn btn-outline-primary btn-sm" onclick="poolImport()">Add to pool</button>
                <button class="btn btn-success btn-sm" onclick="poolOpenTemplate()"><i class="fe fe-plus"></i> New template</button>
            </div>
        </div>
    </div>
</div>

<div class="modern-table">
    <table class="table table-hover mb-0">
        <thead>
            <tr>
                <th style="width:60px;"></th>
                <th>Prize</th>
                <th>Value</th>
                <th>Packages</th>
                <th>Used</th>
                <th>Status</th>
                <th class="text-right">Actions</th>
            </tr>
        </thead>
        <tbody id="poolTemplates">
            <tr><td colspan="7" class="text-center pool-muted py-4">Loading…</td></tr>
        </tbody>
    </table>
</div>

<!-- Template modal -->
<div class="modal fade" id="poolTemplateModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <form class="modal-content" id="poolTemplateForm" enctype="multipart/form-data" onsubmit="return poolSaveTemplate(event)">
            <div class="modal-header">
                <h5 class="modal-title" id="poolTemplateTitle">New pool template</h5>
                <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
                <input type="hidden" name="template_id" id="tplId">
                <div class="form-row">
                    <div class="form-group col-md-8">
                        <label>Prize name *</label>
                        <input type="text" name="prize_name" id="tplName" class="form-control" required maxlength="255">
                    </div>
                    <div class="form-group col-md-4">
                        <label>Prize value ($) *</label>
                        <input type="number" step="0.01" min="0" name="prize_value" id="tplValue" class="form-control" required>
                    </div>
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="tplDesc" class="form-control" rows="3"></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label>Prize type</label>
                        <select name="prize_type" id="tplType" class="form-control">
                            <option value="bundle">Bundle</option>
                            <option value="discount">Discount</option>
                            <option value="accessory">Accessory</option>
                            <option value="others">Others</option>
                        </select>
                    </div>
                    <div class="form-group col-md-4">
                        <label>Max entries per user</label>
                        <input type="number" min="1" name="max_entries_per_user" id="tplMax" class="form-control" placeholder="No limit">
                    </div>
                    <div class="form-group col-md-4">
                        <label>Animation</label>
                        <select name="animation_style" id="tplAnim" class="form-control">
                            <option value="lottery_balls">Lottery balls</option>
                            <option value="raffle_tickets">Raffle tickets</option>
                        </select>
                    </div>
                </div>
                <div class="form-group">
                    <label>Image</label>
                    <input type="file" name="image" accept="image/*" class="form-control-file">
                    <img id="tplImgPreview" class="tpl-img-preview" style="display:none;">
                </div>
                <div class="form-group">
                    <label>Entry packages <small class="pool-muted">(leave price blank to use the package's normal price)</small></label>
                    <div id="tplPackages" class="pool-muted">Loading packages…</div>
                </div>
                <div class="custom-control custom-switch">
                    <input type="checkbox" class="custom-control-input" id="tplActive" checked>
                    <label class="custom-control-label" for="tplActive">In rotation</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary" id="tplSaveBtn">Save template</button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
(function () {
    const base = '/giveaway-centre/pool';
    let poolPackages = null;
    let poolLoaded = false;

    function esc(v) {
        return $('<div>').text(v == null ? '' : String(v)).html();
    }
    function ok(msg) { (window.toastr ? toastr.success(msg) : alert(msg)); }
    function fail(xhr, fallback) {
        let msg = fallback || 'Something went wrong';
        if (xhr && xhr.responseJSON) {
            if (xhr.responseJSON.errors) msg = Object.values(xhr.responseJSON.errors).flat().join('\n');
            else if (xhr.responseJSON.message) msg = xhr.responseJSON.message;
        }
        (window.toastr ? toastr.error(msg) : alert(msg));
    }
    function post(url, data) {
        return $.ajax({ url, method: 'POST', data: data || {}, headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')} });
    }

    window.loadPool = function () {
        $.get(base + '/data').done(render).fail(xhr => {
            $('#poolTemplates').html('<tr><td colspan="7" class="text-danger text-center py-4">Couldn\'t load the pool. Has the migration been run?</td></tr>');
            fail(xhr, 'Could not load pool');
        });
    };

    function render(d) {
        poolLoaded = true;

        // Health
        const h = d.health;
        if (h.healthy) {
            $('#poolHealth').attr('class', 'pool-card mb-3 pool-health-ok').html(
                '<i class="fe fe-check-circle text-success"></i> Scheduler running — last check ' + esc(h.last_heartbeat) + '.'
            ).show();
        } else {
            $('#poolHealth').attr('class', 'pool-card mb-3 pool-health-bad').html(
                '<strong><i class="fe fe-alert-triangle"></i> The server scheduler isn\'t running</strong> — ' +
                (h.last_heartbeat ? 'last check ' + esc(h.last_heartbeat) : 'it has never run') +
                '. Draws won\'t happen and the next giveaway won\'t start until the cron job is set up on the server.'
            ).show();
        }

        // Settings
        const s = d.settings;
        $('#poolEnabled').prop('checked', !!s.enabled);
        $('#poolDrawDay').html(Object.entries(d.days).map(([k, v]) => `<option value="${k}">${esc(v)}</option>`).join('')).val(String(s.draw_day));
        $('#poolDrawTime').val(s.draw_time);
        $('#poolRotation').html(Object.entries(d.modes).map(([k, v]) => `<option value="${k}">${esc(v)}</option>`).join('')).val(s.rotation_mode);

        // Live
        if (!d.live.length) {
            $('#poolLive').html('<div class="pool-muted">Nothing live. The next giveaway starts on the next check.</div>');
        } else {
            $('#poolLive').html(d.live.map(g => `
                <div class="pool-live-item">
                    <img src="${esc(g.image)}" alt="">
                    <div>
                        <div class="pool-big">${esc(g.name)}</div>
                        <div class="pool-muted">Draws ${esc(g.draws_at)} · ${g.entries} entries
                            ${g.source === 'pool' ? '· <span class="badge badge-light">pool</span>' : '· <span class="badge badge-light">manual</span>'}</div>
                        ${g.auto_draw ? '' : '<div class="text-danger small mt-1">Auto-draw is off — the pool is paused until you draw this one manually.</div>'}
                    </div>
                </div>`).join(''));
        }

        // Up next
        const n = d.up_next;
        if (n.type === 'none') {
            $('#poolUpNext').html(s.enabled
                ? '<div class="pool-muted">The pool is empty — add a template below.</div>'
                : '<div class="pool-muted">Auto-rotate is off. Only scheduled giveaways will start.</div>');
        } else {
            $('#poolUpNext').html(`
                <div class="pool-live-item">
                    <img src="${esc(n.image)}" alt="">
                    <div>
                        <div class="pool-big">${esc(n.name)}</div>
                        <div class="pool-muted">${d.live.length ? 'Goes live after the current draw' : 'Goes live within a minute'}
                            · ${n.type === 'scheduled' ? '<span class="badge badge-info">scheduled</span>' : '<span class="badge badge-light">pool pick</span>'}</div>
                    </div>
                </div>
                ${n.type === 'pool' ? '<button class="btn btn-outline-secondary btn-sm mt-2" onclick="poolReroll()"><i class="fe fe-shuffle"></i> Re-roll</button>' : ''}
                ${d.live.length && n.type === 'pool' ? `<div class="pool-muted mt-2">Will draw ${esc(d.next_slot)}</div>` : ''}
            `);
        }

        // Scheduled
        if (d.scheduled.length) {
            $('#poolScheduled').html(d.scheduled.map(g => `
                <tr>
                    <td><a href="/giveaway-centre/${g.id}/edit">${esc(g.name)}</a></td>
                    <td>${esc(g.starts || '—')}</td>
                    <td>${esc(g.draws || '—')}</td>
                    <td>${g.takes_next_slot ? '<span class="pool-next-badge">takes next slot</span>' : ''}</td>
                </tr>`).join(''));
            $('#poolScheduledWrap').show();
        } else {
            $('#poolScheduledWrap').hide();
        }

        // Import list
        $('#poolImportSelect').html('<option value="">Copy from an existing giveaway…</option>' +
            d.importable.map(g => `<option value="${g.id}">${esc(g.name)}</option>`).join(''));

        // Templates
        if (!d.templates.length) {
            $('#poolTemplates').html('<tr><td colspan="7" class="text-center pool-muted py-4">No templates yet. Add one, or copy a past giveaway into the pool.</td></tr>');
            return;
        }
        $('#poolTemplates').html(d.templates.map(t => `
            <tr class="${t.is_active ? '' : 'pool-row-paused'}">
                <td><img class="pool-thumb" src="${esc(t.image)}" alt=""></td>
                <td>
                    <strong>${esc(t.name)}</strong> ${t.is_next ? '<span class="pool-next-badge">up next</span>' : ''}
                    <div class="pool-muted">${esc(t.prize_type)}</div>
                </td>
                <td>$${esc(t.prize_value)}</td>
                <td>${t.packages}</td>
                <td>${t.times_used}× ${t.last_used ? '<div class="pool-muted">last ' + esc(t.last_used) + '</div>' : ''}</td>
                <td>
                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" id="tplT${t.id}" ${t.is_active ? 'checked' : ''} onchange="poolToggle(${t.id})">
                        <label class="custom-control-label" for="tplT${t.id}">${t.is_active ? 'In rotation' : 'Paused'}</label>
                    </div>
                </td>
                <td class="text-right text-nowrap">
                    ${t.is_active && !t.is_next ? `<button class="btn btn-sm btn-outline-secondary" onclick="poolUseNext(${t.id})" title="Make this next week's prize">Use next</button>` : ''}
                    <button class="btn btn-sm btn-outline-primary" onclick="poolOpenTemplate(${t.id})"><i class="fe fe-edit-2"></i></button>
                    <button class="btn btn-sm btn-outline-danger" onclick="poolDelete(${t.id})"><i class="fe fe-trash-2"></i></button>
                </td>
            </tr>`).join(''));
    }

    window.poolSaveSettings = function () {
        post(base + '/settings', {
            enabled: $('#poolEnabled').is(':checked') ? 1 : 0,
            draw_day: $('#poolDrawDay').val(),
            draw_time: $('#poolDrawTime').val(),
            rotation_mode: $('#poolRotation').val(),
        }).done(r => { ok(r.message); loadPool(); }).fail(x => fail(x));
    };

    window.poolRunNow = function () {
        post(base + '/run-now').done(r => { (r.success ? ok : (m => toastr.error(m)))(r.message); loadPool(); }).fail(x => fail(x));
    };

    window.poolReroll = function () {
        post(base + '/reroll').done(r => { ok(r.message); loadPool(); }).fail(x => fail(x));
    };

    window.poolUseNext = function (id) {
        post(base + '/templates/' + id + '/next').done(r => { ok(r.message); loadPool(); }).fail(x => fail(x));
    };

    window.poolToggle = function (id) {
        post(base + '/templates/' + id + '/toggle').done(r => { ok(r.message); loadPool(); }).fail(x => { fail(x); loadPool(); });
    };

    window.poolDelete = function (id) {
        if (!confirm('Remove this template from the pool? Past giveaways made from it are not affected.')) return;
        $.ajax({ url: base + '/templates/' + id, method: 'DELETE', headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')} })
            .done(r => { ok(r.message); loadPool(); }).fail(x => fail(x));
    };

    window.poolImport = function () {
        const id = $('#poolImportSelect').val();
        if (!id) return;
        post(base + '/import/' + id).done(r => { ok(r.message); loadPool(); }).fail(x => fail(x));
    };

    function loadPackages() {
        if (poolPackages) return $.Deferred().resolve(poolPackages).promise();
        return $.get('/giveaway-centre/entry-packages/data').then(r => (poolPackages = r.data || []));
    }

    function renderPackages(selected) {
        selected = selected || {};
        if (!poolPackages.length) {
            $('#tplPackages').html('<div class="pool-muted">No entry packages set up yet.</div>');
            return;
        }
        $('#tplPackages').html(poolPackages.map(p => {
            const on = Object.prototype.hasOwnProperty.call(selected, p.id);
            const custom = on && selected[p.id] !== null ? selected[p.id] : '';
            return `<div class="pkg-row">
                <input type="checkbox" name="entry_packages[]" value="${p.id}" id="tplPkg${p.id}" ${on ? 'checked' : ''}>
                <label for="tplPkg${p.id}">${esc(p.name)} <span class="pool-muted">· ${p.entry_quantity} entries · $${esc(p.price)}${p.status ? '' : ' · inactive'}</span></label>
                <input type="number" step="0.01" min="0" class="form-control form-control-sm" name="package_custom_price[${p.id}]" placeholder="$${esc(p.price)}" value="${esc(custom)}">
            </div>`;
        }).join(''));
    }

    window.poolOpenTemplate = function (id) {
        const f = document.getElementById('poolTemplateForm');
        f.reset();
        $('#tplId').val('');
        $('#tplImgPreview').hide();
        $('#tplActive').prop('checked', true);
        $('#poolTemplateTitle').text(id ? 'Edit pool template' : 'New pool template');

        loadPackages().then(() => {
            if (!id) { renderPackages({}); $('#poolTemplateModal').modal('show'); return; }
            $.get(base + '/templates/' + id).done(t => {
                $('#tplId').val(t.id);
                $('#tplName').val(t.prize_name);
                $('#tplValue').val(t.prize_value);
                $('#tplDesc').val(t.description || '');
                $('#tplType').val(t.prize_type);
                $('#tplMax').val(t.max_entries_per_user || '');
                $('#tplAnim').val(t.animation_style || 'lottery_balls');
                $('#tplActive').prop('checked', !!t.is_active);
                if (t.image) $('#tplImgPreview').attr('src', t.image).show();
                renderPackages(t.packages || {});
                $('#poolTemplateModal').modal('show');
            }).fail(x => fail(x));
        }, x => fail(x, 'Could not load entry packages'));
    };

    window.poolSaveTemplate = function (e) {
        e.preventDefault();
        const id = $('#tplId').val();
        const fd = new FormData(document.getElementById('poolTemplateForm'));
        fd.delete('template_id');
        fd.append('is_active', $('#tplActive').is(':checked') ? 1 : 0);
        // only send custom prices for ticked packages
        poolPackages.forEach(p => { if (!$('#tplPkg' + p.id).is(':checked')) fd.delete(`package_custom_price[${p.id}]`); });

        $('#tplSaveBtn').prop('disabled', true);
        $.ajax({
            url: base + '/templates' + (id ? '/' + id : ''),
            method: 'POST',
            data: fd, processData: false, contentType: false,
            headers: {'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')},
        }).done(r => {
            ok(r.message);
            $('#poolTemplateModal').modal('hide');
            loadPool();
        }).fail(x => fail(x)).always(() => $('#tplSaveBtn').prop('disabled', false));
        return false;
    };
})();
</script>
@endpush
