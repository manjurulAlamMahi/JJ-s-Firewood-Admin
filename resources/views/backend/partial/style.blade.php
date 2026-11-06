{{--
    Stylesheet order matters here.

    1. Vendor CSS (Bootstrap, plugins) — the baseline.
    2. admin-v2.css — the design system, loaded LAST so it wins over both the
       vendor defaults and the per-page <style> blocks that master.blade.php
       renders via @stack('styles') before this partial.

    The legacy theme sheets (style.css, skin-modes.css, switcher.css) are no
    longer loaded: they carried the old shell layout, which admin-v2.css now
    replaces. The handful of theme classes pages still use (.page-header,
    .page-title, .tag, .avatar) are re-implemented in admin-v2.css.
--}}

{{-- Favicon and brand marks live in partial/meta.blade.php now — they are fixed
     admin assets rather than System Settings uploads. --}}

{{-- Vendor --}}
<link href="{{ asset('admin/assets/plugins/bootstrap/css/bootstrap.min.css') }}" rel="stylesheet">
<link href="{{ asset('admin/assets/css/icons-slim.css') }}" rel="stylesheet">
<link href="{{ asset('admin/assets/css/icons-compat.css') }}" rel="stylesheet">
<link href="{{ asset('admin/assets/css/dropify.min.css') }}" rel="stylesheet">

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css">

{{-- Design system — must stay last --}}
<link rel="stylesheet"
      href="{{ asset('admin/assets/css/admin-v2.css') }}?v={{ @filemtime(public_path('admin/assets/css/admin-v2.css')) ?: 1 }}">
