@props([
    'title' => 'Nothing here yet',
    'text' => null,
    'icon' => 'inbox',
])

<div {{ $attributes->merge(['class' => 'av-empty']) }}>
    <div class="av-empty__icon">
        <x-admin-icon :name="$icon" :size="24" />
    </div>

    <p class="av-empty__title">{{ $title }}</p>

    @if ($text)
        <p class="av-empty__text">{{ $text }}</p>
    @endif

    {{ $slot }}
</div>
