@props([
    'title',
    'subtitle' => null,
    'crumbs' => [],   // ['Label' => url, 'Current' => null]
])

@if (!empty($crumbs))
    <ol class="av-crumbs">
        @foreach ($crumbs as $label => $url)
            <li>
                @if ($url)
                    <a href="{{ $url }}">{{ $label }}</a>
                @else
                    {{ $label }}
                @endif
            </li>
        @endforeach
    </ol>
@endif

<div {{ $attributes->merge(['class' => 'av-page-head']) }}>
    <div class="av-page-head__text">
        <h1 class="av-page-title">{{ $title }}</h1>
        @if ($subtitle)
            <p class="av-page-head__sub">{{ $subtitle }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="av-page-head__actions">{{ $actions }}</div>
    @endisset
</div>
