@php
    /** Desktop sidebar and mobile drawer share this markup — CSS decides which. */
    $navGroups = \App\Support\AdminNav::groups();
    $authUser = auth()->user();
@endphp

<aside class="av-sidebar" id="av-sidebar" aria-label="Main navigation">

    <div class="av-sidebar__head">
        <a class="av-brand av-truncate" href="{{ route('dashboard') }}">
            <x-admin-brand :size="32" word />
        </a>

        <button type="button" class="av-iconbtn av-only-mobile" data-drawer-close aria-label="Close menu">
            <x-admin-icon name="x" :size="20" />
        </button>
    </div>

    <div class="av-sidebar__search">
        <div class="av-navsearch">
            <x-admin-icon name="search" :size="16" />
            <input type="search" id="av-nav-search" placeholder="Search menu…"
                   autocomplete="off" aria-label="Search the menu">
        </div>
    </div>

    <nav class="av-nav av-scroll" id="av-nav">
        @foreach ($navGroups as $gi => $group)
            <div class="av-nav__group">
                <div class="av-nav__title">{{ $group['label'] }}</div>

                @foreach ($group['items'] as $ii => $item)
                    @php $panelId = 'av-nav-' . $gi . '-' . $ii; @endphp

                    <div class="av-nav__item" data-search="{{ $item['search'] }}">
                        @if ($item['children'])
                            <button type="button"
                                    class="av-nav__toggle {{ $item['active'] ? 'is-active' : '' }}"
                                    data-nav-toggle="{{ $panelId }}"
                                    aria-expanded="false" aria-controls="{{ $panelId }}">
                                <x-admin-icon :name="$item['icon'] ?? 'dot'" :size="18" />
                                <span class="av-nav__label">{{ $item['label'] }}</span>
                                @if ($item['badge_count'])
                                    <span class="av-nav__badge">{{ $item['badge_count'] }}</span>
                                @endif
                                <x-admin-icon name="chevron-down" :size="16" class="av-icon av-nav__caret" />
                            </button>

                            <ul class="av-nav__sub" id="{{ $panelId }}">
                                @foreach ($item['children'] as $child)
                                    <li>
                                        <a href="{{ $child['url'] }}"
                                           class="{{ $child['active'] ? 'is-active' : '' }}">
                                            {{ $child['label'] }}
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @else
                            <a href="{{ $item['url'] }}"
                               class="av-nav__link {{ $item['active'] ? 'is-active' : '' }}"
                               @if ($item['active']) aria-current="page" @endif>
                                <x-admin-icon :name="$item['icon'] ?? 'dot'" :size="18" />
                                <span class="av-nav__label">{{ $item['label'] }}</span>
                                @if ($item['badge_count'])
                                    <span class="av-nav__badge">{{ $item['badge_count'] }}</span>
                                @endif
                            </a>
                        @endif
                    </div>
                @endforeach
            </div>
        @endforeach

        <p class="av-nav__empty">No menu items match that search.</p>
    </nav>

    <div class="av-sidebar__foot">
        <a class="av-userchip" href="{{ route('profile.index') }}">
            <x-admin-avatar :user="$authUser" :size="34" />
            <span class="av-userchip__meta">
                <span class="av-userchip__name">{{ $authUser?->name ?? 'Guest' }}</span>
                <span class="av-userchip__role">{{ $authUser?->email ?? '' }}</span>
            </span>
        </a>
    </div>
</aside>
