@extends('backend.master')

@section('title')
    Info - List
@endsection

@section('body')
    <x-admin.page-header title="Info List"
        :crumbs="['Info' => null, 'List' => null]" />

<!-- TABLE -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h3 class="card-title mb-0">Info List</h3>
            <!-- <a href="{{ route('info.create') }}" class="btn btn-sm btn-primary">Add New Info</a> -->

        </div>

        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="infos-table">
                    <thead>
                        <tr>
                            <th>SL No</th>
                            <th>Title</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Website</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
    <!-- TABLE END -->
@endsection

@push('scripts')
    <meta name="csrf-token" content="{{ csrf_token() }}">


    <script>
        $(document).ready(function() {
            let csrfToken = $('meta[name="csrf-token"]').attr('content');

            let table = $('#infos-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: '{{ route('info.data') }}',
                columns: [{
                        data: 'DT_RowIndex',
                        name: 'DT_RowIndex',
                        orderable: false,
                        searchable: false,
                        width: '5%'
                    },
                    {
                        data: 'title',
                        name: 'title'
                    },
                    {
                        data: 'phone',
                        name: 'phone'
                    },
                    {
                        data: 'email',
                        name: 'email'
                    },
                    {
                        data: 'website',
                        name: 'website'
                    },
                    {
                        data: null,
                    defaultContent: '',
                        name: 'action',
                        orderable: false,
                        searchable: false,
                        width: '15%',
                        render: function(data, type, row) {
                            let editUrl = `/info/${row.id}/edit`;
                            let deleteUrl = `/info/${row.id}`;
                            return `
                            <a href="${editUrl}" class="btn btn-sm btn-icon btn-warning me-1" aria-label="Edit">
                                <i class="fa fa-edit"></i>
                            </a>

                        `;
                        }
                    }
                ],
                order: [
                    [0, 'desc']
                ],
            });
        });
    </script>
@endpush
