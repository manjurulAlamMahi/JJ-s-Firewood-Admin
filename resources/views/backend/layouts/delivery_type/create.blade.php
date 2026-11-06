@extends('backend.master')

@section('title')
    Delivery Type - Create
@endsection

@section('body')
    <x-admin.page-header title="Delivery Type Create"
        :crumbs="['Delivery Type' => null, 'Create' => null]" />

<div class="col-md-12">
        <div class="card">
            <div class="card-header border-bottom">
                <h3 class="card-title">Create New Delivery Type</h3>
            </div>
            <div class="card-body">
                <form action="{{ route('delivery-type.store') }}" method="POST">
                    @csrf
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Name</label>
                                <input type="text" class="form-control" name="name" placeholder="Delivery Type Name">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Fee</label>
                                <input type="number" step="0.01" class="form-control" name="fee" placeholder="Delivery Fee">
                            </div>
                        </div>

                        <div>
                            <div class="mb-3">
                                <label class="form-label">Days</label>
                                <input type="text" class="form-control" name="days" placeholder="Delivery Days">
                            </div>
                        </div>

                    </div>

                    <div>
                        <button type="submit" class="btn btn-primary">Create</button>
                    </div>
                </form>
            </div>

        </div>
    </div>
@endsection
