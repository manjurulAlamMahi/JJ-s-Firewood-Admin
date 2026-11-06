@props(['user' => null, 'size' => 34])

@php
    /**
     * The theme's stock avatar images were never shipped with this repo, so a
     * missing photo falls back to initials rather than a broken image.
     */
    $photo = $user?->profile_photo_path;

    $initials = collect(explode(' ', trim((string) ($user?->name ?? ''))))
        ->filter()
        ->take(2)
        ->map(fn ($part) => mb_strtoupper(mb_substr($part, 0, 1)))
        ->implode('');
@endphp

@if ($photo)
    <img src="{{ asset($photo) }}" alt=""
         {{ $attributes->merge(['class' => 'av-avatar']) }}
         style="width:{{ $size }}px;height:{{ $size }}px;">
@else
    <span {{ $attributes->merge(['class' => 'av-avatar av-avatar--initials']) }}
          aria-hidden="true"
          style="width:{{ $size }}px;height:{{ $size }}px;font-size:{{ max(10, (int) round($size * 0.38)) }}px;">
        {{ $initials !== '' ? $initials : '?' }}
    </span>
@endif
