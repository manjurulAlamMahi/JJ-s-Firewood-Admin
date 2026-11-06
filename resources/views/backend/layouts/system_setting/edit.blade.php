@extends('backend.master')

@section('title')
    System Settings
@endsection

@section('body')
    <x-admin.page-header title="System Settings"
        :crumbs="['Settings' => null, 'System Settings' => null]" />

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

                    <div class="card">
<div class="card-body">
                            <form action="{{ route('system-settings.update') }}" method="POST"
                                enctype="multipart/form-data">
                                @csrf
                                <div class="row">

                                    {{-- System Logo --}}
                                    <div class="col-md-6 mb-3">
                                        <label for="system_logo" class="form-label">System Logo</label>

                                        {{-- Preview the current logo. The stored path can outlive the
                                             file it points at (this row referenced a logo.png that was
                                             never uploaded), so check the disk before rendering an
                                             <img> that can only ever 404. --}}
                                        @php
                                            $logoOnDisk = $settings->system_logo
                                                && file_exists(public_path($settings->system_logo));
                                        @endphp

                                        <div class="mb-2">
                                            @if ($logoOnDisk)
                                                <img src="{{ asset($settings->system_logo) }}?v={{ filemtime(public_path($settings->system_logo)) }}"
                                                    alt="System Logo" class="img-thumbnail" style="height: 60px;">
                                            @else
                                                <span class="av-pill av-pill--neutral">
                                                    <i class="fa fa-image"></i>
                                                    {{ $settings->system_logo ? 'Saved logo file is missing — upload a new one' : 'No logo uploaded yet' }}
                                                </span>
                                            @endif
                                        </div>

                                        <input type="file" name="system_logo" id="system_logo"
                                            class="form-control @error('system_logo') is-invalid @enderror"
                                            accept="image/jpeg,image/jpg,image/png,image/svg+xml">

                                        @error('system_logo')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror

                                        <small class="form-text text-muted">
                                            Accepted formats: JPG, PNG, SVG. Max size: 2MB
                                        </small>
                                    </div>


                                    {{-- System Favicon --}}
                                    <div class="col-md-6 mb-3">
                                        <label for="system_favicon" class="form-label">System Favicon</label>
                                        @if ($settings->system_favicon_url)
                                            <div class="mb-2">
                                                <img src="{{ $settings->system_favicon_url }}" alt="System Favicon"
                                                    class="img-thumbnail" style="height: 40px;">
                                            </div>
                                        @endif
                                        <input type="file" name="system_favicon" id="system_favicon"
                                            class="form-control @error('system_favicon') is-invalid @enderror"
                                            accept="image/png,image/ico,image/jpeg,image/jpg">
                                        @error('system_favicon')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                        <small class="form-text text-muted">Accepted formats: PNG, ICO, JPG. Max size:
                                            1MB</small>
                                    </div>

                                    {{-- System Title --}}
                                    <div class="col-md-6 mb-3">
                                        <label for="system_title" class="form-label">System Title</label>
                                        <input type="text" name="system_title" id="system_title"
                                            value="{{ old('system_title', $settings->system_title) }}"
                                            class="form-control @error('system_title') is-invalid @enderror"
                                            placeholder="Enter system title">
                                        @error('system_title')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    {{-- System Short Title --}}
                                    <div class="col-md-6 mb-3">
                                        <label for="system_short_title" class="form-label">System Short Title</label>
                                        <input type="text" name="system_short_title" id="system_short_title"
                                            value="{{ old('system_short_title', $settings->system_short_title) }}"
                                            class="form-control @error('system_short_title') is-invalid @enderror"
                                            placeholder="Enter short title">
                                        @error('system_short_title')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    {{-- Company Name --}}
                                    <div class="col-md-6 mb-3">
                                        <label for="company_name" class="form-label">Company Name</label>
                                        <input type="text" name="company_name" id="company_name"
                                            value="{{ old('company_name', $settings->company_name) }}"
                                            class="form-control @error('company_name') is-invalid @enderror"
                                            placeholder="Enter company name">
                                        @error('company_name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    {{-- Tagline --}}
                                    <div class="col-md-6 mb-3">
                                        <label for="tagline" class="form-label">Tagline</label>
                                        <input type="text" name="tagline" id="tagline"
                                            value="{{ old('tagline', $settings->tagline) }}"
                                            class="form-control @error('tagline') is-invalid @enderror"
                                            placeholder="Enter tagline">
                                        @error('tagline')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    {{-- Company Address --}}
                                    <div class="col-md-6 mb-3">
                                        <label for="company_address" class="form-label">Company Address</label>
                                        <input type="text" name="company_address" id="company_address"
                                            value="{{ old('company_address', $settings->company_address) }}"
                                            class="form-control @error('company_address') is-invalid @enderror"
                                            placeholder="Enter company address">
                                        @error('company_address')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    {{-- Phone --}}
                                    <div class="col-md-6 mb-3">
                                        <label for="phone" class="form-label">Phone</label>
                                        <input type="text" name="phone" id="phone"
                                            value="{{ old('phone', $settings->phone) }}"
                                            class="form-control @error('phone') is-invalid @enderror"
                                            placeholder="Enter phone number">
                                        @error('phone')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    {{-- Email --}}
                                    <div class="col-md-6 mb-3">
                                        <label for="email" class="form-label">Email</label>
                                        <input type="email" name="email" id="email"
                                            value="{{ old('email', $settings->email) }}"
                                            class="form-control @error('email') is-invalid @enderror"
                                            placeholder="Enter email address">
                                        @error('email')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    {{-- Timezone --}}
                                    <div class="col-md-6 mb-3">
                                        <label for="timezone" class="form-label">Timezone</label>
                                        <div class="input-group">
                                            <input type="text" name="timezone" id="timezone"
                                                value="{{ old('timezone', $settings->timezone) }}"
                                                class="form-control @error('timezone') is-invalid @enderror"
                                                placeholder="e.g., Asia/Dhaka">
                                            <button type="button" class="btn btn-outline-info" id="detect-timezone">
                                                <i class="fas fa-crosshairs me-1"></i> Auto-detect
                                            </button>
                                        </div>
                                        @error('timezone')
                                            <div class="invalid-feedback d-block">{{ $message }}</div>
                                        @enderror
                                        <small class="form-text text-muted">Detected: <span id="current-browser-tz">Loading...</span></small>
                                    </div>

                                    {{-- Language --}}
                                    <div class="col-md-6 mb-3">
                                        <label for="language" class="form-label">Language</label>
                                        <input type="text" name="language" id="language"
                                            value="{{ old('language', $settings->language) }}"
                                            class="form-control @error('language') is-invalid @enderror"
                                            placeholder="e.g., en, bn">
                                        @error('language')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    {{-- Features Toggle --}}
                                    <div class="col-md-12 mb-3">
                                        <div class="card bg-light border-0">
                                            <div class="card-body py-3">
                                                <h6 class="card-title fw-bold mb-3">Feature Toggles</h6>
                                                <div class="form-check form-switch">
                                                    <input class="form-check-input" type="checkbox" role="switch"
                                                        name="is_bundles_enabled" id="is_bundles_enabled"
                                                        value="1"
                                                        {{ old('is_bundles_enabled', $settings->is_bundles_enabled) ? 'checked' : '' }}>
                                                    <label class="form-check-label" for="is_bundles_enabled">
                                                        Enable Bundle Packages
                                                    </label>
                                                    <small class="form-text text-muted d-block">
                                                        If disabled, the "Build Your Bundle" section will be hidden from the mobile app.
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Copyright Text --}}
                                    <div class="col-md-12 mb-3">
                                        <label for="copyright_text" class="form-label">Copyright Text</label>
                                        <textarea name="copyright_text" id="copyright_text"
                                            class="form-control @error('copyright_text') is-invalid @enderror" rows="3"
                                            placeholder="Enter copyright text">{{ old('copyright_text', $settings->copyright_text) }}</textarea>
                                        @error('copyright_text')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>

                                    {{-- Submit Button --}}
                                    <div class="col-md-12 text-end">
                                        <button type="submit" class="btn btn-danger">
                                            <i class="fas fa-save me-2"></i> Update Settings
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

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            function previewImage(input, imgContainer) {
                if (input.files && input.files[0]) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        let existingImg = imgContainer.querySelector('.mb-2 img');
                        if (existingImg) {
                            existingImg.src = e.target.result;
                        } else {
                            const div = document.createElement('div');
                            div.className = 'mb-2';
                            const img = document.createElement('img');
                            img.className = 'img-thumbnail';
                            img.style.height = input.id === 'system_favicon' ? '40px' : '60px';
                            img.src = e.target.result;
                            img.alt = 'Preview';
                            div.appendChild(img);
                            input.parentElement.insertBefore(div, input);
                        }
                    };
                    reader.readAsDataURL(input.files[0]);
                }
            }

            const logoInput = document.getElementById('system_logo');
            if (logoInput) {
                logoInput.addEventListener('change', function() {
                    previewImage(this, this.parentElement);
                });
            }

            const faviconInput = document.getElementById('system_favicon');
            if (faviconInput) {
                faviconInput.addEventListener('change', function() {
                    previewImage(this, this.parentElement);
                });
            }

            // Timezone Detection
            const timezoneInput = document.getElementById('timezone');
            const detectBtn = document.getElementById('detect-timezone');
            const browserTzSpan = document.getElementById('current-browser-tz');

            function getBrowserTimezone() {
                try {
                    return Intl.DateTimeFormat().resolvedOptions().timeZone;
                } catch (e) {
                    console.error("Timezone detection failed:", e);
                    return null;
                }
            }

            const detectedTz = getBrowserTimezone();
            if (browserTzSpan) {
                browserTzSpan.textContent = detectedTz || 'Unknown';
            }

            // Auto-populate if empty
            if (timezoneInput && !timezoneInput.value && detectedTz) {
                timezoneInput.value = detectedTz;
            }

            if (detectBtn && timezoneInput) {
                detectBtn.addEventListener('click', function() {
                    const tz = getBrowserTimezone();
                    if (tz) {
                        timezoneInput.value = tz;
                        // Optional: Show a small toast or feedback
                    }
                });
            }
        });
    </script>
@endpush
