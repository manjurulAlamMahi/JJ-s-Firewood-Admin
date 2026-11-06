<?php

return [
    'client_id'   => env('PAYPAL_CLIENT_ID'),
    'secret'      => env('PAYPAL_SECRET'),
    'environment' => env('PAYPAL_ENVIRONMENT', 'sandbox'),
];
