<?php

return [

    'env' => env('MPESA_ENV', 'sandbox'),

    'base_url' => rtrim((string) env('MPESA_BASE_URL', 'https://sandbox.safaricom.co.ke'), '/'),

    'consumer_key' => env('MPESA_CONSUMER_KEY'),

    'consumer_secret' => env('MPESA_CONSUMER_SECRET'),

    'passkey' => env('MPESA_PASSKEY'),

    'shortcode' => env('MPESA_SHORTCODE'),

    'callback_url' => env('MPESA_CALLBACK_URL'),

    'initiator_name' => env('MPESA_INITIATOR_NAME'),

    'security_credential' => env('MPESA_SECURITY_CREDENTIAL'),

    'b2c_shortcode' => env('MPESA_B2C_SHORTCODE'),

    'b2c_result_url' => env('MPESA_B2C_RESULT_URL'),

    'b2c_timeout_url' => env('MPESA_B2C_TIMEOUT_URL'),

    'timeout' => 20,

];
