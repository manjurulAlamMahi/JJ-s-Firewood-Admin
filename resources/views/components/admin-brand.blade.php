@props([
    'size' => 30,
    'word' => false,   // show the wordmark next to the badge
])

{{--
    The admin brand mark. Deliberately a fixed asset copied from the customer
    app (assets/images/logo.png) rather than $systemSettings->system_logo: that
    setting drives the public site and emails, and has held a stock fireplace
    photo before now. Hard-wiring it keeps the admin and the app identical.
--}}
<img src="{{ asset('admin/assets/brand/logo.png') }}"
     alt="JJ'S Firewood"
     width="{{ $size }}" height="{{ $size }}"
     {{ $attributes->merge(['class' => 'av-logo']) }}
     style="width:{{ $size }}px;height:{{ $size }}px;">

@if ($word)
    <span class="av-brand__word av-truncate">JJ'S Firewood</span>
@endif
