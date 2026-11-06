<!doctype html>
<html lang="en" dir="ltr">

<head>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('backend.partial.meta')

    <title>@yield('title', 'Admin') · {{ $systemSettings?->site_name ?? "JJ'S Firewood Perth" }}</title>

    {{-- Set the theme before first paint so dark mode never flashes white. --}}
    <script>
        (function () {
            try {
                var saved = localStorage.getItem('av-theme');
                var mode = saved || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
                document.documentElement.setAttribute('data-theme', mode);
            } catch (e) {
                document.documentElement.setAttribute('data-theme', 'light');
            }
        })();
    </script>

    {{-- Page styles first, the design system last: admin-v2.css is meant to be
         the final word, which lets it tame the bespoke <style> blocks that the
         older pages still push. --}}
    @stack('styles')
    @include('backend.partial.style')

    {{-- Page stylesheets that need to win over the design system rather than be
         tamed by it. Redesigned pages push here; legacy pages keep using
         @push('styles') above and get overridden, which is the point. --}}
    @stack('page-styles')
</head>

<body class="av-body">

    <a href="#av-main" class="av-skip-link">Skip to main content</a>

    <div id="av-loader" aria-hidden="true">
        <div class="av-spinner"></div>
    </div>

    <div class="av-shell">

        @include('backend.partial.sidebar')

        <div class="av-main">

            @include('backend.partial.header')

            <main class="av-content" id="av-main">
                @yield('body')
            </main>

            @include('backend.partial.footer')
        </div>
    </div>

    <div class="av-backdrop" id="av-backdrop" tabindex="-1" aria-hidden="true"></div>

    @include('backend.partial.bottom-nav')

    <button type="button" id="av-top" aria-label="Back to top">
        <x-admin-icon name="chevron-up" :size="20" />
    </button>

    @include('backend.partial.script')
    @stack('scripts')
</body>

</html>
