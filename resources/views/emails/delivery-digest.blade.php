<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Digest</title>
</head>
<body style="margin:0; padding:0; background-color:#f4f4f5; font-family:Arial, Helvetica, sans-serif; color:#1f2937;">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f4f5; padding:24px 0;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff; border-radius:8px; overflow:hidden; max-width:600px; width:100%;">
                    <!-- Header -->
                    <tr>
                        <td style="background-color:#7c3aed; padding:24px 32px;">
                            <h1 style="margin:0; color:#ffffff; font-size:20px;">Auto-Delivery Digest</h1>
                            <p style="margin:4px 0 0; color:#e9d5ff; font-size:14px;">{{ $dateLabel }}</p>
                        </td>
                    </tr>

                    <!-- Summary -->
                    <tr>
                        <td style="padding:24px 32px 8px;">
                            <p style="margin:0 0 16px; font-size:15px;">
                                The 6:00pm auto-delivery sweep marked
                                <strong>{{ $stats['delivered'] }}</strong>
                                order{{ $stats['delivered'] === 1 ? '' : 's' }} as delivered.
                            </p>
                        </td>
                    </tr>

                    <!-- Orders table -->
                    <tr>
                        <td style="padding:0 32px 24px;">
                            @if(count($orders) > 0)
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; font-size:14px;">
                                <thead>
                                    <tr>
                                        <th align="left" style="padding:8px 8px; border-bottom:2px solid #e5e7eb; color:#6b7280; font-size:12px; text-transform:uppercase;">Order</th>
                                        <th align="left" style="padding:8px 8px; border-bottom:2px solid #e5e7eb; color:#6b7280; font-size:12px; text-transform:uppercase;">Customer</th>
                                        <th align="left" style="padding:8px 8px; border-bottom:2px solid #e5e7eb; color:#6b7280; font-size:12px; text-transform:uppercase;">Payment</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($orders as $o)
                                    <tr>
                                        <td style="padding:10px 8px; border-bottom:1px solid #f1f1f3;">{{ $o['order_number'] ?? ('#' . ($o['id'] ?? '?')) }}</td>
                                        <td style="padding:10px 8px; border-bottom:1px solid #f1f1f3;">{{ $o['customer'] ?? '—' }}</td>
                                        <td style="padding:10px 8px; border-bottom:1px solid #f1f1f3; text-transform:capitalize;">{{ $o['payment_method'] ?? '—' }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            @else
                            <p style="margin:0; color:#6b7280; font-size:14px;">No orders were auto-delivered in this run.</p>
                            @endif
                        </td>
                    </tr>

                    {{-- Optional footnote about skipped orders so you know some needed manual attention --}}
                    @if(($stats['skipped'] ?? 0) > 0)
                    <tr>
                        <td style="padding:0 32px 24px;">
                            <p style="margin:0; padding:12px 16px; background-color:#fef9c3; border-radius:6px; font-size:13px; color:#854d0e;">
                                {{ $stats['skipped'] }} order{{ $stats['skipped'] === 1 ? '' : 's' }} reached the delivery date but
                                {{ $stats['skipped'] === 1 ? 'was' : 'were' }} left for manual review
                                (cash, bank transfer, or unpaid). Check the Needs Action tab in Order Centre.
                            </p>
                        </td>
                    </tr>
                    @endif

                    @if(($stats['errors'] ?? 0) > 0)
                    <tr>
                        <td style="padding:0 32px 24px;">
                            <p style="margin:0; padding:12px 16px; background-color:#fee2e2; border-radius:6px; font-size:13px; color:#991b1b;">
                                {{ $stats['errors'] }} order{{ $stats['errors'] === 1 ? '' : 's' }} hit an error during the sweep. Check the logs.
                            </p>
                        </td>
                    </tr>
                    @endif

                    <!-- Outstanding orders needing admin action -->
                    <tr>
                        <td style="padding:8px 32px 8px;">
                            <h2 style="margin:16px 0 4px; font-size:16px; color:#1f2937; border-top:1px solid #f1f1f3; padding-top:20px;">
                                Needs your attention
                            </h2>
                            <p style="margin:0 0 16px; font-size:14px; color:#6b7280;">
                                @if(count($outstanding) > 0)
                                    {{ count($outstanding) }} order{{ count($outstanding) === 1 ? '' : 's' }} still need action — pending payment, scheduling, or date confirmation.
                                @else
                                    Nothing outstanding — all orders are scheduled and up to date.
                                @endif
                            </p>
                        </td>
                    </tr>

                    @if(count($outstanding) > 0)
                    <tr>
                        <td style="padding:0 32px 24px;">
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; font-size:14px;">
                                <thead>
                                    <tr>
                                        <th align="left" style="padding:8px 8px; border-bottom:2px solid #e5e7eb; color:#6b7280; font-size:12px; text-transform:uppercase;">Order</th>
                                        <th align="left" style="padding:8px 8px; border-bottom:2px solid #e5e7eb; color:#6b7280; font-size:12px; text-transform:uppercase;">Customer</th>
                                        <th align="left" style="padding:8px 8px; border-bottom:2px solid #e5e7eb; color:#6b7280; font-size:12px; text-transform:uppercase;">Delivery date</th>
                                        <th align="left" style="padding:8px 8px; border-bottom:2px solid #e5e7eb; color:#6b7280; font-size:12px; text-transform:uppercase;">Reason</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($outstanding as $o)
                                    <tr>
                                        <td style="padding:10px 8px; border-bottom:1px solid #f1f1f3; white-space:nowrap;">{{ $o['order_number'] }}</td>
                                        <td style="padding:10px 8px; border-bottom:1px solid #f1f1f3;">{{ $o['customer'] }}</td>
                                        <td style="padding:10px 8px; border-bottom:1px solid #f1f1f3; white-space:nowrap;">{{ $o['delivery_date'] }}</td>
                                        <td style="padding:10px 8px; border-bottom:1px solid #f1f1f3;">{{ $o['reasons'] }}</td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </td>
                    </tr>
                    @endif

                    <!-- Footer -->
                    <tr>
                        <td style="padding:16px 32px 24px; border-top:1px solid #f1f1f3;">
                            <p style="margin:0; color:#9ca3af; font-size:12px;">
                                Automated message from JJ's Firewood Order Centre. The "auto-delivered" section covers
                                only orders the automatic sweep marked delivered (orders you mark delivered by hand are
                                not listed). The "needs your attention" section mirrors the Needs Action tab in Order Centre.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
