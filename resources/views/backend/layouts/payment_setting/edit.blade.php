@extends('backend.master')

@section('title')
    Payment Settings
@endsection

@section('body')
    <x-admin.page-header title="Payment Settings"
        :crumbs="['Settings' => null, 'Payment Settings' => null]" />

<div class="page">
        <div class="page-main">
            <div class="row">
                <div class="col-md-12">

                    {{-- Success Message --}}
                    @if (session('success'))
                        <div class="alert alert-success alert-dismissible fade show" role="alert">
                            {{ session('success') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    {{-- Error Message --}}
                    @if (session('error'))
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            {{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    {{-- Validation Errors --}}
                    @if ($errors->any())
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    {{-- Square --}}
                    <div class="card">
                        <div class="card-header">
                            <h3 class="card-title">Square (Card, Apple Pay, Google Pay)</h3>
                        </div>
                        <div class="card-body">
                            <form action="{{ route('payment-settings.update') }}" method="POST">
                                @csrf
                                <div class="row">

                                    {{-- Square Application ID --}}
                                    <div class="col-md-6 mb-3">
                                        <label for="square_application_id" class="form-label">Square Application ID</label>
                                        <input type="text" name="square_application_id" id="square_application_id"
                                            value="{{ old('square_application_id', $setting->square_application_id) }}"
                                            class="form-control @error('square_application_id') is-invalid @enderror"
                                            placeholder="Enter Square Application ID">
                                        @error('square_application_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    {{-- Square Location ID --}}
                                    <div class="col-md-6 mb-3">
                                        <label for="square_location_id" class="form-label">Square Location ID</label>
                                        <input type="text" name="square_location_id" id="square_location_id"
                                            value="{{ old('square_location_id', $setting->square_location_id) }}"
                                            class="form-control @error('square_location_id') is-invalid @enderror"
                                            placeholder="Enter Square Location ID">
                                        @error('square_location_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    {{-- Square Environment --}}
                                    <div class="col-md-6 mb-3">
                                        <label for="square_environment" class="form-label">Square Environment</label>
                                        <select name="square_environment" id="square_environment"
                                            class="form-control @error('square_environment') is-invalid @enderror">
                                            <option value="sandbox" {{ old('square_environment', $setting->square_environment) === 'sandbox' ? 'selected' : '' }}>Sandbox</option>
                                            <option value="production" {{ old('square_environment', $setting->square_environment) === 'production' ? 'selected' : '' }}>Production</option>
                                        </select>
                                        @error('square_environment')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    {{-- Square Access Token --}}
                                    <div class="col-md-6 mb-3">
                                        <label for="square_access_token" class="form-label">Square Access Token</label>
                                        <input type="password" name="square_access_token" id="square_access_token"
                                            value=""
                                            class="form-control @error('square_access_token') is-invalid @enderror"
                                            autocomplete="new-password" placeholder="Leave blank to keep existing">
                                        @error('square_access_token')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        <small class="form-text text-muted">Leave blank to keep the current token.</small>
                                    </div>

                                    {{-- Square Webhook Signature Key --}}
                                    <div class="col-md-6 mb-3">
                                        <label for="webhook_signature_key" class="form-label">Square Webhook Signature Key</label>
                                        <input type="password" name="webhook_signature_key" id="webhook_signature_key"
                                            value=""
                                            class="form-control @error('webhook_signature_key') is-invalid @enderror"
                                            autocomplete="new-password" placeholder="Leave blank to keep existing">
                                        @error('webhook_signature_key')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        <small class="form-text text-muted">Leave blank to keep the current key.</small>
                                    </div>

                                    <hr class="my-3">

                                    {{-- PayPal Client ID --}}
                                    <div class="col-md-6 mb-3">
                                        <label for="paypal_client_id" class="form-label">PayPal Client ID</label>
                                        <input type="text" name="paypal_client_id" id="paypal_client_id"
                                            value="{{ old('paypal_client_id', $setting->paypal_client_id) }}"
                                            class="form-control @error('paypal_client_id') is-invalid @enderror"
                                            placeholder="Enter PayPal Client ID">
                                        @error('paypal_client_id')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    {{-- PayPal Environment --}}
                                    <div class="col-md-6 mb-3">
                                        <label for="paypal_environment" class="form-label">PayPal Environment</label>
                                        <select name="paypal_environment" id="paypal_environment"
                                            class="form-control @error('paypal_environment') is-invalid @enderror">
                                            <option value="sandbox" {{ old('paypal_environment', $setting->paypal_environment) === 'sandbox' ? 'selected' : '' }}>Sandbox</option>
                                            <option value="production" {{ old('paypal_environment', $setting->paypal_environment) === 'production' ? 'selected' : '' }}>Production</option>
                                        </select>
                                        @error('paypal_environment')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    {{-- PayPal Secret --}}
                                    <div class="col-md-6 mb-3">
                                        <label for="paypal_secret" class="form-label">PayPal Secret</label>
                                        <input type="password" name="paypal_secret" id="paypal_secret"
                                            value=""
                                            class="form-control @error('paypal_secret') is-invalid @enderror"
                                            autocomplete="new-password" placeholder="Leave blank to keep existing">
                                        @error('paypal_secret')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        <small class="form-text text-muted">Leave blank to keep the current secret.</small>
                                    </div>

                                    {{-- Submit Button --}}
                                    <div class="col-md-12 text-end">
                                        <button type="submit" class="btn btn-danger">
                                            <i class="fas fa-save me-2"></i> Update Payment Settings
                                        </button>
                                    </div>

                                </div>
                            </form>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
@endsection
