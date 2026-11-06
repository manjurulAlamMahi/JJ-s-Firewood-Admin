{{--
    Delivery contact + address block for non-app (phone/WC) delivery emails.

    Mirrors the look of emails/partials/contact-block.blade.php but takes loose
    fields instead of an Order address object, because phone/WC Setmore bookings
    don't carry a full address relation. Every field is optional — the block
    renders only the rows it has data for.

    Expects (all optional):
        $customerName, $addressLine, $suburb, $state, $postcode,
        $customerPhone, $customerEmail
--}}
@php
    $dcName    = $customerName ?? null;
    $dcLine    = $addressLine ?? null;
    $dcSuburb  = $suburb ?? null;
    $dcState   = $state ?? null;
    $dcZip     = $postcode ?? null;
    $dcPhone   = $customerPhone ?? null;
    $dcEmail   = $customerEmail ?? null;
    $dcTel     = $dcPhone ? preg_replace('/[^0-9+]/', '', $dcPhone) : '';
    $dcHasAddr = $dcLine || $dcSuburb || $dcState || $dcZip;
@endphp

<div class="contact-block">
    <div class="cb-title">Delivery details</div>

    @if($dcHasAddr)
        <div class="cb-row cb-address-line">
            <table cellpadding="0" cellspacing="0" border="0" style="border: 0;">
                <tr>
                    <td style="vertical-align: top; padding-right: 8px; color: #9b2d2d; font-weight: 700;">📍</td>
                    <td style="vertical-align: top;">
                        @if($dcLine){{ $dcLine }}<br>@endif
                        @if($dcSuburb){{ $dcSuburb }}<br>@endif
                        @if($dcState || $dcZip)
                            {{ trim(($dcState ?? '') . ' ' . ($dcZip ?? '')) }}
                        @endif
                    </td>
                </tr>
            </table>
        </div>
    @endif

    @if($dcPhone)
        <div class="cb-row">
            <span class="cb-icon">📞</span>
            <a href="tel:{{ $dcTel }}" style="color: #3e1f1f; text-decoration: none;">{{ $dcPhone }}</a>
        </div>
    @endif

    @if($dcEmail)
        <div class="cb-row">
            <span class="cb-icon">✉️</span>
            <a href="mailto:{{ $dcEmail }}" style="color: #3e1f1f; text-decoration: none;">{{ $dcEmail }}</a>
        </div>
    @endif
</div>
