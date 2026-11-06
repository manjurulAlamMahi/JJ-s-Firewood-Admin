@extends('backend.master')

@section('title')
    Create Postcode Zone
@endsection

@section('body')
    <x-admin.page-header title="Create Postcode Zone"
        :crumbs="['Postcodes' => null, 'Create Zone' => null]" />

<div class="col-md-12">
        <form action="{{ route('zip-code.store-group') }}" method="POST">
            @csrf

            <!-- Zone Name -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header border-bottom">
                            <h3 class="card-title">Zone Details</h3>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label fw-bold">Zone Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="name"
                                       placeholder="e.g. 30km Radius, Free Delivery, South Perth"
                                       value="{{ old('name') }}" required>
                                @error('name')
                                    <small class="text-danger">{{ $message }}</small>
                                @enderror
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header border-bottom">
                            <h3 class="card-title">Delivery Fees</h3>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                @foreach($deliveryTypes as $type)
                                    <div class="col-md-12 mb-3">
                                        <label class="form-label fw-bold">{{ $type->name }} Fee</label>
                                        <input type="number" step="0.01" class="form-control" 
                                               name="delivery_fees[{{ $type->id }}]" 
                                               value="{{ old('delivery_fees.'.$type->id, $type->fee) }}" required>
                                        @error('delivery_fees.'.$type->id)
                                            <small class="text-danger">{{ $message }}</small>
                                        @enderror
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bulk Zip Codes -->
            <div class="card">
                <div class="card-header border-bottom">
                    <h3 class="card-title">Add Postcodes</h3>
                    <span class="text-muted ms-2">(optional — you can add them later)</span>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Postcodes</label>
                        <textarea class="form-control" name="zip_codes_input" rows="5"
                                  placeholder="Enter postcodes separated by commas, spaces, or new lines.&#10;&#10;Supports ranges: 6100-6150&#10;&#10;Example: 6100, 6101, 6102, 6150-6170, 6200">{{ old('zip_codes_input') }}</textarea>
                        @error('zip_codes_input')
                            <small class="text-danger">{{ $message }}</small>
                        @enderror
                    </div>
                    <div class="alert alert-light border mb-0">
                        <strong><i class="fa fa-info-circle text-primary"></i> Input Tips:</strong>
                        <ul class="mb-0 mt-1">
                            <li><strong>Comma-separated:</strong> 6100, 6101, 6102, 6103</li>
                            <li><strong>One per line:</strong> paste a list from a spreadsheet</li>
                            <li><strong>Ranges:</strong> 6100-6150 will add all codes from 6100 to 6150</li>
                            <li><strong>Mix:</strong> 6100-6110, 6200, 6305, 6400-6420</li>
                            <li>Duplicates within this zone are automatically skipped</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="mb-4">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="fa fa-check"></i> Create Zone
                </button>
                <a href="{{ route('zip-code.index') }}" class="btn btn-secondary">
                    <i class="fa fa-arrow-left"></i> Cancel
                </a>
            </div>
        </form>
    </div>
@endsection