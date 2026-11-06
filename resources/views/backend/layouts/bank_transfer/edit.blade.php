@extends('backend.master')

@section('title')
    Bank Transfer - Edit
@endsection

@section('body')
    <x-admin.page-header title="Bank Transfer Edit"
        :crumbs="['Bank Transfer' => null, 'Edit' => null]" />

<div class="col-md-12">
        <div class="card">
            <div class="card-header border-bottom">
                <h3 class="card-title">Edit Bank Transfer</h3>
            </div>
            <div class="card-body">
                <form method="POST" action="{{ route('bank_transfer.update', $bankTransfer->id) }}" enctype="multipart/form-data">
                    @csrf

                    <div class="row">
                        <!-- Account Holder Name -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Account Name</label>
                                <input type="text" class="form-control" name="account_holder_name" 
                                    value="{{ old('account_holder_name', $bankTransfer->account_holder_name) }}" 
                                    placeholder="Enter Account Name">
                                @error('account_holder_name')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <!-- Account Number -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Account Number</label>
                                <input type="text" class="form-control" name="account_number" 
                                    value="{{ old('account_number', $bankTransfer->account_number) }}" 
                                    placeholder="Enter Account Number">
                                @error('account_number')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <!-- Bank Name -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">BSB</label>
                                <input type="text" class="form-control" name="bank_name" 
                                    value="{{ old('bank_name', $bankTransfer->bank_name) }}" 
                                    placeholder="Enter BSB">
                                @error('bank_name')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <!-- Branch Name -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">PAY ID</label>
                                <input type="text" class="form-control" name="branch_name" 
                                    value="{{ old('branch_name', $bankTransfer->branch_name) }}" 
                                    placeholder="Enter PAY ID">
                                @error('branch_name')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        <!-- Note -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Note</label>
                                <input type="text" class="form-control" name="note" 
                                    value="{{ old('note', $bankTransfer->note) }}" 
                                    placeholder="Enter Note">   
                                @error('note')
                                    <div class="text-danger">{{ $message }}</div>
                                @enderror
                            </div>
                        </div>

                        

                        <!-- Submit Button -->
                        <div class="text-end">
                            <button type="submit" class="btn btn-danger">Update</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
