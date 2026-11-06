@extends('backend.master')

@section('title')
    Dynamic Pages - Index
@endsection

@section('body')
    <x-admin.page-header title="Dynamic Pages"
        :crumbs="['Dynamic Pages' => null, 'Index' => null]" />

<!-- TABLE -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Dynamic Pages List</h3>
            <div class="ms-auto pageheader-btn">
                <a href="{{ route('dynamicpage.create') }}" class="btn btn-danger">+ Add Dynamic Page</a>
            </div>
        </div>

        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="dynamicpages-table">
                    <thead>
                        <tr>
                            <th>SL No</th>
                            <th>Title</th>
                            <th>Content</th>
                            <th>Status</th>
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

            $('#dynamicpages-table').DataTable({
                processing: true,
                serverSide: true,
                ajax: {
                    url: '{{ route('dynamicpage.data') }}',
                    type: 'GET',
                    error: function(xhr, error, thrown) {
                        console.log('DataTables error:', xhr.responseText);
                        alert('Error loading data. Check console for details.');
                    }
                },
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
                        data: 'page_content',
                        name: 'page_content'
                    },
                    {
                        data: 'status',
                        name: 'status'
                    },
                    {
                        data: null,
                    defaultContent: '',
                        name: 'action',
                        orderable: false,
                        searchable: false,
                        width: '20%',
                        render: function(data, type, row) {
                            let editUrl = `/dynamicpage/${row.id}/edit`;
                            let deleteUrl = `/dynamicpage/${row.id}`;
                            return `
                                <a href="${editUrl}" class="btn btn-sm btn-warning me-1" title="Edit">
                                    <i class="fa fa-edit"></i>
                                </a>
                                <form action="${deleteUrl}" method="POST" style="display:inline-block;" onsubmit="return confirm('Delete this data?')">
                                    <input type="hidden" name="_token" value="${csrfToken}">
                                    <input type="hidden" name="_method" value="DELETE">
                                    <button class="btn btn-sm btn-danger" title="Delete">
                                        <i class="fa fa-trash"></i>
                                    </button>
                                </form>
                            `;
                        }

                    }
                ],
                order: [
                    [0, 'desc']
                ],
                drawCallback: function(settings) {
                    var pagination = $(this).closest('.dataTables_wrapper').find(
                    '.dataTables_paginate');
                    var info = $(this).closest('.dataTables_wrapper').find('.dataTables_info');
                    pagination.toggle(this.api().page.info().pages > 1);
                    info.toggle(this.api().page.info().pages > 1);
                }
            });
        });
    </script>
@endpush
