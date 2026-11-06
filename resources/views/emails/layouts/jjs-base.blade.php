{{--
    JJ's Firewood Perth — shared email base layout.

    Use:
        @extends('emails.layouts.jjs-base')

        @section('header_title', 'Order #JJS00xxx Placed')
        @section('header_subtitle', 'Thanks for ordering with us')

        @section('content')
            <p>Hi {{ $name }},</p>
            ...
        @endsection

    Sections:
        header_badge     — small pill above the icon (optional)
        header_icon      — emoji or character to show large (defaults to a checkmark)
        header_title     — h1 in header
        header_subtitle  — line under h1
        content          — main email body

    Brand colours:
        #9b2d2d  brick red (primary)
        #7a1f1f  brick red dark
        #f1eada  cream page bg
        #faf6ec  cream soft accent
        #e6dbb9  cream border
        #3e1f1f  warm dark brown text
        #c89538  gold accent
--}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', "JJ's Firewood Perth")</title>
    <style>
        body {
            margin: 0; padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
            line-height: 1.6;
            color: #3e1f1f;
            background-color: #f1eada;
            -webkit-font-smoothing: antialiased;
        }
        table { border-collapse: collapse; }
        img { max-width: 100%; height: auto; border: 0; }

        .container {
            max-width: 600px;
            margin: 20px auto;
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 22px rgba(155, 45, 45, 0.15);
        }

        .content { padding: 36px 32px; color: #3e1f1f; }
        .content p { margin: 0 0 16px 0; font-size: 16px; line-height: 1.6; color: #3e1f1f; }
        .content strong { color: #3e1f1f; }
        .content h2 { color: #9b2d2d; font-size: 22px; font-weight: 700; margin: 28px 0 14px 0; }
        .content h3 { color: #9b2d2d; font-size: 17px; font-weight: 700; margin: 22px 0 12px 0; }
        .content a { color: #9b2d2d; }

        /* Info cards (cream) */
        .card-cream {
            background: #faf6ec;
            border: 1px solid #e6dbb9;
            border-radius: 10px;
            padding: 20px 22px;
            margin: 20px 0;
        }
        .card-cream h3 { margin-top: 0; color: #9b2d2d; }

        /* Contact / address block (used in all order emails) */
        .contact-block {
            background: #faf6ec;
            border: 1px solid #e6dbb9;
            border-radius: 10px;
            padding: 18px 22px;
            margin: 20px 0;
        }
        .contact-block .cb-title {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #9b2d2d;
            margin: 0 0 10px 0;
        }
        .contact-block .cb-row {
            display: block;
            margin: 4px 0;
            font-size: 15px;
            color: #3e1f1f;
            line-height: 1.5;
        }
        .contact-block .cb-icon {
            display: inline-block;
            width: 18px;
            color: #9b2d2d;
            font-weight: 700;
        }
        .contact-block .cb-name { font-weight: 700; font-size: 16px; color: #3e1f1f; margin-bottom: 8px; }
        .contact-block .cb-address-line { color: #3e1f1f; }

        /* Order summary table */
        .summary-table {
            width: 100%;
            border-collapse: collapse;
            margin: 14px 0;
        }
        .summary-table td {
            padding: 10px 0;
            border-bottom: 1px solid #f0e8d0;
            font-size: 15px;
            color: #3e1f1f;
        }
        .summary-table tr:last-child td { border-bottom: none; }
        .summary-table .label { color: #6e4f3a; }
        .summary-table .value { text-align: right; color: #3e1f1f; font-weight: 500; }
        .summary-table .total-row td {
            border-top: 2px solid #c89538;
            padding-top: 14px;
            padding-bottom: 14px;
            font-size: 17px;
            font-weight: 700;
            color: #9b2d2d;
        }

        /* Item rows */
        .item-row {
            display: block;
            padding: 12px 0;
            border-bottom: 1px solid #f0e8d0;
            font-size: 15px;
        }
        .item-row:last-child { border-bottom: none; }
        .item-row .item-name { font-weight: 600; color: #3e1f1f; }
        .item-row .item-meta { color: #6e4f3a; font-size: 13px; margin-top: 2px; }
        .item-row .item-price { color: #9b2d2d; font-weight: 700; }

        /* Status badges */
        .status-pill {
            display: inline-block;
            padding: 5px 14px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }
        .status-pill.status-paid { background: #d1fae5; color: #065f46; border: 1px solid #10b981; }
        .status-pill.status-pending { background: #fef3c7; color: #78350f; border: 1px solid #f59e0b; }
        .status-pill.status-success { background: #d1fae5; color: #065f46; border: 1px solid #10b981; }
        .status-pill.status-error { background: #fee2e2; color: #991b1b; border: 1px solid #ef4444; }
        .status-pill.status-info { background: #faf6ec; color: #9b2d2d; border: 1px solid #c89538; }

        /* Signoff + footer */
        .signoff { margin-top: 28px; font-size: 15px; color: #4b3030; }
        .signoff strong { color: #9b2d2d; }

        .footer {
            background: #faf6ec;
            border-top: 1px solid #e6dbb9;
            padding: 22px;
            text-align: center;
            color: #8a6e58;
            font-size: 13px;
        }
        .footer p { margin: 4px 0; }

        @media only screen and (max-width: 620px) {
            .container { margin: 0; border-radius: 0; }
            .content { padding: 28px 22px; }
        }
    </style>
</head>
<body>
    <div class="container">

        {{-- ===== Header banner — brick-red, Outlook-safe ===== --}}
        <table cellpadding="0" cellspacing="0" border="0" width="100%" role="presentation"
               bgcolor="@yield('header_bg', '#9b2d2d')"
               style="background-color: @yield('header_bg', '#9b2d2d'); background-image: linear-gradient(135deg, @yield('header_bg', '#9b2d2d') 0%, @yield('header_bg_dark', '#7a1f1f') 100%);">
            <tr>
                <td align="center" style="padding: 44px 24px 38px 24px;">
                    @hasSection('header_badge')
                        <div style="display: inline-block; background-color: rgba(255,255,255,0.18); border: 1.5px solid rgba(255,255,255,0.55); color: #ffffff; font-size: 11px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; padding: 6px 16px; border-radius: 999px; margin-bottom: 16px;">
                            @yield('header_badge')
                        </div>
                    @endif
                    @hasSection('header_icon_block')
                        @yield('header_icon_block')
                    @else
                        <div style="font-size: 48px; line-height: 1; margin: 0 0 12px 0;">@yield('header_icon', '✓')</div>
                    @endif
                    <h1 style="margin: 0 0 8px 0; color: #ffffff; font-size: 30px; font-weight: 800; letter-spacing: -0.5px;">@yield('header_title')</h1>
                    @hasSection('header_subtitle')
                        <p style="margin: 0; color: #ffffff; font-size: 15px; font-weight: 500; opacity: 0.95;">@yield('header_subtitle')</p>
                    @endif
                </td>
            </tr>
        </table>

        {{-- ===== Body ===== --}}
        <div class="content">
            @yield('content')

            @hasSection('signoff')
                <div class="signoff">@yield('signoff')</div>
            @else
                <div class="signoff">
                    Cheers,<br>
                    <strong>The JJ's Firewood Perth Team</strong>
                </div>
            @endif
        </div>

        {{-- ===== Footer ===== --}}
        <div class="footer">
            <p>This is an automated email &mdash; please don't reply.</p>
            <p>&copy; {{ date('Y') }} JJ's Firewood Perth. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
