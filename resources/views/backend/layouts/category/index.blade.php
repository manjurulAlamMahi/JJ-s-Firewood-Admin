@extends('backend.master')

@section('title', 'Categories')

@section('body')

    <x-admin.page-header
        title="Categories"
        subtitle="Product groupings shown in the app."
        :crumbs="['Catalogue' => null, 'Categories' => null]">
        <x-slot:actions>
            <a href="{{ route('category.create') }}" class="btn btn-primary">
                <x-admin-icon name="plus" :size="17" /> Add category
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <x-admin.card flush>
        <div class="av-card__body">
            {{-- admin-v2.js turns these rows into stacked cards below 768px, so
                 no horizontal scrolling is needed on a phone. --}}
            <div class="table-responsive">
                <table class="table" id="categories-table" style="width:100%">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Name</th>
                            <th>In-app name</th>
                            <th>Image</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </x-admin.card>
@endsection

@push('scripts')
    <script>
        $(function () {
            var csrfToken = $('meta[name="csrf-token"]').attr('content');

            $('#categories-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: '{{ route('category.data') }}',
                order: [[0, 'desc']],
                columns: [
                    { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false, width: '5%' },
                    { data: 'name', name: 'name' },
                    { data: 'inapp_name', name: 'inapp_name' },
                    {
                        data: 'image',
                        name: 'image',
                        orderable: false,
                        searchable: false,
                        width: '10%',
                        render: function (data) {
                            // Category::$image runs through image_asset(), which
                            // already returns an absolute URL (and substitutes
                            // the shared placeholder when there is no image).
                            // Prefixing "/" here produced "/https://host/…".
                            var src = data || '/no_image/no_image.jpg';
                            if (!/^(https?:)?\/\//i.test(src) && src.charAt(0) !== '/') {
                                src = '/' + src;
                            }
                            return '<img src="' + src + '" class="img-thumbnail" ' +
                                'style="width:52px;height:52px" alt="">';
                        }
                    },
                    {
                        data: 'status',
                        name: 'status',
                        orderable: false,
                        searchable: false,
                        width: '8%',
                        render: function (data, type, row) {
                            var checked = parseInt(data, 10) === 1 ? 'checked' : '';
                            return '<label class="switch">' +
                                '<input type="checkbox" class="toggle-status" data-id="' + row.id + '" ' + checked + '>' +
                                '<span class="slider"></span></label>';
                        }
                    },
                    {
                        data: null,
                        name: 'action',
                        defaultContent: '',
                        orderable: false,
                        searchable: false,
                        width: '15%',
                        render: function (data, type, row) {
                            return '' +
                                '<div class="av-actions">' +
                                    '<a href="/category/' + row.id + '/edit" class="btn btn-sm btn-secondary" title="Edit">' +
                                        '<i class="fa fa-edit"></i><span class="av-btn-label">Edit</span>' +
                                    '</a>' +
                                    '<form action="/category/' + row.id + '/duplicate" method="POST" class="duplicate-form">' +
                                        '<input type="hidden" name="_token" value="' + csrfToken + '">' +
                                        '<button type="button" class="btn btn-sm btn-secondary btn-duplicate" title="Duplicate">' +
                                            '<i class="fa fa-copy"></i><span class="av-btn-label">Duplicate</span>' +
                                        '</button>' +
                                    '</form>' +
                                    '<form action="/category/' + row.id + '" method="POST" class="delete-form">' +
                                        '<input type="hidden" name="_token" value="' + csrfToken + '">' +
                                        '<input type="hidden" name="_method" value="DELETE">' +
                                        '<button type="button" class="btn btn-sm btn-outline-danger btn-delete" title="Delete">' +
                                            '<i class="fa fa-trash"></i><span class="av-btn-label">Delete</span>' +
                                        '</button>' +
                                    '</form>' +
                                '</div>';
                        }
                    }
                ]
            });

            $(document).on('click', '.btn-duplicate', function () {
                var form = $(this).closest('form');

                Swal.fire({
                    title: 'Duplicate this category?',
                    text: 'All details including the image will be cloned.',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Duplicate'
                }).then(function (result) {
                    if (result.isConfirmed) form.submit();
                });
            });

            // Replaces the browser confirm() — unreadable and easy to mis-tap.
            $(document).on('click', '.btn-delete', function () {
                var form = $(this).closest('form');

                Swal.fire({
                    title: 'Delete this category?',
                    text: 'This cannot be undone.',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Delete',
                    confirmButtonColor: '#c02626'
                }).then(function (result) {
                    if (result.isConfirmed) form.submit();
                });
            });

            $(document).on('change', '.toggle-status', function () {
                var checkbox = $(this);
                checkbox.prop('disabled', true);

                $.ajax({
                    url: '/category/' + checkbox.data('id') + '/status',
                    type: 'POST',
                    data: { _token: csrfToken, status: checkbox.is(':checked') ? 1 : 0 },
                    success: function (response) {
                        toastr.success(response.message);
                    },
                    error: function () {
                        toastr.error('Failed to update status.');
                        // Put the switch back where it was so it stops lying.
                        checkbox.prop('checked', !checkbox.is(':checked'));
                    },
                    complete: function () {
                        checkbox.prop('disabled', false);
                    }
                });
            });
        });
    </script>
@endpush
