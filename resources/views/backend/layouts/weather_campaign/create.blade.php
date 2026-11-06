@extends('backend.master')

@section('title')
    Weather Campaign - Create
@endsection

@section('body')
    <x-admin.page-header title="Weather Campaign Create"
        :crumbs="['Weather Campaigns' => null, 'Create' => null]" />

@if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Create Weather Campaign</h3>
                </div>

                <div class="card-body">
                    <form method="POST" action="{{ route('weather-campaigns.store') }}">
                        @csrf

                        @include('backend.layouts.weather_campaign._form')

                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">Create Campaign</button>
                            <button type="button" class="btn btn-info ms-2" id="btn-estimate">Estimate Audience</button>
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
    });
</script>
@endpush
