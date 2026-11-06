{{--
    Customer contact + address block — shared across all order emails.
    Expects $address (an object with full_name, phone, email, address, suburb, city, state, zip_code).
    Optional: $userEmail fallback if address email is missing.
    Optional: $title — defaults to "Delivery details"
--}}
@php
    $address    = $address ?? null;
    $title      = $title ?? 'Delivery details';
    $userEmail  = $userEmail ?? null;
    $name       = optional($address)->full_name;
    $phone      = optional($address)->phone;
    $emailAddr  = optional($address)->email ?: $userEmail;
    $line1      = optional($address)->address;
    $suburb     = optional($address)->suburb;
    $city       = optional($address)->city;
    $state      = optional($address)->state;
    $zip        = optional($address)->zip_code;
    $telHref    = $phone ? preg_replace('/[^0-9+]/', '', $phone) : '';
@endphp

<div class="contact-block">
    <div class="cb-title">{{ $title }}</div>

    @if($name)
        <div class="cb-name">{{ $name }}</div>
    @endif

    @if($line1 || $suburb || $city || $state || $zip)
        <div class="cb-row cb-address-line">
            <table cellpadding="0" cellspacing="0" border="0" style="border: 0;">
                <tr>
                    <td style="vertical-align: top; padding-right: 8px; color: #9b2d2d; font-weight: 700;">📍</td>
                    <td style="vertical-align: top;">
                        @if($line1){{ $line1 }}<br>@endif
                        @if($suburb && $city)
                            {{ $suburb }}, {{ $city }}<br>
                        @elseif($suburb)
                            {{ $suburb }}<br>
                        @elseif($city)
                            {{ $city }}<br>
                        @endif
                        @if($state || $zip)
                            {{ trim(($state ?? '') . ' ' . ($zip ?? '')) }}
                        @endif
                    </td>
                </tr>
            </table>
        </div>
    @endif

    @if($phone)
        <div class="cb-row">
            <span class="cb-icon">📞</span>
            <a href="tel:{{ $telHref }}" style="color: #3e1f1f; text-decoration: none;">{{ $phone }}</a>
        </div>
    @endif

    @if($emailAddr)
        <div class="cb-row">
            <span class="cb-icon">✉️</span>
            <a href="mailto:{{ $emailAddr }}" style="color: #3e1f1f; text-decoration: none;">{{ $emailAddr }}</a>
        </div>
    @endif
</div>
