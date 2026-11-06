{{--
    Script order matters here.

    jQuery and DataTables were previously loaded twice — once locally and again
    from a CDN — which re-registered DataTables against a second jQuery instance
    and cost ~400 KB per page view. Each library is now loaded exactly once.

    admin-v2.js runs before @stack('scripts') so the DataTables defaults it sets
    (mobile card mode, language strings) apply to tables the pages build after.

    The old theme scripts (sidemenu.js, sticky.js, custom.js, index.js,
    switcher.js, perfect-scrollbar) drove the previous shell and are gone.
--}}

{{-- Core --}}
<script src="{{ asset('admin/assets/plugins/jquery/jquery.min.js') }}"></script>
<script src="{{ asset('admin/assets/plugins/bootstrap/js/popper.min.js') }}"></script>
<script src="{{ asset('admin/assets/plugins/bootstrap/js/bootstrap.min.js') }}"></script>

{{-- Plugins used across the admin --}}
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<script src="{{ asset('admin/assets/plugins/select2/select2.full.min.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/summernote@0.8.20/dist/summernote-lite.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="{{ asset('admin/assets/js/dropify.min.js') }}"></script>

{{-- Shell: drawer, nav, theme, table card mode. Must precede @stack('scripts'). --}}
<script src="{{ asset('admin/assets/js/admin-v2.js') }}?v={{ @filemtime(public_path('admin/assets/js/admin-v2.js')) ?: 1 }}"></script>

<script>
    $(function () {
        // File inputs across the create/edit forms.
        $('.dropify').dropify();

        $('#logo').on('dropify.afterClear', function () {
            $('input[name="remove_logo"]').val('1');
        });

        $('#favicon').on('dropify.afterClear', function () {
            $('input[name="remove_favicon"]').val('1');
        });

        // Keep the stored timezone aligned with the device the admin is using.
        var tz = Intl.DateTimeFormat().resolvedOptions().timeZone;

        if (localStorage.getItem('admin_synced_tz') !== tz) {
            $.ajax({
                url: "{{ route('system-settings.sync-timezone') }}",
                method: 'POST',
                data: { _token: "{{ csrf_token() }}", timezone: tz },
                success: function (response) {
                    if (response.success) {
                        localStorage.setItem('admin_synced_tz', tz);
                    }
                }
            });
        }
    });
</script>
