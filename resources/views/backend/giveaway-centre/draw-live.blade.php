@extends('backend.master')

@section('title', 'Live Draw Animation')

@section('styles')
<style>
    .draw-animation-container {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        min-height: 600px;
        border-radius: 20px;
        padding: 40px;
        text-align: center;
        color: white;
        position: relative;
        overflow: hidden;
    }
    
    .winner-card {
        background: white;
        color: #333;
        padding: 30px;
        border-radius: 15px;
        box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        max-width: 600px;
        margin: 40px auto;
    }
    
    .winner-avatar {
        width: 120px;
        height: 120px;
        border-radius: 50%;
        margin: 0 auto 20px;
        border: 5px solid #667eea;
    }
    
    .prize-image {
        max-width: 300px;
        height: auto;
        border-radius: 10px;
        margin: 20px auto;
        display: block;
    }
    
    .confetti {
        position: absolute;
        width: 10px;
        height: 10px;
        background: #f0f;
        animation: confetti-fall 3s linear infinite;
    }
    
    @keyframes confetti-fall {
        to {
            transform: translateY(100vh) rotate(360deg);
        }
    }
</style>
@endsection

@section('body')
<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">
                        <i class="fe fe-award"></i> Live Draw: {{ $giveaway->prize_name }}
                    </h4>
                    <a href="{{ route('giveaway-centre.index') }}" class="btn btn-secondary">
                        <i class="fe fe-arrow-left"></i> Back to Giveaway Centre
                    </a>
                </div>
                <div class="card-body">
                    @if($replay)
                        <div class="draw-animation-container">
                            <!-- Confetti animation -->
                            @for($i = 0; $i < 50; $i++)
                                <div class="confetti" style="left: {{ rand(0, 100) }}%; animation-delay: {{ rand(0, 3000) }}ms; background: {{ ['#ff0000', '#00ff00', '#0000ff', '#ffff00', '#ff00ff'][rand(0, 4)] }};"></div>
                            @endfor
                            
                            <h1 class="display-3 mb-4">🎉 Winner Drawn! 🎉</h1>
                            
                            @if($giveaway->image && $giveaway->image != url('no_image/no_image.jpg'))
                                <img src="{{ $giveaway->image }}" alt="{{ $giveaway->prize_name }}" class="prize-image">
                            @endif
                            
                            <div class="winner-card">
                                @if($replay->winner->avatar)
                                    <img src="{{ $replay->winner->avatar }}" alt="{{ $replay->winner->name }}" class="winner-avatar">
                                @else
                                    <div class="winner-avatar d-flex align-items-center justify-content-center" style="background: #667eea; color: white; font-size: 48px; font-weight: bold;">
                                        {{ strtoupper(substr($replay->winner->name, 0, 1)) }}
                                    </div>
                                @endif
                                
                                <h2 class="mb-3">{{ $replay->winner->name }}</h2>
                                
                                <div class="row text-center mt-4">
                                    <div class="col-4">
                                        <div class="text-muted small">Prize Value</div>
                                        <div class="h4">${{ number_format($giveaway->prize_value, 2) }}</div>
                                    </div>
                                    <div class="col-4">
                                        <div class="text-muted small">Total Entries</div>
                                        <div class="h4">{{ $replay->total_entries }}</div>
                                    </div>
                                    <div class="col-4">
                                        <div class="text-muted small">Participants</div>
                                        <div class="h4">{{ $replay->total_participants }}</div>
                                    </div>
                                </div>
                                
                                <div class="mt-4 pt-3 border-top">
                                    <small class="text-muted">
                                        <i class="fe fe-clock"></i> 
                                        Drawn at {{ $replay->drawn_at->format('d/m/Y H:i') }}
                                    </small>
                                </div>
                                
                                <div class="mt-3">
                                    <small class="text-muted">
                                        <i class="fe fe-zap"></i> 
                                        Animation Style: {{ ucwords(str_replace('_', ' ', $replay->animation_style)) }}
                                    </small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4 text-center">
                            <a href="{{ route('giveaway-centre.index') }}" class="btn btn-primary btn-lg">
                                <i class="fe fe-arrow-left"></i> Back to Giveaway Centre
                            </a>
                        </div>
                    @else
                        <div class="alert alert-warning">
                            <i class="fe fe-alert-triangle"></i>
                            No draw replay data found for this giveaway.
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection