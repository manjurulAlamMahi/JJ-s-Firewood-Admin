@php
    $authUser = auth()->user();

    // Pages set the title with @section('title'); most of them indent it.
    $pageTitle = trim($__env->yieldContent('title', 'Dashboard'));
@endphp

<header class="av-topbar">

    <button type="button" class="av-iconbtn av-only-mobile" data-drawer-open
            aria-expanded="false" aria-controls="av-sidebar" aria-label="Open menu">
        <x-admin-icon name="menu" :size="22" />
    </button>

    <a class="av-brand av-topbar__brand av-only-mobile" href="{{ route('dashboard') }}" aria-label="Home">
        <x-admin-brand :size="30" />
    </a>

    <h1 class="av-topbar__title av-only-desktop">{{ $pageTitle }}</h1>

    {{-- Keeps the right-hand controls pinned right on mobile, where the title
         is hidden and there is nothing else to push them across. --}}
    <span class="av-grow av-only-mobile"></span>

    <button type="button" class="av-iconbtn" data-theme-toggle aria-label="Toggle colour theme">
        <span data-theme-icon="light" hidden><x-admin-icon name="sun" :size="20" /></span>
        <span data-theme-icon="dark" hidden><x-admin-icon name="moon" :size="20" /></span>
    </button>

    <div class="dropdown">
        <button type="button" class="av-iconbtn" data-bs-toggle="dropdown" data-bs-display="static"
                aria-expanded="false" aria-label="Account menu">
            <x-admin-avatar :user="$authUser" :size="30" />
        </button>

        <div class="dropdown-menu dropdown-menu-end av-menu">
            <div class="av-menu__head">
                <div class="av-userchip__name">{{ $authUser?->name ?? 'Guest' }}</div>
                <div class="av-userchip__role av-truncate">{{ $authUser?->email }}</div>
            </div>

            <a class="av-menu__item" href="{{ route('profile.index') }}">
                <x-admin-icon name="user" :size="18" /> Profile
            </a>

            @if (Route::has('system-settings.edit'))
                <a class="av-menu__item" href="{{ route('system-settings.edit') }}">
                    <x-admin-icon name="settings" :size="18" /> System settings
                </a>
            @endif

            <button type="button" class="av-menu__item" data-theme-toggle>
                <x-admin-icon name="moon" :size="18" />
                <span data-theme-label>Dark mode</span>
            </button>

            <div class="av-menu__sep"></div>

            <button type="button" class="av-menu__item av-menu__item--danger"
                    onclick="document.getElementById('logoutForm').submit();">
                <x-admin-icon name="log-out" :size="18" /> Log out
            </button>
        </div>
    </div>

    <form action="{{ route('logout') }}" method="POST" id="logoutForm" class="d-none" data-allow-resubmit>
        @csrf
    </form>
</header>
