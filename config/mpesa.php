<?php

return [

    'env' => env('MPESA_ENV', 'sandbox'),

    'base_url' => rtrim((string) env('MPESA_BASE_URL', 'https://sandbox.safaricom.co.ke'), '/'),

    'consumer_key' => env('MPESA_CONSUMER_KEY'),

    'consumer_secret' => env('MPESA_CONSUMER_SECRET'),

    'passkey' => env('MPESA_PASSKEY'),

    'shortcode' => env('MPESA_SHORTCODE'),

    'callback_url' => env('MPESA_CALLBACK_URL'),

    'timeout' => 20,

];
