@component('mail::message')
# New Quote Request

## User Details
- Name: {{ $mailData['user']['name'] }}
- Email: {{ $mailData['user']['email'] }}
- Phone: {{ $mailData['user']['phone'] }}

## Product Details
- Name: {{ $mailData['product']['name'] }}
- Price: {{ $mailData['product']['price'] }}
- Features:
@foreach ($mailData['product']['features'] as $feature)
- {{ $feature }}
@endforeach

Thanks,<br>
{{ config('app.name') }}
@endcomponent
