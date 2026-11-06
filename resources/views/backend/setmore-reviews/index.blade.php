@extends('backend.master')

@section('title', 'Setmore Matching')

@push('styles')
<style>
    /* ===== Setmore Reviews — visual lift ===== */

    .smr-header {
        background: var(--av-surface);
        color: var(--av-text);
        border-radius: var(--av-r);
        padding: 1.25rem;
        margin-bottom: 1.25rem;
        border: 1px solid var(--av-border);
    }
    .smr-header h2 { color: var(--av-text); font-weight: 700; margin: 0 0 .35rem 0; }
    .smr-header .subtitle { color: var(--av-muted); font-size: .95rem; margin: 0; }
    .smr-header .smr-stat {
        background: var(--av-surface-2);
        border: 1px solid var(--av-border);
        color: var(--av-text);
        padding: .45rem .9rem;
        border-radius: 8px;
        font-weight: 600;
        font-size: .9rem;
        display: inline-flex; align-items: center; gap: .4rem;
    }
    .smr-header .smr-stat.warn { background: #fef3c7; border-color: #fcd34d; color: #92400e; }
    .smr-header .smr-stat.muted { opacity: .7; }

    /* Auto-apply toggle card */
    .smr-autoapply {
        background: var(--av-surface);
        border: 1px solid var(--av-border);
        border-left: 4px solid var(--av-primary);
        border-radius: 10px;
        padding: .85rem 1rem;
        margin-bottom: 1.25rem;
        display: flex; align-items: center; gap: .75rem; flex-wrap: wrap;
    }
    .smr-autoapply .title { font-weight: 600; color: var(--av-text); margin: 0; }
    .smr-autoapply .desc  { color: var(--av-muted); font-size: .85rem; margin: .15rem 0 0 0; }
    .smr-autoapply .form-check-input {
        width: 2.6rem; height: 1.4rem;
        cursor: pointer;
    }
    .smr-autoapply .form-check-input:checked {
        background-color: #10b981;
        border-color: #10b981;
    }
    .smr-autoapply .status-pill {
        font-size: .75rem; font-weight: 600; padding: .25rem .65rem; border-radius: 999px;
    }
    .smr-autoapply .status-pill.on  { background: #d1fae5; color: #065f46; }
    .smr-autoapply .status-pill.off { background: #f3f4f6; color: #6b7280; }

    /* Tabs — readable in both states */
    .smr-tabs {
        border-bottom: 1px solid var(--av-border);
        margin-bottom: 1.25rem;
        padding-left: 0;
        list-style: none;
        display: flex;
        gap: .25rem;
        flex-wrap: nowrap;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
    }
    .smr-tabs::-webkit-scrollbar { display: none; }
    .smr-tabs .nav-link {
        color: var(--av-muted) !important;
        font-weight: 500;
        padding: .65rem 1rem;
        border: 1px solid transparent;
        border-bottom: none;
        border-radius: 8px 8px 0 0;
        margin-bottom: -1px;
        background: transparent;
        transition: all 0.15s;
        white-space: nowrap;
    }
    .smr-tabs .nav-link:hover {
        color: var(--av-primary) !important;
        background: var(--av-surface-2);
    }
    .smr-tabs .nav-link.active {
        color: var(--av-primary) !important;
        background: var(--av-surface);
        border-color: var(--av-border) var(--av-border) transparent;
        border-bottom: 2px solid var(--av-surface);
        font-weight: 600;
    }
    .smr-tabs .nav-link .badge {
        margin-left: .3rem;
        font-size: .7rem;
        font-weight: 600;
    }

    /* Event cards */
    .smr-event {
        background: var(--av-surface);
        border: 1px solid var(--av-border);
        border-radius: 10px;
        padding: 1rem;
        margin-bottom: 1rem;
        transition: box-shadow .15s;
    }
    .smr-event:hover { box-shadow: var(--av-shadow-xs); }
    .smr-event.is-pending  { border-left: 4px solid #f59e0b; }
    .smr-event.is-no-match { border-left: 4px solid #9ca3af; }
    .smr-event.is-applied  { border-left: 4px solid #10b981; }
    .smr-event.is-rejected { border-left: 4px solid #ef4444; }
    .smr-event.is-ignored  { border-left: 4px solid #d1d5db; opacity: 0.7; }
    /* Cancellation events override the left border (cancellations are
       distinct from reschedule events regardless of status). */
    .smr-event.is-cancel    { border-left: 4px solid #dc2626; }
    .smr-event.is-cancel.is-ignored { opacity: 1; }
    .smr-event .smr-section-label {
        font-size: 0.7rem; text-transform: uppercase; font-weight: 700;
        letter-spacing: 0.5px; color: #6b7280; margin-bottom: .4rem;
    }

    /* Empty state */
    .smr-empty {
        text-align: center;
        padding: 3rem 1rem;
        background: var(--av-surface);
        border-radius: 12px;
        border: 1px dashed var(--av-border);
    }
    .smr-empty .icon {
        width: 72px; height: 72px; border-radius: 50%;
        background: linear-gradient(135deg, #10b981, #059669);
        color: #fff;
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 2rem;
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);
        margin-bottom: 1rem;
    }
    .smr-empty h5 { color: var(--av-text); font-weight: 600; }
    .smr-empty p { color: var(--av-muted); margin: 0; }

</style>
@endpush

@section('body')
<div>

    {{-- Header banner --}}
    <div class="smr-header d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h2><i class="fa fa-calendar-check me-2"></i> Setmore Matching</h2>
            <p class="subtitle">
                Each row is a Setmore appointment received via Zapier. Apply to set the delivery date on the matched
                order, or Reject if the match is wrong.
            </p>
        </div>
        <div class="d-flex flex-column gap-2 align-items-end">
            <div class="smr-stat warn">
                <i class="fa fa-clock"></i> {{ $pendingCount ?? 0 }} pending
            </div>
            @if(($noMatchCount ?? 0) > 0)
                <div class="smr-stat muted">
                    <i class="fa fa-question-circle"></i> {{ $noMatchCount }} no-match
                </div>
            @endif
            @if(($ignoredCount ?? 0) > 0)
                <div class="smr-stat muted" title="Setmore events with no matching app order (likely phone customers). Visible in the All tab.">
                    <i class="fa fa-eye-slash"></i> {{ $ignoredCount }} ignored
                </div>
            @endif
        </div>
    </div>

    {{-- Auto-apply toggle --}}
    @if(!($tableMissing ?? false))
        <div class="smr-autoapply" id="smrAutoApplyCard">
            <div class="form-check form-switch m-0">
                <input class="form-check-input" type="checkbox" id="smrAutoApplyToggle"
                       {{ ($autoApply ?? false) ? 'checked' : '' }}>
            </div>
            <div class="flex-grow-1">
                <p class="title">
                    Auto-apply Setmore appointments
                    <span class="status-pill {{ ($autoApply ?? false) ? 'on' : 'off' }}" id="smrAutoApplyStatus">
                        {{ ($autoApply ?? false) ? 'ON' : 'OFF' }}
                    </span>
                </p>
                <p class="desc">
                    <strong>New appointments:</strong> when ON, single-candidate matches auto-apply.
                    0 candidates → no-match. 2+ candidates → manual review.
                    <br>
                    <strong>Reschedules:</strong> always auto-apply (matched by Setmore appointment ID — exact match).
                    <br>
                    <strong>Filter:</strong> only app-placed orders are candidates. Phone orders never match.
                </p>
            </div>
        </div>

        {{-- Auto-cancel toggle (Setmore "Appointment Deleted" → cancel the order) --}}
        <div class="smr-autoapply" id="smrAutoCancelCard">
            <div class="form-check form-switch m-0">
                <input class="form-check-input" type="checkbox" id="smrAutoCancelToggle"
                       {{ ($autoCancel ?? true) ? 'checked' : '' }}>
            </div>
            <div class="flex-grow-1">
                <p class="title">
                    Auto-cancel from Setmore deletions
                    <span class="status-pill {{ ($autoCancel ?? true) ? 'on' : 'off' }}" id="smrAutoCancelStatus">
                        {{ ($autoCancel ?? true) ? 'ON' : 'OFF' }}
                    </span>
                </p>
                <p class="desc">
                    When ON, deleting an appointment in Setmore automatically cancels the linked order:
                    customer gets the cancellation email, paid orders appear in the Refund Queue.
                    <br>
                    <strong>Safety:</strong> already-delivered orders are NEVER auto-cancelled — flagged for manual review instead.
                    <br>
                    When OFF, cancellations queue in <em>Pending</em> for admin to action manually.
                </p>
            </div>
        </div>
    @endif

    @if(!empty($tableMissing))
        <div class="alert alert-warning">
            <strong>Migrations pending.</strong> Run <code>php artisan migrate</code> to enable Setmore reviews.
        </div>
    @endif

    {{-- Tabs --}}
    <ul class="smr-tabs">
        <li class="nav-item">
            <a class="nav-link {{ ($tab ?? 'pending') === 'pending' ? 'active' : '' }}"
               href="{{ url()->current() }}?tab=pending">
                Pending @if(($pendingCount ?? 0) > 0)<span class="badge bg-warning text-dark">{{ $pendingCount }}</span>@endif
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ ($tab ?? '') === 'no_match' ? 'active' : '' }}"
               href="{{ url()->current() }}?tab=no_match">
                No Match @if(($noMatchCount ?? 0) > 0)<span class="badge bg-secondary">{{ $noMatchCount }}</span>@endif
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ ($tab ?? '') === 'cancellations' ? 'active' : '' }}"
               href="{{ url()->current() }}?tab=cancellations">
                <i class="fa fa-ban" style="color: #ef4444;"></i>
                Cancellations
                @if(($cancellationActiveCount ?? 0) > 0)<span class="badge bg-danger">{{ $cancellationActiveCount }}</span>@endif
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ ($tab ?? '') === 'history' ? 'active' : '' }}"
               href="{{ url()->current() }}?tab=history">History</a>
        </li>
        <li class="nav-item">
            <a class="nav-link {{ ($tab ?? '') === 'all' ? 'active' : '' }}"
               href="{{ url()->current() }}?tab=all">All</a>
        </li>
    </ul>

    @if(empty($events) || $events->isEmpty())
        <div class="smr-empty">
            <div class="icon"><i class="fa fa-check"></i></div>
            <h5>All caught up</h5>
            <p>No Setmore events to review.</p>
        </div>
    @else
        <div class="row g-3">
            @foreach($events as $event)
                @php
                    $cands = is_array($event->candidate_order_ids) ? $event->candidate_order_ids : [];
                    $payload = is_array($event->raw_payload) ? $event->raw_payload : [];
                    $statusClass = match($event->status) {
                        'pending'  => 'is-pending',
                        'no_match' => 'is-no-match',
                        'applied'  => 'is-applied',
                        'rejected' => 'is-rejected',
                        'ignored'  => 'is-ignored',
                        default    => '',
                    };

                    // Detect cancellation kind from the raw payload _kind marker.
                    // Used to show a "Cancellation" badge so admin can spot these
                    // at a glance even when scrolling the All tab.
                    $payloadKind = (string) ($payload['_kind'] ?? '');
                    $isCancellation = str_starts_with($payloadKind, 'cancellation');
                    $cancelBadge = match($payloadKind) {
                        'cancellation'                  => ['label' => 'Cancelled', 'colour' => '#dc2626'],
                        'cancellation_no_match'         => ['label' => 'Cancel — no order', 'colour' => '#9ca3af'],
                        'cancellation_duplicate'        => ['label' => 'Cancel — duplicate', 'colour' => '#a3a3a3'],
                        'cancellation_after_delivered'  => ['label' => 'Cancel — DELIVERED', 'colour' => '#b91c1c'],
                        default                         => null,
                    };
                @endphp

                <div class="col-12">
                    <div class="smr-event {{ $statusClass }} @if($isCancellation) is-cancel @endif" id="setmore-event-{{ $event->id }}">
                        @if($cancelBadge)
                            <div style="margin: -4px 0 14px 0;">
                                <span style="display: inline-block; padding: 4px 12px; border-radius: 999px; background: {{ $cancelBadge['colour'] }}; color: #fff; font-size: .75rem; font-weight: 700; letter-spacing: .5px; text-transform: uppercase;">
                                    <i class="fa fa-ban me-1"></i>{{ $cancelBadge['label'] }}
                                </span>
                            </div>
                        @endif
                        <div class="row g-3">
                                {{-- Setmore data column --}}
                                <div class="col-md-4">
                                    <h6 class="smr-section-label">From Setmore</h6>
                                    <div class="fw-bold" style="font-size: 1.05rem;">
                                        {{ $event->customer_name ?? '(no name)' }}
                                    </div>
                                    <div class="small">
                                        <i class="fa fa-envelope text-muted me-1"></i>
                                        {{ $event->customer_email ?? '—' }}
                                    </div>
                                    <div class="small">
                                        <i class="fa fa-phone text-muted me-1"></i>
                                        {{ $event->customer_phone ?? '—' }}
                                    </div>
                                    <div class="mt-2">
                                        @if($event->appointment_at)
                                            <span class="badge bg-primary">
                                                <i class="fa fa-calendar me-1"></i>
                                                {{ $event->appointment_at?->format('D d M Y, H:i') }}
                                            </span>
                                        @else
                                            <span class="badge bg-danger">
                                                <i class="fa fa-exclamation-triangle me-1"></i>
                                                No date received from Zapier
                                            </span>
                                        @endif
                                    </div>
                                    @if($event->appointment_id)
                                        <div class="small text-muted mt-1">
                                            Appt ID: <code>{{ $event->appointment_id }}</code>
                                        </div>
                                    @endif
                                    <div class="small text-muted mt-1">
                                        Received {{ $event->created_at?->diffForHumans() }}
                                    </div>

                                    {{-- Expandable raw payload — useful for diagnosing format issues.
                                         Hidden by default to keep the panel uncluttered. --}}
                                    @php $payload = is_array($event->raw_payload) ? $event->raw_payload : []; @endphp
                                    @if(!empty($payload))
                                        <details class="mt-2">
                                            <summary class="small text-muted" style="cursor: pointer;">
                                                <i class="fa fa-code me-1"></i> Show raw Zapier payload
                                            </summary>
                                            <pre style="background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 4px; padding: 8px; font-size: 11px; margin-top: 6px; max-height: 200px; overflow-y: auto;">{{ json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                        </details>
                                    @endif
                                </div>

                                {{-- Candidates column --}}
                                <div class="col-md-6">
                                    <h6 class="smr-section-label">
                                        Matched Orders
                                        @if($event->candidate_count > 1)
                                            <span class="badge bg-warning text-dark ms-1">{{ $event->candidate_count }} candidates — pick one</span>
                                        @elseif($event->candidate_count === 1)
                                            <span class="badge bg-success ms-1">1 match</span>
                                        @else
                                            <span class="badge bg-secondary ms-1">No matches</span>
                                        @endif
                                    </h6>

                                    @if(empty($cands))
                                        <div class="alert alert-light border mb-0 small">
                                            <i class="fa fa-info-circle me-1"></i>
                                            No unconfirmed paid orders found for this customer.
                                            <br>
                                            <small class="text-muted">
                                                Possible reasons: customer doesn't exist in admin, hasn't paid yet,
                                                or all their orders have already been admin-confirmed.
                                            </small>
                                        </div>
                                    @else
                                        @foreach($cands as $candId)
                                            @php $ord = $candidateOrders[$candId] ?? null; @endphp
                                            @if($ord)
                                                <label class="d-flex align-items-start gap-2 p-2 rounded mb-2 border setmore-candidate-row"
                                                       style="cursor: pointer;">
                                                    @if($event->status === 'pending' && $event->candidate_count > 1)
                                                        <input type="radio" name="cand-{{ $event->id }}" value="{{ $ord->id }}" class="mt-1">
                                                    @endif
                                                    <div class="flex-grow-1">
                                                        <div class="fw-bold">
                                                            #{{ $ord->order_number }}
                                                            <span class="text-muted small">·</span>
                                                            ${{ number_format((float) $ord->total, 2) }}
                                                        </div>
                                                        <div class="small text-muted">
                                                            Created {{ $ord->created_at?->format('d M Y') }}
                                                            · <span class="text-{{ $ord->payment_status === 'paid' ? 'success' : 'warning' }}">{{ $ord->payment_status }}</span>
                                                            · {{ $ord->order_status }}
                                                        </div>
                                                    </div>
                                                </label>
                                            @endif
                                        @endforeach
                                    @endif
                                </div>

                                {{-- Actions column --}}
                                <div class="col-md-2">
                                    @if($event->status === 'pending')
                                        @if(!empty($cands) && $event->appointment_at)
                                            <button class="btn btn-success w-100 mb-2 setmore-apply-btn"
                                                    data-event-id="{{ $event->id }}"
                                                    data-candidate-count="{{ $event->candidate_count }}">
                                                <i class="fa fa-check me-1"></i> Apply
                                            </button>
                                        @elseif(!empty($cands) && !$event->appointment_at)
                                            <div class="alert alert-warning py-2 px-2 mb-2 small">
                                                <i class="fa fa-exclamation-triangle me-1"></i>
                                                Can't apply — Zapier didn't send a date. Check the raw payload below to see what came through, then fix the Zap.
                                            </div>
                                        @endif
                                        <button class="btn btn-outline-danger w-100 setmore-reject-btn"
                                                data-event-id="{{ $event->id }}">
                                            <i class="fa fa-times me-1"></i> Reject
                                        </button>
                                    @elseif($event->status === 'applied')
                                        <div class="alert alert-success py-2 px-2 mb-0 small">
                                            <i class="fa fa-check-circle me-1"></i> Applied
                                            @if($event->appliedOrder)
                                                <br>to #{{ $event->appliedOrder->order_number }}
                                            @endif
                                            <br>
                                            <small class="text-muted">
                                                by {{ $event->reviewer?->name ?? 'admin' }}
                                                · {{ $event->reviewed_at?->diffForHumans() }}
                                            </small>
                                        </div>
                                    @elseif($event->status === 'rejected')
                                        <div class="alert alert-danger py-2 px-2 mb-0 small">
                                            <i class="fa fa-times-circle me-1"></i> Rejected
                                            <br>
                                            <small class="text-muted">
                                                by {{ $event->reviewer?->name ?? 'admin' }}
                                                · {{ $event->reviewed_at?->diffForHumans() }}
                                            </small>
                                            @if($event->notes)
                                                <br><em>"{{ $event->notes }}"</em>
                                            @endif
                                        </div>
                                    @elseif($event->status === 'ignored')
                                        <div class="alert alert-light border py-2 px-2 mb-0 small">
                                            <i class="fa fa-eye-slash me-1 text-muted"></i> Ignored
                                            <br>
                                            <small class="text-muted">
                                                No matching app order — likely a phone-order customer.
                                            </small>
                                        </div>
                                    @else  {{-- no_match --}}
                                        <button class="btn btn-outline-secondary w-100 setmore-reject-btn"
                                                data-event-id="{{ $event->id }}"
                                                title="Mark as resolved — won't appear in pending">
                                            <i class="fa fa-archive me-1"></i> Dismiss
                                        </button>
                                    @endif
                                </div>
                            </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-4">
            {{ $events->links() }}
        </div>
    @endif
</div>

@endsection

@push('scripts')
{{-- Note: master.blade.php uses stack('scripts'), so push not section --}}
<script>
$(function () {
    var token = $('meta[name="csrf-token"]').attr('content');

    // ---- Apply ----
    $('.setmore-apply-btn').on('click', function () {
        var $btn = $(this);
        var eventId = $btn.data('event-id');
        var candCount = parseInt($btn.data('candidate-count')) || 0;

        // For ambiguous events (2+ candidates) admin must pick which one
        var chosenOrderId = null;
        if (candCount > 1) {
            chosenOrderId = $('input[name="cand-' + eventId + '"]:checked').val();
            if (!chosenOrderId) {
                alert('Pick which order to apply the date to first.');
                return;
            }
        }

        var url = '{{ url('setmore-reviews') }}/' + eventId + '/apply';

        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> Applying…');

        $.ajax({
            url: url,
            type: 'POST',
            data: {
                _token: token,
                order_id: chosenOrderId,
            },
        })
        .done(function (resp) {
            if (resp.ok) {
                // Replace the card with a "done" state without full reload
                var $card = $('#setmore-event-' + eventId);
                $card.find('.card-body').css('opacity', 0.4);
                $card.find('.setmore-apply-btn, .setmore-reject-btn').remove();
                $card.find('.col-md-2').html(
                    '<div class="alert alert-success py-2 px-2 mb-0 small">' +
                        '<i class="fa fa-check-circle me-1"></i> Applied' +
                        '<br><small class="text-muted">just now</small>' +
                    '</div>'
                );
                // Decrement pending badge
                decrementPendingBadge();
            } else {
                alert('Apply failed: ' + (resp.message || 'unknown error'));
                $btn.prop('disabled', false).html('<i class="fa fa-check me-1"></i> Apply');
            }
        })
        .fail(function (xhr) {
            var msg = xhr.responseJSON?.message || ('HTTP ' + xhr.status);
            alert('Apply failed: ' + msg);
            $btn.prop('disabled', false).html('<i class="fa fa-check me-1"></i> Apply');
        });
    });

    // ---- Reject / Dismiss ----
    $('.setmore-reject-btn').on('click', function () {
        var $btn = $(this);
        var eventId = $btn.data('event-id');
        var notes = prompt('Reason for rejecting (optional):');
        if (notes === null) return;  // user cancelled the prompt

        var url = '{{ url('setmore-reviews') }}/' + eventId + '/reject';

        $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin me-1"></i> Rejecting…');

        $.ajax({
            url: url,
            type: 'POST',
            data: { _token: token, notes: notes || null },
        })
        .done(function (resp) {
            if (resp.ok) {
                var $card = $('#setmore-event-' + eventId);
                $card.find('.card-body').css('opacity', 0.4);
                $card.find('.setmore-apply-btn, .setmore-reject-btn').remove();
                $card.find('.col-md-2').html(
                    '<div class="alert alert-danger py-2 px-2 mb-0 small">' +
                        '<i class="fa fa-times-circle me-1"></i> Rejected' +
                        '<br><small class="text-muted">just now</small>' +
                    '</div>'
                );
                decrementPendingBadge();
            } else {
                alert('Reject failed: ' + (resp.message || 'unknown error'));
                $btn.prop('disabled', false).html('<i class="fa fa-times me-1"></i> Reject');
            }
        })
        .fail(function (xhr) {
            var msg = xhr.responseJSON?.message || ('HTTP ' + xhr.status);
            alert('Reject failed: ' + msg);
            $btn.prop('disabled', false).html('<i class="fa fa-times me-1"></i> Reject');
        });
    });

    function decrementPendingBadge() {
        // New layout uses .smr-stat.warn for the pending count in the header
        $('.smr-stat.warn').each(function () {
            var $el = $(this);
            var html = $el.html();
            // Extract the number from the text — anywhere after the icon
            var match = html.match(/(\d+)\s+pending/);
            if (match) {
                var current = parseInt(match[1], 10);
                var next = Math.max(0, current - 1);
                $el.html($el.html().replace(/\d+\s+pending/, next + ' pending'));
            }
        });
        // Also update the tab badge count
        $('.smr-tabs .nav-link.active .badge.bg-warning').each(function () {
            var current = parseInt($(this).text(), 10) || 0;
            $(this).text(Math.max(0, current - 1));
        });
    }

    // ============== Auto-apply / auto-cancel toggles ==============
    // Both toggles share the same behaviour, just different endpoints + IDs.
    // Bind via a small helper to avoid duplicate code.
    function wireToggle(toggleId, statusId, url) {
        $('#' + toggleId).on('change', function () {
            var enabled = this.checked;
            var $toggle = $(this).prop('disabled', true);
            var $status = $('#' + statusId);

            $.ajax({
                url: url,
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                data: { enabled: enabled ? 1 : 0 }
            })
            .done(function (r) {
                if (r && r.ok) {
                    $status.text(r.enabled ? 'ON' : 'OFF')
                           .removeClass('on off')
                           .addClass(r.enabled ? 'on' : 'off');
                    // Brief flash to confirm
                    $status.css('transition', 'background-color 0.6s');
                    $status.css('background-color', r.enabled ? '#86efac' : '#e5e7eb');
                    setTimeout(function () { $status.css('background-color', ''); }, 600);
                } else {
                    alert((r && r.message) || 'Failed to update setting.');
                    $toggle.prop('checked', !enabled);
                }
            })
            .fail(function (xhr) {
                var msg = xhr.responseJSON?.message || ('HTTP ' + xhr.status);
                alert('Could not update setting: ' + msg);
                $toggle.prop('checked', !enabled);
            })
            .always(function () {
                $toggle.prop('disabled', false);
            });
        });
    }

    wireToggle('smrAutoApplyToggle',  'smrAutoApplyStatus',  '{{ route('setmore-reviews.toggle-auto-apply') }}');
    wireToggle('smrAutoCancelToggle', 'smrAutoCancelStatus', '{{ route('setmore-reviews.toggle-auto-cancel') }}');
});
</script>
@endpush
