@props([
    'label',
    'value',
    'icon' => null,
    'tone' => 'primary',   // primary | success | warning | danger | info
    'note' => null,
    'href' => null,
])

@php $tag = $href ? 'a' : 'div'; @endphp

<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => 'av-stat']) }}>
    <div class="av-stat__top">
        @if ($icon)
            <span class="av-stat__icon av-stat__icon--{{ $tone }}">
                <x-admin-icon :name="$icon" :size="18" />
            </span>
        @endif
        <span class="av-stat__label">{{ $label }}</span>
    </div>

    <div class="av-stat__value">{{ $value }}</div>

    @if ($note)
        <div class="av-stat__note">{{ $note }}</div>
    @endif
</{{ $tag }}>
