@extends('backend.master')
@section('title')
    Delivery Type - Edit
@endsection

@section('body')
    <x-admin.page-header title="Delivery Type Edit"
        :crumbs="['Delivery Type' => null, 'Edit' => null]" />

<div class="col-md-12">
        <div class="card">
            <div class="card-header border-bottom">
                <h3 class="card-title">Edit Delivery Type</h3>
            </div>
            <div class="card-body">
                <form action="{{ route('delivery-type.update', $deliveryType->id) }}" method="POST">
                    @csrf
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Name</label>
                                <input type="text" class="form-control" name="name"
                                       value="{{ old('name', $deliveryType->name) }}" placeholder="Delivery Type Name">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Fee</label>
                                <input type="number" step="0.01" class="form-control" name="fee"
                                       value="{{ old('fee', $deliveryType->fee) }}" placeholder="Delivery Fee">
                            </div>
                        </div>

                        <div>
                            <div class="mb-3">
                                <label class="form-label">Days</label>
                                <input type="text" class="form-control" name="days"
                                       value="{{ old('days', $deliveryType->days) }}" placeholder="Delivery Days">
                            </div>
                        </div>

                    </div>

                    <div>
                        <button type="submit" class="btn btn-primary">Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
