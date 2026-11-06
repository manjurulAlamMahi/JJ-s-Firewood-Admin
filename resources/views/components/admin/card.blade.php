@props([
    'title' => null,
    'flush' => false,   // drop body padding — for tables and list rows
])

<div {{ $attributes->merge(['class' => 'av-card']) }}>
    @if ($title || isset($actions))
        <div class="av-card__head">
            @if ($title)
                <h2 class="av-card__title">{{ $title }}</h2>
            @endif

            @isset($actions)
                <div class="av-row">{{ $actions }}</div>
            @endisset
        </div>
    @endif

    @if ($flush)
        {{ $slot }}
    @else
        <div class="av-card__body">{{ $slot }}</div>
    @endif

    @isset($footer)
        <div class="av-card__foot">{{ $footer }}</div>
    @endisset
</div>
