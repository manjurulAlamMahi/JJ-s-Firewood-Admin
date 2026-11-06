@extends('backend.master')

@section('title')
    Entry Package - Edit
@endsection

@section('body')
    <x-admin.page-header title="Edit Entry Package"
        :crumbs="['Entry Packages' => null, 'Edit' => null]" />

<div class="col-md-12">
        <div class="card">
<div class="card-body">

                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul>
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('entry-package.update', $entryPackage->id) }}" method="POST">
                    @csrf
                    <div class="row">

                        <!-- Name -->
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">Package Name</label>
                                <input type="text" class="form-control" name="name" placeholder="Enter Package Name"
                                       value="{{ old('name', $entryPackage->name) }}" required>
                            </div>
                        </div>

                        <!-- Price -->
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">Price ($)</label>
                                <input type="number" step="0.01" class="form-control" name="price" placeholder="Enter Price"
                                       value="{{ old('price', $entryPackage->price) }}" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Quantity</label>
                                <input type="text" class="form-control" name="entry_quantity" value="{{ $entryPackage->entry_quantity }}"
                                    placeholder="Enter Quantity">
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">Description (optional)</label>
                                <textarea class="form-control" name="description" rows="3"
                                    placeholder="Shown to customers on the entry package card">{{ old('description', $entryPackage->description) }}</textarea>
                            </div>
                        </div>

                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-warning">Update Entry Package</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
