<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    //push notification
    'firebase' => [
        'credentials' => env('FIREBASE_CREDENTIALS'),
    ],

    // FCM Push Notifications
    'fcm' => [
        'server_key' => env('FCM_SERVER_KEY'),
        'endpoint' => env('FCM_ENDPOINT', 'https://fcm.googleapis.com/fcm/send'),
    ],

    // for stripe
    'stripe' => [
        'secret' => env('STRIPE_SECRET_KEY'),
        'key'    => env('STRIPE_PUBLIC_KEY'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],
  
    'brevo' => [
        'api_key' => env('BREVO_API_KEY'),
        'sender_name' => env('BREVO_SENDER_NAME', "JJ's Firewood Perth"),
        'sender_email' => env('BREVO_SENDER_EMAIL', 'admin@jjsfirewoodperth.com.au'),
        'reply_to' => env('BREVO_REPLY_TO', 'admin@jjsfirewoodperth.com.au'),
        'sms_sender' => env('BREVO_SMS_SENDER', 'JJSFIREWOOD'),
    ],

    // Weather campaigns
    'openweathermap' => [
        'api_key' => env('OPENWEATHERMAP_API_KEY'),
        'base_url' => env('OPENWEATHERMAP_BASE_URL', 'https://api.openweathermap.org'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URL'),
    ],

    'apple' => [
        'client_id' => env('APPLE_CLIENT_ID'),
        'client_secret' => env('APPLE_CLIENT_SECRET'),
        'redirect' => env('APPLE_REDIRECT_URL'),
        'team_id' => env('APPLE_TEAM_ID'),
        'key_id' => env('APPLE_KEY_ID'),
        'private_key' => env('APPLE_PRIVATE_KEY'),
    ],

];
