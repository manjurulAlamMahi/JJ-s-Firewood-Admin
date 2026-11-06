@extends('backend.master')

@section('title')
    Giveaway - Create
@endsection

@section('body')
    <x-admin.page-header title="Create Giveaway"
        :crumbs="['Giveaway' => null, 'Create' => null]" />

<div class="col-md-12">
        <div class="card">
            <div class="card-header border-bottom">
                <h3 class="card-title">Create New Giveaway</h3>
            </div>
            <div class="card-body">

                {{-- Validation Errors --}}
                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul>
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('giveaways.store') }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="row">

                        <!-- Prize Name -->
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">Prize Name</label>
                                <input type="text" class="form-control" name="prize_name" placeholder="Enter Prize Name"
                                    value="{{ old('prize_name') }}" required>
                            </div>
                        </div>


                        <!-- Description -->
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" rows="4" placeholder="Enter Description">{{ old('description') }}</textarea>
                            </div>
                        </div>

                        <!-- Image -->
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">Image</label>
                                <input type="file" class="form-control dropify" name="image">
                            </div>
                        </div>


                        <!-- Prize Value -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Prize Value</label>
                                <input type="number" step="0.01" class="form-control" name="prize_value"
                                    placeholder="Enter Prize Value" value="{{ old('prize_value') }}" required>
                            </div>
                        </div>


                        <!-- Title -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Prize Type</label>
                                <select name="prize_type" class="form-control" required>
                                    <option value="" selected disabled>Select Prize Type</option>
                                    <option value="bundle" {{ old('prize_type') == 'bundle' ? 'selected' : '' }}>Bundles
                                    </option>
                                    <option value="discount" {{ old('prize_type') == 'discount' ? 'selected' : '' }}>Discount
                                    </option>
                                    <option value="accessory" {{ old('prize_type') == 'accessory' ? 'selected' : '' }}>
                                        Accessories</option>
                                    <option value="others" {{ old('prize_type') == 'others' ? 'selected' : '' }}>Others
                                    </option>
                                </select>
                            </div>
                        </div>

                        <!-- Start Date -->
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Start Date</label>
                                <input type="date" class="form-control" name="start_date" value="{{ old('start_date') }}"
                                    required>
                            </div>
                        </div>



                        <!-- Draw Date -->
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Draw Date</label>
                                <input type="date" class="form-control" name="draw_date" value="{{ old('draw_date') }}">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Required Entries</label>
                                <input type="number" class="form-control" name="required_entries"
                                    placeholder="Enter Required Entries" value="{{ old('required_entries') }}" >
                            </div>
                        </div>



                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-danger">Create Giveaway</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection