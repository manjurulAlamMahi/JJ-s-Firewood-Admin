@extends('backend.master')

@section('title')
    Roles - Create
@endsection

@section('body')
    <section class="forms">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center">
                        <h4>Group Permission</h4>
                    </div>

                    <!-- Start of standard HTML form -->
                    <form action="{{ route('role.setPermission') }}" method="POST">
                        @csrf
                        <div class="card-body">
                            <input type="hidden" name="role_id" value="{{ $lims_role_data->id }}" />
                            <div class="table-responsive">
                                <table class="table table-bordered permission-table">
                                    <thead>
                                    <tr>
                                        <th colspan="5" class="text-center">{{ $lims_role_data->name }} Group Permission</th>
                                    </tr>
                                    <tr>
                                        <th rowspan="2" class="text-center">Module Name</th>
                                        <th colspan="4" class="text-center">
                                            <div class="checkbox">
                                                <input type="checkbox" id="select_all">
                                                <label for="select_all">Permissions</label>
                                            </div>
                                        </th>
                                    </tr>
                                    <tr>
                                        <th class="text-center">View</th>
                                        <th class="text-center">Add</th>
                                        <th class="text-center">Edit</th>
                                        <th class="text-center">Delete</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <tr>
                                        <td>User</td>
                                        <td class="text-center">
                                            <div class="icheckbox_square-blue checked" aria-checked="false" aria-disabled="false">
                                                <div class="checkbox">
                                                    @if(in_array("user-index", $all_permission))
                                                    <input type="checkbox" value="1" id="user-index" name="user-index" checked />
                                                    @else
                                                    <input type="checkbox" value="1" id="user-index" name="user-index" />
                                                    @endif
                                                    <label for="user-index"></label>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
                                                <div class="checkbox">
                                                    @if(in_array("user-add", $all_permission))
                                                    <input type="checkbox" value="1" id="user-add" name="user-add" checked>
                                                    @else
                                                    <input type="checkbox" value="1" id="user-add" name="user-add">
                                                    @endif
                                                    <label for="user-add"></label>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
                                                <div class="checkbox">
                                                    @if(in_array("user-edit", $all_permission))
                                                    <input type="checkbox" value="1" id="user-edit" name="user-edit" checked />
                                                    @else
                                                    <input type="checkbox" value="1" id="user-edit" name="user-edit" />
                                                    @endif
                                                    <label for="user-edit"></label>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-center">
                                            <div class="icheckbox_square-blue" aria-checked="false" aria-disabled="false">
                                                <div class="checkbox">
                                                    @if(in_array("user-delete", $all_permission))
                                                    <input type="checkbox" value="1" id="user-delete" name="user-delete" checked />
                                                    @else
                                                    <input type="checkbox" value="1" id="user-delete" name="user-delete" />
                                                    @endif
                                                    <label for="user-delete"></label>
                                                </div>
                                            </div>
                                        </td>
                                    </tr>

                                    </tbody>
                                </table>
                            </div>
                            <div class="form-group">
                                <input type="submit" value="Submit" class="btn btn-primary">
                            </div>
                        </div>
                    </form>
                    <!-- End of standard HTML form -->

                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@push('scripts')
<script type="text/javascript">

    $("ul#setting").siblings('a').attr('aria-expanded','true');
    $("ul#setting").addClass("show");
    $("ul#setting #role-menu").addClass("active");

    @if(config('database.connections.saleprosaas_landlord'))
        $.ajax({
        type: 'GET',
        async: false,
        url: '{{ route("package.fetchData", $general_setting->package_id) }}',
        success: function(data) {
            features = data['features'];
            if(!features.includes("expense"))
                $("tr.expense-row").addClass('d-none');
            if(!features.includes("quotation"))
                $("tr.quotation-row").addClass('d-none');
            if(!features.includes("transfer"))
                $("tr.transfer-row").addClass('d-none');
            if(!features.includes("delivery"))
                $("span.delivery-section").addClass('d-none');
            if(!features.includes("stock_count_and_adjustment ")) {
                $("span.stock-count-section").addClass('d-none');
                $("span.adjustment-section").addClass('d-none');
            }
            if(!features.includes("report"))
                $("tr.report-row").addClass('d-none');
            if(!features.includes("accounting"))
                $("tr.accounting-row").addClass('d-none');
            if(!features.includes("hrm")) {
                $("tr.employee-row").addClass('d-none');
                $("tr.hrm-row").addClass('d-none');
                $("span.hrm-setting-section").addClass('d-none');
            }
        }
    });
    @endif

    $("#select_all").on( "change", function() {
        if ($(this).is(':checked')) {
            $("tbody input[type='checkbox']").prop('checked', true);
        }
        else {
            $("tbody input[type='checkbox']").prop('checked', false);
        }
    });
</script>
@endpush
