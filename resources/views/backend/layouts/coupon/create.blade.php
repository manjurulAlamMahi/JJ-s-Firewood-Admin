@extends('backend.master')
@section('title')
    Coupon - Create
@endsection
@section('body')
    <!-- PAGE-HEADER -->
    <div class="page-header">
        <div>
            <h1 class="page-title">Create Coupon</h1>
        </div>
        <div class="ms-auto pageheader-btn"></div>
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="javascript:void(0);">Coupon</a></li>
            <li class="breadcrumb-item active" aria-current="page">Create</li>
        </ol>
    </div>
    <!-- PAGE-HEADER END -->

    <div class="col-md-12">
        <div class="card">
            <div class="card-header border-bottom">
                <h3 class="card-title">Create New Coupon</h3>
            </div>
            <div class="card-body">
                {{-- Show validation errors --}}
                @if ($errors->any())
                    <div class="alert alert-danger mb-3">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form action="{{ route('coupon.store') }}" method="POST">
                    @csrf
                    <div class="row">
                        <!-- Coupon Code -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Coupon Code</label>
                            <input type="text" id="code" name="code" class="form-control" placeholder="Enter code manually or click Generate">
                            <button type="button" class="btn btn-danger mt-2" id="generateCodeBtn">Generate Code</button>
                        </div>

                        <!-- Type -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Type</label>
                            <select name="type" class="form-control">
                                <option value="percentage">Percentage</option>
                                <option value="fixed">Fixed Amount</option>
                            </select>
                        </div>

                        <!-- Value -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Value</label>
                            <input type="number" name="value" class="form-control" placeholder="Enter discount value">
                        </div>

                        <!-- Min Order Amount -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Minimum Order Amount</label>
                            <input type="number" name="min_order_amount" class="form-control" placeholder="Optional">
                        </div>

                        <!-- Max Discount -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Maximum Discount (for %)</label>
                            <input type="number" name="max_discount" class="form-control" placeholder="Optional">
                        </div>

                        <!-- Usage Limit -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Usage Limit</label>
                            <input type="number" name="usage_limit" class="form-control" placeholder="Optional">
                        </div>

                        <!-- Start Date -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="start_date" class="form-control">
                        </div>

                        <!-- End Date -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">End Date</label>
                            <input type="date" name="end_date" class="form-control">
                        </div>

                        <!-- Status -->
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select name="is_active" class="form-control">
                                <option value="1" selected>Active</option>
                                <option value="0">Inactive</option>
                            </select>
                        </div>

                    </div>

                    <button type="submit" class="btn btn-danger">Save Coupon</button>
                </form>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    document.getElementById('generateCodeBtn').addEventListener('click', function() {
        fetch('{{ route("coupon.generateCode") }}')
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    document.getElementById('code').value = data.data.code;
                } else {
                    alert('Failed to generate code');
                }
            });
    });
</script>
@endpush
