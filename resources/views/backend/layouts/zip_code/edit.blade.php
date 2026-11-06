@extends('backend.master')

@section('title')
    Edit Zone - {{ $group->name }}
@endsection

@section('body')
    <x-admin.page-header title="Edit Zone"
        :crumbs="['Postcodes' => null, 'id) }}">{{ $group->name }}' => null, 'Edit' => null]" />

<div class="col-md-12">
        <div class="card">
<div class="card-body">
                <form action="{{ route('zip-code.update-group', $group->id) }}" method="POST">
                    @csrf
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Zone Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="name"
                                       value="{{ old('name', $group->name) }}"
                                       placeholder="Enter zone name" required>
                                @error('name')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Sort Order</label>
                                <input type="number" class="form-control" name="sort_order"
                                       value="{{ old('sort_order', $group->sort_order) }}"
                                       placeholder="e.g. 1, 2, 3">
                                @error('sort_order')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>
                            <div class="mb-3">
                                <label class="form-label fw-bold">Status</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="status" value="1" id="statusSwitch" {{ old('status', $group->status) ? 'checked' : '' }}>
                                    <label class="form-check-label" for="statusSwitch">Active</label>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card p-3 border">
                                <h5 class="fw-bold mb-3">Delivery Fees for this Zone</h5>
                                <div class="row">
                                    @foreach($deliveryTypes as $type)
                                        <div class="col-md-12 mb-3">
                                            <label class="form-label fw-bold">{{ $type->name }} Fee</label>
                                            <input type="number" step="0.01" class="form-control" 
                                                   name="delivery_fees[{{ $type->id }}]" 
                                                   value="{{ old('delivery_fees.'.$type->id, $existingFees[$type->id] ?? $type->fee) }}" required>
                                            @error('delivery_fees.'.$type->id)
                                                <small class="text-danger">{{ $message }}</small>
                                            @enderror
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bulk Zip Codes -->
                    <div class="card shadow-none border mt-4">
                        <div class="card-header border-bottom">
                            <h3 class="card-title">Edit/Add Postcodes</h3>
                            <span class="text-muted ms-2">(duplicates are automatically skipped)</span>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Postcodes</label>
                                <textarea class="form-control" name="zip_codes_input" rows="5"
                                          placeholder="Enter postcodes separated by commas, spaces, or new lines. Example: 6100, 6101, 6150-6170">{{ old('zip_codes_input', $zipCodesString) }}</textarea>
                                @error('zip_codes_input')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>
                            <div class="alert alert-light border mb-0">
                                <strong><i class="fa fa-info-circle text-primary"></i> Input Tips:</strong>
                                <ul class="mb-0 mt-1">
                                    <li><strong>Comma-separated:</strong> 6100, 6101, 6102</li>
                                    <li><strong>Ranges:</strong> 6100-6150 will add all codes from 6100 to 6150</li>
                                    <li>Existing postcodes in this zone are pre-loaded above.</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-12 mt-4">
                            <button type="submit" class="btn btn-primary me-2">
                                <i class="fa fa-check"></i> Update Zone
                            </button>
                            <a href="{{ route('zip-code.show', $group->id) }}" class="btn btn-secondary">
                                <i class="fa fa-arrow-left"></i> Back to Zone
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection