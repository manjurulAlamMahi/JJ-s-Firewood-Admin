@extends('backend.master')

@section('title', 'Delivery Email Log')

@section('content')
<div class="container-fluid p-3">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="m-0"><i class="fa fa-envelope me-2"></i> Delivery Email Log</h2>
        <a href="{{ route('setmore-reviews.index') }}" class="btn btn-sm btn-outline-secondary">
            &larr; Back to Setmore Matching
        </a>
    </div>

    <p class="text-muted" style="font-size:14px;">
        Server-sent delivery emails for phone &amp; WooCommerce bookings (booked / rescheduled / cancelled).
        App-order emails are not shown here — they have their own notification flow.
    </p>

    @if(!empty($tableMissing))
        <div class="alert alert-warning">
            <strong>Log table missing.</strong> Run <code>php artisan migrate</code> to enable the delivery email log.
        </div>
    @else
        <div class="card">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle m-0">
                    <thead class="table-light">
                        <tr>
                            <th>When</th>
                            <th>Type</th>
                            <th>Customer</th>
                            <th>Sent to</th>
                            <th>Delivery date</th>
                            <th>Status</th>
                            <th>Mailer</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($logs as $log)
                            <tr>
                                <td style="white-space:nowrap; font-size:13px;">
                                    {{ $log->created_at?->format('d/m/y H:i') }}
                                </td>
                                <td>
                                    @php
                                        $typeColors = ['booked' => 'success', 'rescheduled' => 'info', 'cancelled' => 'danger'];
                                        $tc = $typeColors[$log->type] ?? 'secondary';
                                    @endphp
                                    <span class="badge bg-{{ $tc }}">{{ ucfirst($log->type) }}</span>
                                </td>
                                <td>{{ $log->customer_name ?: '—' }}</td>
                                <td style="font-size:13px;">
                                    {{ $log->recipient ?: '—' }}
                                    @if($log->was_test)
                                        <span class="badge bg-warning text-dark ms-1" title="Sent to test recipient, not the real customer">TEST</span>
                                        @if($log->real_recipient && $log->real_recipient !== $log->recipient)
                                            <br><small class="text-muted">would be: {{ $log->real_recipient }}</small>
                                        @endif
                                    @endif
                                </td>
                                <td style="font-size:13px;">
                                    {{ $log->appointment_date?->format('d/m/Y') ?: '—' }}
                                </td>
                                <td>
                                    @php
                                        $statusColors = ['sent' => 'success', 'test' => 'warning', 'failed' => 'danger', 'skipped' => 'secondary'];
                                        $sc = $statusColors[$log->status] ?? 'secondary';
                                    @endphp
                                    <span class="badge bg-{{ $sc }} {{ $log->status === 'test' ? 'text-dark' : '' }}">
                                        {{ ucfirst($log->status) }}
                                    </span>
                                    @if($log->status === 'failed' && $log->error)
                                        <br><small class="text-danger" title="{{ $log->error }}">
                                            {{ \Illuminate\Support\Str::limit($log->error, 60) }}
                                        </small>
                                    @endif
                                </td>
                                <td style="font-size:13px;">{{ $log->mailer ?: '—' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="text-center text-muted py-4">
                                    No delivery emails sent yet. They'll appear here once the system is turned on
                                    and a phone/WooCommerce booking comes through.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($logs->hasPages())
            <div class="mt-3">
                {{ $logs->links() }}
            </div>
        @endif
    @endif
</div>
@endsection
