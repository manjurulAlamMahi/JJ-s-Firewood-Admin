{{--
    Coupons tab.

    Backed by the existing coupon.data endpoint, same as the standalone Coupons
    page it replaces in the sidebar.
--}}
<div class="av-card">
    <div class="av-card__head">
        <div class="av-row av-grow">
            <label class="av-input-icon av-grow" style="max-width:380px">
                <i class="fa fa-search"></i>
                <input type="search" id="couponSearch" class="form-control"
                       placeholder="Search coupons…" aria-label="Search coupons">
            </label>
        </div>

        <a href="{{ route('coupon.create') }}" class="btn btn-primary">
            <i class="fa fa-plus"></i> Add Coupon
        </a>
    </div>

    <div class="av-card__body">
        <div class="table-responsive">
            <table class="table" id="coupons-table" style="width:100%">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Code</th>
                        <th>Type</th>
                        <th>Value</th>
                        <th>Min Order</th>
                        <th>Max Discount</th>
                        <th>Usage</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>
