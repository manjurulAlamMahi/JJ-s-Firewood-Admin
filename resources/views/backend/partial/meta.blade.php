<meta charset="UTF-8">

{{-- viewport-fit=cover lets the shell paint under the iOS home indicator; the
     safe-area insets in admin-v2.css keep controls clear of it. Pinch-zoom is
     deliberately left enabled — blocking it fails WCAG 1.4.4. --}}
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">

<meta name="robots" content="noindex, nofollow">
<meta name="description" content="{{ $systemSettings?->site_name ?? "JJ'S Firewood Perth" }} admin panel">
<meta name="format-detection" content="telephone=no">

{{-- Paints the Android Chrome address bar in the brand colour. --}}
<meta name="theme-color" content="#b93030" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#212121" media="(prefers-color-scheme: dark)">

{{-- Brand marks are fixed admin assets copied from the customer app
     (assets/images/logo.png), not the System Settings upload — that field is
     for the public site and has held the wrong image before now. --}}
<link rel="icon" type="image/png" href="{{ asset('admin/assets/brand/favicon.png') }}">
<link rel="apple-touch-icon" href="{{ asset('admin/assets/brand/apple-touch-icon.png') }}">

{{-- Lets the operator add the admin to his Android home screen and open it
     without browser chrome. --}}
<link rel="manifest" href="{{ asset('admin/manifest.webmanifest') }}">

<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="JJ'S Admin">
