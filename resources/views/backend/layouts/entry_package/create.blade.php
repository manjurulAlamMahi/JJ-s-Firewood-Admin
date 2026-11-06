@extends('backend.master')

@section('title')
    Entry Package - Create
@endsection

@section('body')
    <x-admin.page-header title="Create Entry Package"
        :crumbs="['Entry Packages' => null, 'Create' => null]" />

<div class="col-md-12">
        <div class="card">
            <div class="card-header border-bottom">
                <h3 class="card-title">Create New Entry Package</h3>
            </div>
            <div class="card-body">

                {{-- Validation Errors --}}
                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul>
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('entry-package.store') }}" method="POST">
                    @csrf
                    <div class="row">

                        <!-- Name -->
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">Package Name</label>
                                <input type="text" class="form-control" name="name" placeholder="Enter Package Name"
                                       value="{{ old('name') }}" required>
                            </div>
                        </div>

                        <!-- Price -->
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">Price ($)</label>
                                <input type="number" step="0.01" class="form-control" name="price" placeholder="Enter Price"
                                       value="{{ old('price') }}" required>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Quantity</label>
                                <input type="text" class="form-control" name="entry_quantity" placeholder="Enter Quantity">
                            </div>
                        </div>

                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">Description (optional)</label>
                                <textarea class="form-control" name="description" rows="3"
                                    placeholder="Shown to customers on the entry package card">{{ old('description') }}</textarea>
                            </div>
                        </div>

                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-danger">Create Entry Package</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection
