@extends('backend.master')

@section('title')
    Weather Campaign - Edit
@endsection

@section('body')
    <x-admin.page-header title="Weather Campaign Edit"
        :crumbs="['Weather Campaigns' => null, 'Edit' => null]" />

@if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if ($campaign->last_evaluated_at)
        <div class="alert alert-info">
            Last evaluated {{ $campaign->last_evaluated_at->diffForHumans() }} —
            matched {{ count($campaign->last_matched_zip_codes ?? []) }} postcode(s) on that run.
        </div>
    @endif

    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Edit Weather Campaign</h3>
                </div>

                <div class="card-body">
                    <form method="POST" action="{{ route('weather-campaigns.update', $campaign->id) }}">
                        @csrf

                        @include('backend.layouts.weather_campaign._form')

                        <div class="mt-4">
                            @if (!in_array($campaign->status, ['active', 'completed', 'cancelled']))
                                <button type="submit" class="btn btn-primary">Update Campaign</button>
                                <button type="button" class="btn btn-info ms-2" id="btn-estimate">Estimate Audience</button>
                                <button type="button" class="btn btn-warning ms-2" id="btn-send-test">Send Test (Simulated)</button>
                            @else
                                <div class="alert alert-warning mb-3">This campaign is {{ $campaign->status }}. Editing is disabled to preserve data integrity.</div>
                            @endif
                            <a href="{{ route('weather-campaigns.index') }}" class="btn btn-secondary ms-2">Back to List</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    $(document).ready(function() {
        let status = "{{ $campaign->status }}";
        if (['active', 'completed', 'cancelled'].includes(status)) {
            $('form input, form select, form textarea, form button:not(.btn-secondary)').prop('disabled', true);
        }

        $('#btn-estimate').click(function() {
            let btn = $(this);
            btn.prop('disabled', true).text('Estimating...');
            
            $.ajax({
                url: "{{ route('weather-campaigns.estimate') }}",
                type: 'POST',
                data: $('form').serialize(),
                success: function(res) {
                    if (res.success && res.data) {
                        alert(`Audience Estimate:\nTotal Eligible: ${res.data.total_eligible}\nTotal Excluded: ${res.data.total_excluded}\nFinal Count: ${res.data.final_count}`);
                    }
                },
                error: function(xhr) {
                    alert('Error estimating audience.');
                },
                complete: function() {
                    btn.prop('disabled', false).text('Estimate Audience');
                }
            });
        });

        $('#btn-send-test').click(function() {
            let btn = $(this);
            btn.prop('disabled', true).text('Sending...');
            
            $.ajax({
                url: "{{ route('weather-campaigns.sendTest', $campaign->id) }}",
                type: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                success: function(res) {
                    toastr.success(res.success || 'Test sent.');
                },
                error: function(xhr) {
                    toastr.error('Error sending test.');
                },
                complete: function() {
                    btn.prop('disabled', false).text('Send Test (Simulated)');
                }
            });
        });
    });
</script>
@endpush
