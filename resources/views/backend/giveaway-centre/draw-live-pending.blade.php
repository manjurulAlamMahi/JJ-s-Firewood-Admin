@extends('backend.master')

@section('title', 'Draw Pending')

@section('body')
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">
                        <i class="fe fe-clock"></i> Draw Pending: {{ $giveaway->prize_name }}
                    </h4>
                    <a href="{{ route('giveaway-centre.index') }}" class="btn btn-secondary">
                        <i class="fe fe-arrow-left"></i> Back to Giveaway Centre
                    </a>
                </div>
                <div class="card-body text-center py-5">
                    <div style="font-size: 80px; color: #667eea;">
                        <i class="fe fe-clock"></i>
                    </div>
                    
                    <h2 class="mt-4 mb-3">This giveaway hasn't been drawn yet</h2>
                    
                    <p class="text-muted mb-4">
                        Please draw a winner first to see the live draw animation.
                    </p>
                    
                    <div class="card mx-auto" style="max-width: 500px;">
                        <div class="card-body">
                            <h5 class="card-title">{{ $giveaway->prize_name }}</h5>
                            
                            @if($giveaway->image && $giveaway->image != url('no_image/no_image.jpg'))
                                <img src="{{ $giveaway->image }}" alt="{{ $giveaway->prize_name }}" class="img-fluid rounded my-3" style="max-height: 200px;">
                            @endif
                            
                            <div class="row text-center mt-3">
                                <div class="col-6">
                                    <div class="text-muted small">Prize Value</div>
                                    <div class="h5">${{ number_format($giveaway->prize_value, 2) }}</div>
                                </div>
                                <div class="col-6">
                                    <div class="text-muted small">Draw Date</div>
                                    <div class="h5">{{ $giveaway->draw_date->format('d/m/Y') }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-4">
                        <a href="{{ route('giveaway-centre.index') }}" class="btn btn-primary">
                            <i class="fe fe-arrow-left"></i> Back to Giveaway Centre
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection