@extends('backend.master')

@section('title')
    Delivery Calculator
@endsection

@section('body')
    <x-admin.page-header title="Delivery Calculator"
        :crumbs="['Delivery Calculator' => null]" />

<div class="row">
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Check Delivery Fee for a Postcode</h3>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Postcode</label>
                        <input type="text" id="calc-postcode" class="form-control" placeholder="e.g. 6000">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Delivery Type</label>
                        <select id="calc-delivery-type" class="form-control">
                            <option value="">-- Select Delivery Type --</option>
                            @foreach ($deliveryTypes as $type)
                                <option value="{{ $type->id }}">{{ $type->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="button" id="calc-submit" class="btn btn-primary">Calculate</button>

                    <div id="calc-result" class="mt-4" style="display: none;">
                        <hr>
                        <p><strong>Deliverable:</strong> <span id="calc-deliverable"></span></p>
                        <p><strong>Fee:</strong> $<span id="calc-fee"></span></p>
                        <p><strong>Zone matched:</strong> <span id="calc-zone"></span></p>
                    </div>
                    <div id="calc-error" class="alert alert-danger mt-3" style="display: none;"></div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('scripts')
<script>
    $(document).ready(function () {
        let csrf = $('meta[name="csrf-token"]').attr('content');

        $('#calc-submit').on('click', function () {
            let postcode = $('#calc-postcode').val();
            let deliveryTypeId = $('#calc-delivery-type').val();

            $('#calc-error').hide();
            $('#calc-result').hide();

            if (!postcode || !deliveryTypeId) {
                $('#calc-error').text('Enter a postcode and select a delivery type.').show();
                return;
            }

            $.ajax({
                url: "{{ route('delivery-calculator.calculate') }}",
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf },
                data: { postcode: postcode, delivery_type_id: deliveryTypeId },
                success: function (res) {
                    $('#calc-deliverable').text(res.deliverable ? 'Yes' : 'No');
                    $('#calc-fee').text(res.fee !== null ? Number(res.fee).toFixed(2) : 'N/A');
                    $('#calc-zone').text(res.zone_matched ? 'Yes (zone-specific fee)' : 'No (base fee)');
                    $('#calc-result').show();
                },
                error: function (xhr) {
                    $('#calc-error').text('Error: ' + (xhr.responseJSON?.message || 'Calculation failed.')).show();
                }
            });
        });
    });
</script>
@endpush
