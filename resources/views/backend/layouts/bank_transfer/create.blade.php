@extends('backend.master')

@section('title')
    Bank Transfer - Create
@endsection

@section('body')
    <x-admin.page-header title="Bank Transfer Create"
        :crumbs="['Bank Transfer' => null, 'Create' => null]" />

<div class="row">
        <div class="col-lg-12">
            <div class="card">
<div class="card-body">
                    <form method="post" action="{{ route('bank_transfer.store') }}" enctype="multipart/form-data">
                        @csrf
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Account Name</label>
                                    <input type="text" class="form-control" name="account_holder_name" placeholder="Enter Account Name">
                                </div>
                            </div>

                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">Account Number</label>
                                        <input type="text" class="form-control" name="account_number" placeholder="Enter Account Number">
                                    </div>
                                </div>

                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">BSB</label>
                                    <input type="text" class="form-control" name="bank_name" placeholder="Enter BSB">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">PAY ID</label>
                                    <input type="text" class="form-control" name="branch_name" placeholder="Enter PAY ID">
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Note</label>
                                    <input type="text" class="form-control" name="note" placeholder="Enter Note">
                                </div>
                            </div>
                            
                        </div>
                        <div class="text-end">
                            <button type="submit" class="btn btn-danger">Create</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection
