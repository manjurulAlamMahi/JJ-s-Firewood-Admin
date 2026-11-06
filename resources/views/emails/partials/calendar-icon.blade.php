{{--
    Calendar icon block — table-based, Outlook-safe, mobile-friendly.
    Renders the actual delivery date instead of using the 📅 emoji
    (which is hardcoded to "July 17" on most platforms).

    Expects $calendarDate (string or Carbon instance — anything parseable).

    Optional: $calendarSubLabel — if provided, replaces the auto day-of-week
    (defaults to e.g. "Wednesday").
--}}
@php
    // Parse the date inline. Fall back to dashes if missing/invalid.
    $_calDateInput = $calendarDate ?? null;
    $calMonth = $_calDateInput ? strtoupper(\Carbon\Carbon::parse($_calDateInput)->format('M')) : '—';
    $calDay = $_calDateInput ? \Carbon\Carbon::parse($_calDateInput)->format('j') : '—';
    $calWeekday = $_calDateInput ? \Carbon\Carbon::parse($_calDateInput)->format('l') : '';
    $calSubLabel = $calendarSubLabel ?? $calWeekday;
@endphp

<table cellpadding="0" cellspacing="0" border="0" align="center" role="presentation" style="margin: 0 auto 14px auto;">
    <tr>
        <td>
            <table cellpadding="0" cellspacing="0" border="0" bgcolor="#ffffff" style="background-color: #ffffff; border-radius: 10px; border: 1px solid rgba(255,255,255,0.35); box-shadow: 0 4px 12px rgba(0,0,0,0.25); width: 88px;">
                {{-- Month strip (cream-coloured for contrast against the white card and brick header) --}}
                <tr>
                    <td align="center" bgcolor="#7a1f1f" style="background-color: #7a1f1f; padding: 6px 0; border-top-left-radius: 10px; border-top-right-radius: 10px;">
                        <div style="font-size: 12px; color: #ffffff; font-weight: 800; letter-spacing: 2px; line-height: 1;">{{ $calMonth }}</div>
                    </td>
                </tr>
                {{-- Day number (large) --}}
                <tr>
                    <td align="center" style="padding: 8px 0 4px 0;">
                        <div style="font-size: 36px; color: #3e1f1f; font-weight: 800; line-height: 1; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;">{{ $calDay }}</div>
                    </td>
                </tr>
                {{-- Weekday (small, gold) --}}
                @if($calSubLabel)
                <tr>
                    <td align="center" style="padding: 0 0 8px 0;">
                        <div style="font-size: 10px; color: #9b2d2d; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; line-height: 1;">{{ $calSubLabel }}</div>
                    </td>
                </tr>
                @endif
            </table>
        </td>
    </tr>
</table>
