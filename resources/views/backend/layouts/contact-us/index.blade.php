@extends('backend.master')

@section('title')
Contact Us - List
@endsection

@section('body')
    <x-admin.page-header title="Contact Us List"
        :crumbs="['Contact Us' => null, 'List' => null]" />

<!-- TABLE -->
<div class="card">
<div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" id="contact-us-table">
                <thead>
                    <tr>
                        <th>SL No</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Subject</th>
                        <th>Message</th>
                        <th>Attachment</th>
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

<!-- Switch CSS -->
<style>
    .switch {
        position: relative;
        display: inline-block;
        width: 50px;
        height: 26px;
    }

    .switch input {
        opacity: 0;
        width: 0;
        height: 0;
    }

    .slider {
        position: absolute;
        cursor: pointer;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: #dc3545;
        transition: .4s;
        border-radius: 34px;
    }

    .slider:before {
        position: absolute;
        content: "";
        height: 20px;
        width: 20px;
        left: 3px;
        bottom: 3px;
        background-color: white;
        transition: .4s;
        border-radius: 50%;
    }

    input:checked+.slider {
        background-color: #28a745;
    }

    input:checked+.slider:before {
        transform: translateX(24px);
    }
</style>
<script>
    $(document).ready(function() {
        let csrfToken = $('meta[name="csrf-token"]').attr('content');

        // Show session flash messages via toastr
        @if(session('success'))
            toastr.success('{{ session('success') }}');
        @endif
        @if(session('error'))
            toastr.error('{{ session('error') }}');
        @endif

        let table = $('#contact-us-table').DataTable({
            processing: true,
            serverSide: true,
            ajax: '{{ route('contact-us.data') }}',
            columns: [{
                    data: 'DT_RowIndex',
                    name: 'DT_RowIndex',
                    orderable: false,
                    searchable: false,
                    width: '5%'
                },
                {
                    data: 'name',
                    name: 'name'
                },
                {
                    data: 'email',
                    name: 'email'
                },
                {
                    data: 'subject',
                    name: 'subject'
                },
                {
                    data: 'message',
                    name: 'message'
                },
                {
                    data: 'attachment',
                    name: 'attachment',
                    orderable: false,
                    searchable: false
                },
                {
                    data: null,
                    defaultContent: '',
                    name: 'action',
                    orderable: false,
                    searchable: false,
                    width: '15%',
                    render: function(data, type, row) {
                        let showUrl = `/contact-us/${row.id}`;
                        let deleteUrl = `/contact-us/${row.id}`;
                        return `
                        <a href="${showUrl}" class="btn btn-sm btn-icon btn-info me-1" title="View">
                            <i class="fa fa-eye"></i>
                        </a>
                        <form action="${deleteUrl}" method="POST" style="display:inline-block;" class="delete-form">
                            <input type="hidden" name="_token" value="${csrfToken}">
                            <input type="hidden" name="_method" value="DELETE">
                            <button type="button" class="btn btn-sm btn-icon btn-danger btn-delete" title="Delete">
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
        });

        // SweetAlert Delete Confirmation
        $(document).on('click', '.btn-delete', function(e) {
            e.preventDefault();
            let form = $(this).closest('form');

            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to revert this!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            })
        });
    });
</script>
@endpush