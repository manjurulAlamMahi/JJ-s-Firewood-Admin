@extends('backend.master')

@section('title')
    Giveaway - Edit
@endsection

@section('body')
    <x-admin.page-header title="Edit Giveaway"
        :crumbs="['Giveaway' => null, 'Edit' => null]" />

<div class="col-md-12">
        <div class="card">
<div class="card-body">
                <form action="{{ route('giveaways.update', $giveaway->id) }}" method="POST" enctype="multipart/form-data">
                    @csrf
                    <div class="row">

                        <!-- Prize Name -->
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">Prize Name</label>
                                <input type="text" class="form-control" name="prize_name" placeholder="Enter Prize Name"
                                    value="{{ old('prize_name', $giveaway->prize_name) }}" required>
                            </div>
                        </div>



                        <!-- Description -->
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">Description</label>
                                <textarea class="form-control" name="description" rows="4" placeholder="Enter Description">{{ old('description', $giveaway->description) }}</textarea>
                            </div>
                        </div>

                        <!-- Image -->
                        <div class="col-md-12">
                            <div class="mb-3">
                                <label class="form-label">Image</label>
                                <input type="file" class="form-control" name="image">
                                @if ($giveaway->image)
                                    <img src="{{ asset('storage/' . $giveaway->image) }}" alt="Giveaway Image"
                                        class="img-thumbnail mt-2" width="150">
                                @endif
                            </div>
                        </div>



                        <!-- Prize Value -->
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Prize Value</label>
                                <input type="number" step="0.01" class="form-control" name="prize_value"
                                    placeholder="Enter Prize Value" value="{{ old('prize_value', $giveaway->prize_value) }}"
                                    required>
                            </div>
                        </div>


                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Prize Type</label>
                                <select name="prize_type" class="form-control">
                                    <option value="" disabled>Select Prize Type</option>
                                    <option value="bundle"
                                        {{ old('prize_type', $giveaway->prize_type) == 'bundle' ? 'selected' : '' }}>Bundles
                                    </option>
                                    <option value="discount"
                                        {{ old('prize_type', $giveaway->prize_type) == 'discount' ? 'selected' : '' }}>
                                        Discount</option>
                                    <option value="accessory"
                                        {{ old('prize_type', $giveaway->prize_type) == 'accessory' ? 'selected' : '' }}>
                                        Accessories</option>
                                    <option value="others"
                                        {{ old('prize_type', $giveaway->prize_type) == 'others' ? 'selected' : '' }}>Others
                                    </option>
                                </select>
                            </div>
                        </div>

                        <!-- Start Date -->
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Start Date</label>
                                <input type="date" class="form-control" name="start_date"
                                    value="{{ old('start_date', $giveaway->start_date->format('Y-m-d')) }}" required>
                            </div>
                        </div>


                        <!-- Draw Date -->
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">Draw Date</label>
                                <input type="date" class="form-control" name="draw_date"
                                    value="{{ old('draw_date', $giveaway->draw_date->format('Y-m-d')) }}">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Required Entries</label>
                                <input type="number" class="form-control" name="required_entries"
                                    placeholder="Enter Required Entries"
                                    value="{{ old('required_entries', $giveaway->required_entries) }}">
                            </div>
                        </div>

                    </div>

                    <div class="text-end">
                        <button type="submit" class="btn btn-danger">Update Giveaway</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
@endsection