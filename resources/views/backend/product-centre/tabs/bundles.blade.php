{{--
    Bundles tab.

    Reuses the existing server-side endpoint (bundles.data) rather than
    duplicating any query logic — this is the same data the standalone Bundles
    page served before it was folded into Product Centre.
--}}
<div class="av-card">
    <div class="av-card__head">
        <div class="av-row av-grow">
            <label class="av-input-icon av-grow" style="max-width:380px">
                <i class="fa fa-search"></i>
                <input type="search" id="bundleSearch" class="form-control"
                       placeholder="Search bundles…" aria-label="Search bundles">
            </label>
        </div>

        <a href="{{ route('bundles.create') }}" class="btn btn-primary">
            <i class="fa fa-plus"></i> Add Bundle
        </a>
    </div>

    <div class="av-card__body">
        <div class="table-responsive">
            <table class="table" id="bundles-table" style="width:100%">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Base Product</th>
                        <th>Price</th>
                        <th>Image</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>
