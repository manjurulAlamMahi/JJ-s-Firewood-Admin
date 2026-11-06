{{-- Shell for the admin sign-in screens. Shares admin-v2.css with the panel
     so the first screen an admin sees already looks like the panel. --}}
<!doctype html>
<html lang="en" dir="ltr">

<head>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('backend.partial.meta')

    <title>@yield('title', 'Sign in') · {{ $systemSettings?->site_name ?? "JJ'S Firewood Perth" }}</title>

    <script>
        (function () {
            try {
                var saved = localStorage.getItem('av-theme');
                document.documentElement.setAttribute('data-theme',
                    saved || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'));
            } catch (e) {
                document.documentElement.setAttribute('data-theme', 'light');
            }
        })();
    </script>

    @include('backend.partial.style')
</head>

<body class="av-body av-auth">

    <main class="av-auth__panel">
        <div class="av-auth__brand">
            <x-admin-brand :size="72" />
        </div>

        <h1 class="av-auth__title">@yield('heading', 'Sign in')</h1>
        <p class="av-auth__sub">@yield('lede', ($systemSettings?->site_name ?? "JJ'S Firewood Perth") . ' admin')</p>

        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        @yield('form')
    </main>

    <script src="{{ asset('admin/assets/js/admin-v2.js') }}?v={{ @filemtime(public_path('admin/assets/js/admin-v2.js')) ?: 1 }}"></script>
</body>

</html>
