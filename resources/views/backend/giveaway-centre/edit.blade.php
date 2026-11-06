@extends('backend.master')

@section('title', 'Edit Giveaway')

@section('body')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header bg-gradient-primary">
                    <h3 class="card-title text-white">
                        <i class="fe fe-edit"></i> Edit Giveaway
                    </h3>
                    <div class="card-tools">
                        <a href="{{ route('giveaway-centre.index') }}" class="btn btn-sm btn-light">
                            <i class="fe fe-arrow-left"></i> Back to List
                        </a>
                    </div>
                </div>

                <form action="{{ route('giveaway-centre.update', $giveaway->id) }}" method="POST" enctype="multipart/form-data" id="editGiveawayForm">
                    @csrf
                    @method('PUT')
                    
                    <div class="card-body">
                        @if ($errors->any())
                            <div class="alert alert-danger">
                                <ul class="mb-0">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        <!-- Prize Information -->
                        <div class="card mb-3">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">Prize Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="form-group">
                                            <label for="prize_name">Prize Name <span class="text-danger">*</span></label>
                                            <input type="text" 
                                                   name="prize_name" 
                                                   id="prize_name" 
                                                   class="form-control @error('prize_name') is-invalid @enderror" 
                                                   value="{{ old('prize_name', $giveaway->prize_name) }}" 
                                                   required>
                                            @error('prize_name')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="form-group">
                                            <label for="description">Description <span class="text-danger">*</span></label>
                                            <textarea name="description" 
                                                      id="description" 
                                                      rows="4" 
                                                      class="form-control @error('description') is-invalid @enderror" 
                                                      >{{ old('description', $giveaway->description) }}</textarea>
                                            <small class="form-text text-muted">Line breaks show in the app as typed. Formatting like **bold** isn't supported.</small>
                                            @error('description')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>

                                        <div class="form-group">
                                            <label for="prize_value">Prize Value ($) <span class="text-danger">*</span></label>
                                            <input type="number" 
                                                   name="prize_value" 
                                                   id="prize_value" 
                                                   class="form-control @error('prize_value') is-invalid @enderror" 
                                                   value="{{ old('prize_value', $giveaway->prize_value) }}" 
                                                   step="0.01" 
                                                   min="0" 
                                                   required>
                                            @error('prize_value')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>Current Image</label>
                                            <div class="mb-2">
                                                @if($giveaway->image)
                                                    <img src="{{ asset($giveaway->image) }}" 
                                                         alt="Giveaway Image" 
                                                         class="img-fluid rounded" 
                                                         style="max-height: 200px;">
                                                @else
                                                    <div class="alert alert-info">No image uploaded</div>
                                                @endif
                                            </div>
                                            
                                            <label for="image">Update Image (Optional)</label>
                                            <input type="file" 
                                                   name="image" 
                                                   id="image" 
                                                   class="form-control @error('image') is-invalid @enderror" 
                                                   accept="image/*">
                                            <small class="form-text text-muted">Leave empty to keep current image</small>
                                            @error('image')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Schedule & Status -->
                        <div class="card mb-3">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">Schedule & Status</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="start_date">Start Date <span class="text-danger">*</span></label>
                                            <input type="date" 
                                                   name="start_date" 
                                                   id="start_date" 
                                                   class="form-control @error('start_date') is-invalid @enderror" 
                                                   value="{{ old('start_date', $giveaway->start_date ? $giveaway->start_date->format('Y-m-d') : '') }}" 
                                                   required>
                                            @error('start_date')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="start_time">Start Time <span class="text-danger">*</span></label>
                                            <input type="time" 
                                                   name="start_time" 
                                                   id="start_time" 
                                                   class="form-control @error('start_time') is-invalid @enderror" 
                                                   value="{{ old('start_time', $giveaway->start_time ? substr($giveaway->start_time, 0, 5) : '00:00') }}" 
                                                   required>
                                            @error('start_time')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="draw_date">Draw Date <span class="text-danger">*</span></label>
                                            <input type="date" 
                                                   name="draw_date" 
                                                   id="draw_date" 
                                                   class="form-control @error('draw_date') is-invalid @enderror" 
                                                   value="{{ old('draw_date', $giveaway->draw_date ? $giveaway->draw_date->format('Y-m-d') : '') }}" 
                                                   required>
                                            @error('draw_date')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="col-md-3">
                                        <div class="form-group">
                                            <label for="draw_time">Draw Time <span class="text-danger">*</span></label>
                                            <input type="time" 
                                                   name="draw_time" 
                                                   id="draw_time" 
                                                   class="form-control @error('draw_time') is-invalid @enderror" 
                                                   value="{{ old('draw_time', $giveaway->draw_time) }}" 
                                                   required>
                                            @error('draw_time')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="status">Status <span class="text-danger">*</span></label>
                                            <select name="status" 
                                                    id="status" 
                                                    class="form-control @error('status') is-invalid @enderror" 
                                                    required>
                                                <option value="draft" {{ old('status', $giveaway->status) == 'draft' ? 'selected' : '' }}>Draft</option>
                                                <option value="scheduled" {{ old('status', $giveaway->status) == 'scheduled' ? 'selected' : '' }}>Scheduled</option>
                                                <option value="active" {{ old('status', $giveaway->status) == 'active' || old('status', $giveaway->status) == '1' ? 'selected' : '' }}>Active</option>
                                                <option value="cancelled" {{ old('status', $giveaway->status) == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                                            </select>
                                            @error('status')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="prize_type">Prize Type <span class="text-danger">*</span></label>
                                            <select name="prize_type" id="prize_type" class="form-control" required>
                                                <option value="bundle" {{ old('prize_type', $giveaway->prize_type) == 'bundle' ? 'selected' : '' }}>Bundle</option>
                                                <option value="discount" {{ old('prize_type', $giveaway->prize_type) == 'discount' ? 'selected' : '' }}>Discount</option>
                                                <option value="accessory" {{ old('prize_type', $giveaway->prize_type) == 'accessory' ? 'selected' : '' }}>Accessory</option>
                                                <option value="others" {{ old('prize_type', $giveaway->prize_type) == 'others' ? 'selected' : '' }}>Others</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label for="max_entries_per_user">Max Entries Per User</label>
                                            <input type="number" 
                                                   name="max_entries_per_user" 
                                                   id="max_entries_per_user" 
                                                   class="form-control @error('max_entries_per_user') is-invalid @enderror" 
                                                   value="{{ old('max_entries_per_user', $giveaway->max_entries_per_user) }}" 
                                                   min="1">
                                            <small class="form-text text-muted">Leave empty for unlimited</small>
                                            @error('max_entries_per_user')
                                                <div class="invalid-feedback">{{ $message }}</div>
                                            @enderror
                                        </div>
                                    </div>

                                    <div class="col-md-4">
                                        <div class="form-group">
                                            <label>&nbsp;</label>
                                            <div class="custom-control custom-checkbox">
                                                <input type="checkbox" 
                                                       name="auto_draw" 
                                                       id="auto_draw" 
                                                       class="custom-control-input" 
                                                       value="1" 
                                                       {{ old('auto_draw', $giveaway->auto_draw) ? 'checked' : '' }}>
                                                <label class="custom-control-label" for="auto_draw">
                                                    Enable Auto-Draw
                                                </label>
                                            </div>
                                            <small class="form-text text-muted">Automatically draw winner at scheduled time</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Animation Style Selection -->
                                <div class="row">
                                    <div class="col-md-12">
                                        <div class="form-group">
                                            <label for="animation_style">
                                                Draw Animation Style 
                                                <span class="text-muted">(How winner reveal looks)</span>
                                            </label>
                                            <select name="animation_style" id="animation_style" class="form-control">
                                                <option value="lottery_balls" {{ old('animation_style', $giveaway->animation_style ?? 'lottery_balls') == 'lottery_balls' ? 'selected' : '' }}>
                                                    🎱 Lottery Ball Machine (Professional)
                                                </option>
                                                <option value="raffle_tickets" {{ old('animation_style', $giveaway->animation_style ?? 'lottery_balls') == 'raffle_tickets' ? 'selected' : '' }}>
                                                    🎡 Spinning Wheel (With Names)
                                                </option>
                                            </select>
                                            <small class="form-text text-muted">
                                                Choose the animation style for the winner reveal. Both include sound effects and confetti!
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Entry Packages -->
                        <div class="card mb-3">
                            <div class="card-header bg-light">
                                <h5 class="mb-0">Entry Packages (Optional Custom Pricing)</h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted">Select which entry packages users can purchase for this giveaway. You can override the default price for each package.</p>
                                
                                @if($entryPackages->isEmpty())
                                    <div class="alert alert-warning">
                                        <i class="fe fe-alert-triangle"></i>
                                        No entry packages available. Please create entry packages first.
                                    </div>
                                @else
                                    <div class="row">
                                        @foreach($entryPackages as $package)
                                            <div class="col-md-6 mb-3">
                                                <div class="card">
                                                    <div class="card-body">
                                                        <div class="custom-control custom-checkbox mb-2">
                                                            <input type="checkbox" 
                                                                   name="entry_packages[]" 
                                                                   value="{{ $package->id }}" 
                                                                   id="package_{{ $package->id }}" 
                                                                   class="custom-control-input package-checkbox"
                                                                   {{ $giveaway->entryPackages->contains($package->id) ? 'checked' : '' }}>
                                                            <label class="custom-control-label" for="package_{{ $package->id }}">
                                                                <strong>{{ $package->name }}</strong> 
                                                                ({{ $package->entry_quantity }} entries)
                                                            </label>
                                                        </div>
                                                        
                                                        <div class="form-group mb-0">
                                                            <label class="small">Custom Price (Default: ${{ $package->price }})</label>
                                                            <input type="number" 
                                                                   name="package_custom_price[{{ $package->id }}]" 
                                                                   class="form-control form-control-sm" 
                                                                   value="{{ $giveaway->entryPackages->find($package->id)?->pivot->custom_price ?? $package->price }}"
                                                                   step="0.01" 
                                                                   min="0" 
                                                                   placeholder="${{ $package->price }}">
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        </div>

                        @if($giveaway->winner_id)
                            <div class="alert alert-success">
                                <h5><i class="fe fe-award"></i> Winner Information</h5>
                                <p class="mb-0">
                                    <strong>Winner:</strong> {{ $giveaway->winner->name ?? 'Unknown' }}<br>
                                    <strong>Drawn At:</strong> {{ $giveaway->winner_announced_at ? $giveaway->winner_announced_at->format('F d, Y h:i A') : 'N/A' }}
                                </p>
                            </div>
                        @endif
                    </div>

                    <div class="card-footer">
                        <button type="submit" class="btn btn-primary">
                            <i class="fe fe-save"></i> Update Giveaway
                        </button>
                        <a href="{{ route('giveaway-centre.index') }}" class="btn btn-secondary">
                            <i class="fe fe-x"></i> Cancel
                        </a>
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
    // Set minimum draw date to start date
    $('#start_date').on('change', function() {
        $('#draw_date').attr('min', $(this).val());
    });
    
    // Initialize on load
    if ($('#start_date').val()) {
        $('#draw_date').attr('min', $('#start_date').val());
    }
});
</script>
@endpush