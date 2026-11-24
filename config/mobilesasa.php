<?php

return [

    'base_url' => rtrim((string) env('MOBILESASA_BASE_URL', 'https://api.mobilesasa.com'), '/'),

    'token' => env('MOBILESASA_TOKEN'),

    'sender_id' => env('MOBILESASA_SENDER_ID', 'MOBILESASA'),

    'timeout' => 20,

];
