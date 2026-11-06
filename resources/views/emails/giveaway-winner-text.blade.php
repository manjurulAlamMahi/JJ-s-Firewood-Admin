Hi {{ $winner->name }},

You won!

Great news — we pulled your name out for this week's giveaway. Genuine prize, no catch:

YOUR PRIZE: {{ $giveaway->prize_name }}
@if(!empty($giveaway->prize_value) && (float) $giveaway->prize_value > 0)
Value: ${{ number_format((float) $giveaway->prize_value, 2) }}
@endif
@if(!empty($giveaway->description))
{{ $giveaway->description }}
@endif

HOW TO CLAIM YOUR PRIZE
1. Open the JJ's Firewood Perth app on your phone
2. Go to the Giveaways screen
3. Tap into the "My Prizes" section — your prize will be waiting
4. Use it on any app order whenever suits you

THE FINE PRINT
- One-time use only — once you redeem it, it's gone
- Never expires — take your time, use it whenever suits you
- No minimum order — use it on any size order
- App orders only — can't be applied to phone or website orders

No need to reply or call us — everything's already set up and waiting in your app.

Thanks for being part of the JJ's community. Keep an eye on the app for more chances to win.

Cheers,
The JJ's Firewood Perth Team

---
This is an automated email — please don't reply.
(C) {{ date('Y') }} JJ's Firewood Perth. All rights reserved.
