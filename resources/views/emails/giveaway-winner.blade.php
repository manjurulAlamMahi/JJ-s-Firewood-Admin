<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>You won!</title>
    <style>
        /* === JJ's Firewood Perth brand theme ===
           Primary: #9b2d2d brick red (matches website header + buttons)
           Cream:   #f1eada page bg / #faf6ec soft accents
           Dark:    #3e1f1f warm dark brown text
           Gold:    #c89538 accent for value pills
        */
        body {
            margin: 0; padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
            line-height: 1.6;
            color: #3e1f1f;
            background-color: #f1eada;
            -webkit-font-smoothing: antialiased;
        }
        table { border-collapse: collapse; }

        .container {
            max-width: 600px;
            margin: 20px auto;
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 22px rgba(155, 45, 45, 0.15);
        }

        /* Header styles inlined directly on the table element below for email
           client compatibility. Outlook strips most <style> tag content. */

        .content { padding: 36px 32px; color: #3e1f1f; }
        .content p { margin: 0 0 16px 0; font-size: 16px; line-height: 1.6; color: #3e1f1f; }
        .content strong { color: #3e1f1f; }

        /* Prize callout box — cream tones with brick-red accent */
        .prize-box {
            background: linear-gradient(135deg, #faf6ec 0%, #f6efd9 100%);
            background-color: #faf6ec;
            border: 1px solid #e6dbb9;
            padding: 0;
            margin: 28px 0;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(155, 45, 45, 0.08);
        }
        .prize-box .prize-label {
            background: #9b2d2d;
            color: #ffffff;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            padding: 8px 24px;
            display: block;
        }
        .prize-box .prize-body { padding: 24px 26px 22px 26px; }
        .prize-box h2 {
            margin: 0 0 12px 0;
            color: #9b2d2d;
            font-size: 26px;
            font-weight: 800;
            line-height: 1.25;
            letter-spacing: -0.3px;
        }
        .prize-box .prize-value {
            display: inline-block;
            background: #ffffff;
            color: #6f4a1a;
            border: 1.5px solid #c89538;
            border-radius: 999px;
            padding: 4px 14px;
            font-size: 13px;
            font-weight: 700;
            margin: 0 0 14px 0;
        }
        .prize-box .prize-description {
            margin: 0;
            color: #3e1f1f;
            font-size: 15px;
            line-height: 1.6;
        }
        .prize-box .prize-description p { margin: 0 0 12px 0; color: #3e1f1f; }
        .prize-box .prize-description p:last-child { margin-bottom: 0; }
        .prize-box .prize-description ul {
            margin: 0 0 14px 0;
            padding-left: 22px;
            list-style: none;
        }
        .prize-box .prize-description ul li {
            position: relative;
            margin-bottom: 6px;
            padding-left: 4px;
            color: #3e1f1f;
        }
        .prize-box .prize-description ul li::before {
            content: "•";
            color: #9b2d2d;
            font-weight: 700;
            position: absolute;
            left: -16px;
            font-size: 18px;
            line-height: 1;
        }
        .prize-box img.prize-image {
            display: block;
            width: 100%;
            height: auto;
            max-width: 100%;
        }

        /* "How to redeem" section — cream */
        .how-to {
            background: #faf6ec;
            border: 1px solid #e6dbb9;
            border-radius: 10px;
            padding: 22px 24px;
            margin: 24px 0;
        }
        .how-to h3 {
            margin: 0 0 14px 0;
            color: #9b2d2d;
            font-size: 17px;
            font-weight: 700;
        }
        .how-to ol { margin: 0; padding-left: 24px; color: #3e1f1f; }
        .how-to ol li { margin-bottom: 8px; font-size: 15px; color: #3e1f1f; }
        .how-to strong { color: #9b2d2d; }

        /* Fine print */
        .fine-print {
            border: 1px solid #e6dbb9;
            background: #ffffff;
            border-radius: 10px;
            padding: 18px 22px;
            margin: 24px 0;
        }
        .fine-print h4 {
            margin: 0 0 10px 0;
            color: #9b2d2d;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
        }
        .fine-print ul { margin: 0; padding-left: 20px; color: #4b3030; font-size: 14px; }
        .fine-print ul li { margin-bottom: 6px; color: #4b3030; }
        .fine-print strong { color: #3e1f1f; }

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

        /* Mobile */
        @media only screen and (max-width: 620px) {
            .container { margin: 0; border-radius: 0; }
            .content { padding: 28px 22px; }
        }
    </style>
</head>
<body>
    <div class="container">
        {{-- Header banner — JJ's brick red. Inline styles + bgcolor for Outlook
             compatibility. Solid red shows even when CSS gets stripped. --}}
        <table cellpadding="0" cellspacing="0" border="0" width="100%" role="presentation"
               bgcolor="#9b2d2d"
               style="background-color: #9b2d2d; background-image: linear-gradient(135deg, #9b2d2d 0%, #7a1f1f 100%);">
            <tr>
                <td align="center" style="padding: 50px 24px 44px 24px;">
                    <div style="display: inline-block; background-color: rgba(255,255,255,0.18); border: 1.5px solid rgba(255,255,255,0.55); color: #ffffff; font-size: 11px; font-weight: 700; letter-spacing: 1.5px; text-transform: uppercase; padding: 6px 16px; border-radius: 999px; margin-bottom: 18px;">
                        🏆 Winner Selected
                    </div>
                    <div style="font-size: 56px; line-height: 1; margin: 0 0 14px 0;">🎉</div>
                    <h1 style="margin: 0 0 8px 0; color: #ffffff; font-size: 34px; font-weight: 800; letter-spacing: -0.5px;">You won!</h1>
                    <p style="margin: 0; color: #ffffff; font-size: 16px; font-weight: 500;">Congratulations from JJ's Firewood Perth</p>
                </td>
            </tr>
        </table>

        <div class="content">
            <p>Hi <strong>{{ $winner->name }}</strong>,</p>

            <p>Great news &mdash; we pulled your name out for this week's giveaway. You've genuinely won, no catch:</p>

            {{-- Prize callout --}}
            <div class="prize-box">
                <span class="prize-label">🏆 Your prize</span>

                @if(!empty($giveaway->image))
                    @php
                        $imgUrl = $giveaway->image;
                        if (!preg_match('#^https?://#', $imgUrl)) {
                            $imgUrl = rtrim(config('app.url'), '/') . '/' . ltrim($imgUrl, '/');
                        }
                    @endphp
                    <img src="{{ $imgUrl }}" alt="{{ $giveaway->prize_name }}" class="prize-image">
                @endif

                <div class="prize-body">
                    <h2>{{ $giveaway->prize_name }}</h2>

                    @if(!empty($giveaway->prize_value) && (float) $giveaway->prize_value > 0)
                        <span class="prize-value">Value: ${{ number_format((float) $giveaway->prize_value, 2) }}</span>
                    @endif

                    @if(!empty($giveaway->description))
                        @php
                            // Normalise description text from admin field so a single
                            // paragraph with bullets renders as readable structure.
                            $desc = (string) $giveaway->description;
                            $desc = preg_replace('/\s*•\s*/u', "\n• ", $desc);
                            $desc = preg_replace('/\s*(🏆|📲|⚠️|🎉|✅|🔥)\s*/u', "\n\n$1 ", $desc);
                            $desc = preg_replace("/\n{3,}/", "\n\n", $desc);
                            $desc = trim($desc);
                            $paragraphs = preg_split('/\n\n+/', $desc);
                        @endphp

                        <div class="prize-description">
                            @foreach($paragraphs as $para)
                                @php $para = trim($para); @endphp
                                @if($para === '') @continue @endif

                                @php
                                    $lines = preg_split('/\n/', $para);
                                    $bullets = [];
                                    $textLines = [];
                                    foreach ($lines as $ln) {
                                        $ln = trim($ln);
                                        if ($ln === '') continue;
                                        if (str_starts_with($ln, '•')) {
                                            $bullets[] = ltrim($ln, "• \t");
                                        } else {
                                            $textLines[] = $ln;
                                        }
                                    }
                                @endphp

                                @if(!empty($textLines))
                                    <p>{!! e(implode(' ', $textLines)) !!}</p>
                                @endif
                                @if(!empty($bullets))
                                    <ul>
                                        @foreach($bullets as $b)
                                            <li>{!! e($b) !!}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            {{-- How to redeem --}}
            <div class="how-to">
                <h3>How to claim your prize</h3>
                <ol>
                    <li>Open the <strong>JJ's Firewood Perth</strong> app on your phone</li>
                    <li>Go to the <strong>Giveaways</strong> screen</li>
                    <li>Tap into the <strong>My Prizes</strong> section &mdash; your prize will be waiting</li>
                    <li>Use it on any app order whenever suits you</li>
                </ol>
            </div>

            {{-- Fine print --}}
            <div class="fine-print">
                <h4>The fine print</h4>
                <ul>
                    <li><strong>One-time use only</strong> &mdash; once you redeem it, it's gone</li>
                    <li><strong>Never expires</strong> &mdash; take your time, use it whenever suits you</li>
                    <li><strong>No minimum order</strong> &mdash; use it on any size order</li>
                    <li><strong>App orders only</strong> &mdash; can't be applied to phone or website orders</li>
                </ul>
            </div>

            <p>No need to reply or call us &mdash; everything's already set up and waiting in your app.</p>

            <p>Thanks for being part of the JJ's community. Keep an eye on the app for more chances to win.</p>

            <div class="signoff">
                Cheers,<br>
                <strong>The JJ's Firewood Perth Team</strong>
            </div>
        </div>

        <div class="footer">
            <p>This is an automated email &mdash; please don't reply.</p>
            <p>&copy; {{ date('Y') }} JJ's Firewood Perth. All rights reserved.</p>
        </div>
    </div>
</body>
</html>
