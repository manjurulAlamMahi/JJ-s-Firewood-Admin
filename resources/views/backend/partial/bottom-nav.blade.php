@php
    /** Mobile-only tab bar: the four destinations the admin opens all day. */
    $tabs = \App\Support\AdminNav::primary();
@endphp

<nav class="av-tabbar" aria-label="Quick navigation">
    @foreach ($tabs as $tab)
        <a href="{{ $tab['url'] }}"
           class="av-tabbar__item {{ $tab['active'] ? 'is-active' : '' }}"
           @if ($tab['active']) aria-current="page" @endif>
            <x-admin-icon :name="$tab['icon'] ?? 'dot'" :size="21" />
            <span>{{ $tab['label'] }}</span>
        </a>
    @endforeach

    <button type="button" class="av-tabbar__item" data-drawer-open
            aria-expanded="false" aria-controls="av-sidebar">
        <x-admin-icon name="menu" :size="21" />
        <span>More</span>
    </button>
</nav>
