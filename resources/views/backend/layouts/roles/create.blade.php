@extends('backend.master')

@section('title')
    Roles - Create
@endsection

@section('body')
    <x-admin.page-header title="Roles Create"
        :crumbs="['Role' => null, 'Create' => null]" />

@if ($errors->any())
        <div class="alert alert-danger">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <section>
        <div class="container-fluid mb-3">
            <a href="#" data-bs-toggle="modal" data-bs-target="#createModal" class="btn btn-primary">
                <i class="dripicons-plus"></i> Add Role
            </a>
        </div>
        <div class="table-responsive">
            <table id="role-table" class="table table-hover">
                <thead>
                    <tr>
                        <th class="not-exported"></th>
                        <th>Name</th>
                        <th>Description</th>
                        <th class="not-exported">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($lims_role_all as $key => $role)
                    <tr>
                        <td>{{ $key + 1 }}</td>
                        <td>{{ $role->name }}</td>
                        <td>{{ $role->description }}</td>
                        <td>
                            <div class="btn-group">
                                <button type="button" class="btn btn-secondary btn-sm dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                                    Action
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <button type="button" data-id="{{ $role->id }}" class="open-EditroleDialog btn btn-link dropdown-item" data-bs-toggle="modal" data-bs-target="#editModal">
                                            <i class="dripicons-document-edit"></i> Edit
                                        </button>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a href="{{ route('role.permission', ['id' => $role->id]) }}" class="dropdown-item">
                                            <i class="dripicons-lock-open"></i> Change Permission
                                        </a>
                                    </li>
                                    @if($role->id > 2 && $role->id != 5)
                                        <li>
                                            <form action="{{ route('role.destroy', $role->id) }}" method="POST" style="display:inline-block;">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="dropdown-item text-danger" onclick="return confirmDelete()">
                                                    <i class="dripicons-trash"></i> Delete
                                                </button>
                                            </form>
                                        </li>
                                    @endif
                                </ul>
                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    <!-- Create Role Modal -->
    <div id="createModal" tabindex="-1" aria-labelledby="createModalLabel" aria-hidden="true" class="modal fade text-left">
        <div class="modal-dialog">
            <div class="modal-content">
                <form action="{{ route('role.store') }}" method="POST">
                    @csrf
                    <div class="modal-header">
                        <h5 id="createModalLabel" class="modal-title">Add Role</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="italic"><small>The field labels marked with * are required input fields.</small></p>
                        <div class="mb-3">
                            <label class="form-label">Name *</label>
                            <input type="text" name="name" required class="form-control" />
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" rows="5" class="form-control"></textarea>
                        </div>
                        <input type="hidden" name="is_active" value="1" />
                        <input type="hidden" name="guard_name" value="web" />
                    </div>
                    <div class="modal-footer">
                        <input type="submit" value="Submit" class="btn btn-primary" />
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Role Modal -->
    <div id="editModal" tabindex="-1" aria-labelledby="editModalLabel" aria-hidden="true" class="modal fade text-left">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="editRoleForm" action="" method="POST">
                    @csrf
                    <!-- No method spoofing _method -->
                    <div class="modal-header">
                        <h5 id="editModalLabel" class="modal-title">Update Role</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <p class="italic"><small>The field labels marked with * are required input fields.</small></p>
                        <input type="hidden" name="role_id" />
                        <div class="mb-3">
                            <label class="form-label">Name *</label>
                            <input type="text" name="name" required class="form-control" />
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea name="description" rows="5" class="form-control"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <input type="submit" value="Submit" class="btn btn-primary" />
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script type="text/javascript">
    function confirmDelete() {
        return confirm("Are you sure want to delete?");
    }

    $(document).ready(function() {
        // Open Edit Modal and populate data
        $(document).on('click', '.open-EditroleDialog', function() {
            var id = $(this).data('id').toString();
            var url = "role/" + id + "/edit";

            $.get(url, function(data) {
                var form = $('#editRoleForm');
                // Set form action to POST route
                form.attr('action', 'role/update/' + id);

                // Remove _method if present (no PUT spoofing)
                form.find("input[name='_method']").remove();

                form.find("input[name='name']").val(data.name);
                form.find("textarea[name='description']").val(data.description);
                form.find("input[name='role_id']").val(data.id);
            });
        });

        $.ajaxSetup({
            headers: {
                'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
            }
        });

        $('#role-table').DataTable({
            "order": [],
            'language': {
                'lengthMenu': '_MENU_ records per page',
                "info": '<small>Showing _START_ - _END_ (_TOTAL_)</small>',
                "search": 'Search',
                'paginate': {
                    'previous': '<i class="dripicons-chevron-left"></i>',
                    'next': '<i class="dripicons-chevron-right"></i>'
                }
            },
            'columnDefs': [
                {
                    "orderable": false,
                    'targets': [0, 3]
                },
                {
                    'render': function(data, type, row, meta){
                        if(type === 'display'){
                            data = '<div class="form-check"><input type="checkbox" class="form-check-input dt-checkboxes"><label></label></div>';
                        }
                        return data;
                    },
                    'checkboxes': {
                        'selectRow': true,
                        'selectAllRender': '<div class="form-check"><input type="checkbox" class="form-check-input dt-checkboxes"><label></label></div>'
                    },
                    'targets': [0]
                }
            ],
            'select': { style: 'multi',  selector: 'td:first-child'},
            'lengthMenu': [[10, 25, 50, -1], [10, 25, 50, "All"]],
            dom: '<"row"lfB>rtip',
            buttons: [
                {
                    extend: 'pdf',
                    text: '<i title="export to pdf" class="fa fa-file-pdf-o"></i>',
                    exportOptions: {
                        columns: ':visible:Not(.not-exported)',
                        rows: ':visible'
                    },
                },
                {
                    extend: 'excel',
                    text: '<i title="export to excel" class="dripicons-document-new"></i>',
                    exportOptions: {
                        columns: ':visible:Not(.not-exported)',
                        rows: ':visible'
                    },
                },
                {
                    extend: 'csv',
                    text: '<i title="export to csv" class="fa fa-file-text-o"></i>',
                    exportOptions: {
                        columns: ':visible:Not(.not-exported)',
                        rows: ':visible'
                    },
                },
                {
                    extend: 'print',
                    text: '<i title="print" class="fa fa-print"></i>',
                    exportOptions: {
                        columns: ':visible:Not(.not-exported)',
                        rows: ':visible'
                    },
                },
                {
                    extend: 'colvis',
                    text: '<i title="column visibility" class="fa fa-eye"></i>',
                    columns: ':gt(0)'
                },
            ],
        });
    });
</script>
@endpush
